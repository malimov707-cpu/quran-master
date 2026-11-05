<?php

namespace App\Providers;

use App\Services\AI\Contracts\SpeechRecognitionProvider;
use App\Services\AI\Contracts\TajweedAnalysisProvider;
use App\Services\AI\Providers\UnavailableTajweedProvider;
use App\Services\AI\Providers\WhisperSpeechProvider;
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
    }

    public function boot(): void
    {
        $this->app->make('translation.loader')->addJsonPath(resource_path('lang'));

        ViewFacade::composer('layouts.app', function (View $view): void {
            $permissions = auth()->user()?->permissions() ?? collect();
            $isAdministration = auth()->user()?->hasRole('SUPER_ADMIN', 'CENTER_ADMIN') ?? false;
            $view->with('isAdministration', $isAdministration);
            $view->with('unreadNotificationCount', auth()->user()?->appNotifications()->whereNull('read_at')->count() ?? 0);
            $navigation = [
                ['route' => 'dashboard', 'label' => 'Bosh sahifa', 'permission' => null],
                ['route' => 'education.courses', 'label' => 'Kurslar', 'permission' => 'courses.view'],
                ['route' => 'education.lessons', 'label' => 'Darslar', 'permission' => 'lessons.view'],
                ['route' => 'education.groups', 'label' => 'Guruhlar', 'permission' => 'groups.view'],
                ['route' => 'education.schedules', 'label' => 'Jadval va davomat', 'permission' => 'schedules.view'],
                ['route' => 'quran.surahs', 'label' => 'Qur’on', 'permission' => null],
                ['route' => 'assignments.manage', 'label' => 'Topshiriqlar boshqaruvi', 'permission' => 'assignments.manage'],
                ['route' => 'homework.index', 'label' => 'Uy vazifalarim', 'permission' => 'submissions.create'],
                ['route' => 'mentor.submissions', 'label' => 'Mentor tekshiruvi', 'permission' => 'submissions.review'],
                ['route' => 'parents.dashboard', 'label' => 'Farzandlarim', 'permission' => 'children.view'],
                ['route' => 'notifications.index', 'label' => 'Bildirishnomalar', 'permission' => null],
            ];
            $view->with('navigation', array_filter($navigation, fn (array $item): bool => ($item['permission'] === null || $permissions->contains($item['permission'])) && (! $isAdministration || ! in_array($item['route'], ['homework.index', 'mentor.submissions', 'parents.dashboard'], true))));
        });
    }
}
