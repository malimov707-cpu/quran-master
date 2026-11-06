<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('ceo sees all ai capabilities without requiring premium', function () {
    config()->set('services.openrouter.key', null);
    $ceo = User::where('email', 'malimov707@gmail.com')->firstOrFail();

    $this->actingAs($ceo, 'sanctum')
        ->getJson('/api/v1/ai/capabilities')
        ->assertOk()
        ->assertJsonPath('premium', true)
        ->assertJsonFragment(['Premium boshqaruvi']);
});

test('student ai is blocked without premium or high score', function () {
    config()->set('services.openrouter.key', 'test-key');
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();

    $this->actingAs($student, 'sanctum')
        ->postJson('/api/v1/ai/chat', ['question' => 'Tajvidni tushuntir'])
        ->assertStatus(402)
        ->assertJsonPath('status', 'premium_required');
});

test('admins can enable student premium access', function () {
    $ceo = User::where('email', 'malimov707@gmail.com')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();

    $this->actingAs($ceo, 'sanctum')
        ->patchJson("/api/v1/ai/users/{$student->id}/premium", ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('ai_premium', true);

    expect($student->fresh()->ai_premium)->toBeTrue();
});

test('student with premium can reach configured provider', function () {
    config()->set('services.openrouter.key', null);
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $student->update(['ai_premium' => true]);

    $this->actingAs($student, 'sanctum')
        ->postJson('/api/v1/ai/chat', ['question' => 'Tajvidni tushuntir'])
        ->assertStatus(503)
        ->assertJsonPath('status', 'unavailable');
});
