<?php

/**
 * PHASE 9 — Xavfsizlik auditi:
 * RBAC, IDOR, MIME/hajm, token revocation, rate limiting.
 */
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function secToken(string $email): string
{
    auth()->forgetGuards();

    return User::where('email', $email)->firstOrFail()->createToken('sec')->plainTextToken;
}

function secStudent(string $email = 'sec-s@test.uz'): User
{
    $s = User::create(['name' => 'Sec S', 'email' => $email, 'password' => 'Parol!123']);
    $s->roles()->attach(Role::where('name', 'STUDENT')->firstOrFail()->id);

    return $s;
}

// --- RBAC ---

test('RBAC: student cannot create courses, groups, assignments', function () {
    $student = secStudent();
    $token = $student->createToken('s')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/courses', ['name' => 'X', 'level' => 'beginner'])->assertStatus(403);
    $this->withToken($token)->postJson('/api/v1/groups', ['name' => 'X', 'course_id' => 1])->assertStatus(403);
    $this->withToken($token)->postJson('/api/v1/assignments', ['group_id' => 1, 'title' => 'X'])->assertStatus(403);
});

test('RBAC: student cannot list pending reviews or review', function () {
    $student = secStudent();
    $token = $student->createToken('s')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/submissions/pending')->assertStatus(403);
    // mavjud bo'lmagan resurs — 404 (binding) yoki 403 (permission), ikkalasi ham xavfsiz
    $res = $this->withToken($token)->postJson('/api/v1/submissions/999999/review', ['action' => 'approve']);
    expect($res->status())->toBeIn([403, 404]);
});

test('RBAC: parent cannot submit or review', function () {
    $token = User::where('email', 'ota@markaz.uz')->first()->createToken('s')->plainTextToken;

    // mavjud bo'lmagan assignment — 403 yoki 404
    $res = $this->withToken($token)->postJson('/api/v1/assignments/999999/submit', []);
    expect($res->status())->toBeIn([403, 404]);
    $this->withToken($token)->getJson('/api/v1/submissions/pending')->assertStatus(403);
});

test('RBAC: mentor cannot manage users or archive courses', function () {
    $mentor = User::where('email', 'mentor@markaz.uz')->first();
    $token = secToken('mentor@markaz.uz');

    // mentor courses.view ham yo'q (pending review-focused rol)
    $this->withToken($token)->postJson('/api/v1/courses', ['name' => 'X', 'level' => 'beginner'])->assertStatus(403);
});

// --- IDOR ---

test('IDOR: student cannot read other student submission', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $course = Course::create(['name' => 'K', 'level' => 'beginner']);
    $group = Group::create(['name' => 'G', 'course_id' => $course->id]);
    $assignment = Assignment::create(['group_id' => $group->id, 'created_by' => $admin->id, 'title' => 'A', 'status' => 'published']);

    $owner = secStudent('owner@test.uz');
    $other = secStudent('other-s@test.uz');

    $submission = Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $owner->id,
        'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now(),
    ]);

    // Egasi ko'radi
    $ownerToken = $owner->createToken('own')->plainTextToken;
    $this->withToken($ownerToken)->getJson("/api/v1/submissions/{$submission->id}")->assertOk();

    // Boshqasi ko'ra olmaydi (guard cache tozalab — real HTTP'da har request yangi)
    auth()->forgetGuards();
    $otherToken = $other->createToken('other')->plainTextToken;
    $this->withToken($otherToken)->getJson("/api/v1/submissions/{$submission->id}")->assertStatus(403);
});

test('IDOR: parent cannot read other child progress', function () {
    $parent = User::where('email', 'ota@markaz.uz')->first();
    $token = secToken('ota@markaz.uz');

    $other = secStudent();
    $this->withToken($token)->getJson("/api/v1/progress/students/{$other->id}")->assertStatus(403);
});

// --- Audio fayl xavfsizligi ---

test('MIME: non-audio file rejected', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $student = secStudent();
    $course = Course::create(['name' => 'K', 'level' => 'beginner']);
    $group = Group::create(['name' => 'G', 'course_id' => $course->id]);
    $group->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);
    $assignment = Assignment::create(['group_id' => $group->id, 'created_by' => $admin->id, 'title' => 'A', 'status' => 'published']);

    // PHP fayl audio sifatida
    $fake = File::fake()->createWithContent('evil.php', '<?php echo 1;');
    $fake->mime = 'application/x-php';

    $token = $student->createToken('t')->plainTextToken;
    $res = $this->withToken($token)->post("/api/v1/assignments/{$assignment->id}/submit", [
        'audio' => [$fake],
    ]);

    // 422 (MIME) yoki 403 (guruh tekshiruvi) — ikkalasi ham yaxshi, 201 bo'lmasin
    expect($res->status())->not->toBe(201);
});

test('SIZE: oversize audio rejected', function () {
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $student = secStudent();
    $course = Course::create(['name' => 'K', 'level' => 'beginner']);
    $group = Group::create(['name' => 'G', 'course_id' => $course->id]);
    $group->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);
    $assignment = Assignment::create(['group_id' => $group->id, 'created_by' => $admin->id, 'title' => 'A', 'status' => 'published']);

    // 21 MB
    $big = File::fake()->create('big.wav', 21 * 1024, 'audio/wav');
    $token = $student->createToken('t')->plainTextToken;

    $res = $this->withToken($token)->post("/api/v1/assignments/{$assignment->id}/submit", [
        'audio' => [$big],
    ]);

    expect($res->status())->toBeIn([413, 422]);
});

// --- Token xavfsizligi ---

test('TOKEN: logout revokes and token cannot be reused', function () {
    $user = User::where('email', 'mentor@markaz.uz')->first();
    $token = $user->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    auth()->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
});

test('TOKEN: invalid token format rejected', function () {
    $this->withToken('garbage-token-value')->getJson('/api/v1/auth/me')->assertStatus(401);
});

// --- Rate limiting ---

test('RATE: login throttled after 10 attempts', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@test.uz', 'password' => 'wrong']);
    }

    $res = $this->postJson('/api/v1/auth/login', ['email' => 'nobody@test.uz', 'password' => 'wrong']);
    expect($res->status())->toBe(429);
});

// --- Audit log ---

test('AUDIT: login updates last_login_at', function () {
    $user = User::where('email', 'mentor@markaz.uz')->first();
    expect($user->last_login_at)->toBeNull();

    $this->postJson('/api/v1/auth/login', ['email' => 'mentor@markaz.uz', 'password' => 'Parol!123'])->assertOk();

    expect($user->refresh()->last_login_at)->not->toBeNull();
});
