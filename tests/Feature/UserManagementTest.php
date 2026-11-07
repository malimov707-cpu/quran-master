<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function managerAccount(): User
{
    return User::where('email', 'malimov707@gmail.com')->firstOrFail();
}

function managementRole(string $name): int
{
    return Role::where('name', $name)->firstOrFail()->id;
}

/** @return array<string, mixed> */
function managedAccountPayload(string $role = 'MENTOR'): array
{
    return ['name' => 'New staff', 'email' => 'new-staff@example.com', 'password' => 'Staff!70788', 'password_confirmation' => 'Staff!70788', 'role_ids' => [managementRole($role)]];
}

test('management requires login and refuses each non manager role', function (string $role) {
    $target = User::factory()->create();
    $actor = User::factory()->create();
    $actor->roles()->attach(managementRole($role));
    $this->get(route('administration.users.create'))->assertRedirect(route('login'));
    $this->actingAs($actor)->get(route('administration.users.create'))->assertForbidden();
    $this->get(route('administration.users.edit', $target))->assertForbidden();
    $this->post(route('administration.users.store'), managedAccountPayload())->assertForbidden();
    $this->patch(route('administration.users.roles', $target), ['role_ids' => [managementRole('CEO')]])->assertForbidden();
    $this->put(route('administration.users.children', $target), [])->assertForbidden();
    $this->put(route('administration.users.mentor-groups', $target), [])->assertForbidden();
    expect($target->fresh()->roles)->toBeEmpty();
    $this->assertDatabaseMissing('users', ['email' => 'new-staff@example.com']);
})->with(['CENTER_ADMIN', 'HEAD_TEACHER', 'MENTOR', 'STUDENT', 'PARENT']);

test('CEO can create every supported role with usable credentials', function (string $role) {
    $response = $this->actingAs(managerAccount())->post(route('administration.users.store'), managedAccountPayload($role) + ['ai_premium' => true]);
    $user = User::where('email', 'new-staff@example.com')->firstOrFail();
    $response->assertRedirect(route('administration.users.edit', $user));
    expect($user->roles->pluck('name')->all())->toBe([$role]);
    expect(Hash::check('Staff!70788', $user->password))->toBeTrue();
    expect($user->ai_premium)->toBeFalse();
    $this->assertDatabaseHas('audit_logs', ['user_id' => managerAccount()->id, 'action' => 'user.created', 'subject_id' => $user->id]);
    expect(DB::table('audit_logs')->where('action', 'user.created')->value('properties'))->not->toContain('Staff!70788');
    auth()->logout();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Staff!70788'])->assertOk()->assertJsonPath('user.roles', [$role]);
})->with(['CEO', 'SUPER_ADMIN', 'CENTER_ADMIN', 'HEAD_TEACHER', 'MENTOR', 'STUDENT', 'PARENT']);

test('create validates roles password and duplicate email without partial accounts', function () {
    $this->actingAs(managerAccount())->postJson(route('administration.users.store'), array_replace(managedAccountPayload(), ['role_ids' => [999999], 'password_confirmation' => 'wrong']))->assertUnprocessable()->assertJsonValidationErrors(['role_ids.0', 'password']);
    $this->postJson(route('administration.users.store'), array_replace(managedAccountPayload(), ['email' => 'MENTOR@MARKAZ.UZ']))->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson(route('administration.users.store'), array_replace(managedAccountPayload(), ['role_ids' => []]))->assertUnprocessable()->assertJsonValidationErrors('role_ids');
    $this->assertDatabaseMissing('users', ['email' => 'new-staff@example.com']);
    $this->assertDatabaseMissing('audit_logs', ['action' => 'user.created']);
});

test('role update grants access revokes prior sessions and prevents self lockout', function () {
    $student = User::factory()->create();
    $student->roles()->attach(managementRole('STUDENT'));
    $student->createToken('old-device');
    DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $student->id, 'payload' => '', 'last_activity' => time()]);
    $this->actingAs(managerAccount())->get(route('administration', 'users'))->assertOk()->assertSee(route('administration.users.create'))->assertSee(route('administration.users.edit', $student));
    $this->patch(route('administration.users.roles', $student), ['role_ids' => [managementRole('MENTOR')]])->assertRedirect();
    expect($student->fresh()->roles->pluck('name')->all())->toBe(['MENTOR']);
    expect($student->fresh()->hasPermission('submissions.review'))->toBeTrue();
    $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
    expect($student->tokens()->count())->toBe(0);
    $this->patch(route('administration.users.roles', managerAccount()), ['role_ids' => [managementRole('STUDENT')]])->assertForbidden();
    expect(managerAccount()->hasRole('SUPER_ADMIN'))->toBeTrue();
    $this->patchJson(route('administration.users.roles', $student), ['role_ids' => []])->assertUnprocessable();
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.roles.updated', 'subject_id' => $student->id]);
});

test('parents see only selected children and links can be removed', function () {
    $parent = User::where('email', 'ota@markaz.uz')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $other = User::factory()->create();
    $other->roles()->attach(managementRole('STUDENT'));
    $this->actingAs(managerAccount())->put(route('administration.users.children', $parent), ['student_ids' => [$student->id]])->assertRedirect();
    $this->assertDatabaseHas('parent_student_links', ['parent_id' => $parent->id, 'student_id' => $student->id]);
    $this->actingAs($parent)->getJson('/api/v1/parents/children')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $student->id);
    $this->actingAs(managerAccount())->put(route('administration.users.children', $parent), [])->assertRedirect();
    expect($parent->children()->count())->toBe(0);
    $this->actingAs($parent)->getJson('/api/v1/parents/children')->assertOk()->assertJsonCount(0);
});

