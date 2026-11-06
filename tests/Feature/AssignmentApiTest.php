<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function testAssignmentFixture(): array
{
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $mentor = User::where('email', 'mentor@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    $course = Course::create(['name' => 'K', 'level' => 'beginner']);
    $group = Group::create(['name' => 'G', 'course_id' => $course->id, 'mentor_id' => $mentor->id]);
    $group->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);

    $assignment = Assignment::create([
        'group_id' => $group->id,
        'created_by' => $admin->id,
        'title' => 'Fotiha surasini o\'qish',
        'surah_number' => 1,
        'ayah_from' => 1,
        'ayah_to' => 7,
        'status' => 'published',
        'due_at' => now()->addDays(3),
    ]);

    return [$admin, $mentor, $student, $assignment];
}

test('head teacher can create assignment', function () {
    [$admin, $mentor, $student, $group] = testAssignmentFixture();
    $token = User::where('email', 'ustoz@markaz.uz')->first()->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/assignments', [
        'group_id' => 1,
        'title' => 'Baqara 1-5',
        'surah_number' => 2,
        'ayah_from' => 1,
        'ayah_to' => 5,
        'due_at' => now()->addWeek()->toIso8601String(),
    ])->assertCreated()->assertJsonPath('assignment.title', 'Baqa 1-5' === '' ? '' : 'Baqara 1-5');
});

test('mentor cannot create assignment', function () {
    testAssignmentFixture();
    $token = User::where('email', 'mentor@markaz.uz')->first()->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/assignments', [
        'group_id' => 1, 'title' => 'X',
    ])->assertStatus(403);
});

test('student submits audio for assignment', function () {
    [, , $student, $assignment] = testAssignmentFixture();
    $token = $student->createToken('t')->plainTextToken;

    // Soxta audio (valid wav sarlavhasi bilan)
    $audio = File::fake()->createWithContent('recitation.wav', str_repeat('RIFF', 100));

    $response = $this->withToken($token)
        ->post("/api/v1/assignments/{$assignment->id}/submit", [
            'student_note' => 'Uyda mashq qildim',
            'audio' => [$audio],
        ]);

    $response->assertCreated();
    expect(Submission::where('student_id', $student->id)->exists())->toBeTrue();
});

test('student cannot submit without audio when required', function () {
    [, , $student, $assignment] = testAssignmentFixture();
    $token = $student->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson("/api/v1/assignments/{$assignment->id}/submit", [
        'student_note' => 'audio yo\'q',
    ])->assertStatus(422);
});

test('non-group student cannot submit', function () {
    [$admin, $mentor, $student, $assignment] = testAssignmentFixture();

    $outsider = User::create(['name' => 'Out', 'email' => 'out@markaz.uz', 'password' => 'Parol!123']);
    $outsider->roles()->attach(5); // STUDENT
    $token = $outsider->createToken('t')->plainTextToken;

    $audio = File::fake()->createWithContent('a.wav', str_repeat('RIFF', 100));
    $this->withToken($token)
        ->post("/api/v1/assignments/{$assignment->id}/submit", [
            'audio' => [$audio],
        ])->assertStatus(403);
});

test('student sees own submission history', function () {
    [, , $student, $assignment] = testAssignmentFixture();
    $token = $student->createToken('t')->plainTextToken;

    Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now(),
    ]);

    $this->withToken($token)->getJson('/api/v1/submissions/me')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.assignment.title', 'Fotiha surasini o\'qish');
});

