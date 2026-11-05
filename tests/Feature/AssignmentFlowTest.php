<?php

use App\Jobs\AnalyzeRecitationJob;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** @return array{User, User, User, Assignment} */
function assignmentFlowFixture(): array
{
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $course = Course::create(['name' => 'Qiroat', 'level' => 'beginner']);
    $group = Group::create(['name' => 'Qiroat guruhi', 'course_id' => $course->id]);
    $group->students()->attach($student, ['joined_at' => now()]);
    $assignment = Assignment::create(['group_id' => $group->id, 'created_by' => $admin->id, 'title' => 'Fotiha', 'status' => 'published']);

    return [$admin, $mentor, $student, $assignment];
}

test('student sees only published assignments in active groups and own attempts', function () {
    $this->seed(DatabaseSeeder::class);
    [, , $student, $assignment] = assignmentFlowFixture();
    $other = User::factory()->create();
    Submission::create(['assignment_id' => $assignment->id, 'student_id' => $other->id, 'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now()]);
    Assignment::create(['group_id' => $assignment->group_id, 'created_by' => $assignment->created_by, 'title' => 'Draft', 'status' => 'draft']);

    $this->actingAs($student, 'sanctum')->getJson('/api/v1/assignments')
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $assignment->id)->assertJsonPath('0.submissions', []);
});

test('former group member cannot see or open assignments', function () {
    $this->seed(DatabaseSeeder::class);
    [, , $student, $assignment] = assignmentFlowFixture();
    $assignment->group->students()->updateExistingPivot($student->id, ['left_at' => now()]);

    $this->actingAs($student, 'sanctum')->getJson('/api/v1/assignments')->assertOk()->assertJsonCount(0);
    $this->getJson("/api/v1/assignments/{$assignment->id}")->assertForbidden();
});

test('assignment list requires authentication and student permission', function () {
    $this->getJson('/api/v1/assignments')->assertUnauthorized();
    $this->seed(DatabaseSeeder::class);
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();

    $this->actingAs($mentor, 'sanctum')->getJson('/api/v1/assignments')->assertForbidden();
});

test('student can open a published assignment with own submission history', function () {
    $this->seed(DatabaseSeeder::class);
    [, , $student, $assignment] = assignmentFlowFixture();
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'resubmit', 'attempt' => 1, 'submitted_at' => now()]);

    $this->actingAs($student, 'sanctum')->getJson("/api/v1/assignments/{$assignment->id}")
        ->assertOk()->assertJsonPath('id', $assignment->id)->assertJsonPath('submissions.0.id', $submission->id);
    $assignment->update(['status' => 'draft']);
    $this->getJson("/api/v1/assignments/{$assignment->id}")->assertNotFound();
});

test('owner and mentor can listen to private audio but unrelated student cannot', function () {
    $this->seed(DatabaseSeeder::class);
    [, $mentor, $student, $assignment] = assignmentFlowFixture();
    Storage::fake('local');
    Storage::disk('local')->put('submissions/voice.m4a', 'private audio bytes');
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now()]);
    $file = $submission->files()->create(['disk' => 'local', 'path' => 'submissions/voice.m4a', 'original_name' => 'voice.m4a', 'mime_type' => 'audio/mp4', 'size_bytes' => 19]);
    $url = route('api.submissions.audio', [$submission, $file]);

    $this->getJson($url)->assertUnauthorized();
    $this->actingAs($student, 'sanctum')->get($url)->assertOk()->assertHeader('Content-Type', 'audio/mp4');
    $this->actingAs($mentor, 'sanctum')->get($url)->assertOk();
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertForbidden();
    Storage::disk('local')->assertExists('submissions/voice.m4a');
});

test('audio endpoint rejects files from another submission and missing files', function () {
    $this->seed(DatabaseSeeder::class);
    [, $mentor, $student, $assignment] = assignmentFlowFixture();
    Storage::fake('local');
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now()]);
    $other = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'submitted', 'attempt' => 2, 'submitted_at' => now()]);
    $file = $other->files()->create(['disk' => 'local', 'path' => 'missing.m4a', 'original_name' => 'voice.m4a', 'mime_type' => 'audio/mp4', 'size_bytes' => 19]);

    $this->actingAs($mentor, 'sanctum')->getJson(route('api.submissions.audio', [$submission, $file]))->assertNotFound();
    $this->getJson(route('api.submissions.audio', [$other, $file]))->assertNotFound();
    Storage::disk('local')->assertMissing('missing.m4a');
});

