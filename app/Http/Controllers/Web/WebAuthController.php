<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Course;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WebAuthController extends BaseController
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Kiritilgan ma\'lumotlar noto\'g\'ri.'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('Akkaunt faol emas.'),
            ]);
        }

        auth()->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function dashboard(Request $request, DashboardService $dashboard): View
    {
        /** @var User $user */
        $user = $request->user();
        $permissions = $user->permissions();
        $isAdministration = $user->hasRole('SUPER_ADMIN', 'CENTER_ADMIN');
        $can = fn (string $permission): bool => $permissions->contains($permission);

        // Real statistika — faqat foydalanuvchi ruxsatiga mos ko'rsatkichlar
        $cards = [];

        if ($can('groups.view') || $can('users.view')) {
            $newThisWeek = User::whereHas('roles', fn ($query) => $query->where('name', 'STUDENT'))->where('created_at', '>=', now()->subWeek())->count();
            $totalStudents = User::whereHas('roles', fn ($q) => $q->where('name', 'STUDENT'))->count();
            $cards[] = ['icon' => '👤', 'title' => "O'quvchilar", 'description' => 'Jami ro\'yxatdan o\'tgan',
                'value' => (string) $totalStudents,
                'trend' => $newThisWeek > 0 ? $newThisWeek.' ta yangi bu hafta' : null];
            $cards[] = ['icon' => '🎓', 'title' => 'Ustozlar va mentorlar', 'description' => 'Faol xodimlar',
                'value' => (string) User::where('is_active', true)->whereHas('roles', fn ($q) => $q->whereIn('name', ['HEAD_TEACHER', 'MENTOR']))->count()];
        }

        if ($can('courses.view')) {
            $cards[] = ['icon' => '📚', 'title' => 'Kurslar', 'description' => 'Faol kurslar',
                'value' => (string) Course::where('status', 'published')->count()];
        }

        if ($isAdministration && $can('lessons.view')) {
            $cards[] = ['icon' => '▤', 'title' => 'Darslar', 'description' => 'E’lon qilingan darslar', 'value' => (string) Lesson::where('status', 'published')->count()];
        }

        if ($can('groups.view')) {
            $cards[] = ['icon' => '👥', 'title' => 'Guruhlar', 'description' => 'Faol guruhlar',
                'value' => (string) Group::where('status', 'active')->count()];
        }

        if ($can('submissions.review')) {
            $pending = Submission::whereIn('status', ['submitted', 'in_review', 'resubmit']);
            if (! $can('reports.view')) {
                $pending->whereHas('assignment.group', fn (Builder $groups): Builder => $groups->where('mentor_id', $user->id)->orWhere('teacher_id', $user->id));
            }
            $pendingCount = $pending->count();
            $cards[] = ['icon' => '🎤', 'title' => 'Tekshiruv kutmoqda', 'description' => 'Yangi topshirishlar',
                'value' => (string) $pendingCount,
                'trend' => $pendingCount > 0 ? 'tezkor tekshiruv kerak' : 'hammasi ko\'rib chiqilgan'];
        }

        if (! $isAdministration && $can('submissions.create')) {
            $cards[] = ['icon' => '📝', 'title' => 'Mening topshirishlarim', 'description' => 'Baholangan',
                'value' => (string) Submission::where('student_id', $user->id)->where('status', 'graded')->count()];
            $cards[] = ['icon' => '⭐', 'title' => "O'rtacha ball", 'description' => 'Baholangan ishlar',
                'value' => (string) (Submission::where('student_id', $user->id)->where('status', 'graded')->avg('final_score') ?? '—')];
        }

        $cards[] = ['icon' => '◉', 'title' => "O'qilmagan xabarlar", 'description' => 'Bildirishnomalar',
            'value' => (string) $user->appNotifications()->whereNull('read_at')->count()];

        $filters = $request->validate(['period' => ['nullable', 'in:week,month']]);
        $days = ($filters['period'] ?? 'week') === 'month' ? 30 : 7;
        $newUsers = $can('users.view') ? User::with('roles:id,label')->latest()->limit(4)->get(['id', 'name', 'created_at']) : collect();
        $registrationChart = [];
        if ($can('users.view')) {
            $byDate = User::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
            for ($offset = $days - 1; $offset >= 0; $offset--) {
                $date = now()->subDays($offset);
                $registrationChart[] = ['label' => $date->format('d.m'), 'total' => (int) ($byDate[$date->toDateString()] ?? 0)];
            }
        }

        return view('dashboard', compact('cards', 'user', 'isAdministration', 'newUsers', 'registrationChart') + $dashboard->forUser($user, $days));
    }

    public function logout(Request $request): RedirectResponse
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
