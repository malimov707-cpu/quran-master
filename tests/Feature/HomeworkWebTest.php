<?php

use App\Jobs\AnalyzeRecitationJob;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\MentorReview;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** @return array{User, User, Assignment} */
function homeworkWebFixture(): array
{
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $course = Course::create(['name' => 'Qiroat', 'level' => 'beginner']);
    $group = Group::create(['name' => 'Audio guruhi', 'course_id' => $course->id]);
    $group->students()->attach($student, ['joined_at' => now()]);
    $assignment = Assignment::create(['group_id' => $group->id, 'created_by' => $mentor->id, 'title' => 'Fotiha audio vazifasi', 'status' => 'published', 'requires_audio' => true]);

    return [$student, $mentor, $assignment];
}

test('homework pages require login', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/homework', '/homework/1', '/my-submissions/1']);

test('student sees only published homework from active groups and own history', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, $mentor, $assignment] = homeworkWebFixture();
    $draft = $assignment->replicate();
    $draft->fill(['title' => 'Hidden draft', 'status' => 'draft'])->save();
    $other = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $mentor->id, 'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now()]);

    $this->actingAs($student)->get(route('homework.index'))->assertOk()->assertSee($assignment->title)
        ->assertDontSee('Hidden draft')->assertDontSee(route('homework.submission', $other));
    $this->get(route('homework.show', $assignment))->assertOk()->assertSee('multipart/form-data')->assertSee('Topshiriqni yuborish');
    $assignment->group->students()->updateExistingPivot($student->id, ['left_at' => now()]);
    $this->get(route('homework.index'))->assertOk()->assertDontSee($assignment->title);
    $this->get(route('homework.show', $assignment))->assertNotFound();
});

test('mentor cannot access student homework list or upload form', function () {
    $this->seed(DatabaseSeeder::class);
    [, $mentor, $assignment] = homeworkWebFixture();

    $this->actingAs($mentor)->get(route('homework.index'))->assertForbidden();
    $this->get(route('homework.show', $assignment))->assertForbidden();
    $this->post(route('homework.submit', $assignment))->assertForbidden();
});

test('web audio upload stores file and queues analysis before showing result', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, , $assignment] = homeworkWebFixture();
    Queue::fake([AnalyzeRecitationJob::class]);
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
    $audio = File::fake()->createWithContent('recitation.wav', str_repeat('RIFF', 100));

    $response = $this->actingAs($student)->post(route('homework.submit', $assignment), ['audio' => $audio, 'student_note' => 'Mashq yozuvi']);

    $submission = Submission::where('student_id', $student->id)->firstOrFail();
    $response->assertRedirect(route('homework.submission', $submission))->assertSessionHas('status');
    $this->assertDatabaseHas('submissions', ['id' => $submission->id, 'status' => 'submitted', 'attempt' => 1, 'student_note' => 'Mashq yozuvi']);
    Storage::disk('local')->assertExists($submission->files()->firstOrFail()->path);
    Queue::assertPushed(AnalyzeRecitationJob::class, 1);
    $this->get(route('homework.submission', $submission))->assertOk()->assertSee('Mashq yozuvi')->assertSee('audio controls');
});

test('invalid audio uploads do not create submissions', function (string $scenario, string $field) {
    $this->seed(DatabaseSeeder::class);
    [$student, , $assignment] = homeworkWebFixture();
    Queue::fake([AnalyzeRecitationJob::class]);
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
    $payload = match ($scenario) {
        'missing' => [],
        'wrong type' => ['audio' => File::fake()->createWithContent('fake.mp3', 'not audio')->mimeType('text/plain')],
        'too large' => ['audio' => File::fake()->create('large.wav', 20481, 'audio/wav')],
        'note too long' => ['student_note' => str_repeat('x', 5001)],
    };

    $this->actingAs($student)->from(route('homework.show', $assignment))->post(route('homework.submit', $assignment), $payload)
        ->assertRedirect(route('homework.show', $assignment))->assertSessionHasErrors($field);

    $this->assertDatabaseCount('submissions', 0);
    Queue::assertNothingPushed();
    Storage::disk('local')->assertEmpty();
})->with([
    ['missing', 'audio'], ['wrong type', 'audio'], ['too large', 'audio'], ['note too long', 'student_note'],
]);

test('non member cannot upload to another group', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, , $assignment] = homeworkWebFixture();
    $assignment->group->students()->detach($student);

    $this->actingAs($student)->post(route('homework.submit', $assignment))->assertNotFound();
    $this->assertDatabaseCount('submissions', 0);
});

test('closed homework hides upload form and rejects uploads', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, , $assignment] = homeworkWebFixture();
    $assignment->update(['status' => 'closed', 'requires_audio' => false]);

    $this->actingAs($student)->get(route('homework.show', $assignment))->assertOk()->assertSee('Bu topshiriq yopilgan.')->assertDontSee('multipart/form-data');
    $this->post(route('homework.submit', $assignment))->assertUnprocessable();
    $this->assertDatabaseCount('submissions', 0);
});

test('student can submit note without audio for an optional audio assignment', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, , $assignment] = homeworkWebFixture();
    $assignment->update(['requires_audio' => false]);
    Queue::fake([AnalyzeRecitationJob::class]);

    $this->actingAs($student)->post(route('homework.submit', $assignment), ['student_note' => 'Tayyor'])->assertRedirect();

    $this->assertDatabaseHas('submissions', ['student_id' => $student->id, 'student_note' => 'Tayyor']);
    $this->assertDatabaseCount('submission_files', 0);
    Queue::assertPushed(AnalyzeRecitationJob::class, 1);
});

test('student sees mentor segments and can resubmit with a new attempt', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, $mentor, $assignment] = homeworkWebFixture();
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'resubmit', 'attempt' => 1, 'submitted_at' => now()]);
    $review = MentorReview::create(['submission_id' => $submission->id, 'mentor_id' => $mentor->id, 'action' => 'resubmit', 'comment' => 'Qayta mashq qiling', 'reviewed_at' => now()]);
    $review->segments()->create(['ayah_from' => 4, 'start_ms' => 12500, 'end_ms' => 14800, 'error_type' => 'pronunciation', 'comment' => 'Talaffuzni takrorlang']);
    Queue::fake([AnalyzeRecitationJob::class]);
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $this->actingAs($student)->get(route('homework.submission', $submission))->assertOk()
        ->assertSee('Qayta topshirish')->assertSee('Talaffuzni takrorlang')->assertSee('12.5–14.8 soniya');
    $this->post(route('homework.submit', $assignment), ['audio' => File::fake()->createWithContent('new.wav', str_repeat('RIFF', 100))])->assertRedirect();

    $this->assertDatabaseHas('submissions', ['assignment_id' => $assignment->id, 'student_id' => $student->id, 'attempt' => 2, 'status' => 'submitted']);
    Storage::disk('local')->assertCount('/submissions', 1, true);
    Queue::assertPushed(AnalyzeRecitationJob::class, 1);
});

test('another user cannot see private submission details', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, , $assignment] = homeworkWebFixture();
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'graded', 'attempt' => 1, 'final_score' => 0, 'submitted_at' => now()]);

    $this->actingAs(User::factory()->create())->get(route('homework.submission', $submission))->assertNotFound();
    $this->actingAs($student)->get(route('homework.submission', $submission))->assertOk()->assertSee('0 ball');
});
