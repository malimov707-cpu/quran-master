<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Notification service — in-app yozuv har doim, boshqa kanallar
 * kanal driver'lari orqali (email log, FCM push tayyor arxitektura).
 */
class NotificationService
{
    /**
     * Bildirishnoma yaratish (in-app) + kanallarga yuborish.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(User $user, string $type, string $title, ?string $body = null, array $data = []): void
    {
        $user->appNotifications()->create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        // Kanallar: email (log), push (FCM tayyor), telegram (tayyor)
        foreach (['email', 'push'] as $channel) {
            $enabled = $user->notificationPreferences()
                ->where('channel', $channel)
                ->where('type', $type)
                ->value('enabled') ?? true; // default yoqilgan

            if ($enabled) {
                $this->dispatchChannel($user, $channel, $type, $title, $body, $data);
            }
        }
    }

    /**
     * Kanalga yuborish — hozircha log/queue, FCM keyingi bosqichda.
     */
    private function dispatchChannel(User $user, string $channel, string $type, string $title, ?string $body, array $data): void
    {
        match ($channel) {
            'email' => $this->sendEmail($user, $title, $body),
            'push' => $this->sendPush($user, $type, $title, $body, $data),
            default => null,
        };
    }

    private function sendEmail(User $user, string $title, ?string $body): void
    {
        if (config('mail.default') === 'log') {
            logger()->info('notification.email.log', ['to' => $user->email, 'title' => $title]);

            return;
        }

        Mail::raw($body ?: $title, fn ($message) => $message->to($user->email)->subject($title));
    }

    private function sendPush(User $user, string $type, string $title, ?string $body, array $data): void
    {
        $serverKey = (string) config('services.fcm.server_key');
        $expoEndpoint = (string) config('services.fcm.expo_endpoint');
        if ($serverKey === '' && $expoEndpoint === '') {
            logger()->info('notification.push.unconfigured', ['user_id' => $user->id]);

            return;
        }

        foreach ($user->pushDevices()->get()->unique('token') as $device) {
            try {
                if (str_starts_with($device->token, 'ExponentPushToken[')) {
                    if ($expoEndpoint === '') {
                        continue;
                    }

                    $response = Http::acceptJson()
                        ->timeout(10)
                        ->post($expoEndpoint, [
                            [
                                'to' => $device->token,
                                'title' => $title,
                                'body' => $body ?: $title,
                                'data' => array_merge($data, ['notification_type' => $type]),
                                'priority' => 'high',
                                'channelId' => 'default',
                            ],
                        ]);

                    $tickets = collect($response->json('data', []));
                    $invalidTicket = $tickets->first(fn (array $ticket): bool => data_get($ticket, 'details.error') === 'DeviceNotRegistered');
                    if (! $response->successful() || $tickets->contains(fn (array $ticket): bool => data_get($ticket, 'status') === 'error')) {
                        logger()->warning('notification.push.failed', [
                            'user_id' => $user->id,
                            'platform' => $device->platform,
                            'status' => $response->status(),
                            'provider' => 'expo',
                        ]);
                    }
                    if ($invalidTicket !== null) {
                        $device->delete();
                    }

                    continue;
                }

                if ($serverKey === '') {
                    continue;
                }

                $response = Http::timeout(10)->withHeaders([
                    'Authorization' => 'key='.$serverKey,
                    'Content-Type' => 'application/json',
                ])->post((string) config('services.fcm.endpoint'), [
                    'to' => $device->token,
                    'notification' => ['title' => $title, 'body' => $body ?: $title],
                    'data' => array_map('strval', array_merge($data, ['notification_type' => $type])),
                    'priority' => 'high',
                ]);

                if (! $response->successful()) {
                    logger()->warning('notification.push.failed', [
                        'user_id' => $user->id,
                        'platform' => $device->platform,
                        'status' => $response->status(),
                    ]);
                } elseif ((int) data_get($response->json(), 'failure', 0) > 0) {
                    $device->delete();
                }
            } catch (\Throwable $exception) {
                logger()->warning('notification.push.exception', [
                    'user_id' => $user->id,
                    'platform' => $device->platform,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * Ko'p foydalanuvchilarga yuborish (guruh o'quvchilari uchun).
     *
     * @param  iterable<User>  $users
     */
    public function sendMany(iterable $users, string $type, string $title, ?string $body = null, array $data = []): void
    {
        foreach ($users as $user) {
            $this->send($user, $type, $title, $body, $data);
        }
    }
}
