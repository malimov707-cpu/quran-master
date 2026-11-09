<?php

namespace App\Providers;

use App\Services\AI\Contracts\AIExplanationProvider;
use App\Services\AI\Contracts\SpeechRecognitionProvider;
use App\Services\AI\Contracts\TajweedAnalysisProvider;
use App\Services\AI\Providers\OpenRouterExplanationProvider;
use App\Services\AI\Providers\UnavailableTajweedProvider;
use App\Services\AI\Providers\WhisperSpeechProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * AI provayderlari — almashtirilishi mumkin (kalitlar faqat backendda).
     */
    public function register(): void
    {
        $this->app->bind(SpeechRecognitionProvider::class, WhisperSpeechProvider::class);
        $this->app->bind(TajweedAnalysisProvider::class, UnavailableTajweedProvider::class);
        $this->app->bind(AIExplanationProvider::class, OpenRouterExplanationProvider::class);
    }

    public function boot(): void
    {
        RateLimiter::for('sign-in', function (Request $request): array {
            $email = $request->input('email');
            $identifier = is_string($email) ? mb_strtolower(trim($email)) : '';

            return [Limit::perMinute(10)->by('sign-in:'.hash('sha256', $identifier.'|'.$request->ip())), Limit::perMinute(60)->by('sign-in-ip:'.$request->ip())];
        });
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by('registration:'.$request->ip()));
        RateLimiter::for('password-recovery', fn (Request $request) => Limit::perMinute(5)->by('password-recovery:'.$request->ip()));

        $this->app->make('translation.loader')->addJsonPath(resource_path('lang'));
        ResetPassword::toMailUsing(fn (object $notifiable, string $token): MailMessage => (new MailMessage)
            ->subject(__('Parolni tiklash'))
            ->greeting(__('Assalomu alaykum!'))
            ->line(__('Akkauntingiz parolini yangilash uchun quyidagi havolani oching.'))
            ->action(__('Parolni yangilash'), rtrim(config('app.url'), '/').route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false))
            ->line(__('Havola :minutes daqiqa davomida amal qiladi.', ['minutes' => config('auth.passwords.users.expire')]))
            ->line(__('Agar parol tiklashni so‘ramagan bo‘lsangiz, bu xatni e’tiborsiz qoldiring.')));

        ViewFacade::composer('layouts.app', function (View $view): void {
            $permissions = auth()->user()?->permissions() ?? collect();
            $isAdministration = auth()->user()?->hasRole('SUPER_ADMIN', 'CENTER_ADMIN') ?? false;
            $view->with('isAdministration', $isAdministration);
            $view->with('unreadNotificationCount', auth()->user()?->appNotifications()->whereNull('read_at')->count() ?? 0);
            $navigation = [
                ['route' => 'dashboard', 'label' => 'Bosh sahifa', 'permission' => null],
                ['route' => 'prayer-times', 'label' => 'Namoz vaqtlari', 'permission' => null],
                ['route' => 'education.courses', 'label' => 'Kurslar', 'permission' => 'courses.view'],
                ['route' => 'education.lessons', 'label' => 'Darslar', 'permission' => 'lessons.view'],
                ['route' => 'education.groups', 'label' => 'Guruhlar', 'permission' => 'groups.view'],
                ['route' => 'education.schedules', 'label' => 'Jadval va davomat', 'permission' => 'schedules.view'],
                ['route' => 'quran.surahs', 'label' => 'Qur’on', 'permission' => null],
                ['route' => 'ai.voice', 'label' => 'AI Ustoz (ovozli)', 'permission' => null],
                ['route' => 'tajweed.index', 'label' => 'Tajvid akademiyasi', 'permission' => null],
                ['route' => 'assignments.manage', 'label' => 'Topshiriqlar boshqaruvi', 'permission' => 'assignments.manage'],
                ['route' => 'homework.index', 'label' => 'Uy vazifalarim', 'permission' => 'submissions.create'],
                ['route' => 'mentor.submissions', 'label' => 'Mentor tekshiruvi', 'permission' => 'submissions.review'],
                ['route' => 'parents.dashboard', 'label' => 'Farzandlarim', 'permission' => 'children.view'],
                ['route' => 'payments.index', 'label' => 'To‘lovlar', 'permission' => 'submissions.create|children.view'],
                ['route' => 'notifications.index', 'label' => 'Bildirishnomalar', 'permission' => null],
            ];
            $view->with('navigation', array_filter($navigation, function (array $item) use ($permissions, $isAdministration): bool {
                // 'a|b' sintaksisi: kamida bittasi yetarli (OR)
                $allowed = $item['permission'] === null || collect(explode('|', $item['permission']))->contains(fn ($permission): bool => $permissions->contains($permission));
                if (! $allowed) {
                    return false;
                }

                return ! $isAdministration || ! in_array($item['route'], ['homework.index', 'mentor.submissions', 'parents.dashboard'], true);
            }));
        });
    }
}
