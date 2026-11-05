<?php

use App\Models\Assignment;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('assignment management requires login', function () {
    $this->get(route('assignments.manage'))->assertRedirect(route('login'));
    $this->post(route('assignments.store'))->assertRedirect(route('login'));
    $this->patch('/assignments/1/status')->assertRedirect(route('login'));
});

test('student and mentor cannot manage assignments', function (string $email) {
    $this->seed(DatabaseSeeder::class);
    $user = User::where('email', $email)->firstOrFail();
    $group = Group::firstOrFail();
    $assignment = Assignment::create(['group_id' => $group->id, 'created_by' => $user->id, 'title' => 'Qoralama', 'status' => 'draft']);

    $this->actingAs($user)->get(route('assignments.manage'))->assertForbidden();
    $this->post(route('assignments.store'), ['group_id' => $group->id, 'title' => 'No permission'])->assertForbidden();
    $this->patch(route('assignments.status', $assignment), ['status' => 'published'])->assertForbidden();
    $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'status' => 'draft']);
})->with(['oquvchi@markaz.uz', 'mentor@markaz.uz']);

test('admin and head teacher create assignment with authenticated author and Tashkent deadline', function (string $email) {
    $this->travelTo(now()->setDate(2026, 11, 5)->startOfDay());
    $this->seed(DatabaseSeeder::class);
    $user = User::where('email', $email)->firstOrFail();
    $group = Group::firstOrFail();

    $this->actingAs($user)->post(route('assignments.store'), [
        'group_id' => $group->id, 'title' => 'Yangi qiroat vazifasi', 'description' => 'Audio yozib yuboring',
        'surah_number' => 1, 'ayah_from' => 1, 'ayah_to' => 7,
        'requires_audio' => true, 'status' => 'published', 'created_by' => 999,
        'due_at' => '2026-11-06T15:00',
    ])->assertRedirect(route('assignments.manage'))->assertSessionHas('status');

    $this->assertDatabaseHas('assignments', [
        'title' => 'Yangi qiroat vazifasi', 'group_id' => $group->id, 'created_by' => $user->id,
        'requires_audio' => true, 'status' => 'published', 'due_at' => '2026-11-06 10:00:00',
    ]);
    $this->get(route('assignments.manage'))->assertOk()->assertSee('Yangi qiroat vazifasi')->assertSee('06.11.2026 15:00');
})->with(['admin@markaz.uz', 'ustoz@markaz.uz']);

test('draft creation stays hidden from student until published', function () {
    $this->seed(DatabaseSeeder::class);
    $teacher = User::where('email', 'ustoz@markaz.uz')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $group = Group::firstOrFail();
    $group->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);

    $this->actingAs($teacher)->post(route('assignments.store'), ['group_id' => $group->id, 'title' => 'Draft vazifa', 'requires_audio' => false, 'status' => 'draft'])->assertRedirect();
    $assignment = Assignment::where('title', 'Draft vazifa')->firstOrFail();
    $this->actingAs($student)->get(route('homework.index'))->assertOk()->assertDontSee('Draft vazifa');
    $this->actingAs($teacher)->patch(route('assignments.status', $assignment), ['status' => 'published'])->assertRedirect();
    $this->actingAs($student)->get(route('homework.index'))->assertOk()->assertSee('Draft vazifa');
    $this->actingAs($teacher)->patch(route('assignments.status', $assignment), ['status' => 'closed'])->assertRedirect();
    $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'status' => 'closed']);
    $this->actingAs($student)->get(route('homework.index'))->assertOk()->assertDontSee('Draft vazifa');
});

test('closed assignment can be reopened only for active groups', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $group = Group::firstOrFail();
    $assignment = Assignment::create(['group_id' => $group->id, 'created_by' => $admin->id, 'title' => 'Closed', 'status' => 'closed']);

    $this->actingAs($admin)->patch(route('assignments.status', $assignment), ['status' => 'published'])->assertRedirect();
    $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'status' => 'published']);
    $assignment->refresh()->update(['status' => 'closed']);
    $group->update(['status' => 'archived']);
    $this->patch(route('assignments.status', $assignment), ['status' => 'published'])->assertUnprocessable();
    $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'status' => 'closed']);
});

test('assignment form rejects invalid fields without saving', function (array $invalid, string $field) {
    $this->travelTo(now()->setDate(2026, 11, 5)->startOfDay());
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $group = Group::firstOrFail();

    $this->actingAs($admin)->post(route('assignments.store'), array_replace([
        'group_id' => $group->id, 'title' => 'Invalid task', 'requires_audio' => true, 'status' => 'draft',
    ], $invalid))->assertSessionHasErrors($field);

    $this->assertDatabaseCount('assignments', 0);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'invalid group' => [['group_id' => 999999], 'group_id'],
    'invalid state' => [['status' => 'closed'], 'status'],
    'reversed ayahs' => [['surah_number' => 1, 'ayah_from' => 7, 'ayah_to' => 1], 'ayah_to'],
    'missing surah' => [['ayah_from' => 1, 'ayah_to' => 7], 'surah_number'],
    'past deadline in Tashkent' => [['due_at' => '2026-11-05T04:00'], 'due_at'],
    'missing audio flag' => [['requires_audio' => null], 'requires_audio'],
]);

test('assignments cannot be created for archived groups', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $group = Group::firstOrFail();
    $group->update(['status' => 'archived']);

    $this->actingAs($admin)->post(route('assignments.store'), ['group_id' => $group->id, 'title' => 'Archived group task', 'requires_audio' => true, 'status' => 'draft'])->assertSessionHasErrors('group_id');
    $this->assertDatabaseCount('assignments', 0);
});

test('management list filters by group and status', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $group = Group::firstOrFail();
    Assignment::create(['group_id' => $group->id, 'created_by' => $admin->id, 'title' => 'Published task', 'status' => 'published']);
    Assignment::create(['group_id' => $group->id, 'created_by' => $admin->id, 'title' => 'Draft task', 'status' => 'draft']);

    $this->actingAs($admin)->get(route('assignments.manage', ['group_id' => $group->id, 'status' => 'published']))
        ->assertOk()->assertSee('Published task')->assertDontSee('Draft task');
});

test('draft cannot be closed before publication', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $assignment = Assignment::create(['group_id' => Group::firstOrFail()->id, 'created_by' => $admin->id, 'title' => 'Draft', 'status' => 'draft']);

    $this->actingAs($admin)->patch(route('assignments.status', $assignment), ['status' => 'closed'])->assertUnprocessable();
    $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'status' => 'draft']);
});

test('status endpoint rejects unsupported states', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $assignment = Assignment::create(['group_id' => Group::firstOrFail()->id, 'created_by' => $admin->id, 'title' => 'Draft', 'status' => 'draft']);

    $this->actingAs($admin)->patchJson(route('assignments.status', $assignment), ['status' => 'archived'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'status' => 'draft']);
});
