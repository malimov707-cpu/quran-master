<?php

use App\Models\Course;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('api attendance rejects students outside the schedule group atomically', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $member = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $outsider = User::factory()->create();
    $group = Group::create([
        'name' => 'API davomat guruhi',
        'course_id' => Course::firstOrFail()->id,
    ]);
    $group->students()->attach($member, ['joined_at' => now()->subMonth()]);
    $schedule = Schedule::create([
        'group_id' => $group->id,
        'date' => '2026-11-05',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/v1/schedules/{$schedule->id}/attendance", [
            'records' => [
                ['student_id' => $member->id, 'status' => 'present'],
                ['student_id' => $outsider->id, 'status' => 'absent'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('records');

    $this->assertDatabaseCount('attendance', 0);
});

test('api attendance rejects duplicate students atomically', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $group = Group::create([
        'name' => 'Takroriy davomat guruhi',
        'course_id' => Course::firstOrFail()->id,
    ]);
    $group->students()->attach($student, ['joined_at' => now()->subMonth()]);
    $schedule = Schedule::create([
        'group_id' => $group->id,
        'date' => '2026-11-05',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/v1/schedules/{$schedule->id}/attendance", [
            'records' => [
                ['student_id' => $student->id, 'status' => 'present'],
                ['student_id' => $student->id, 'status' => 'absent'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('records');

    $this->assertDatabaseCount('attendance', 0);
});
