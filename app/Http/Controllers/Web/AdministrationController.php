<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AI\Contracts\SpeechRecognitionProvider;
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

        return view('administration.index', compact('section', 'users', 'roles', 'logs', 'filters', 'speechConfigured') + ['pageTitle' => $titles[$section]]);
    }
}
