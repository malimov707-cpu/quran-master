<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('login valid credentials returns token and roles', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'mentor@markaz.uz',
        'password' => 'Parol!123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']]);

    expect($response->json('user.roles'))->toContain('MENTOR');
});

test('super admin login uses configured credentials', function () {
    config()->set('super_admin.email', 'malimov707@gmail.com');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'malimov707@gmail.com',
        'password' => 'mashhurbek707',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.email', 'malimov707@gmail.com');
});

test('login invalid credentials fails', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'mentor@markaz.uz',
        'password' => 'wrong-password',
    ])->assertStatus(422);
});

test('me returns authenticated user with permissions', function () {
    $user = User::where('email', 'malimov707@gmail.com')->first();

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('email', 'malimov707@gmail.com')
        ->assertJsonPath('roles.0', 'SUPER_ADMIN');
});

test('me requires authentication', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

test('logout revokes current token', function () {
    $user = User::where('email', 'malimov707@gmail.com')->first();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);

    // RequestGuard singleton'da user'ni cache qiladi (process qayta ishga tushmasa)
    auth()->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
});

test('inactive user cannot login', function () {
    User::create([
        'name' => 'Blocked',
        'email' => 'blocked@markaz.uz',
        'password' => Hash::make('Parol!123'),
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'blocked@markaz.uz',
        'password' => 'Parol!123',
    ])->assertStatus(403);
});

test('user hasPermission checks role permissions', function () {
    $mentor = User::where('email', 'mentor@markaz.uz')->first();

    expect($mentor->hasPermission('submissions.review'))->toBeTrue()
        ->and($mentor->hasPermission('users.delete'))->toBeFalse()
        ->and($mentor->hasRole('MENTOR'))->toBeTrue()
        ->and($mentor->hasRole('ADMIN'))->toBeFalse();
});
