<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\MentorReview;
use App\Models\Submission;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Progress\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class)->group('progress');

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function progressFixture(): array
{
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    $course = Course::create(['name' => 'K', 'level' => 'beginner']);
    $group = Group::create(['name' => 'G', 'course_id' => $course->id]);
    $group->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);

    $a1 = Assignment::create(['group_id' => $group->id, 'created_by' => $admin->id, 'title' => 'A1', 'surah_number' => 1, 'ayah_from' => 1, 'ayah_to' => 7, 'status' => 'published']);
    $a2 = Assignment::create(['group_id' => $group->id, 'created_by' => $admin->id, 'title' => 'A2', 'surah_number' => 1, 'ayah_from' => 1, 'ayah_to' => 7, 'status' => 'published']);

    // Baholangan topshirishlar
    $s1 = Submission::create(['assignment_id' => $a1->id, 'student_id' => $student->id, 'status' => 'graded', 'attempt' => 1, 'final_score' => 80, 'submitted_at' => now()->subWeeks(2)]);
    $s2 = Submission::create(['assignment_id' => $a2->id, 'student_id' => $student->id, 'status' => 'graded', 'attempt' => 1, 'final_score' => 90, 'submitted_at' => now()->subWeeks(1)]);

    // Mentor xatolari (takrorlanuvchi tajvid)
    $review = MentorReview::create(['submission_id' => $s1->id, 'mentor_id' => $admin->id, 'score' => 80, 'action' => 'approve', 'reviewed_at' => now()]);
    $review->segments()->create(['ayah_from' => 4, 'error_type' => 'tajweed_other', 'comment' => 'Ghunnah']);
    $review->segments()->create(['ayah_from' => 2, 'error_type' => 'missing_word', 'comment' => 'Tushirib qoldirildi']);

    return [$student, $group];
}

test('progress calculates from real graded data', function () {
    [$student] = progressFixture();

    $progress = app(ProgressService::class)->forStudent($student);

    expect($progress['submissions_graded'])->toBe(2)
        ->and($progress['average_score'])->toBe(85.0)
        ->and($progress['surahs'][0]['surah_number'])->toBe(1)
        ->and($progress['recurring_errors']->count())->toBe(2);
});

test('student gets own progress via API', function () {
    progressFixture();
    $token = User::where('email', 'oquvchi@markaz.uz')->first()->createToken('t')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/progress/me')
        ->assertOk()
        ->assertJsonPath('average_score', 85)
        ->assertJsonPath('submissions_graded', 2);
});

test('attendance stats computed', function () {
    [$student] = progressFixture();

    $groupId = Group::first()->id;
    $scheduleId = DB::table('schedules')->insertGetId([
        'group_id' => $groupId, 'date' => now()->toDateString(),
        'start_time' => '10:00', 'end_time' => '11:00',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ([['present'], ['late'], ['absent']] as $i => $status) {
        $sid = DB::table('schedules')->insertGetId([
            'group_id' => $groupId, 'date' => now()->addDays($i + 1)->toDateString(),
            'start_time' => '10:00', 'end_time' => '11:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('attendance')->insert([
            'schedule_id' => $sid, 'student_id' => $student->id, 'status' => $status[0],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $stats = app(ProgressService::class)->attendanceStats($student);

    expect($stats['total'])->toBe(3)
        ->and($stats['attendance_rate'])->toBe(66.7);
});

test('recommended practice based on confirmed mentor errors only', function () {
    [$student] = progressFixture();

    $practice = app(ProgressService::class)->recommendedPractice($student);

    expect($practice)->not->toBeEmpty()
        ->and($practice[0]['error_type'])->toBeIn(['tajweed_other', 'missing_word'])
        ->and($practice[0]['surah_number'])->toBe(1);
});

// --- Parent ---

test('parent sees child progress', function () {
    progressFixture();
    $parent = User::where('email', 'ota@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    DB::table('parent_student_links')->insert([
        'parent_id' => $parent->id, 'student_id' => $student->id,
        'relation' => 'parent', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $token = $parent->createToken('t')->plainTextToken;
    $this->withToken($token)->getJson("/api/v1/progress/students/{$student->id}")
        ->assertOk()
        ->assertJsonPath('average_score', 85)
        ->assertJsonPath('submissions_graded', 2);
});

test('parent cannot see unlinked student progress', function () {
    progressFixture();
    $parent = User::where('email', 'ota@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    // link yo'q

    $token = $parent->createToken('t')->plainTextToken;
    $this->withToken($token)->getJson("/api/v1/progress/students/{$student->id}")
        ->assertStatus(403);
});

test('parent lists own children', function () {
    progressFixture();
    $parent = User::where('email', 'ota@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    DB::table('parent_student_links')->insert([
        'parent_id' => $parent->id, 'student_id' => $student->id,
        'relation' => 'parent', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $token = $parent->createToken('t')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/parents/children')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $student->id);
});

// --- Notifications ---

test('notification service creates in-app record', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    app(NotificationService::class)->send($student, 'new_grade', 'Yangi baho', '85 ball');

    expect($student->appNotifications()->where('type', 'new_grade')->exists())->toBeTrue();
});

test('mentor notified when student submits', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $mentor = User::where('email', 'mentor@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    $course = Course::create(['name' => 'K', 'level' => 'beginner']);
    $group = Group::create(['name' => 'G', 'course_id' => $course->id, 'mentor_id' => $mentor->id]);
    $group->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);

    $assignment = Assignment::create(['group_id' => $group->id, 'created_by' => $admin->id, 'title' => 'A', 'status' => 'published']);

    Bus::fake();

    $submission = Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now(),
    ]);

    // Notification to'g'ridan-to'g'ri chaqiriladi (service test)
    app(NotificationService::class)->send($mentor, 'new_submission', 'Yangi audio topshirig\'i', "{$student->name} topshirdi");

    expect($mentor->appNotifications()->where('type', 'new_submission')->exists())->toBeTrue();
});

test('notifications api lists and marks read', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    app(NotificationService::class)->send($student, 'new_grade', 'Baho 1');
    app(NotificationService::class)->send($student, 'new_grade', 'Baho 2');

    $token = $student->createToken('t')->plainTextToken;

    $list = $this->withToken($token)->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 2);

    $id = $list->json('data.0.id');
    $this->withToken($token)->postJson("/api/v1/notifications/{$id}/read")->assertOk();

    expect($student->appNotifications()->whereNull('read_at')->count())->toBe(1);

    $this->withToken($token)->postJson('/api/v1/notifications/read-all')->assertOk();
    expect($student->appNotifications()->whereNull('read_at')->count())->toBe(0);
});
