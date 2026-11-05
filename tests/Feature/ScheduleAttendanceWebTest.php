<?php

use App\Models\Course;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{User, User, Group, Schedule} */
function scheduleAttendanceFixture(): array
{
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $group = Group::create(['name' => 'Davomat guruhi', 'course_id' => Course::firstOrFail()->id]);
    $group->students()->attach($student, ['joined_at' => now()->subMonth()]);
    $schedule = Schedule::create(['group_id' => $group->id, 'date' => '2026-11-05', 'start_time' => '10:00:00', 'end_time' => '11:00:00']);

    return [$admin, $student, $group, $schedule];
}

test('schedule and attendance routes require login', function () {
    $this->get(route('education.schedules'))->assertRedirect(route('login'));
    $this->post(route('education.schedules.store'))->assertRedirect(route('login'));
    $this->get('/schedules/1/attendance')->assertRedirect(route('login'));
    $this->post('/schedules/1/attendance')->assertRedirect(route('login'));
});

test('student and parent cannot manage schedules or group attendance', function (string $email) {
    $this->seed(DatabaseSeeder::class);
    [, , $group, $schedule] = scheduleAttendanceFixture();
    $user = User::where('email', $email)->firstOrFail();

    $this->actingAs($user)->get(route('education.schedules'))->assertForbidden();
    $this->post(route('education.schedules.store'), ['group_id' => $group->id])->assertForbidden();
    $this->get(route('education.attendance', $schedule))->assertForbidden();
    $this->post(route('education.attendance.store', $schedule))->assertForbidden();
    $this->assertDatabaseCount('attendance', 0);
})->with(['oquvchi@markaz.uz', 'ota@markaz.uz']);

test('admin sees filtered schedule list and attendance form', function () {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(7, 0));
    $this->seed(DatabaseSeeder::class);
    [$admin, $student, $group, $schedule] = scheduleAttendanceFixture();

    $this->actingAs($admin)->get(route('education.schedules', ['group_id' => $group->id, 'date' => '2026-11-05']))
        ->assertOk()->assertSee($group->name)->assertSee('10:00–11:00')->assertSee(route('education.attendance', $schedule));
    $this->get(route('education.attendance', $schedule))->assertOk()->assertSee($student->name)->assertSee('Davomatni saqlash');
});

test('overlapping schedules are rejected and adjacent lessons are accepted', function (string $start, string $end, bool $conflict) {
    $this->seed(DatabaseSeeder::class);
    [$admin, , $group] = scheduleAttendanceFixture();

    $response = $this->actingAs($admin)->post(route('education.schedules.store'), [
        'group_id' => $group->id, 'date' => '2026-11-05', 'start_time' => $start, 'end_time' => $end, 'room' => 'A-1',
    ]);

    $response->assertRedirect();
    if ($conflict) {
        $response->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('schedules', 1);
    } else {
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('schedules', 2);
        $this->assertDatabaseHas('schedules', ['group_id' => $group->id, 'start_time' => $start.':00', 'end_time' => $end.':00', 'room' => 'A-1']);
    }
})->with([
    'partial overlap' => ['09:30', '10:30', true],
    'inside existing' => ['10:15', '10:45', true],
    'covers existing' => ['09:00', '12:00', true],
    'adjacent after' => ['11:00', '12:00', false],
    'adjacent before' => ['09:00', '10:00', false],
]);

test('invalid schedule does not create a lesson', function (array $invalid, string $field) {
    $this->seed(DatabaseSeeder::class);
    [$admin, , $group] = scheduleAttendanceFixture();

    $this->actingAs($admin)->post(route('education.schedules.store'), array_replace([
        'group_id' => $group->id, 'date' => '2026-11-06', 'start_time' => '10:00', 'end_time' => '11:00',
    ], $invalid))->assertSessionHasErrors($field);

    $this->assertDatabaseCount('schedules', 1);
})->with([
    [['end_time' => '09:00'], 'end_time'],
    [['date' => 'invalid'], 'date'],
    [['group_id' => 999999], 'group_id'],
]);

test('archived group cannot receive a new schedule', function () {
    $this->seed(DatabaseSeeder::class);
    [$admin, , $group] = scheduleAttendanceFixture();
    $group->update(['status' => 'archived']);

    $this->actingAs($admin)->post(route('education.schedules.store'), ['group_id' => $group->id, 'date' => '2026-11-06', 'start_time' => '10:00', 'end_time' => '11:00'])->assertSessionHasErrors('group_id');
    $this->assertDatabaseCount('schedules', 1);
});

test('saved attendance updates instead of duplicating and changes student progress', function () {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(7, 0));
    $this->seed(DatabaseSeeder::class);
    [$admin, $student, , $schedule] = scheduleAttendanceFixture();
    $payload = ['records' => [['student_id' => $student->id, 'status' => 'present', 'note' => 'Vaqtida keldi']]];

    $this->actingAs($admin)->post(route('education.attendance.store', $schedule), $payload)->assertRedirect(route('education.attendance', $schedule));
    $this->assertDatabaseHas('attendance', ['schedule_id' => $schedule->id, 'student_id' => $student->id, 'status' => 'present', 'marked_by' => $admin->id]);
    $this->actingAs($student, 'sanctum')->getJson('/api/v1/progress/me')->assertOk()->assertJsonPath('attendance.attendance_rate', 100);
    $payload['records'][0]['status'] = 'absent';
    $this->actingAs($admin)->post(route('education.attendance.store', $schedule), $payload)->assertRedirect();
    $this->assertDatabaseCount('attendance', 1);
    $this->actingAs($student, 'sanctum')->getJson('/api/v1/progress/me')->assertOk()->assertJsonPath('attendance.attendance_rate', 0)->assertJsonPath('attendance.total', 1);
});

test('outsider in batch rejects all attendance changes', function () {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(7, 0));
    $this->seed(DatabaseSeeder::class);
    [$admin, $student, , $schedule] = scheduleAttendanceFixture();
    $outsider = User::factory()->create();

    $this->actingAs($admin)->post(route('education.attendance.store', $schedule), ['records' => [
        ['student_id' => $student->id, 'status' => 'present'], ['student_id' => $outsider->id, 'status' => 'absent'],
    ]])->assertSessionHasErrors('records.1.student_id');
    $this->assertDatabaseCount('attendance', 0);
});

test('duplicate students and invalid status cannot be saved', function (string $scenario, string $field) {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(7, 0));
    $this->seed(DatabaseSeeder::class);
    [$admin, $student, , $schedule] = scheduleAttendanceFixture();
    $records = $scenario === 'duplicate' ? [
        ['student_id' => $student->id, 'status' => 'present'], ['student_id' => $student->id, 'status' => 'absent'],
    ] : [['student_id' => $student->id, 'status' => 'unknown']];

    $this->actingAs($admin)->post(route('education.attendance.store', $schedule), ['records' => $records])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('attendance', 0);
})->with([['duplicate', 'records.0.student_id'], ['status', 'records.0.status']]);

test('future class attendance cannot be recorded using Tashkent time', function () {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(4, 0));
    $this->seed(DatabaseSeeder::class);
    [$admin, $student, , $schedule] = scheduleAttendanceFixture();

    $this->actingAs($admin)->get(route('education.attendance', $schedule))->assertOk()->assertSee('Davomat dars boshlangandan keyin kiritiladi.');
    $this->post(route('education.attendance.store', $schedule), ['records' => [['student_id' => $student->id, 'status' => 'present']]])->assertUnprocessable();
    $this->assertDatabaseCount('attendance', 0);
});
