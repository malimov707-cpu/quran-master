<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('schedules.view'), 403);
        $filters = $request->validate([
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $schedules = Schedule::query()->with('group:id,name')->withCount('attendance')
            ->when($filters['group_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('group_id', $id))
            ->when($filters['date'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('date', $date))
            ->orderByDesc('date')->orderBy('start_time')->paginate(15)->withQueryString();
        $groups = Group::orderBy('name')->get(['id', 'name', 'status']);
        $canCreate = $request->user()->hasPermission('schedules.manage');
        $canMark = $request->user()->hasPermission('attendance.manage');

        return view('education.schedules', compact('schedules', 'groups', 'filters', 'canCreate', 'canMark'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('schedules.manage'), 403);
        $data = $request->validate([
            'group_id' => ['required', 'integer', Rule::exists('groups', 'id')->where('status', 'active')],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:64'],
        ]);
        $data['start_time'] .= ':00';
        $data['end_time'] .= ':00';
        DB::transaction(function () use ($data): void {
            Group::whereKey($data['group_id'])->lockForUpdate()->firstOrFail();
            $overlaps = Schedule::where('group_id', $data['group_id'])->whereDate('date', $data['date'])
                ->whereTime('start_time', '<', $data['end_time'])->whereTime('end_time', '>', $data['start_time'])->exists();
            if ($overlaps) {
                throw ValidationException::withMessages(['start_time' => 'Bu vaqtda guruhning boshqa darsi bor.']);
            }
            Schedule::create($data);
        });

        return redirect()->route('education.schedules')->with('status', 'Dars jadvalga qo‘shildi.');
    }

    public function attendance(Request $request, Schedule $schedule): View
    {
        abort_unless($request->user()->hasPermission('attendance.manage'), 403);
        $schedule->load('group:id,name');
        $students = $schedule->group->students()->orderBy('name')->get(['users.id', 'users.name']);
        $attendance = $schedule->attendance()->get()->keyBy('student_id');
        $hasStarted = $this->hasStarted($schedule);

        return view('education.attendance', compact('schedule', 'students', 'attendance', 'hasStarted'));
    }

    public function saveAttendance(Request $request, Schedule $schedule): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('attendance.manage'), 403);
        abort_unless($this->hasStarted($schedule), 422, 'Davomat dars boshlangandan keyin kiritiladi.');
        $studentIds = $schedule->group->students()->pluck('users.id')->all();
        $data = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*' => ['required', 'array:student_id,status,note'],
            'records.*.student_id' => ['required', 'integer', 'distinct', Rule::in($studentIds)],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'records.*.note' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($data, $schedule, $request): void {
            foreach ($data['records'] as $record) {
                Attendance::updateOrCreate([
                    'schedule_id' => $schedule->id, 'student_id' => $record['student_id'],
                ], [
                    'status' => $record['status'], 'note' => $record['note'] ?? null, 'marked_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()->route('education.attendance', $schedule)->with('status', 'Davomat saqlandi.');
    }

    private function hasStarted(Schedule $schedule): bool
    {
        return CarbonImmutable::parse($schedule->date->format('Y-m-d').' '.$schedule->start_time, config('app.display_timezone'))->isPast();
    }
}
