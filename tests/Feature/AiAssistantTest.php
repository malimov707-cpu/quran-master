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
        ->assertJsonFragment(['Premium va AI boshqaruvi']);
});

test('student ai works within free daily limit, then asks for premium', function () {
    config()->set('services.openrouter.key', 'test-key');
    config()->set('ai.free_daily_limit', 2);
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();

    Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'Ikhfa — nogizor harf qoidasi.']]]])]);

    $this->actingAs($student, 'sanctum')
        ->postJson('/api/v1/ai/chat', ['question' => 'Birinchi savol'])
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    $this->actingAs($student, 'sanctum')
        ->postJson('/api/v1/ai/chat', ['question' => 'Ikkinchi savol'])
        ->assertOk();

    // Limit tugadi — 429
    $this->actingAs($student, 'sanctum')
        ->postJson('/api/v1/ai/chat', ['question' => 'Uchinchi savol'])
        ->assertStatus(429)
        ->assertJsonPath('status', 'daily_limit_reached');
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

test('role assistant returns distinct task prompts', function () {
    config()->set('services.openrouter.key', null);
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();

    $this->actingAs($mentor, 'sanctum')
        ->getJson('/api/v1/ai/capabilities')
        ->assertOk()
        ->assertJsonFragment(['Shaxsiy feedback'])
        ->assertJsonFragment(['Talaba topshirig‘i tahlili']);
});
