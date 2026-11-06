<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AI\Contracts\AIExplanationProvider;
use App\Services\AI\Contracts\SpeechRecognitionProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdministrationController extends Controller
{
    public function index(Request $request, string $section): View
    {
        abort_unless($request->user()->hasRole('SUPER_ADMIN', 'CENTER_ADMIN'), 403);
        abort_unless(in_array($section, ['users', 'roles', 'payments', 'ai', 'audit', 'settings'], true), 404);
        if (in_array($section, ['roles', 'audit', 'ai', 'settings'], true)) {
            abort_unless($request->user()->hasRole('SUPER_ADMIN'), 403);
        }
        if ($section === 'users') {
            abort_unless($request->user()->hasPermission('users.view'), 403);
        }
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', 'string', 'max:64']]);
        $users = $section === 'users' ? User::with('roles:id,name,label')
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->whereHas('roles', fn ($query) => $query->where('name', $role)))
            ->latest()->paginate(15)->withQueryString() : null;
        $roles = in_array($section, ['users', 'roles'], true) ? Role::with('permissions:id,name,label')->withCount('users')->orderBy('id')->get() : collect();
        $logs = $section === 'audit' ? DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->select('audit_logs.id', 'audit_logs.action', 'audit_logs.subject_type', 'audit_logs.subject_id', 'audit_logs.created_at', 'users.name')
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where('audit_logs.action', 'like', '%'.$search.'%'))
            ->orderByDesc('audit_logs.id')->paginate(20)->withQueryString() : null;
        $titles = ['users' => 'Foydalanuvchilar', 'roles' => 'Rollar va ruxsatlar', 'payments' => 'To‘lovlar', 'ai' => 'AI xizmatlari', 'audit' => 'Faoliyat jurnali', 'settings' => 'Tizim sozlamalari'];
        $speechConfigured = app(SpeechRecognitionProvider::class)->isConfigured();
        $aiProvider = app(AIExplanationProvider::class);
        $aiConfigured = $aiProvider->isConfigured();
        $aiModel = $aiProvider->model();
        $premiumUsers = $section === 'ai' ? User::with('roles:id,name,label')->where('ai_premium', true)->orderBy('name')->get() : collect();
        $studentUsers = $section === 'ai' ? User::whereHas('roles', fn ($query) => $query->where('name', 'STUDENT'))->orderBy('name')->get() : collect();

        return view('administration.index', compact('section', 'users', 'roles', 'logs', 'filters', 'speechConfigured', 'aiConfigured', 'aiModel', 'premiumUsers', 'studentUsers') + ['pageTitle' => $titles[$section]]);
    }

    public function chat(Request $request, AIExplanationProvider $provider): JsonResponse
    {
        abort_unless($request->user()->hasRole('CEO', 'SUPER_ADMIN', 'CENTER_ADMIN', 'HEAD_TEACHER', 'MENTOR', 'PARENT'), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        if (! $provider->isConfigured()) {
            return response()->json([
                'status' => 'unavailable',
                'message' => __('OpenRouter API kaliti sozlanmagan.'),
            ], 503);
        }

        try {
            $answer = $provider->explain($data['message'], [
                'Rol: '.$request->user()->roles->pluck('label')->join(', '),
                'Markaz boshqaruviga xizmat qiladigan xavfsiz va qisqa yordam ber.',
            ]);
        } catch (\Throwable) {
            return response()->json([
                'status' => 'unavailable',
                'message' => __('AI provayder hozir javob bera olmadi. Keyinroq qayta urinib ko‘ring.'),
            ], 502);
        }

        if (! is_string($answer) || trim($answer) === '') {
            return response()->json([
                'status' => 'unavailable',
                'message' => __('AI provayder javob qaytarmadi.'),
            ], 502);
        }

        return response()->json([
            'status' => 'completed',
            'answer' => $answer,
        ]);
    }

    public function setAiPremium(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->hasRole('CEO', 'SUPER_ADMIN', 'CENTER_ADMIN'), 403);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $user->update(['ai_premium' => $data['enabled']]);

        return response()->json([
            'status' => 'ok',
            'ai_premium' => $user->ai_premium,
        ]);
    }
}