test('mentor sees pending reviews and can grade with segments', function () {
    [, $mentor, $student, $assignment] = testAssignmentFixture();
    $token = $mentor->createToken('t')->plainTextToken;

    $submission = Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now(),
    ]);

    $this->withToken($token)->getJson('/api/v1/submissions/pending')
        ->assertOk()
        ->assertJsonCount(1);

    $this->withToken($token)->postJson("/api/v1/submissions/{$submission->id}/review", [
        'score' => 85,
        'action' => 'approve',
        'comment' => 'Yaxshi, faqat 4-oyatda ghunnah kuchaytirilishi kerak.',
        'segments' => [
            ['ayah_from' => 4, 'start_ms' => 12500, 'end_ms' => 14800, 'error_type' => 'tajweed_other', 'comment' => 'Ghunnah'],
        ],
    ])->assertCreated();

    expect($submission->refresh()->status)->toBe('graded')
        ->and($submission->final_score)->toBe(85);
});

test('mentor can request resubmission', function () {
    [, $mentor, $student, $assignment] = testAssignmentFixture();
    $token = $mentor->createToken('t')->plainTextToken;

    $submission = Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now(),
    ]);

    $this->withToken($token)->postJson("/api/v1/submissions/{$submission->id}/review", [
        'action' => 'resubmit',
        'comment' => 'Qayta yozib yuboring.',
    ])->assertCreated();

    expect($submission->refresh()->status)->toBe('resubmit')
        ->and($submission->final_score)->toBeNull();
});

test('student cannot review submissions', function () {
    [, $mentor, $student, $assignment] = testAssignmentFixture();

    $submission = Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now(),
    ]);

    $token = $student->createToken('t')->plainTextToken;
    $this->withToken($token)->postJson("/api/v1/submissions/{$submission->id}/review", [
        'action' => 'approve', 'score' => 100,
    ])->assertStatus(403);
});

test('resubmitted attempt increments', function () {
    [$admin, $mentor, $student, $assignment] = testAssignmentFixture();
    $mentorToken = $mentor->createToken('t')->plainTextToken;
    $studentToken = $student->createToken('t')->plainTextToken;

    Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'status' => 'resubmit', 'attempt' => 1, 'submitted_at' => now(),
    ]);

    $audio = File::fake()->createWithContent('r2.wav', str_repeat('RIFF', 100));
    $response = $this->withToken($studentToken)
        ->post("/api/v1/assignments/{$assignment->id}/submit", [
            'audio' => [$audio],
        ])->assertCreated();

    expect($response->json('submission.attempt'))->toBe(2);
});

// --- Web Review Studio testlari ---

test('mentor sees review studio with audio player', function () {
    [$admin, $mentor, $student, $assignment] = testAssignmentFixture();

    $submission = Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now(),
    ]);
    $submission->files()->create([
        'disk' => 'public', 'path' => 'submissions/1/test.wav',
        'mime_type' => 'audio/wav', 'size_bytes' => 1000, 'original_name' => 'test.wav',
    ]);

    $this->actingAs($mentor)->get(route('mentor.review-studio', $submission))
        ->assertOk()
        ->assertSee($student->name)
        ->assertSee('audio controls');
});

test('student cannot open review studio', function () {
    [$admin, $mentor, $student, $assignment] = testAssignmentFixture();

    $submission = Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now(),
    ]);

    // Student submissions.review permissioniga ega emas — Review Studio mentor uchun
    $response = $this->actingAs($student)->get(route('mentor.review-studio', $submission));

    // Student role submissions.view bor lekin review yo'q — sahifa ochiladi lekin forma yubora olmaydi.
    // Xavfsizlik asosiy tekshiruv API darajasida bajariladi (yuoqoridagi test).
    $response->assertForbidden();
});

test('mentor reviews submission via web form', function () {
    [$admin, $mentor, $student, $assignment] = testAssignmentFixture();

    $submission = Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now(),
    ]);

    $this->actingAs($mentor)->post(route('mentor.review', $submission), [
        'score' => 90,
        'action' => 'approve',
        'comment' => 'Ajoyib!',
    ])->assertRedirect(route('mentor.submissions'))->assertSessionHas('status');

    expect($submission->refresh()->status)->toBe('graded')
        ->and($submission->final_score)->toBe(90);
});
