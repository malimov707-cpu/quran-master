<?php

use App\Models\Course;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\User;
use App\Services\Groups\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Test;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function actingAs(string $email): TestResponse
{
    $user = User::where('email', $email)->firstOrFail();
    $token = $user->createToken('test')->plainTextToken;

    return Test::class === 'x'
        ? throw new RuntimeException
        : test()->withToken($token);
}

test('center admin can create course', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $token = $admin->createToken('t')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/courses', [
        'name' => 'Qur\'on o\'qish — Boshlang\'ich',
        'level' => 'beginner',
        'description' => 'Harflardan boshlab',
        'duration_weeks' => 24,
    ]);

    $response->assertCreated()->assertJsonPath('course.name', 'Qur\'on o\'qish — Boshlang\'ich');
    expect(Course::where('name', 'like', '%Boshlang%')->exists())->toBeTrue();
});

test('mentor cannot create course (backend permission check)', function () {
    $mentor = User::where('email', 'mentor@markaz.uz')->first();
    $token = $mentor->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/courses', [
        'name' => 'Test kurs',
        'level' => 'beginner',
    ])->assertStatus(403);
});

test('courses list requires authentication', function () {
    $this->getJson('/api/v1/courses')->assertStatus(401);
});

test('admin can list and filter courses', function () {
    Course::create(['name' => 'API-test Tajvid kursi', 'level' => 'advanced', 'status' => 'published']);

    $admin = User::where('email', 'admin@markaz.uz')->first();
    $token = $admin->createToken('t')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/courses?level=advanced')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'API-test Tajvid kursi');
});

test('course can be archived', function () {
    $course = Course::create(['name' => 'Arxiv test', 'level' => 'beginner']);

    $admin = User::where('email', 'admin@markaz.uz')->first();
    $token = $admin->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson("/api/v1/courses/{$course->id}/archive")
        ->assertOk()
        ->assertJsonPath('course.status', 'archived');
});

test('admin can create group and add student', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $token = $admin->createToken('t')->plainTextToken;

    $course = Course::create(['name' => 'Kurs', 'level' => 'beginner']);
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    $groupId = $this->withToken($token)->postJson('/api/v1/groups', [
        'name' => '1-guruh',
        'course_id' => $course->id,
        'capacity' => 10,
    ])->assertCreated()->json('group.id');

    $this->withToken($token)->postJson("/api/v1/groups/{$groupId}/students", [
        'student_id' => $student->id,
    ])->assertOk();

    expect(Group::find($groupId)->students->pluck('id'))->toContain($student->id);
});

test('non-student cannot be added to group', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $token = $admin->createToken('t')->plainTextToken;

    $course = Course::create(['name' => 'Kurs', 'level' => 'beginner']);
    $groupId = $this->withToken($token)->postJson('/api/v1/groups', [
        'name' => 'G', 'course_id' => $course->id,
    ])->assertCreated()->json('group.id');

    $parent = User::where('email', 'ota@markaz.uz')->first();
    $this->withToken($token)->postJson("/api/v1/groups/{$groupId}/students", [
        'student_id' => $parent->id,
    ])->assertStatus(422);
});

test('assigning mentor creates mentor assignments for group students', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $token = $admin->createToken('t')->plainTextToken;

    $course = Course::create(['name' => 'Kurs', 'level' => 'beginner']);
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $mentor = User::where('email', 'mentor@markaz.uz')->first();

    $groupId = $this->withToken($token)->postJson('/api/v1/groups', [
        'name' => 'G', 'course_id' => $course->id,
    ])->assertCreated()->json('group.id');

    $this->withToken($token)->postJson("/api/v1/groups/{$groupId}/students", ['student_id' => $student->id])->assertOk();
    $this->withToken($token)->postJson("/api/v1/groups/{$groupId}/mentor", ['mentor_id' => $mentor->id])->assertOk();

    $assignment = DB::table('mentor_assignments')
        ->where('student_id', $student->id)->whereNull('ended_at')->first();

    expect($assignment->mentor_id)->toBe($mentor->id);
});

