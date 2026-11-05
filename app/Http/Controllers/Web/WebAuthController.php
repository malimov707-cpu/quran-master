<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\User;
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

    public function dashboard(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $cards = [
            ['icon' => '📚', 'title' => 'Kurslar', 'description' => 'Faol kurslar', 'value' => '—'],
            ['icon' => '👥', 'title' => 'Guruhlar', 'description' => 'Jami guruhlar', 'value' => '—'],
            ['icon' => '🎤', 'title' => 'Topshiriqlar', 'description' => 'Yangi topshiriqlar', 'value' => '—'],
        ];

        return view('dashboard', compact('cards'));
    }

    public function logout(Request $request): RedirectResponse
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