test('parent linking rejects non students self links and non parent targets', function () {
    $parent = User::where('email', 'ota@markaz.uz')->firstOrFail();
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $parent->roles()->attach(managementRole('STUDENT'));
    $this->actingAs(managerAccount())->putJson(route('administration.users.children', $parent), ['student_ids' => [$mentor->id]])->assertUnprocessable()->assertJsonValidationErrors('student_ids');
    $this->putJson(route('administration.users.children', $parent), ['student_ids' => [$parent->id]])->assertUnprocessable();
    $this->putJson(route('administration.users.children', $mentor), ['student_ids' => []])->assertUnprocessable();
    $this->assertDatabaseCount('parent_student_links', 0);
});

test('mentor group assignment changes actual review access preserves history and is idempotent', function () {
    $old = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $new = User::factory()->create();
    $new->roles()->attach(managementRole('MENTOR'));
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $group = Group::create(['name' => 'Managed group', 'course_id' => Course::firstOrFail()->id, 'mentor_id' => $old->id, 'status' => 'active']);
    $group->students()->attach($student, ['joined_at' => now()]);
    $assignment = Assignment::create(['title' => 'Managed task', 'group_id' => $group->id, 'created_by' => $old->id, 'status' => 'published']);
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now()]);
    $this->actingAs(managerAccount())->put(route('administration.users.mentor-groups', $new), ['group_ids' => [$group->id]])->assertRedirect();
    expect($group->fresh()->mentor_id)->toBe($new->id);
    $this->assertDatabaseHas('mentor_assignments', ['mentor_id' => $new->id, 'student_id' => $student->id, 'group_id' => $group->id, 'ended_at' => null]);
    $count = DB::table('mentor_assignments')->count();
    $this->put(route('administration.users.mentor-groups', $new), ['group_ids' => [$group->id]])->assertRedirect();
    expect(DB::table('mentor_assignments')->count())->toBe($count);
    $this->actingAs($new)->getJson("/api/v1/submissions/{$submission->id}")->assertOk();
    $this->actingAs($old)->getJson("/api/v1/submissions/{$submission->id}")->assertForbidden();
    $this->actingAs(managerAccount())->put(route('administration.users.mentor-groups', $new), [])->assertRedirect();
    expect($group->fresh()->mentor_id)->toBeNull();
    expect(DB::table('mentor_assignments')->where('mentor_id', $new->id)->whereNull('ended_at')->exists())->toBeFalse();
});

test('linked roles cannot be removed until their links are cleared', function () {
    $parent = User::where('email', 'ota@markaz.uz')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $parent->children()->attach($student);
    $this->actingAs(managerAccount())->patchJson(route('administration.users.roles', $parent), ['role_ids' => [managementRole('MENTOR')]])->assertUnprocessable()->assertJsonValidationErrors('role_ids');
    $this->patchJson(route('administration.users.roles', $student), ['role_ids' => [managementRole('MENTOR')]])->assertUnprocessable();
    $this->put(route('administration.users.children', $parent), [])->assertRedirect();
    $this->patch(route('administration.users.roles', $parent), ['role_ids' => [managementRole('MENTOR')]])->assertRedirect();
    expect($parent->fresh()->hasRole('MENTOR'))->toBeTrue();
});

test('inactive groups and invalid mentor targets do not change assignments', function () {
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $group = Group::create(['name' => 'Archived', 'course_id' => Course::firstOrFail()->id, 'status' => 'archived']);
    $this->actingAs(managerAccount())->putJson(route('administration.users.mentor-groups', $mentor), ['group_ids' => [$group->id]])->assertUnprocessable()->assertJsonValidationErrors('group_ids');
    expect($group->fresh()->mentor_id)->toBeNull();
    $this->putJson(route('administration.users.mentor-groups', User::where('email', 'ota@markaz.uz')->firstOrFail()), ['group_ids' => [$group->id]])->assertUnprocessable();
});

test('management forms render existing selections and escape account names', function () {
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $parent = User::where('email', 'ota@markaz.uz')->firstOrFail();
    $mentor->update(['name' => '<script>alert(1)</script>']);
    $this->actingAs(managerAccount())->get(route('administration.users.create'))->assertOk()->assertSee('name="role_ids[]"', false);
    $this->get(route('administration.users.edit', $mentor))->assertOk()->assertSee('Mentorning guruhlari')->assertDontSee('<script>alert(1)</script>', false);
    $this->get(route('administration.users.edit', $parent))->assertOk()->assertSee('Ota-ona va farzandlar');
});

test('a failed group batch rolls back earlier assignments and their history', function () {
    $mentor = User::factory()->create();
    $mentor->roles()->attach(managementRole('MENTOR'));
    $active = Group::create(['name' => 'Active', 'course_id' => Course::firstOrFail()->id, 'status' => 'active']);
    $archived = Group::create(['name' => 'Archived', 'course_id' => $active->course_id, 'status' => 'archived']);
    $this->actingAs(managerAccount())->putJson(route('administration.users.mentor-groups', $mentor), ['group_ids' => [$active->id, $archived->id]])->assertUnprocessable();
    expect($active->fresh()->mentor_id)->toBeNull();
    expect($archived->fresh()->mentor_id)->toBeNull();
    $this->assertDatabaseMissing('audit_logs', ['action' => 'user.mentor_groups.updated', 'subject_id' => $mentor->id]);
});
