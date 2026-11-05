<?php

use App\Models\Assignment;
use App\Models\Group;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{User, User} */
function familyWebFixture(): array
{
    $parent = User::where('email', 'ota@markaz.uz')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $parent->children()->attach($student, ['relation' => 'parent', 'created_at' => now(), 'updated_at' => now()]);

    return [$parent, $student];
}

test('family web pages redirect guests to login', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['parents.dashboard', 'notifications.index']);

test('parent dashboard displays linked child grades and attendance', function () {
    $this->seed(DatabaseSeeder::class);
    [$parent, $student] = familyWebFixture();
    $assignment = Assignment::create(['group_id' => Group::firstOrFail()->id, 'created_by' => $parent->id, 'title' => 'Fotiha', 'surah_number' => 1, 'status' => 'published']);
    Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'graded', 'attempt' => 1, 'final_score' => 91, 'submitted_at' => now()]);

    $this->actingAs($parent)->get(route('parents.dashboard'))->assertOk()
        ->assertSee($student->name)->assertSee('91')->assertSee('Davomat')->assertSee('Haftalik natijalar')
        ->assertViewHas('progress', fn (array $progress): bool => $progress['submissions_graded'] === 1 && $progress['average_score'] === 91.0);
});

test('parent can select another linked child', function () {
    $this->seed(DatabaseSeeder::class);
    [$parent] = familyWebFixture();
    $second = User::factory()->create();
    $parent->children()->attach($second, ['relation' => 'parent', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($parent)->get(route('parents.dashboard', ['student' => $second->id]))->assertOk()
        ->assertViewHas('selectedChild', fn (User $selected): bool => $selected->id === $second->id);
});

test('parent cannot select an unlinked child', function () {
    $this->seed(DatabaseSeeder::class);
    [$parent] = familyWebFixture();
    $unlinked = User::factory()->create();

    $this->actingAs($parent)->get(route('parents.dashboard', ['student' => $unlinked->id]))->assertNotFound();
});

test('student cannot access parent dashboard', function () {
    $this->seed(DatabaseSeeder::class);
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();

    $this->actingAs($student)->get(route('parents.dashboard'))->assertForbidden();
});

test('parent dashboard handles no linked children', function () {
    $this->seed(DatabaseSeeder::class);
    $parent = User::where('email', 'ota@markaz.uz')->firstOrFail();

    $this->actingAs($parent)->get(route('parents.dashboard'))->assertOk()->assertSee('Akkauntingizga farzand biriktirilmagan.');
});

test('parent dashboard validates child selection', function () {
    $this->seed(DatabaseSeeder::class);
    [$parent] = familyWebFixture();

    $this->actingAs($parent)->get(route('parents.dashboard', ['student' => 'invalid']))->assertSessionHasErrors('student');
});

test('notifications show only own messages with pagination and escaped content', function () {
    $this->seed(DatabaseSeeder::class);
    [$parent, $student] = familyWebFixture();
    $parent->appNotifications()->create(['type' => 'new_grade', 'title' => '<script>alert(1)</script>']);
    for ($index = 0; $index < 20; $index++) {
        $parent->appNotifications()->create(['type' => 'new_grade', 'title' => 'Natija']);
    }
    $student->appNotifications()->create(['type' => 'new_grade', 'title' => 'Boshqa foydalanuvchining xabari']);

    $this->actingAs($parent)->get(route('notifications.index'))->assertOk()->assertSee('21 ta')
        ->assertDontSee('Boshqa foydalanuvchining xabari')
        ->assertViewHas('notifications', fn ($notifications): bool => $notifications->total() === 21 && $notifications->count() === 20);
    $this->get(route('notifications.index', ['page' => 2]))->assertOk()
        ->assertViewHas('notifications', fn ($notifications): bool => $notifications->count() === 1);
    $this->get(route('notifications.index', ['page' => 1]))->assertDontSee('<script>alert(1)</script>', false);
    $this->get(route('notifications.index', ['page' => 2]))->assertDontSee('<script>alert(1)</script>', false);
});

test('web notification read action only updates own unread notification', function () {
    $this->seed(DatabaseSeeder::class);
    [$parent, $student] = familyWebFixture();
    $notification = $parent->appNotifications()->create(['type' => 'new_grade', 'title' => 'Natija']);
    $other = $student->appNotifications()->create(['type' => 'new_grade', 'title' => 'Boshqa natija']);

    $this->actingAs($parent)->post(route('notifications.read', $notification->id))->assertRedirect(route('notifications.index'));
    expect($notification->refresh()->read_at)->not->toBeNull();
    $this->post(route('notifications.read', $other->id))->assertNotFound();
    expect($other->refresh()->read_at)->toBeNull();
});

test('web mark all read preserves other users messages and previous read times', function () {
    $this->travelTo(now()->startOfSecond());
    $this->seed(DatabaseSeeder::class);
    [$parent, $student] = familyWebFixture();
    $unread = $parent->appNotifications()->create(['type' => 'new_grade', 'title' => 'Natija']);
    $read = $parent->appNotifications()->create(['type' => 'new_grade', 'title' => 'Eski natija', 'read_at' => now()->subDay()]);
    $other = $student->appNotifications()->create(['type' => 'new_grade', 'title' => 'Boshqa natija']);

    $this->actingAs($parent)->post(route('notifications.read-all'))->assertRedirect(route('notifications.index'));

    expect($unread->refresh()->read_at->equalTo(now()))->toBeTrue();
    expect($read->refresh()->read_at->equalTo(now()->subDay()))->toBeTrue();
    expect($other->refresh()->read_at)->toBeNull();
});

test('admin navigation links to existing education routes', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertSee(route('education.courses'))->assertSee(route('education.groups'))->assertSee(route('notifications.index'))
        ->assertDontSee(route('parents.dashboard'));
});

test('notification write actions redirect guests to login', function (string $path) {
    $this->post($path)->assertRedirect(route('login'));
})->with(['/notifications/read-all', '/notifications/unknown/read']);
