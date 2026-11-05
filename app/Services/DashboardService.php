<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    /** @return array{teachingGroups: Collection, chartDays: int, recentActivity: Collection, attendanceChart: list<array{date: string, label: string, pct: ?float, total: int}>, todaySchedules: Collection, canViewSchedules: bool} */
    public function forUser(User $user, int $days = 7): array
    {
        $permissions = $user->permissions();
        $canViewAll = $permissions->intersect(['reports.view', 'schedules.view', 'attendance.manage'])->isNotEmpty();
        $canViewChildren = $permissions->contains('children.view');
        $now = now(config('app.display_timezone', 'Asia/Tashkent'));
        $firstDate = $now->copy()->subDays($days - 1)->toDateString();
        $today = $now->toDateString();
        $groups = Group::query();
        if (! $canViewAll) {
            $groups->where(function (Builder $query) use ($user, $canViewChildren): void {
                $query->where('mentor_id', $user->id)->orWhere('teacher_id', $user->id)
                    ->orWhereHas('students', fn (Builder $students): Builder => $students->where('users.id', $user->id));
                if ($canViewChildren) {
                    $query->orWhereHas('students', fn (Builder $students): Builder => $students->whereIn('users.id', $user->children()->select('users.id')));
                }
            });
        }
        $teachingGroups = $user->hasRole('HEAD_TEACHER', 'MENTOR')
            ? (clone $groups)->with('mentor:id,name')->withCount('students')->orderBy('name')->limit(6)->get()
            : collect();
        $todaySchedules = Schedule::query()->with('group:id,name')
            ->whereIn('group_id', $groups->select('groups.id'))->whereDate('date', $today)
            ->orderBy('start_time')->limit(5)->get();

        $attendance = Attendance::query()->join('schedules', 'attendance.schedule_id', '=', 'schedules.id')
            ->whereDate('schedules.date', '>=', $firstDate)->whereDate('schedules.date', '<=', $today);
        if (! $canViewAll) {
            $attendance->where(function (Builder $query) use ($user, $canViewChildren): void {
                $query->where('attendance.student_id', $user->id)
                    ->orWhereHas('schedule.group', fn (Builder $groups): Builder => $groups->where('mentor_id', $user->id)->orWhere('teacher_id', $user->id));
                if ($canViewChildren) {
                    $query->orWhereIn('attendance.student_id', $user->children()->select('users.id'));
                }
            });
        }
        $byDate = $attendance->selectRaw('DATE(schedules.date) as day, count(*) as total, sum(case when attendance.status in (?, ?) then 1 else 0 end) as present', ['present', 'late'])
            ->groupByRaw('DATE(schedules.date)')->get()->keyBy('day');
        $attendanceChart = [];
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = $now->copy()->subDays($offset);
            $row = $byDate->get($date->toDateString());
            $total = (int) ($row?->total ?? 0);
            $attendanceChart[] = [
                'date' => $date->toDateString(), 'label' => $date->format('d.m'),
                'pct' => $total > 0 ? round($row->present / $total * 100, 1) : null, 'total' => $total,
            ];
        }

        return ['teachingGroups' => $teachingGroups, 'chartDays' => $days, 'recentActivity' => $user->appNotifications()->latest()->limit(4)->get(), 'attendanceChart' => $attendanceChart, 'todaySchedules' => $todaySchedules, 'canViewSchedules' => $permissions->contains('schedules.view')];
    }
}
