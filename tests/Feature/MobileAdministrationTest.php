<?php

use App\Models\Course;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function mobileAdmin(): User
{
    return User::where('email', 'malimov707@gmail.com')->firstOrFail();
}

function mobileRole(string $name): int
{
    return Role::where('name', $name)->firstOrFail()->id;
}

test('administration API refuses guests and every non administrative role', function (string $role) {
    $this->getJson('/api/v1/admin/overview')->assertUnauthorized();
    $user = User::factory()->create();
    $user->roles()->attach(mobileRole($role));
    $this->actingAs($user)->getJson('/api/v1/admin/overview')->assertForbidden();
    $this->getJson('/api/v1/admin/users')->assertForbidden();
    $this->getJson('/api/v1/admin/options?type=roles')->assertForbidden();
    $this->postJson('/api/v1/admin/courses', ['name' => 'Unauthorized'])->assertForbidden();
})->with(['STUDENT', 'PARENT', 'MENTOR', 'HEAD_TEACHER']);

test('CEO overview lists manageable sections and uses real scoped counts', function () {
    $user = User::factory()->create();
    $user->roles()->attach(mobileRole('CEO'));
    $this->actingAs($user)->getJson('/api/v1/admin/overview')->assertOk()->assertJsonCount(6, 'sections')->assertJsonPath('counts.users', User::count())->assertJsonCount(7, 'attendance');
    foreach (['users', 'courses', 'lessons', 'groups', 'schedules', 'audit'] as $section) {
        $this->getJson('/api/v1/admin/'.$section)->assertOk()->assertJsonStructure(['data', 'current_page', 'last_page', 'can_create', 'can_edit']);
    }
});

test('center administrator sees only permitted sections and cannot grant roles or inspect audit logs', function () {
    $admin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $this->actingAs($admin)->getJson('/api/v1/admin/overview')->assertOk()->assertJsonCount(4, 'sections');
    $this->getJson('/api/v1/admin/users')->assertOk()->assertJsonPath('can_create', false)->assertJsonPath('can_edit', false);
    $this->postJson('/api/v1/admin/users', [])->assertForbidden();
    $this->putJson('/api/v1/admin/users/'.mobileAdmin()->id.'/roles', ['role_ids' => [mobileRole('CEO')]])->assertForbidden();
    $this->getJson('/api/v1/admin/audit')->assertForbidden();
    $this->getJson('/api/v1/admin/lessons')->assertForbidden();
});

test('user management mutations return JSON and preserve web role and relationship rules', function () {
    $this->actingAs(mobileAdmin())->postJson('/api/v1/admin/users', ['name' => 'Phone parent', 'email' => 'phone-parent@example.com', 'password' => 'Phone!70788', 'password_confirmation' => 'Phone!70788', 'role_ids' => [mobileRole('PARENT')]])
        ->assertCreated()->assertJsonPath('record.roles.0.name', 'PARENT')->assertJsonMissingPath('record.password');
    $parent = User::where('email', 'phone-parent@example.com')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $this->putJson("/api/v1/admin/users/{$parent->id}/children", ['student_ids' => [$student->id]])->assertOk();
    $this->getJson("/api/v1/admin/users/{$parent->id}")->assertOk()->assertJsonPath('record.children.0.id', $student->id)->assertJsonMissingPath('record.password');
    $this->putJson("/api/v1/admin/users/{$parent->id}/roles", ['role_ids' => [mobileRole('MENTOR')]])->assertUnprocessable();
    $this->putJson("/api/v1/admin/users/{$parent->id}/children", ['student_ids' => []])->assertOk();
    $this->putJson("/api/v1/admin/users/{$parent->id}/roles", ['role_ids' => [mobileRole('MENTOR')]])->assertOk();
    $this->putJson('/api/v1/admin/users/'.mobileAdmin()->id.'/roles', ['role_ids' => [mobileRole('STUDENT')]])->assertForbidden();
});

