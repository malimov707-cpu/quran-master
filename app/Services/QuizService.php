<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonQuiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Progress\StudentJourneyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizService
{
    public function __construct(private StudentJourneyService $journey) {}

    public function authorizeStudent(User $user, Lesson $lesson): void
    {
        abort_unless($user->hasRole('STUDENT'), 403);
        abort_unless($this->journey->eligibleLesson($user, $lesson), 404);
    }

    /** @return list<array{prompt: string, options: list<string>, correct_index: int, explanation: string, rule: string}> */
    public static function tajwidTemplate(): array
    {
        return [
            ['prompt' => 'Madd tabiiy necha harakat cho‘ziladi?', 'options' => ['2 harakat', '4 harakat', '6 harakat'], 'correct_index' => 0, 'explanation' => 'Madd tabiiy ikki harakat cho‘ziladi.', 'rule' => 'madd'],
            ['prompt' => 'Qaysi qoida burun orqali chiqadigan tovush bilan bog‘liq?', 'options' => ['Qalqala', 'G‘unna', 'Izhor'], 'correct_index' => 1, 'explanation' => 'G‘unna burun orqali chiqadigan tovushdir.', 'rule' => 'ghunnah'],
            ['prompt' => 'Qalqala harflari qaysilar?', 'options' => ['ق ط ب ج د', 'ي ر م ل و ن', 'ء ه ع ح غ خ'], 'correct_index' => 0, 'explanation' => 'Qalqala harflari: qof, to, bo, jim va dol.', 'rule' => 'qalqalah'],
        ];
    }

    /** @param array{revision: int, submission_key: string, answers: list<int>} $data */
    public function grade(User $user, Lesson $lesson, array $data): QuizAttempt
    {
        $this->authorizeStudent($user, $lesson);

        return DB::transaction(function () use ($user, $lesson, $data): QuizAttempt {
            Lesson::whereKey($lesson->id)->lockForUpdate()->firstOrFail();
            $quiz = LessonQuiz::where('lesson_id', $lesson->id)->lockForUpdate()->firstOrFail();
            abort_unless($quiz->status === 'published', 404);
            $existing = $quiz->attempts()->where('user_id', $user->id)->where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                return $existing;
            }
            abort_unless($quiz->revision === $data['revision'], 409, 'Test yangilangan. Sahifani yangilab qayta topshiring.');
            $questions = $quiz->questions;
            if (count($data['answers']) !== count($questions)) {
                throw ValidationException::withMessages(['answers' => 'Barcha savollarga bitta variant tanlang.']);
            }
            $correct = 0;
            $feedback = [];
            foreach ($questions as $index => $question) {
                $selected = $data['answers'][$index] ?? -1;
                if (! array_key_exists($selected, $question['options'])) {
                    throw ValidationException::withMessages(['answers' => 'Tanlangan variant savolga tegishli emas.']);
                }
                $isCorrect = $selected === $question['correct_index'];
                $correct += (int) $isCorrect;
                $feedback[] = $question + ['selected_index' => $selected, 'is_correct' => $isCorrect];
            }
            $score = round($correct / count($questions) * 100, 2);

            return $quiz->attempts()->create([
                'user_id' => $user->id, 'submission_key' => $data['submission_key'], 'revision' => $quiz->revision,
                'correct_count' => $correct, 'question_count' => count($questions), 'score' => $score,
                'passed' => $correct * 100 >= $quiz->pass_percent * count($questions),
                'feedback' => $feedback, 'submitted_at' => now(),
            ]);
        }, attempts: 3);
    }

    /** @return array{attempts: int, completed: int, passed: int, average_best_score: ?float} */
    public function progress(User $user): array
    {
        $best = DB::table('quiz_attempts')->where('user_id', $user->id)
            ->groupBy('lesson_quiz_id')->selectRaw('lesson_quiz_id, MAX(score) as best_score, MAX(CASE WHEN passed THEN 1 ELSE 0 END) as passed');
        $summary = DB::query()->fromSub($best, 'best_quiz_scores')->selectRaw('COUNT(*) as completed, SUM(passed) as passed, AVG(best_score) as average_score')->first();

        return ['attempts' => QuizAttempt::where('user_id', $user->id)->count(), 'completed' => (int) $summary->completed,
            'passed' => (int) $summary->passed, 'average_best_score' => $summary->average_score === null ? null : round((float) $summary->average_score, 1)];
    }
}
