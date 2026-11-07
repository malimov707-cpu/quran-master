<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Services\Groups\GroupService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->is_active && $request->user()->hasRole('SUPER_ADMIN'), 403);
    }

    public function create(Request $request): View
    {
        $this->authorizeManager($request);

        return view('administration.user-create', ['roles' => Role::orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeManager($request);
        $request->merge(['email' => is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : $request->input('email')]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (User::whereRaw('LOWER(email) = ?', [$value])->exists()) {
                    $fail(__('Bu email allaqachon ro‘yxatdan o‘tgan.'));
                }
            }],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen($value) > 72) {
                    $fail(__('Parol juda uzun. Qisqaroq parol kiriting.'));
                }
            }],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'distinct', 'exists:roles,id'],
        ]);
        $user = DB::transaction(function () use ($request, $data): User {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'is_active' => true, 'locale' => 'uz', 'ai_premium' => false]);
            $user->roles()->sync($data['role_ids']);
            $this->audit($request, 'user.created', $user, ['role_ids' => $data['role_ids']]);

            return $user;
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Foydalanuvchi yaratildi.'), 'record' => $user->load('roles:id,name,label')], 201);
        }

        return redirect()->route('administration.users.edit', $user)->with('status', __('Foydalanuvchi yaratildi. Endi kerakli bog‘lanishlarni belgilang.'));
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeManager($request);
        $user->load(['roles', 'children:id,name,email']);
        $roles = Role::orderBy('id')->get();
        $groups = Group::with('mentor:id,name')->orderBy('name')->get();
        $students = User::whereHas('roles', fn ($query) => $query->where('name', 'STUDENT'))->whereKeyNot($user->id)->orderBy('name')->get(['id', 'name', 'email', 'is_active']);
        $parents = User::whereHas('children', fn ($query) => $query->where('users.id', $user->id))->orderBy('name')->get(['id', 'name']);

        return view('administration.user-edit', compact('user', 'roles', 'groups', 'students', 'parents'));
    }

    public function roles(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeManager($request);
        abort_if($user->id === $request->user()->id, 403, __('O‘z rolingizni bu yerdan o‘zgartira olmaysiz.'));
        $data = $request->validate(['role_ids' => ['required', 'array', 'min:1'], 'role_ids.*' => ['required', 'integer', 'distinct', 'exists:roles,id']]);
        DB::transaction(function () use ($request, $user, $data): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $names = Role::whereIn('id', $data['role_ids'])->pluck('name');
            if ((! $names->contains('MENTOR') && Group::where('mentor_id', $user->id)->exists())
                || (! $names->contains('PARENT') && $locked->children()->exists())
                || (! $names->contains('STUDENT') && (DB::table('group_students')->where('student_id', $user->id)->whereNull('left_at')->exists() || DB::table('parent_student_links')->where('student_id', $user->id)->exists()))
                || (! $names->contains('HEAD_TEACHER') && ! $names->contains('MENTOR') && Group::where('teacher_id', $user->id)->exists())) {
                throw ValidationException::withMessages(['role_ids' => __('Avval olib tashlanadigan rolga tegishli guruh yoki oila bog‘lanishlarini uzing.')]);
            }
            $before = $locked->roles()->pluck('roles.id')->sort()->values()->all();
            $after = collect($data['role_ids'])->map(fn ($id): int => (int) $id)->sort()->values()->all();
            if ($before === $after) {
                return;
            }
            $locked->roles()->sync($after);
            $locked->tokens()->delete();
            DB::table('sessions')->where('user_id', $locked->id)->delete();
            $locked->forceFill(['remember_token' => Str::random(60)])->save();
            $this->audit($request, 'user.roles.updated', $locked, ['before' => $before, 'after' => $after]);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Rollar saqlandi. Foydalanuvchi qayta tizimga kirishi kerak.')]);
        }

        return back()->with('status', __('Rollar saqlandi. Foydalanuvchi qayta tizimga kirishi kerak.'));
    }

    public function children(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeManager($request);
        abort_unless($user->roles()->where('name', 'PARENT')->exists(), 422);
        $request->merge(['student_ids' => $request->input('student_ids', [])]);
        $data = $request->validate(['student_ids' => ['present', 'array'], 'student_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id']]);
        DB::transaction(function () use ($request, $user, $data): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->roles()->where('name', 'PARENT')->exists(), 422);
            $valid = User::whereIn('id', $data['student_ids'])->whereKeyNot($user->id)->whereHas('roles', fn ($query) => $query->where('name', 'STUDENT'))->count();
            if ($valid !== count($data['student_ids'])) {
                throw ValidationException::withMessages(['student_ids' => __('Farzand sifatida boshqa o‘quvchi akkauntini tanlang.')]);
            }
            $before = $locked->children()->pluck('users.id')->all();
            $locked->children()->sync($data['student_ids']);
            $this->audit($request, 'user.children.updated', $locked, ['before' => $before, 'after' => $data['student_ids']]);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Ota-ona va farzand bog‘lanishlari saqlandi.')]);
        }

        return back()->with('status', __('Ota-ona va farzand bog‘lanishlari saqlandi.'));
    }

    public function mentorGroups(Request $request, User $user, GroupService $service): JsonResponse|RedirectResponse
    {
        $this->authorizeManager($request);
        abort_unless($user->roles()->where('name', 'MENTOR')->exists(), 422);
        $request->merge(['group_ids' => $request->input('group_ids', [])]);
        $data = $request->validate(['group_ids' => ['present', 'array'], 'group_ids.*' => ['required', 'integer', 'distinct', 'exists:groups,id']]);
        DB::transaction(function () use ($request, $user, $data, $service): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->roles()->where('name', 'MENTOR')->exists(), 422);
            $groups = Group::where(fn ($query) => $query->whereIn('id', $data['group_ids'])->orWhere('mentor_id', $user->id))->orderBy('id')->lockForUpdate()->get();
            foreach ($groups as $group) {
                if (in_array($group->id, array_map('intval', $data['group_ids']), true)) {
                    if ($group->mentor_id !== $user->id) {
                        if ($group->status !== 'active' || ! $locked->is_active) {
                            throw ValidationException::withMessages(['group_ids' => __('Yangi biriktirish uchun faol mentor va faol guruh kerak.')]);
                        }
                        $service->assignMentor($group, $user);
                    }
                } elseif ($group->mentor_id === $user->id) {
                    $group->update(['mentor_id' => null]);
                    DB::table('mentor_assignments')->where('group_id', $group->id)->where('mentor_id', $user->id)->whereNull('ended_at')->update(['ended_at' => now(), 'updated_at' => now()]);
                }
            }
            $this->audit($request, 'user.mentor_groups.updated', $user, ['group_ids' => $data['group_ids']]);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Mentorning guruhlari saqlandi.')]);
        }

        return back()->with('status', __('Mentorning guruhlari saqlandi.'));
    }

    /** @param array<string, mixed> $properties */
    private function audit(Request $request, string $action, User $user, array $properties): void
    {
        DB::table('audit_logs')->insert(['user_id' => $request->user()->id, 'action' => $action, 'subject_type' => User::class, 'subject_id' => $user->id,
            'properties' => json_encode($properties, JSON_THROW_ON_ERROR), 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}
