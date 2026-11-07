<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Submission;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\Groups\GroupService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdministrationController extends Controller
{
    private const SECTIONS = [
        'users' => ['Foydalanuvchilar', 'users.view', null, null],
        'courses' => ['Kurslar', 'courses.view', 'courses.create', 'courses.update'],
        'lessons' => ['Darslar', 'lessons.view', 'lessons.create', 'lessons.update'],
        'groups' => ['Guruhlar', 'groups.view', 'groups.create', 'groups.update'],
        'schedules' => ['Jadval va davomat', 'schedules.view', 'schedules.manage', 'schedules.manage'],
        'audit' => ['Faoliyat jurnali', null, null, null],
    ];

    public function overview(Request $request, DashboardService $dashboard): JsonResponse
    {
        $user = $request->user();
        $sections = [];
        foreach (self::SECTIONS as $key => [$title, $permission]) {
            if (($key === 'audit' && $user->hasRole('SUPER_ADMIN')) || ($permission && $user->hasPermission($permission))) {
                $sections[] = ['key' => $key, 'title' => $title] + $this->capabilities($user, $key);
            }
        }
        $canReport = $user->hasPermission('reports.view');
        $counts = $canReport ? ['users' => User::count(), 'groups' => Group::where('status', 'active')->count(), 'courses' => Course::where('status', 'published')->count(), 'pending_reviews' => Submission::whereIn('status', ['submitted', 'in_review'])->count()] : null;
        $data = $canReport ? $dashboard->forUser($user) : null;

        return response()->json(['sections' => $sections, 'counts' => $counts, 'attendance' => $data['attendanceChart'] ?? [], 'today_schedules' => $data['todaySchedules'] ?? [], 'can_report' => $canReport]);
    }

    public function index(Request $request, string $section): JsonResponse
    {
        $this->authorizeSection($request, $section);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'], 'role' => ['nullable', 'string', 'max:64'], 'role_id' => ['nullable', 'integer', 'exists:roles,id'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $query = match ($section) {
            'users' => User::select('id', 'name', 'email', 'is_active')->with('roles:id,name,label'),
            'courses' => Course::select('id', 'name', 'level', 'status')->withCount('lessons'),
            'lessons' => Lesson::select('id', 'course_id', 'title', 'status', 'sort_order')->with('course:id,name'),
            'groups' => Group::with(['course:id,name', 'mentor:id,name'])->withCount('students'),
            'schedules' => Schedule::with(['group:id,name', 'lesson:id,title']),
            'audit' => DB::table('audit_logs')->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')->select('audit_logs.id', 'audit_logs.action', 'audit_logs.subject_type', 'audit_logs.subject_id', 'audit_logs.created_at', 'users.name'),
        };
        if (! empty($filters['q'])) {
            $column = match ($section) {
                'lessons' => 'title', 'schedules' => 'room', 'audit' => 'audit_logs.action', default => 'name'
            };
            $query->where(function ($query) use ($column, $filters, $section): void {
                $query->where($column, 'like', '%'.$filters['q'].'%');
                if ($section === 'users') {
                    $query->orWhere('email', 'like', '%'.$filters['q'].'%');
                }
            });
        }
        if ($section === 'users' && ! empty($filters['role'])) {
            $query->whereHas('roles', fn ($query) => $query->where('name', $filters['role']));
        }
        if ($section === 'users' && ! empty($filters['role_id'])) {
            $query->whereHas('roles', fn ($query) => $query->where('roles.id', $filters['role_id']));
        }
        if ($section === 'schedules' && ! empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }
        $result = $query->orderByDesc($section === 'audit' ? 'audit_logs.id' : 'id')->paginate(15);

        return response()->json($result->toArray() + $this->capabilities($request->user(), $section));
    }

    public function show(Request $request, string $section, int $id): JsonResponse
    {
        $this->authorizeSection($request, $section);
        abort_if($section === 'audit', 404);
        if ($section === 'users') {
            abort_unless($request->user()->hasRole('SUPER_ADMIN'), 403);
        }
        $record = match ($section) {
            'users' => User::select('id', 'name', 'email', 'is_active')->with(['roles:id,name,label', 'children:id,name,email'])->findOrFail($id),
            'courses' => Course::findOrFail($id),
            'lessons' => Lesson::with('course:id,name')->findOrFail($id),
            'groups' => Group::with(['course:id,name', 'mentor:id,name', 'teacher:id,name', 'students:id,name,email'])->findOrFail($id),
            'schedules' => Schedule::with(['group:id,name,course_id', 'group.students:id,name', 'lesson:id,title', 'attendance'])->findOrFail($id),
        };
        $extra = $section === 'users' ? ['mentor_groups' => Group::where('mentor_id', $id)->get(['id', 'name']), 'parents' => User::whereHas('children', fn ($query) => $query->where('users.id', $id))->get(['id', 'name'])] : [];

        if ($section === 'schedules') {
            $extra['can_mark_attendance'] = $request->user()->hasPermission('attendance.manage') && CarbonImmutable::parse($record->date->format('Y-m-d').' '.$record->start_time, config('app.display_timezone', 'Asia/Tashkent'))->isPast();
        }

        return response()->json(['record' => $record] + $extra + $this->capabilities($request->user(), $section));
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['roles', 'users', 'courses', 'groups', 'lessons'])], 'role' => ['nullable', Rule::in(['MENTOR', 'HEAD_TEACHER', 'STUDENT', 'PARENT'])], 'q' => ['nullable', 'string', 'max:100'], 'course_id' => ['nullable', 'integer', 'exists:courses,id']]);
        $user = $request->user();
        $type = $data['type'];
        $permission = match ($type) {
            'roles' => 'users.view', 'users' => 'groups.update', 'courses' => 'courses.view', 'groups' => 'groups.view', 'lessons' => 'lessons.view'
        };
        abort_unless($user->hasPermission($permission) || ($type === 'users' && $user->hasRole('SUPER_ADMIN')), 403);
        $query = match ($type) {
            'roles' => Role::query(), 'users' => User::query(), 'courses' => Course::query(), 'groups' => Group::query(), 'lessons' => Lesson::query()
        };
        $column = $type === 'roles' ? 'label' : ($type === 'lessons' ? 'title' : 'name');
        $query->when($data['q'] ?? null, fn ($query, $value) => $query->where(function ($query) use ($column, $value, $type): void {
            $query->where($column, 'like', '%'.$value.'%');
            if ($type === 'users') {
                $query->orWhere('email', 'like', '%'.$value.'%');
            }
        }));
        if ($type === 'users') {
            $query->where('is_active', true)->when($data['role'] ?? null, fn ($query, $value) => $query->whereHas('roles', fn ($query) => $query->where('name', $value)));
        }
        if ($type === 'lessons') {
            $query->when($data['course_id'] ?? null, fn ($query, $value) => $query->where('course_id', $value));
        }
        if ($type === 'groups') {
            $query->with('mentor:id,name');
        }
        $columns = ['id', $column, ...($type === 'groups' ? ['mentor_id', 'course_id'] : []), ...($type === 'users' ? ['email'] : [])];
        $page = $query->orderBy($column)->paginate(15, $columns);
        $page->getCollection()->transform(fn ($row): array => ['id' => $row->id, 'label' => $row->{$column}, 'course_id' => $type === 'groups' ? $row->course_id : null, 'detail' => $type === 'groups' ? $row->mentor?->name : ($type === 'users' ? $row->email : null)]);

        return response()->json($page);
    }

    public function storeGroup(Request $request, GroupService $service): JsonResponse
    {
        abort_unless($request->user()->hasPermission('groups.create'), 403);
        $data = $this->groupData($request);
        $group = DB::transaction(function () use ($data, $service): Group {
            $group = Group::create(array_diff_key($data, ['mentor_id' => true]) + ['status' => 'active']);
            if (! empty($data['mentor_id'])) {
                $service->assignMentor($group, User::findOrFail($data['mentor_id']));
            }

            return $group;
        });

        return response()->json(['record' => $group, 'message' => __('Guruh yaratildi.')], 201);
    }

    public function updateGroup(Request $request, Group $group, GroupService $service): JsonResponse
    {
        abort_unless($request->user()->hasPermission('groups.update'), 403);
        $data = $this->groupData($request, $group);
        DB::transaction(function () use ($data, $group, $service): void {
            $locked = Group::whereKey($group->id)->lockForUpdate()->firstOrFail();
            if ($data['capacity'] < $locked->students()->count()) {
                throw ValidationException::withMessages(['capacity' => __('Sig‘im mavjud o‘quvchilar sonidan kam bo‘la olmaydi.')]);
            }
            $mentor = $data['mentor_id'] ?? null;
            $locked->update(array_diff_key($data, ['mentor_id' => true]));
            if ($mentor && (int) $mentor !== $locked->mentor_id) {
                $service->assignMentor($locked, User::findOrFail($mentor));
            } elseif (! $mentor && $locked->mentor_id) {
                DB::table('mentor_assignments')->where('group_id', $locked->id)->where('mentor_id', $locked->mentor_id)->whereNull('ended_at')->update(['ended_at' => now(), 'updated_at' => now()]);
                $locked->update(['mentor_id' => null]);
            }
        });

        return response()->json(['record' => $group->fresh(), 'message' => __('Guruh yangilandi.')]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        return $this->saveSchedule($request, null);
    }

    public function updateSchedule(Request $request, Schedule $schedule): JsonResponse
    {
        return $this->saveSchedule($request, $schedule);
    }

    private function saveSchedule(Request $request, ?Schedule $schedule): JsonResponse
    {
        abort_unless($request->user()->hasPermission('schedules.manage'), 403);
        $data = $request->validate(['group_id' => ['required', 'integer', Rule::exists('groups', 'id')->where('status', 'active')], 'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'], 'date' => ['required', 'date_format:Y-m-d'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'room' => ['nullable', 'string', 'max:64']]);
        $saved = DB::transaction(function () use ($data, $schedule): Schedule {
            $group = Group::whereKey($data['group_id'])->lockForUpdate()->firstOrFail();
            if (! empty($data['lesson_id']) && ! Lesson::whereKey($data['lesson_id'])->where('course_id', $group->course_id)->exists()) {
                throw ValidationException::withMessages(['lesson_id' => __('Dars tanlangan guruh kursiga tegishli bo‘lishi kerak.')]);
            }
            $overlap = Schedule::where('group_id', $group->id)->whereDate('date', $data['date'])->when($schedule, fn ($query) => $query->whereKeyNot($schedule->id))
                ->whereTime('start_time', '<', $data['end_time'].':00')->whereTime('end_time', '>', $data['start_time'].':00')->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['start_time' => __('Bu vaqtda guruhning boshqa darsi bor.')]);
            }
            if ($schedule) {
                if ($schedule->group_id !== $group->id && $schedule->attendance()->exists()) {
                    throw ValidationException::withMessages(['group_id' => __('Davomat kiritilgan jadvalning guruhini o‘zgartirib bo‘lmaydi.')]);
                }
                $schedule->update($data);

                return $schedule->fresh();
            }

            return Schedule::create($data);
        });

        return response()->json(['record' => $saved, 'message' => __('Jadval saqlandi.')], $schedule ? 200 : 201);
    }

    /** @return array<string, mixed> */
    private function groupData(Request $request, ?Group $group = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:128'], 'course_id' => ['required', 'integer', 'exists:courses,id', ...($group ? [Rule::in([$group->course_id])] : [])],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'], 'status' => ['sometimes', Rule::in(['active', 'completed', 'archived'])],
            'mentor_id' => ['bail', 'nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true), function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && ! User::find($value)?->roles()->where('name', 'MENTOR')->exists()) {
                    $fail(__('Foydalanuvchi mentor emas.'));
                }
            }]]);
    }

    private function authorizeSection(Request $request, string $section): void
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);
        if ($section === 'audit') {
            abort_unless($request->user()->hasRole('SUPER_ADMIN'), 403);
        } else {
            abort_unless($request->user()->hasPermission(self::SECTIONS[$section][1]), 403);
        }
    }

    /** @return array{can_create: bool, can_edit: bool} */
    private function capabilities(User $user, string $section): array
    {
        return ['can_create' => $section === 'users' ? $user->hasRole('SUPER_ADMIN') : (self::SECTIONS[$section][2] !== null && $user->hasPermission(self::SECTIONS[$section][2])),
            'can_edit' => $section === 'users' ? $user->hasRole('SUPER_ADMIN') : (self::SECTIONS[$section][3] !== null && $user->hasPermission(self::SECTIONS[$section][3]))];
    }
}
