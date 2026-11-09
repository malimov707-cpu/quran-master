<?php

use App\Models\Quran\TajweedRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('tajweed academy page lists all seeded rules', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    $this->actingAs($student)->get(route('tajweed.index'))
        ->assertOk()
        ->assertSee('Izhor')
        ->assertSee('Qalqala')
        ->assertSee('Madd')
        ->assertSee('Maxraj');
});

test('tajweed rule detail page shows full description', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $rule = TajweedRule::where('code', 'ikhfa')->first();

    $this->actingAs($student)->get(route('tajweed.show', $rule))
        ->assertOk()
        ->assertSee($rule->name_uz);
});

test('tajweed API returns rules with search', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $token = $student->createToken('t')->plainTextToken;

    $all = $this->withToken($token)->getJson('/api/v1/tajweed')->assertOk();
    expect(count($all->json()))->toBeGreaterThanOrEqual(10);

    $this->withToken($token)->getJson('/api/v1/tajweed?q=qalqala')
        ->assertOk()
        ->assertJsonCount(1);
});

test('tajweed visible for all roles', function () {
    foreach (['mentor@markaz.uz', 'ota@markaz.uz'] as $email) {
        $user = User::where('email', $email)->first();
        $this->actingAs($user)->get(route('tajweed.index'))->assertOk();
    }
});
