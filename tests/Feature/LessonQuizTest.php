<?php

use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonQuiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** @return array{User, User, LessonQuiz} */
function quizFixture(): array
{
    test()->seed(DatabaseSeeder::class);
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $teacher = User::where('email', 'ustoz@markaz.uz')->firstOrFail();
    $quiz = LessonQuiz::factory()->published()->create();
    $group = Group::create(['name' => 'Quiz group', 'course_id' => $quiz->lesson->course_id, 'status' => 'active']);
    $group->students()->attach($student->id, ['joined_at' => now()]);

    return [$student, $teacher, $quiz];
}

function quizAnswers(array $answers = [0, 1, 0], int $revision = 1): array
{
    return ['answers' => $answers, 'revision' => $revision, 'submission_key' => (string) Str::uuid()];
}

test('quiz endpoints require authentication', function () {
    $quiz = LessonQuiz::factory()->create();
    $this->getJson("/api/v1/lessons/{$quiz->lesson_id}/quiz")->assertUnauthorized();
    $this->putJson("/api/v1/lessons/{$quiz->lesson_id}/quiz")->assertUnauthorized();
    $this->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts")->assertUnauthorized();
});

test('teacher builds and publishes quiz while students cannot access answer keys or edit', function () {
    [$student, $teacher, $quiz] = quizFixture();
    $payload = ['title' => 'Qalqala imtihoni', 'status' => 'published', 'pass_percent' => '70', 'revision' => 1,
        'questions' => [['prompt' => 'Qalqala harflari?', 'options' => ['ق ط ب ج د', 'ي ر م ل و ن'], 'correct_index' => '0', 'explanation' => 'Besh harf.', 'rule' => 'qalqalah']]];
    $this->actingAs($teacher)->putJson("/api/v1/lessons/{$quiz->lesson_id}/quiz", $payload)->assertOk()->assertJsonPath('revision', 2);
    expect($quiz->fresh()->questions[0]['correct_index'])->toBe(0);
    $this->getJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/edit")->assertOk()->assertJsonPath('quiz.questions.0.correct_index', 0);
    $this->actingAs($student)->getJson("/api/v1/lessons/{$quiz->lesson_id}/quiz")->assertOk()
        ->assertJsonMissingPath('quiz.questions.0.correct_index')->assertJsonMissingPath('quiz.questions.0.explanation');
    $this->getJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/edit")->assertForbidden();
    $this->putJson("/api/v1/lessons/{$quiz->lesson_id}/quiz", $payload)->assertForbidden();
    $this->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", quizAnswers([0], 2))
        ->assertOk()->assertJsonPath('attempt.score', 100)->assertJsonPath('attempt.passed', true);
});

test('teacher can create one draft quiz per lesson and avoid stale overwrites', function () {
    [$student, $teacher, $quiz] = quizFixture();
    $lesson = Lesson::create(['course_id' => $quiz->lesson->course_id, 'title' => 'New lesson', 'status' => 'published']);
    $payload = ['title' => 'New quiz', 'status' => 'draft', 'pass_percent' => 70, 'revision' => 0, 'questions' => QuizService::tajwidTemplate()];
    $this->actingAs($teacher)->putJson("/api/v1/lessons/{$lesson->id}/quiz", $payload)->assertOk();
    $this->putJson("/api/v1/lessons/{$lesson->id}/quiz", $payload)->assertStatus(409);
    expect(LessonQuiz::where('lesson_id', $lesson->id)->count())->toBe(1);
    $this->actingAs($student)->getJson("/api/v1/lessons/{$lesson->id}/quiz")->assertOk()->assertJsonPath('quiz', null);
    $this->postJson("/api/v1/lessons/{$lesson->id}/quiz/attempts", quizAnswers())->assertNotFound();
});