test('reassignment ends previous active mentor assignment', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $token = $admin->createToken('t')->plainTextToken;
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $mentor1 = User::where('email', 'mentor@markaz.uz')->first();

    // Ikkinchi mentor
    $mentor2 = User::create(['name' => 'M2', 'email' => 'm2@markaz.uz', 'password' => 'Parol!123']);
    $mentor2->roles()->attach(4); // MENTOR

    $service = app(GroupService::class);
    $service->assignStudentToMentor($mentor1, $student);
    $service->assignStudentToMentor($mentor2, $student);

    $active = DB::table('mentor_assignments')
        ->where('student_id', $student->id)->whereNull('ended_at')->get();

    expect($active)->toHaveCount(1)
        ->and($active[0]->mentor_id)->toBe($mentor2->id);
});

test('auto distribute balances load across mentors', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $token = $admin->createToken('t')->plainTextToken;

    $mentorRole = Role::where('name', 'MENTOR')->first();
    $studentRole = Role::where('name', 'STUDENT')->first();

    // 3 ta mentor + 6 ta o'quvchi — teng taqsimlanadi (2 tadan)
    $studentIds = [];
    foreach ([1, 2, 3] as $i) {
        $m = User::create(['name' => "M{$i}", 'email' => "m{$i}@markaz.uz", 'password' => 'Parol!123']);
        $m->roles()->attach($mentorRole);
    }
    foreach ([1, 2, 3, 4, 5, 6] as $i) {
        $s = User::create(['name' => "S{$i}", 'email' => "s{$i}@markaz.uz", 'password' => 'Parol!123']);
        $s->roles()->attach($studentRole);
        $studentIds[] = $s->id;
    }

    $response = $this->withToken($token)->postJson('/api/v1/groups/mentor-assignments/auto-distribute', [
        'student_ids' => $studentIds,
    ])->assertOk();

    // Seederdagi mentor bilan jami 4 ta mentor, 6 ta o'quvchi max 2 tadan
    $distribution = $response->json('distribution');
    expect(count($distribution))->toBeLessThanOrEqual(4)
        ->and(max($distribution))->toBeLessThanOrEqual(2)
        ->and(array_sum($distribution))->toBe(6)
        ->and($response->json('message'))->toContain('taqsimlandi');
});

test('head teacher can create lesson through api', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $token = $admin->createToken('test')->plainTextToken;
    $teacher = User::where('email', 'ustoz@markaz.uz')->first();
    $teacherToken = $teacher->createToken('teacher-test')->plainTextToken;
    $course = Course::create(['name' => 'API test darsi', 'level' => 'beginner']);

    $lessonId = $this->withToken($teacherToken)->postJson('/api/v1/lessons', [
        'course_id' => $course->id,
        'title' => 'Niyyat va talqin',
        'status' => 'published',
    ])->assertCreated()->json('lesson.id');

    expect($lessonId)->toBeInt();
});

test('admin can create schedule through api', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $token = $admin->createToken('test')->plainTextToken;
    $course = Course::create(['name' => 'API test darsi', 'level' => 'beginner']);

    $groupId = $this->withToken($token)->postJson('/api/v1/groups', [
        'name' => 'API jadval guruhi',
        'course_id' => $course->id,
        'capacity' => 15,
    ])->assertCreated()->json('group.id');
    $lessonId = Lesson::create([
        'course_id' => $course->id,
        'title' => 'Niyyat va talqin',
        'status' => 'published',
    ])->id;

    $scheduleResponse = $this->withToken($token)->postJson('/api/v1/schedules', [
        'group_id' => $groupId,
        'lesson_id' => $lessonId,
        'date' => '2026-11-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'room' => 'A-1',
    ]);
    $scheduleResponse->assertCreated();

    $this->withToken($token)->getJson('/api/v1/schedules?group_id='.$groupId)
        ->assertOk()
        ->assertJsonPath('data.0.lesson.id', $lessonId);
});