test('mobile course and lesson editors persist changes through existing operations', function () {
    $course = $this->actingAs(mobileAdmin())->postJson('/api/v1/admin/courses', ['name' => 'Mobile course', 'level' => 'beginner', 'status' => 'draft'])->assertCreated()->json('course.id');
    $this->putJson("/api/v1/admin/courses/{$course}", ['name' => 'Updated mobile course', 'status' => 'published'])->assertOk();
    $lesson = $this->postJson('/api/v1/admin/lessons', ['course_id' => $course, 'title' => 'Mobile lesson', 'sort_order' => 0, 'status' => 'draft'])->assertCreated()->json('record.id');
    $this->putJson("/api/v1/admin/lessons/{$lesson}", ['title' => 'Updated mobile lesson', 'description' => 'Reading content', 'sort_order' => 1, 'status' => 'published'])->assertOk();
    $this->getJson("/api/v1/admin/lessons/{$lesson}")->assertOk()->assertJsonPath('record.title', 'Updated mobile lesson')->assertJsonPath('record.description', 'Reading content');
    $this->assertDatabaseHas('courses', ['id' => $course, 'name' => 'Updated mobile course', 'status' => 'published']);
});

test('group capacity mentor validation and membership rejoining are enforced', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $group = $this->actingAs(mobileAdmin())->postJson('/api/v1/admin/groups', ['name' => 'Phone group', 'course_id' => Course::firstOrFail()->id, 'capacity' => 1, 'status' => 'active'])->assertCreated()->json('record.id');
    $this->postJson("/api/v1/admin/groups/{$group}/students", ['student_id' => $student->id])->assertOk();
    $this->postJson("/api/v1/admin/groups/{$group}/students", ['student_id' => $student->id])->assertOk();
    $another = User::factory()->create();
    $another->roles()->attach(mobileRole('STUDENT'));
    $this->postJson("/api/v1/admin/groups/{$group}/students", ['student_id' => $another->id])->assertUnprocessable();
    $this->deleteJson("/api/v1/admin/groups/{$group}/students/{$student->id}")->assertOk();
    $this->postJson("/api/v1/admin/groups/{$group}/students", ['student_id' => $student->id])->assertOk();
    $this->getJson("/api/v1/admin/groups/{$group}")->assertOk()->assertJsonCount(1, 'record.students');
    $this->putJson("/api/v1/admin/groups/{$group}", ['name' => 'Phone group', 'course_id' => Course::firstOrFail()->id, 'capacity' => 2, 'mentor_id' => $student->id])->assertUnprocessable()->assertJsonValidationErrors('mentor_id');
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $this->putJson("/api/v1/admin/groups/{$group}", ['name' => 'Phone group', 'course_id' => Course::firstOrFail()->id, 'capacity' => 2, 'mentor_id' => $mentor->id])->assertOk();
    $this->assertDatabaseHas('mentor_assignments', ['group_id' => $group, 'mentor_id' => $mentor->id, 'student_id' => $student->id, 'ended_at' => null]);
    $this->putJson("/api/v1/admin/groups/{$group}", ['name' => 'Phone group', 'course_id' => Course::firstOrFail()->id, 'capacity' => 2, 'mentor_id' => null])->assertOk();
    expect(DB::table('mentor_assignments')->where('group_id', $group)->whereNull('ended_at')->exists())->toBeFalse();
});

