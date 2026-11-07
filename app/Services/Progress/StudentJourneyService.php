<?php

namespace App\Services\Progress;

use App\Models\Assignment;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Quran\Ayah;
use App\Models\RepetitionCard;
use App\Models\Schedule;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentJourneyService
{
    public function authorize(User $user): void
    {
        app(LearningPathService::class)->authorizeStudent($user);
    }

    /** @return Builder<Group> */
    private function groups(User $user): Builder
    {
        return Group::where('status', 'active')->whereHas('students', fn (Builder $query): Builder => $query->where('users.id', $user->id));
    }

    /** @return Builder<Assignment> */
    public function assignments(User $user): Builder
    {
        return Assignment::where('status', 'published')->whereIn('group_id', $this->groups($user)->select('groups.id'));
    }

    /** @return Collection<int, Assignment> */
    public function pendingAssignments(User $user): Collection
    {
        return $this->assignments($user)->with(['group:id,name', 'submissions' => fn ($query) => $query->where('student_id', $user->id)->orderByDesc('attempt')->orderByDesc('id')->limit(1)])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->orderBy('id')->get()
            ->filter(fn (Assignment $assignment): bool => ! $assignment->submissions->first() || $assignment->submissions->first()->status === 'resubmit')->values();
    }

    public function eligibleLesson(User $user, Lesson $lesson): bool
    {
        return $user->hasPermission('lessons.view') && $lesson->status === 'published'
            && $this->groups($user)->where('course_id', $lesson->course_id)->exists();
    }

    public function completeLesson(User $user, Lesson $lesson): void
    {
        $this->authorize($user);
        abort_unless($this->eligibleLesson($user, $lesson), 404);
        DB::table('lesson_completions')->insertOrIgnore(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
    }

    public function completePractice(User $user, Assignment $assignment): void
    {
        $this->authorize($user);
        abort_unless($this->assignments($user)->whereKey($assignment->id)->exists(), 404);
        DB::table('assignment_practices')->insertOrIgnore(['user_id' => $user->id, 'assignment_id' => $assignment->id, 'completed_at' => now()]);
    }

    /** @return array{revision_added: int} */
    public function acknowledgeFeedback(User $user, Submission $submission): array
    {
        $this->authorize($user);
        abort_unless($submission->student_id === $user->id, 404);

        return DB::transaction(function () use ($user, $submission): array {
            $locked = Submission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['graded', 'resubmit'], true), 422);
            $review = $locked->reviews()->with('segments')->orderByDesc('reviewed_at')->orderByDesc('id')->first();
            abort_unless($review, 422);
            $inserted = DB::table('submission_feedback_reads')->insertOrIgnore(['user_id' => $user->id, 'mentor_review_id' => $review->id, 'read_at' => now()]);
            $added = 0;
            if ($inserted) {
                $ranges = $review->segments->filter(fn ($segment): bool => $segment->ayah_from !== null)->map(fn ($segment): array => [(int) $segment->ayah_from, (int) ($segment->ayah_to ?? $segment->ayah_from)]);
                if ($ranges->isNotEmpty() && $locked->assignment->surah_number) {
                    $ayahs = Ayah::whereHas('surah', fn ($query) => $query->where('number', $locked->assignment->surah_number))
                        ->where(function ($query) use ($ranges): void {
                            foreach ($ranges as [$from, $to]) {
                                $query->orWhereBetween('number_in_surah', [$from, $to]);
                            }
                        })->get(['id']);
                    foreach ($ayahs as $ayah) {
                        $card = RepetitionCard::firstOrCreate(['user_id' => $user->id, 'ayah_id' => $ayah->id], ['due_at' => now()]);
                        if ($card->wasRecentlyCreated) {
                            $added++;
                        }
                    }
                }
            }

            return ['revision_added' => $added];
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    public function overview(User $user): array
    {
        $this->authorize($user);
        $assignments = $this->assignments($user)->with(['group:id,name,course_id', 'lesson:id,course_id,title,status',
            'submissions' => fn ($query) => $query->where('student_id', $user->id)->orderByDesc('attempt')->orderByDesc('id')->limit(1),
            'submissions.reviews' => fn ($query) => $query->orderByDesc('reviewed_at')->orderByDesc('id')->limit(1)])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->orderBy('id')->get();
        $read = DB::table('submission_feedback_reads')->where('user_id', $user->id)->pluck('mentor_review_id');
        $completed = DB::table('lesson_completions')->where('user_id', $user->id)->pluck('lesson_id');
        $practiced = DB::table('assignment_practices')->where('user_id', $user->id)->pluck('assignment_id');
        $due = RepetitionCard::where('user_id', $user->id)->where('due_at', '<=', now())->count();
        $today = Schedule::with(['group:id,name', 'lesson:id,title,status'])->whereIn('group_id', $this->groups($user)->select('groups.id'))
            ->whereDate('date', now(config('app.display_timezone', 'Asia/Tashkent'))->toDateString())->orderBy('start_time')->first();
        $unread = $assignments->filter(function (Assignment $assignment) use ($read): bool {
            $submission = $assignment->submissions->first();
            $review = $submission?->reviews->first();

            return $submission && in_array($submission->status, ['resubmit', 'graded'], true) && $review && ! $read->contains($review->id);
        });
        $actionable = $assignments->filter(fn (Assignment $assignment): bool => ! $assignment->submissions->first() || $assignment->submissions->first()->status === 'resubmit');
        $assignment = $unread->first() ?? $actionable->first(fn (Assignment $item): bool => $item->submissions->first()?->status === 'resubmit') ?? $actionable->first();
        $waiting = $assignments->first(fn (Assignment $item): bool => in_array($item->submissions->first()?->status, ['submitted', 'in_review'], true));
        $lesson = null;
        $submission = $assignment?->submissions->first();
        $stage = 'explore';
        $action = ['type' => 'quran', 'id' => null, 'label' => 'Qur’onni o‘qish'];
        $title = 'Bugungi yo‘lingiz';
        $description = 'Sizga hali yangi vazifa tayinlanmagan. Qur’on o‘qish yoki takrorlashni davom ettiring.';
        if ($assignment) {
            $candidate = $assignment->lesson;
            $lesson = $candidate && $candidate->status === 'published' && $candidate->course_id === $assignment->group->course_id && $user->hasPermission('lessons.view') ? $candidate : null;
            if ($submission && $unread->contains('id', $assignment->id)) {
                $stage = 'feedback';
                $title = 'Ustozingizning javobi tayyor';
                $description = 'Izohlarni o‘qing. Ustoz belgilagan oyatlar takrorlash rejangizga qo‘shiladi.';
                $action = ['type' => 'submission', 'id' => $submission->id, 'label' => 'Ustoz javobini o‘qish'];
            } elseif ($submission?->status === 'resubmit') {
                $stage = 'submit';
                $title = 'Qayta topshirish kerak';
                $description = 'Ustoz izohlarini hisobga olib, yangi yozuv yuboring.';
                $action = ['type' => 'assignment', 'id' => $assignment->id, 'label' => 'Yangi yozuv yuborish'];
            } elseif ($lesson && ! $completed->contains($lesson->id)) {
                $stage = 'learn';
                $title = 'Avval darsni o‘rganing';
                $description = 'Vazifangizga tegishli darsni o‘qing va mustaqil mashq bilan tanishing.';
                $action = ['type' => 'lesson', 'id' => $lesson->id, 'label' => 'Darsni boshlash'];
            } elseif (! $practiced->contains($assignment->id)) {
                $stage = 'practice';
                $title = 'Endi mashq qiling';
                $description = 'Topshiriqni oching, ko‘rsatmalarni bajaring va tayyor bo‘lgach mashqni belgilang.';
                $action = ['type' => 'assignment', 'id' => $assignment->id, 'label' => 'Mashqqa o‘tish'];
            } else {
                $stage = 'submit';
                $title = 'Ishingizni ustozga yuboring';
                $description = 'Audio yozing, tinglab tekshiring va topshiring.';
                $action = ['type' => 'assignment', 'id' => $assignment->id, 'label' => 'Yozib yuborish'];
            }
        } elseif ($due > 0) {
            $stage = 'repeat';
            $title = 'Takrorlash vaqti keldi';
            $description = 'Rejangizdagi oyatlarni takrorlang. O‘z bahoyingiz ustoz bahosidan alohida saqlanadi.';
            $action = ['type' => 'revision', 'id' => null, 'label' => 'Takrorlashni boshlash'];
        } elseif ($waiting) {
            $assignment = $waiting;
            $submission = $waiting->submissions->first();
            $stage = 'await_review';
            $title = 'Yozuvingiz ustoz tekshiruvida';
            $description = 'Yangi yozuv yuborish shart emas. Natijani shu yerdan kuzatishingiz mumkin.';
            $action = ['type' => 'submission', 'id' => $submission->id, 'label' => 'Tekshiruv holatini ko‘rish'];
        } elseif ($user->hasPermission('lessons.view')) {
            $lesson = Lesson::where('status', 'published')->whereIn('course_id', $this->groups($user)->select('course_id'))->whereNotIn('id', $completed)->orderBy('course_id')->orderBy('sort_order')->orderBy('id')->first();
            if ($lesson) {
                $stage = 'learn';
                $title = 'Kursdagi keyingi dars';
                $description = 'Guruhingiz kursidagi keyingi darsni o‘rganishingiz mumkin.';
                $action = ['type' => 'lesson', 'id' => $lesson->id, 'label' => 'Darsni boshlash'];
            }
        }
        $current = match ($stage) {
            'learn' => 'learn', 'practice' => 'practice', 'submit' => 'submit', 'feedback', 'await_review' => 'review', 'repeat' => 'repeat', default => null
        };
        $review = $submission?->reviews->first();
        $done = ['learn' => $lesson && $completed->contains($lesson->id), 'practice' => $assignment && $practiced->contains($assignment->id),
            'submit' => $submission !== null, 'review' => $review && $read->contains($review->id), 'repeat' => false];
        $steps = [];
        foreach (['learn' => 'Dars', 'practice' => 'Mashq', 'submit' => 'Topshirish', 'review' => 'Ustoz javobi', 'repeat' => 'Takrorlash'] as $key => $label) {
            $steps[] = ['key' => $key, 'label' => $label, 'state' => $key === $current ? 'current' : ($done[$key] ? 'done' : 'pending')];
        }

        return ['stage' => $stage, 'title' => $title, 'description' => $description, 'action' => $action, 'steps' => $steps,
            'assignment' => $assignment ? ['id' => $assignment->id, 'title' => $assignment->title, 'due_at' => $assignment->due_at?->toIso8601String(), 'requires_audio' => $assignment->requires_audio] : null,
            'lesson' => $lesson ? ['id' => $lesson->id, 'title' => $lesson->title, 'completed' => $completed->contains($lesson->id)] : null,
            'submission' => $submission ? ['id' => $submission->id, 'status' => $submission->status, 'score' => $submission->final_score] : null,
            'today_lesson' => $today ? ['group' => $today->group->name, 'start_time' => substr($today->start_time, 0, 5), 'end_time' => substr($today->end_time, 0, 5), 'title' => $today->lesson?->status === 'published' ? $today->lesson->title : null] : null,
            'counts' => ['pending_assignments' => $actionable->count(), 'waiting_reviews' => $assignments->filter(fn (Assignment $item): bool => in_array($item->submissions->first()?->status, ['submitted', 'in_review'], true))->count(), 'due_revision' => $due]];
    }
}