test('quiz grading is server authoritative idempotent and included in progress', function () {
    [$student, $teacher, $quiz] = quizFixture();
    $payload = quizAnswers([0, 0, 0]) + ['score' => 100, 'passed' => true, 'user_id' => $teacher->id];
    $this->actingAs($student)->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", $payload)->assertOk()
        ->assertJsonPath('attempt.score', 66.67)->assertJsonPath('attempt.passed', false)
        ->assertJsonPath('attempt.feedback.1.is_correct', false)->assertJsonPath('attempt.feedback.1.correct_index', 1);
    $this->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", $payload)->assertOk()->assertJsonPath('quiz_progress.attempts', 1);
    $this->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", quizAnswers())->assertOk()->assertJsonPath('quiz_progress.completed', 1)->assertJsonPath('quiz_progress.passed', 1)->assertJsonPath('quiz_progress.average_best_score', 100);
    $this->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", quizAnswers([1, 0, 1]))->assertOk()->assertJsonPath('quiz_progress.average_best_score', 100);
    $this->getJson('/api/v1/progress/me')->assertOk()->assertJsonPath('quizzes.attempts', 3)->assertJsonPath('quizzes.completed', 1)->assertJsonPath('quizzes.average_best_score', 100);
    expect(QuizAttempt::where('user_id', $student->id)->count())->toBe(3)
        ->and(QuizAttempt::where('user_id', $teacher->id)->count())->toBe(0)
        ->and(DB::table('lesson_completions')->where('lesson_id', $quiz->lesson_id)->count())->toBe(0);
});

test('unenrolled and departed students cannot view or submit quiz', function () {
    [$student, $teacher, $quiz] = quizFixture();
    DB::table('group_students')->where('student_id', $student->id)->update(['left_at' => now()]);
    $this->actingAs($student)->getJson("/api/v1/lessons/{$quiz->lesson_id}/quiz")->assertNotFound();
    $this->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", quizAnswers())->assertNotFound();
    $this->actingAs($teacher)->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", quizAnswers())->assertForbidden();
    $this->assertDatabaseCount('quiz_attempts', 0);
});

test('unpublished lessons and inactive groups do not expose quizzes', function (string $scope) {
    [$student, $teacher, $quiz] = quizFixture();
    if ($scope === 'lesson') {
        $quiz->lesson->update(['status' => 'draft']);
    } else {
        Group::where('course_id', $quiz->lesson->course_id)->update(['status' => 'archived']);
    }
    $this->actingAs($student)->getJson("/api/v1/lessons/{$quiz->lesson_id}/quiz")->assertNotFound();
    $this->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", quizAnswers())->assertNotFound();
})->with(['lesson', 'group']);

test('invalid answers and stale quiz revisions cannot create results', function (array $payload, int $status) {
    [$student, $teacher, $quiz] = quizFixture();
    $this->actingAs($student)->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", $payload + quizAnswers())->assertStatus($status);
    $this->assertDatabaseCount('quiz_attempts', 0);
})->with([
    [['answers' => [0, 1]], 422], [['answers' => [0, 5, 0]], 422], [['answers' => [null, 1, 0]], 422],
    [['answers' => [1 => 0, 2 => 1, 3 => 0]], 422], [['revision' => 99], 409], [['submission_key' => 'invalid'], 422],
]);

