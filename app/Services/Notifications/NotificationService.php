<?php

namespace App\Services\Notifications;

use App\Models\User;

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
            'email' => logger()->info('notification.email', ['to' => $user->email, 'type' => $type, 'title' => $title]),
            'push' => logger()->info('notification.push.fcm_ready', ['user' => $user->id, 'type' => $type]),
            default => null,
        };
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
