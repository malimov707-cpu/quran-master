<?php

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{Group, Schedule} */
function dashboardGroup(string $name, User $student, ?User $mentor = null): array
{
    $group = Group::create(['name' => $name, 'course_id' => Course::firstOrFail()->id, 'mentor_id' => $mentor?->id]);
    $group->students()->attach($student, ['joined_at' => now()->subMonth()]);
    $schedule = Schedule::create(['group_id' => $group->id, 'date' => '2026-11-05', 'start_time' => '10:00:00', 'end_time' => '11:00:00']);

    return [$group, $schedule];
}

test('student dashboard shows today in Tashkent and only own attendance', function () {
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(20, 30));
    $this->seed(DatabaseSeeder::class);
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    [, $schedule] = dashboardGroup('Mening guruhim', $student);
    $other = User::factory()->create();
    dashboardGroup('Begona guruh', $other);
    Attendance::create(['schedule_id' => $schedule->id, 'student_id' => $student->id, 'status' => 'absent']);
    Attendance::create(['schedule_id' => $schedule->id, 'student_id' => $other->id, 'status' => 'present']);

    $response = $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee('Mening guruhim')
        ->assertDontSee('Begona guruh')->assertSee('10:00–11:00');
    $chart = $response->viewData('attendanceChart');
    expect($chart[6])->toMatchArray(['date' => '2026-11-05', 'pct' => 0.0, 'total' => 1]);
    expect($chart[5]['pct'])->toBeNull();
    expect($response->viewData('todaySchedules'))->toHaveCount(1);
    expect($response->viewData('canViewSchedules'))->toBeFalse();
});

test('parent dashboard excludes unrelated attendance even in the same group', function () {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(7, 0));
    $this->seed(DatabaseSeeder::class);
    $parent = User::where('email', 'ota@markaz.uz')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $parent->children()->attach($student, ['relation' => 'parent']);
    [, $schedule] = dashboardGroup('Farzand guruhi', $student);
    $other = User::factory()->create();
    dashboardGroup('Yashirin guruh', $other);
    Attendance::create(['schedule_id' => $schedule->id, 'student_id' => $student->id, 'status' => 'present']);
    Attendance::create(['schedule_id' => $schedule->id, 'student_id' => $other->id, 'status' => 'absent']);

    $response = $this->actingAs($parent)->get(route('dashboard'))->assertOk()->assertSee('Farzand guruhi')->assertDontSee('Yashirin guruh');
    expect($response->viewData('attendanceChart')[6])->toMatchArray(['pct' => 100.0, 'total' => 1]);
});

test('mentor dashboard is limited to assigned groups', function () {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(7, 0));
    $this->seed(DatabaseSeeder::class);
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    [, $own] = dashboardGroup('Mentor guruhi', $student, $mentor);
    [, $other] = dashboardGroup('Boshqa mentor guruhi', User::factory()->create());
    Attendance::create(['schedule_id' => $own->id, 'student_id' => $student->id, 'status' => 'late']);
    Attendance::create(['schedule_id' => $other->id, 'student_id' => $student->id, 'status' => 'absent']);

    foreach ([$own, $other] as $class) {
        $assignment = Assignment::create(['group_id' => $class->group_id, 'created_by' => $mentor->id, 'title' => 'Audio', 'status' => 'published']);
        Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'in_review', 'attempt' => 1, 'submitted_at' => now()]);
    }

    $response = $this->actingAs($mentor)->get(route('dashboard'))->assertOk()->assertSee('Mentor guruhi')->assertDontSee('Boshqa mentor guruhi');
    expect($response->viewData('attendanceChart')[6])->toMatchArray(['pct' => 100.0, 'total' => 1]);
    expect(collect($response->viewData('cards'))->firstWhere('title', 'Tekshiruv kutmoqda')['value'])->toBe('1');
});

test('admin chart uses class date and draws low percentages inside the graph', function () {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(7, 0));
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $students = User::factory()->count(2)->create();
    [, $schedule] = dashboardGroup('Admin ko‘radigan guruh', $students[0]);
    $schedule->update(['date' => '2026-11-03']);
    foreach (['present', 'absent'] as $index => $status) {
        Attendance::create(['schedule_id' => $schedule->id, 'student_id' => $students[$index]->id, 'status' => $status]);
    }

    $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $chart = $response->viewData('attendanceChart');
    expect($chart[4])->toMatchArray(['date' => '2026-11-03', 'pct' => 50.0, 'total' => 2]);
    expect($chart[6]['pct'])->toBeNull();
    preg_match_all('/<circle[^>]+cy="([0-9.]+)"/', $response->getContent(), $matches);
    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $y) {
        expect((float) $y)->toBeGreaterThanOrEqual(6)->toBeLessThanOrEqual(114);
    }
});

test('unassigned user dashboard does not expose a group or attendance', function () {
    $this->travelTo(now()->setDate(2026, 11, 5)->setTime(7, 0));
    $this->seed(DatabaseSeeder::class);
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    dashboardGroup('Yashirin dars', $student);

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertDontSee('Yashirin dars');
    expect($response->viewData('todaySchedules'))->toHaveCount(0);
    expect(collect($response->viewData('attendanceChart'))->pluck('pct')->filter())->toHaveCount(0);
});
