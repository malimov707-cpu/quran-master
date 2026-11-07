<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonQuiz;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;
use Illuminate\View\View;

class LessonQuizController extends Controller
{
    public function __construct(private QuizService $quizzes) {}

    public function show(Request $request, Lesson $lesson): JsonResponse|View
    {
        $canManage = $request->user()->hasPermission('lessons.update');
        if (! $canManage) {
            $this->quizzes->authorizeStudent($request->user(), $lesson);
        }
        $quiz = LessonQuiz::where('lesson_id', $lesson->id)->where('status', 'published')->first();
        $data = ['submission_key' => (string) Str::uuid(), 'lesson' => $lesson->only('id', 'title'), 'can_manage' => $canManage, 'can_submit' => ! $canManage && $quiz !== null,
            'quiz' => $quiz ? $quiz->toArray() + ['questions' => array_map(fn (array $q): array => ['prompt' => $q['prompt'], 'options' => $q['options'], 'rule' => $q['rule'] ?? null], $quiz->questions)] : null,
            'attempts' => $quiz ? $quiz->attempts()->where('user_id', $request->user()->id)->where('revision', $quiz->revision)->latest('id')->limit(5)->get() : collect()];

        return $request->expectsJson() ? response()->json($data) : view('education.quiz', $data);
    }

    public function edit(Request $request, Lesson $lesson): JsonResponse|View
    {
        abort_unless($request->user()->hasPermission('lessons.update'), 403);
        $quiz = LessonQuiz::where('lesson_id', $lesson->id)->first();
        $data = ['lesson' => $lesson->only('id', 'title'), 'quiz' => $quiz ? $quiz->toArray() + ['questions' => $quiz->questions] : null,
            'template' => QuizService::tajwidTemplate()];

        return $request->expectsJson() ? response()->json($data) : view('education.quiz-builder', $data);
    }

    public function update(Request $request, Lesson $lesson): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->hasPermission('lessons.update'), 403);
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'], 'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'awards_certificate' => ['sometimes', 'boolean'], 'revision' => ['required', 'integer', 'min:0'], 'pass_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'questions' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'questions.*' => ['required', 'array:prompt,options,correct_index,explanation,rule'],
            'questions.*.prompt' => ['required', 'string', 'max:1000'],
            'questions.*.options' => ['required', 'array', 'list', 'min:2', 'max:6'],
            'questions.*.options.*' => ['required', 'string', 'max:500'],
            'questions.*.correct_index' => ['required', 'integer', 'min:0', 'max:5'],
            'questions.*.explanation' => ['nullable', 'string', 'max:2000'],
            'questions.*.rule' => ['nullable', 'string', 'max:80'],
        ]);
        $validator->after(function (ValidationValidator $validator) use ($request): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($request->input('questions', []) as $index => $question) {
                if (! array_key_exists((int) $question['correct_index'], $question['options'])) {
                    $validator->errors()->add("questions.$index.correct_index", 'To‘g‘ri javob mavjud variantlardan tanlanishi kerak.');
                }
                $options = array_map(fn (string $option): string => mb_strtolower(trim($option)), $question['options']);
                if (count(array_unique($options)) !== count($options)) {
                    $validator->errors()->add("questions.$index.options", 'Variantlar bir-biridan farq qilishi kerak.');
                }
            }
        });
        $data = $validator->validate();
        $data['awards_certificate'] = $request->boolean('awards_certificate');
        $data['questions'] = array_map(function (array $question): array {
            $question['correct_index'] = (int) $question['correct_index'];

            return $question;
        }, $data['questions']);
        if ($data['status'] === 'published' || $data['awards_certificate']) {
            abort_unless($request->user()->hasPermission('lessons.publish'), 403);
        }
        $quiz = DB::transaction(function () use ($request, $lesson, $data): LessonQuiz {
            Lesson::whereKey($lesson->id)->lockForUpdate()->firstOrFail();
            $quiz = LessonQuiz::firstOrNew(['lesson_id' => $lesson->id]);
            abort_unless(($quiz->revision ?? 0) === (int) $data['revision'], 409, 'Test boshqa oynada yangilangan. Sahifani yangilang.');
            $quiz->fill($data);
            $quiz->revision = (int) $data['revision'] + 1;
            $quiz->created_by ??= $request->user()->id;
            $quiz->save();

            return $quiz;
        }, attempts: 3);

        return $request->expectsJson() ? response()->json(['message' => 'Test saqlandi.', 'revision' => $quiz->revision])
            : redirect()->route('lessons.quiz.edit', $lesson)->with('status', 'Test saqlandi.');
    }

    public function attempt(Request $request, Lesson $lesson): JsonResponse|RedirectResponse
    {
        $this->quizzes->authorizeStudent($request->user(), $lesson);
        $data = $request->validate([
            'submission_key' => ['required', 'uuid'], 'revision' => ['required', 'integer', 'min:1'],
            'answers' => ['required', 'array', 'list', 'min:1', 'max:50'], 'answers.*' => ['required', 'integer', 'min:0', 'max:5'],
        ]);
        $data['answers'] = array_map(intval(...), $data['answers']);
        $data['revision'] = (int) $data['revision'];
        $attempt = $this->quizzes->grade($request->user(), $lesson, $data);

        return $request->expectsJson() ? response()->json(['attempt' => $attempt, 'next_submission_key' => (string) Str::uuid(), 'quiz_progress' => $this->quizzes->progress($request->user())])
            : redirect()->route('lessons.quiz.show', $lesson)->with('status', 'Test avtomatik baholandi.');
    }
}