test('builder rejects invalid correct choices duplicate options and empty questions', function (array $changes, string $error) {
    [$student, $teacher, $quiz] = quizFixture();
    $payload = ['title' => 'Tajvid quiz', 'status' => 'published', 'revision' => 1, 'pass_percent' => 70, 'questions' => QuizService::tajwidTemplate()];
    $payload = array_replace_recursive($payload, $changes);
    if (array_key_exists('questions', $changes) && $changes['questions'] === []) {
        $payload['questions'] = [];
    }
    $this->actingAs($teacher)->putJson("/api/v1/lessons/{$quiz->lesson_id}/quiz", $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
    expect($quiz->fresh()->revision)->toBe(1);
})->with([
    [['questions' => [0 => ['correct_index' => 5]]], 'questions.0.correct_index'],
    [['questions' => [0 => ['options' => ['Same', 'same', 'Other']]]], 'questions.0.options'],
    [['questions' => []], 'questions'], [['pass_percent' => 101], 'pass_percent'],
]);

test('quiz edits retain historical feedback and refuse outdated new submissions', function () {
    [$student, $teacher, $quiz] = quizFixture();
    $payload = quizAnswers();
    $this->actingAs($student)->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", $payload)->assertOk();
    $quiz->update(['revision' => 2, 'questions' => [['prompt' => 'New question', 'options' => ['A', 'B'], 'correct_index' => 1]]]);
    $this->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", quizAnswers())->assertStatus(409);
    $this->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", $payload)->assertOk()->assertJsonPath('attempt.score', 100);
    $this->getJson("/api/v1/lessons/{$quiz->lesson_id}/quiz")->assertOk()->assertJsonCount(0, 'attempts');
    expect(QuizAttempt::firstOrFail()->feedback[0]['prompt'])->toBe('Madd tabiiy necha harakat cho‘ziladi?');
});

test('web builder student test results and dashboard render without exposing hidden keys', function () {
    [$student, $teacher, $quiz] = quizFixture();
    $this->actingAs($teacher)->get(route('lessons.quiz.edit', $quiz->lesson_id))->assertOk()->assertSee('Quiz builder');
    $this->actingAs($student)->get(route('lessons.quiz.show', $quiz->lesson_id))->assertOk()->assertSee('Madd tabiiy')->assertDontSee('correct_index');
    $this->post(route('lessons.quiz.attempt', $quiz->lesson_id), quizAnswers())->assertRedirect(route('lessons.quiz.show', $quiz->lesson_id));
    $this->get(route('lessons.quiz.show', $quiz->lesson_id))->assertOk()->assertSee('100%')->assertSee('Javoblar va izohlar');
    $this->get(route('dashboard'))->assertOk()->assertSee('Test natijalari');
});

test('quiz feedback stays private and content is escaped in web views', function () {
    [$student, $teacher, $quiz] = quizFixture();
    $foreign = User::factory()->create();
    QuizAttempt::factory()->create(['lesson_quiz_id' => $quiz->id, 'user_id' => $foreign->id, 'feedback' => [['private' => 'other-student-feedback']]]);
    $questions = $quiz->questions;
    $questions[0]['prompt'] = '<script>alert("quiz")</script>';
    $quiz->update(['questions' => $questions]);
    $this->actingAs($student)->getJson("/api/v1/lessons/{$quiz->lesson_id}/quiz")
        ->assertOk()->assertJsonCount(0, 'attempts')->assertDontSee('other-student-feedback');
    $this->get(route('lessons.quiz.show', $quiz->lesson_id))->assertOk()
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("quiz")</script>', false);
    $this->actingAs($teacher)->get(route('lessons.quiz.edit', $quiz->lesson_id))->assertOk()
        ->assertDontSee('<script>alert("quiz")</script>', false);
});

test('empty quiz progress remains empty and parent can read only linked results', function () {
    [$student, $teacher, $quiz] = quizFixture();
    $parent = User::where('email', 'ota@markaz.uz')->firstOrFail();
    expect(app(QuizService::class)->progress($student))->toBe(['attempts' => 0, 'completed' => 0, 'passed' => 0, 'average_best_score' => null]);
    $this->actingAs($student)->postJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/attempts", quizAnswers())->assertOk();
    $this->actingAs($parent)->getJson("/api/v1/progress/students/{$student->id}")->assertForbidden();
    $parent->children()->attach($student->id, ['relation' => 'parent']);
    $this->getJson("/api/v1/progress/students/{$student->id}")->assertOk()->assertJsonPath('quizzes.average_best_score', 100);
    $this->getJson("/api/v1/lessons/{$quiz->lesson_id}/quiz/edit")->assertForbidden();
    $this->get(route('parents.dashboard'))->assertOk()->assertSee('Test natijalari');
});