test('schedule editor rejects overlap and foreign course lessons and supports attendance after start', function () {
    $this->travelTo(Carbon::parse('2026-11-07 08:00:00', 'Asia/Tashkent'));
    $group = Group::firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $group->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);
    $lesson = Lesson::create(['title' => 'Own lesson', 'course_id' => $group->course_id, 'status' => 'published']);
    $payload = ['group_id' => $group->id, 'lesson_id' => $lesson->id, 'date' => '2026-11-07', 'start_time' => '10:00', 'end_time' => '11:00', 'room' => '1'];
    $id = $this->actingAs(mobileAdmin())->postJson('/api/v1/admin/schedules', $payload)->assertCreated()->json('record.id');
    $this->postJson('/api/v1/admin/schedules', $payload)->assertUnprocessable()->assertJsonValidationErrors('start_time');
    $this->putJson("/api/v1/admin/schedules/{$id}", array_replace($payload, ['end_time' => '11:30']))->assertOk();
    $foreign = Lesson::create(['title' => 'Foreign lesson', 'course_id' => Course::whereKeyNot($group->course_id)->firstOrFail()->id, 'status' => 'published']);
    $this->putJson("/api/v1/admin/schedules/{$id}", array_replace($payload, ['lesson_id' => $foreign->id]))->assertUnprocessable()->assertJsonValidationErrors('lesson_id');
    $this->getJson("/api/v1/admin/schedules/{$id}")->assertOk()->assertJsonPath('can_mark_attendance', false);
    $this->putJson("/api/v1/admin/schedules/{$id}/attendance", ['records' => [['student_id' => $student->id, 'status' => 'present']]])->assertUnprocessable();
    $this->travel(3)->hours();
    $this->getJson("/api/v1/admin/schedules/{$id}")->assertOk()->assertJsonPath('can_mark_attendance', true);
    $this->putJson("/api/v1/admin/schedules/{$id}/attendance", ['records' => [['student_id' => $student->id, 'status' => 'present']]])->assertOk();
    $this->assertDatabaseHas('attendance', ['schedule_id' => $id, 'student_id' => $student->id, 'status' => 'present']);
});

test('search pickers and audit results are paginated filtered and omit secrets', function () {
    $studentRole = mobileRole('STUDENT');
    $this->actingAs(mobileAdmin())->getJson('/api/v1/admin/options?type=users&role=STUDENT&q=oquvchi')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.password');
    $this->getJson('/api/v1/admin/users?role_id='.$studentRole)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.roles.0.name', 'STUDENT');
    $this->getJson('/api/v1/admin/options?type=groups')->assertOk()->assertJsonStructure(['data' => [['id', 'label', 'course_id', 'detail']]]);
    $this->getJson('/api/v1/admin/options?type=unknown')->assertUnprocessable();
    $this->getJson('/api/v1/admin/unknown')->assertNotFound();
    DB::table('audit_logs')->insert(['user_id' => mobileAdmin()->id, 'action' => 'user.created', 'properties' => '{"sensitive":"hidden"}', 'ip_address' => '127.0.0.1', 'created_at' => now()]);
    $this->getJson('/api/v1/admin/audit')->assertOk()->assertJsonMissingPath('data.0.properties')->assertJsonMissingPath('data.0.ip_address');
});

test('paginated administrative lists and pickers never return an unbounded user directory', function () {
    User::factory()->count(20)->create();
    $this->actingAs(mobileAdmin())->getJson('/api/v1/admin/users')->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('last_page', 2);
    $this->getJson('/api/v1/admin/users?page=2')->assertOk()->assertJsonPath('current_page', 2);
    $this->getJson('/api/v1/admin/options?type=users&role=STUDENT&q=markaz.uz')->assertOk()->assertJsonCount(1, 'data');
});

test('administrative mutations are audited without secrets and failed operations do not add audit entries', function () {
    $this->actingAs(mobileAdmin())->postJson('/api/v1/admin/courses', ['name' => 'Audited course', 'level' => 'beginner'])->assertCreated();
    $this->assertDatabaseHas('audit_logs', ['action' => 'course.created', 'user_id' => mobileAdmin()->id]);
    $before = DB::table('audit_logs')->count();
    $this->postJson('/api/v1/admin/courses', ['name' => 'Invalid', 'level' => 'unsupported'])->assertUnprocessable();
    expect(DB::table('audit_logs')->count())->toBe($before);
    $this->getJson('/api/v1/admin/audit')->assertOk()->assertJsonPath('data.0.action', 'course.created')->assertJsonMissingPath('data.0.properties');
});