test('web mentor studio uses authenticated audio and refuses students', function () {
    $this->seed(DatabaseSeeder::class);
    [, $mentor, $student, $assignment] = assignmentFlowFixture();
    Storage::fake('local');
    Storage::disk('local')->put('voice.m4a', 'private audio');
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now()]);
    $file = $submission->files()->create(['disk' => 'local', 'path' => 'voice.m4a', 'original_name' => 'voice.m4a', 'mime_type' => 'audio/mp4', 'size_bytes' => 13]);

    $this->actingAs($mentor)->get(route('mentor.review-studio', $submission))->assertOk()->assertSee(route('submissions.audio', [$submission, $file]));
    $this->get(route('submissions.audio', [$submission, $file]))->assertOk();
    $this->actingAs($student)->get(route('mentor.review-studio', $submission))->assertForbidden();
    $this->get(route('mentor.submissions'))->assertForbidden();
    Storage::disk('local')->assertExists('voice.m4a');
});

test('student uploads a single recording and queues analysis', function () {
    $this->seed(DatabaseSeeder::class);
    [, , $student, $assignment] = assignmentFlowFixture();
    Queue::fake([AnalyzeRecitationJob::class]);
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
    $audio = File::fake()->createWithContent('recitation.wav', str_repeat('RIFF', 100));

    $response = $this->actingAs($student, 'sanctum')->post("/api/v1/assignments/{$assignment->id}/submit", ['audio' => $audio]);

    $response->assertCreated()->assertJsonPath('submission.attempt', 1)->assertJsonPath('submission.status', 'submitted');
    $this->assertDatabaseHas('submissions', ['id' => $response->json('submission.id'), 'student_id' => $student->id, 'assignment_id' => $assignment->id]);
    Storage::disk('local')->assertExists($response->json('submission.files.0.path'));
    Queue::assertPushed(AnalyzeRecitationJob::class);
});

test('mentor segment comments are saved and visible to the student', function () {
    $this->seed(DatabaseSeeder::class);
    [, $mentor, $student, $assignment] = assignmentFlowFixture();
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now()]);

    $this->actingAs($mentor, 'sanctum')->postJson("/api/v1/submissions/{$submission->id}/review", [
        'score' => 85, 'action' => 'approve', 'comment' => 'Mashq qiling',
        'segments' => [['ayah_from' => 4, 'ayah_to' => 4, 'start_ms' => 12500, 'end_ms' => 14800, 'error_type' => 'pronunciation', 'comment' => 'Talaffuzni takrorlang']],
    ])->assertCreated()->assertJsonPath('review.segments.0.comment', 'Talaffuzni takrorlang');

    $this->assertDatabaseHas('mentor_review_segments', ['ayah_from' => 4, 'start_ms' => 12500, 'end_ms' => 14800, 'error_type' => 'pronunciation']);
    $this->actingAs($student, 'sanctum')->getJson("/api/v1/submissions/{$submission->id}")
        ->assertOk()->assertJsonPath('reviews.0.segments.0.comment', 'Talaffuzni takrorlang');
});

test('invalid segment ranges do not save a review', function (array $segment, string $error) {
    $this->seed(DatabaseSeeder::class);
    [, $mentor, $student, $assignment] = assignmentFlowFixture();
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now()]);

    $this->actingAs($mentor, 'sanctum')->postJson("/api/v1/submissions/{$submission->id}/review", [
        'score' => 85, 'action' => 'approve', 'comment' => 'Mashq',
        'segments' => [$segment + ['error_type' => 'pronunciation', 'comment' => 'Talaffuz']],
    ])->assertUnprocessable()->assertJsonValidationErrors($error);

    $this->assertDatabaseCount('mentor_reviews', 0);
    $this->assertDatabaseHas('submissions', ['id' => $submission->id, 'status' => 'submitted']);
})->with([
    'reversed time' => [['start_ms' => 1000, 'end_ms' => 500], 'segments.0.end_ms'],
    'missing end' => [['start_ms' => 1000], 'segments.0.end_ms'],
    'missing start' => [['end_ms' => 1000], 'segments.0.start_ms'],
    'reversed ayahs' => [['ayah_from' => 4, 'ayah_to' => 2], 'segments.0.ayah_to'],
    'unknown fields' => [['mentor_review_id' => 999], 'segments.0'],
]);
