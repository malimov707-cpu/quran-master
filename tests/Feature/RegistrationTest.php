<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

/** @return array{name: string, email: string, password: string, password_confirmation: string} */
function registrationPayload(): array
{
    return ['name' => 'Yangi o‘quvchi', 'email' => 'new-student@example.com', 'password' => 'Registration!707', 'password_confirmation' => 'Registration!707'];
}

test('web registration page and login link are available to guests', function () {
    $this->get(route('login'))->assertOk()->assertSee(route('register'));
    $this->get(route('register'))->assertOk()->assertSee('Akkaunt yaratish')->assertSee('name="password_confirmation"', false)->assertSee(route('login'));
});

test('web registration creates only a student and logs them into their own dashboard', function () {
    $response = $this->post(route('register'), registrationPayload() + ['roles' => ['SUPER_ADMIN'], 'is_active' => false, 'ai_premium' => true]);
    $response->assertRedirect(route('dashboard'));
    $user = User::where('email', 'new-student@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->roles->pluck('name')->all())->toBe(['STUDENT']);
    expect(Hash::check('Registration!707', $user->password))->toBeTrue();
    expect($user->is_active)->toBeTrue();
    expect($user->ai_premium)->toBeFalse();
    expect($user->email_verified_at)->toBeNull();
    $this->get(route('dashboard'))->assertOk()->assertSee('Bugungi o‘quv yo‘lingiz');
    $this->assertDatabaseMissing('group_students', ['student_id' => $user->id]);
});

test('api registration returns a working token with student permissions and no credentials', function () {
    $payload = registrationPayload();
    $payload['name'] = '  New student  ';
    $payload['email'] = '  NEW-STUDENT@EXAMPLE.COM  ';
    $response = $this->postJson('/api/v1/auth/register', $payload + ['device_name' => 'Test phone', 'role' => 'CEO', 'permissions' => ['users.create'], 'ai_premium' => true, 'email_verified_at' => now()]);
    $response->assertCreated()->assertJsonPath('user.name', 'New student')->assertJsonPath('user.email', 'new-student@example.com')->assertJsonPath('user.roles', ['STUDENT'])
        ->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.remember_token');
    $user = User::findOrFail($response->json('user.id'));
    expect($user->hasPermission('submissions.create'))->toBeTrue();
    expect($user->hasPermission('users.create'))->toBeFalse();
    expect($user->ai_premium)->toBeFalse();
    expect($user->email_verified_at)->toBeNull();
    expect($user->tokens()->firstOrFail()->name)->toBe('Test phone');
    $this->withToken($response->json('token'))->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('id', $user->id);
    $this->getJson('/api/v1/student/journey')->assertOk()->assertJsonPath('stage', 'explore');
});

test('invalid registrations never create users or tokens', function (array $changes, array $fields) {
    $users = User::count();
    $this->postJson('/api/v1/auth/register', array_replace(registrationPayload(), $changes))
        ->assertUnprocessable()->assertJsonValidationErrors($fields);
    expect(User::count())->toBe($users);
    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with([
    'missing values' => [['name' => '', 'email' => '', 'password' => ''], ['name', 'email', 'password']],
    'invalid email' => [['email' => 'not-an-email'], ['email']],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], ['password']],
    'mismatch' => [['password_confirmation' => 'another-password'], ['password']],
    'missing confirmation' => [['password_confirmation' => null], ['password']],
    'long name' => [['name' => str_repeat('a', 256)], ['name']],
    'invalid name type' => [['name' => ['student']], ['name']],
    'long multibyte password' => [['password' => str_repeat('ў', 40), 'password_confirmation' => str_repeat('ў', 40)], ['password']],
    'invalid device name' => [['device_name' => ['phone']], ['device_name']],
]);

test('registration rejects existing email addresses regardless of capitalization', function () {
    $existing = User::factory()->create(['email' => 'Existing@Example.com']);
    $this->postJson('/api/v1/auth/register', array_replace(registrationPayload(), ['email' => ' EXISTING@EXAMPLE.COM ']))
        ->assertUnprocessable()->assertJsonValidationErrors('email')->assertJsonPath('errors.email.0', 'Bu email allaqachon ro‘yxatdan o‘tgan.');
    expect($existing->fresh()->roles)->toHaveCount(0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('web validation retains safe fields and never flashes passwords', function () {
    $this->from(route('register'))->post(route('register'), array_replace(registrationPayload(), ['password_confirmation' => 'wrong']))
        ->assertRedirect(route('register'))->assertSessionHasErrors('password')->assertSessionHas('_old_input.email', 'new-student@example.com')
        ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'new-student@example.com']);
});

test('registration fails safely when the student role has not been configured', function () {
    Role::where('name', 'STUDENT')->delete();
    $this->postJson('/api/v1/auth/register', registrationPayload())->assertStatus(503);
    $this->assertDatabaseMissing('users', ['email' => 'new-student@example.com']);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('registration endpoints limit repeated attempts', function (string $path) {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson($path, [])->assertUnprocessable();
    }
    $this->postJson($path, registrationPayload())->assertTooManyRequests();
    $this->assertDatabaseMissing('users', ['email' => 'new-student@example.com']);
})->with(['/register', '/api/v1/auth/register']);

test('signed in web users cannot open or submit a second registration', function () {
    $user = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $this->actingAs($user)->get(route('register'))->assertRedirect(route('dashboard'));
    $this->post(route('register'), registrationPayload())->assertRedirect(route('dashboard'));
    $this->assertDatabaseMissing('users', ['email' => 'new-student@example.com']);
});

test('registered credentials work for API and web login with an uppercase email', function () {
    $this->postJson('/api/v1/auth/register', registrationPayload())->assertCreated();
    $this->postJson('/api/v1/auth/login', ['email' => 'NEW-STUDENT@EXAMPLE.COM', 'password' => 'Registration!707'])->assertOk()->assertJsonPath('user.roles', ['STUDENT']);
    $this->post(route('login'), ['email' => 'NEW-STUDENT@EXAMPLE.COM', 'password' => 'Registration!707'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs(User::where('email', 'new-student@example.com')->firstOrFail());
});
