<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonQuiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificateService
{
    public function enrolledCourses(User $user): Builder
    {
        return Course::where('status', 'published')->whereHas('groups', fn (Builder $groups): Builder => $groups
            ->whereIn('status', ['active', 'completed'])->whereHas('students', fn (Builder $students): Builder => $students->where('users.id', $user->id)));
    }

    public function authorizeStudent(User $user): void
    {
        abort_unless($user->hasRole('STUDENT'), 403);
    }

    /** @return array{eligible: bool, requirements: list<array{label: string, done: int, total: int}>, course: array{id: int, name: string}} */
    public function courseEligibility(User $user, Course $course): array
    {
        $this->authorizeStudent($user);
        abort_unless($this->enrolledCourses($user)->whereKey($course->id)->exists(), 404);
        $lessons = Lesson::where('course_id', $course->id)->where('status', 'published')->pluck('id');
        $read = DB::table('lesson_completions')->where('user_id', $user->id)->whereIn('lesson_id', $lessons)->count();
        $quizzes = LessonQuiz::whereIn('lesson_id', $lessons)->where('status', 'published');
        $quizCount = (clone $quizzes)->count();
        $passed = (clone $quizzes)->whereHas('attempts', fn (Builder $query): Builder => $query->where('user_id', $user->id)->where('passed', true)->whereColumn('quiz_attempts.revision', 'lesson_quizzes.revision'))->count();
        $assignments = Assignment::where('status', 'published')->whereHas('group', fn (Builder $groups): Builder => $groups
            ->where('course_id', $course->id)->whereIn('status', ['active', 'completed'])->whereHas('students', fn (Builder $students): Builder => $students->where('users.id', $user->id)));
        $assignmentCount = (clone $assignments)->count();
        $approved = (clone $assignments)->whereHas('submissions', fn (Builder $submissions): Builder => $submissions->where('student_id', $user->id)
            ->where('status', 'graded')->where('final_score', '>=', 70)->whereHas('reviews', fn (Builder $reviews): Builder => $reviews->where('action', 'approve')))->count();
        $requirements = [
            ['label' => 'O‘qilgan darslar', 'done' => $read, 'total' => $lessons->count()],
            ['label' => 'Muvaffaqiyatli testlar', 'done' => $passed, 'total' => $quizCount],
            ['label' => 'Ustoz tasdiqlagan topshiriqlar (kamida 70 ball)', 'done' => $approved, 'total' => $assignmentCount],
        ];

        return ['eligible' => $lessons->isNotEmpty() && $read === $lessons->count() && $passed === $quizCount && $approved === $assignmentCount && ($quizCount + $assignmentCount > 0),
            'requirements' => $requirements, 'course' => ['id' => $course->id, 'name' => $course->name]];
    }

    public function claimCourse(User $user, Course $course): Certificate
    {
        $this->authorizeStudent($user);

        return DB::transaction(function () use ($user, $course): Certificate {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Certificate::where('user_id', $user->id)->where('source_key', 'course:'.$course->id)->first();
            if ($existing) {
                return $existing;
            }
            $eligibility = $this->courseEligibility($user, $course);
            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages(['course' => 'Sertifikat uchun barcha darslarni, testlarni va topshiriqlarni yakunlang. Kamida bitta baholangan tekshiruv bo‘lishi kerak.']);
            }

            return Certificate::create(['code' => (string) Str::uuid(), 'user_id' => $user->id, 'course_id' => $course->id,
                'source_key' => 'course:'.$course->id, 'type' => 'course', 'recipient_name' => $user->name, 'title' => $course->name,
                'score' => null, 'evidence' => $eligibility['requirements'], 'issued_at' => now()]);
        }, attempts: 3);
    }

    public function claimExam(User $user, LessonQuiz $quiz): Certificate
    {
        $this->authorizeStudent($user);

        return DB::transaction(function () use ($user, $quiz): Certificate {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Lesson::whereKey($quiz->lesson_id)->lockForUpdate()->firstOrFail();
            $quiz = LessonQuiz::whereKey($quiz->id)->lockForUpdate()->firstOrFail();
            $key = 'exam:'.$quiz->id.':'.$quiz->revision;
            $existing = Certificate::where('user_id', $user->id)->where('source_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            abort_unless($quiz->awards_certificate && $quiz->status === 'published' && $quiz->lesson->status === 'published'
                && $this->enrolledCourses($user)->whereKey($quiz->lesson->course_id)->exists(), 404);
            $attempt = QuizAttempt::where('lesson_quiz_id', $quiz->id)->where('user_id', $user->id)->where('revision', $quiz->revision)
                ->where('passed', true)->orderByDesc('score')->orderBy('id')->first();
            if (! $attempt) {
                throw ValidationException::withMessages(['exam' => 'Avval tajvid imtihonidan muvaffaqiyatli o‘ting.']);
            }

            return Certificate::create(['code' => (string) Str::uuid(), 'user_id' => $user->id, 'course_id' => $quiz->lesson->course_id,
                'lesson_quiz_id' => $quiz->id, 'source_key' => $key, 'type' => 'tajwid', 'recipient_name' => $user->name, 'title' => $quiz->title,
                'score' => $attempt->score, 'evidence' => ['attempt_id' => $attempt->id, 'revision' => $quiz->revision, 'pass_percent' => $quiz->pass_percent], 'issued_at' => now()]);
        }, attempts: 3);
    }

    public function verificationUrl(Certificate $certificate): string
    {
        return rtrim((string) config('app.url'), '/').route('certificates.verify', ['code' => $certificate->code], false);
    }
}
