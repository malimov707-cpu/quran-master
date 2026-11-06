<?php

use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('push devices are registered and removed for the authenticated user only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = str_repeat('a', 48);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/notifications/devices', [
            'token' => $token,
            'platform' => 'android',
            'device_id' => 'phone-1',
        ])
        ->assertCreated()
        ->assertJsonPath('device.platform', 'android');

    $this->assertDatabaseHas('push_devices', [
        'user_id' => $user->id,
        'token' => $token,
        'platform' => 'android',
    ]);

    $this->actingAs($other, 'sanctum')
        ->deleteJson('/api/v1/notifications/devices', ['token' => $token])
        ->assertOk();
    $this->assertDatabaseHas('push_devices', ['user_id' => $user->id, 'token' => $token]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/v1/notifications/devices', ['token' => $token])
        ->assertOk();
    $this->assertDatabaseMissing('push_devices', ['user_id' => $user->id, 'token' => $token]);
});

test('configured fcm sends one real request for each registered device', function () {
    config()->set('services.fcm.server_key', 'test-server-key');
    config()->set('services.fcm.endpoint', 'https://fcm.example.test/send');
    Http::fake([
        'https://fcm.example.test/send' => Http::response(['success' => 1, 'failure' => 0]),
    ]);

    $user = User::factory()->create();
    $user->pushDevices()->create([
        'token' => str_repeat('b', 48),
        'platform' => 'android',
        'last_seen_at' => now(),
    ]);

    app(NotificationService::class)->send($user, 'new_assignment', 'Yangi vazifa', 'Sura 1');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'key=test-server-key')
        && $request['to'] === str_repeat('b', 48)
        && $request['notification']['title'] === 'Yangi vazifa'
        && $request['data']['notification_type'] === 'new_assignment');
});

test('expo push tokens are sent to the expo push endpoint', function () {
    config()->set('services.fcm.server_key', '');
    config()->set('services.fcm.expo_endpoint', 'https://exp.example.test/push/send');
    Http::fake([
        'https://exp.example.test/push/send' => Http::response([
            'data' => [['status' => 'ok', 'id' => 'push-ticket-id']],
        ]),
    ]);

    $user = User::factory()->create();
    $user->pushDevices()->create([
        'token' => 'ExponentPushToken[abcdefghijklmnopqrstuvwxyz]',
        'platform' => 'android',
        'last_seen_at' => now(),
    ]);

    app(NotificationService::class)->send($user, 'new_assignment', 'Yangi vazifa', 'Sura 1');

    Http::assertSent(function ($request) {
        $payload = json_decode($request->body(), true);

        return $request->url() === 'https://exp.example.test/push/send'
            && $payload[0]['to'] === 'ExponentPushToken[abcdefghijklmnopqrstuvwxyz]'
            && $payload[0]['data']['notification_type'] === 'new_assignment';
    });
});

test('expo device not registered response removes the stale token', function () {
    config()->set('services.fcm.server_key', '');
    config()->set('services.fcm.expo_endpoint', 'https://exp.example.test/push/send');
    Http::fake([
        'https://exp.example.test/push/send' => Http::response([
            'data' => [[
                'status' => 'error',
                'details' => ['error' => 'DeviceNotRegistered'],
            ]],
        ]),
    ]);

    $user = User::factory()->create();
    $device = $user->pushDevices()->create([
        'token' => 'ExponentPushToken[abcdefghijklmnopqrstuvwxyz]',
        'platform' => 'android',
        'last_seen_at' => now(),
    ]);

    app(NotificationService::class)->send($user, 'new_assignment', 'Yangi vazifa', 'Sura 1');

    $this->assertDatabaseMissing('push_devices', ['id' => $device->id]);
});
