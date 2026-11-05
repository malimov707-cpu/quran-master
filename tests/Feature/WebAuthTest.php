<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('guest sees login page', function () {
    $this->get('/login')->assertOk()->assertSee('Tizimga kirish');
});

test('guest is redirected to login from dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('user can login with valid credentials', function () {
    $this->post('/login', [
        'email' => 'mentor@markaz.uz',
        'password' => 'Parol!123',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs(User::where('email', 'mentor@markaz.uz')->first());
});

test('user cannot login with invalid credentials', function () {
    $this->post('/login', [
        'email' => 'mentor@markaz.uz',
        'password' => 'wrong',
    ])->assertSessionHasErrors('email');
});

test('authenticated user sees dashboard with role label', function () {
    $user = User::where('email', 'mentor@markaz.uz')->first();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Mentor');
});

test('user can logout', function () {
    $user = User::where('email', 'mentor@markaz.uz')->first();

    $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});
