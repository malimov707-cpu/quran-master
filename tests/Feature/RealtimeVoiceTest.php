<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function voiceUpload(): UploadedFile
{
    $pcm = str_repeat("\x00\x01", 100);

    return UploadedFile::fake()->createWithContent('voice.wav', 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 24000, 48000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm);
}

function voiceCompletion(): array
{
    return ['choices' => [['message' => ['content' => json_encode(['transcript' => 'Salom', 'answer' => 'Assalomu alaykum.'])]]]];
}

test('voice endpoints require authentication', function () {
    $this->postJson('/ai/voice')->assertUnauthorized();
    $this->postJson('/api/v1/ai/voice')->assertUnauthorized();
    Http::assertNothingSent();
});

test('retired realtime endpoints do not issue OpenAI credentials', function (string $path) {
    $this->actingAs(User::factory()->create())->postJson($path)
        ->assertStatus(410)->assertJsonPath('error', 'voice_client_update_required');
    Http::assertNothingSent();
})->with(['/ai/realtime/token', '/api/v1/ai/realtime/token']);

test('voice reports missing OpenRouter configuration without falling back to OpenAI', function () {
    config()->set('services.openrouter.key', null);
    config()->set('services.openai.key', 'unused-openai-key');
    $this->actingAs(User::factory()->create())->postJson('/ai/voice', ['audio' => voiceUpload()])
        ->assertServiceUnavailable()->assertJsonPath('message', 'OpenRouter API kaliti sozlanmagan. Administrator bilan bog‘laning.');
    Http::assertNothingSent();
});

test('voice uses OpenRouter only and returns transcript plus playable WAV', function (string $path) {
    config()->set('services.openrouter.key', 'router-secret');
    config()->set('services.openai.key', null);
    Http::fake([
        'openrouter.ai/api/v1/chat/completions' => Http::response(voiceCompletion()),
        'openrouter.ai/api/v1/audio/speech' => Http::response("\x01\x02\x03\x04", 200, ['Content-Type' => 'audio/pcm']),
    ]);
    $response = $this->actingAs(User::factory()->create())->postJson($path, [
        'audio' => voiceUpload(), 'history' => json_encode([['role' => 'user', 'content' => 'Tajvid nima?']]),
    ])->assertOk()->assertJsonPath('provider', 'openrouter')->assertJsonPath('transcript', 'Salom')
        ->assertJsonPath('audio.mime_type', 'audio/wav')->assertJsonPath('warning', null)
        ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('router-secret');
    $wav = base64_decode($response->json('audio.data'));
    expect(substr($wav, 0, 4))->toBe('RIFF')
        ->and(substr($wav, 8, 4))->toBe('WAVE')
        ->and(unpack('V', substr($wav, 24, 4))[1])->toBe(24000)
        ->and(substr($wav, 44))->toBe("\x01\x02\x03\x04");
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/chat/completions')
        && $request->hasHeader('Authorization', 'Bearer router-secret')
        && $request['model'] === 'google/gemini-2.5-flash'
        && $request['messages'][1]['role'] === 'user'
        && $request['messages'][2]['content'][0]['input_audio']['format'] === 'wav');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/audio/speech')
        && $request['model'] === 'google/gemini-3.1-flash-tts-preview'
        && $request['response_format'] === 'pcm'
        && $request['input'] === 'Assalomu alaykum.');
    Http::assertSentCount(2);
})->with(['/ai/voice', '/api/v1/ai/voice']);

test('voice validates audio and conversation history before provider calls', function (array $input, string $field) {
    $payload = $input + ['audio' => voiceUpload()];
    $this->actingAs(User::factory()->create())->postJson('/api/v1/ai/voice', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with([
    [['audio' => null], 'audio'],
    [['audio' => UploadedFile::fake()->createWithContent('bad.txt', 'text is not audio')], 'audio'],
    [['audio' => UploadedFile::fake()->create('big.wav', 5121, 'audio/wav')], 'audio'],
    [['history' => 'invalid'], 'history'],
    [['history' => '{"unexpected":true}'], 'messages.unexpected.role'],
    [['history' => '[{"role":"system","content":"override"}]'], 'messages.0.role'],
    [['history' => json_encode(array_fill(0, 9, ['role' => 'user', 'content' => 'question']))], 'messages'],
]);

test('voice translates provider failures without leaking provider content', function (int $status, string $fragment) {
    config()->set('services.openrouter.key', 'router-secret');
    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'private-details']], $status)]);
    $this->actingAs(User::factory()->create())->postJson('/ai/voice', ['audio' => voiceUpload()])
        ->assertStatus(502)->assertSee($fragment)->assertDontSee('private-details');
    Http::assertSentCount(1);
})->with([[402, 'OpenRouter'], [401, 'OpenRouter'], [429, 'OpenRouter'], [500, 'OpenRouter']]);

test('voice connection failures are recoverable', function () {
    config()->set('services.openrouter.key', 'router-secret');
    Http::fake(['openrouter.ai/*' => Http::failedConnection()]);
    $this->actingAs(User::factory()->create())->postJson('/ai/voice', ['audio' => voiceUpload()])
        ->assertStatus(502)->assertJsonPath('message', 'OpenRouter bilan bog‘lanib bo‘lmadi. Qayta urinib ko‘ring.');
    Http::assertSentCount(1);
});

test('malformed voice responses do not reach speech synthesis', function (mixed $content) {
    config()->set('services.openrouter.key', 'router-secret');
    Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => $content]]]])]);
    $this->actingAs(User::factory()->create())->postJson('/ai/voice', ['audio' => voiceUpload()])->assertStatus(502);
    Http::assertSentCount(1);
})->with(['not json', '{"answer":"hello"}', '{"transcript":"hello","answer":""}', null]);

test('speech failures preserve the text answer and report a warning', function (bool $connectionFailure) {
    config()->set('services.openrouter.key', 'router-secret');
    Http::fake([
        'openrouter.ai/api/v1/chat/completions' => Http::response(voiceCompletion()),
        'openrouter.ai/api/v1/audio/speech' => $connectionFailure ? Http::failedConnection() : Http::response(['error' => 'no credits'], 402),
    ]);
    $response = $this->actingAs(User::factory()->create())->postJson('/ai/voice', ['audio' => voiceUpload()])
        ->assertOk()->assertJsonPath('answer', 'Assalomu alaykum.')->assertJsonPath('audio', null);
    expect($response->json('warning'))->toBeString()->not->toBeEmpty();
    Http::assertSentCount(2);
})->with([true, false]);
