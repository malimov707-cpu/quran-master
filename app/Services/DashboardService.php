<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Student KPI ko'rsatkichlari — barchasi real DB ma'lumotlaridan.
     *
     * @return array{ayahs: int, minutes: int, streak: int, achievements: int, progressSeries: list<array{label: string, pct: float|int}>}
     */
    public function studentKpis(User $user, int $days = 30): array
    {
        $progress = $user->studentProgress();

        $ayahs = (int) (clone $progress)->get()->sum(fn ($row): int => max($row->ayah_to - $row->ayah_from + 1, 0) * max($row->lessons_completed, $row->status === 'completed' ? 1 : 0));
        $submissionsCount = (int) (clone $progress)->sum('submissions_count');
        $avgScore = (float) ((clone $progress)->avg('average_score') ?? 0);

        // Ketma-ket kunlar: submission/attendance sanalari bo'yicha streak
        $activeDates = collect([
            ...Submission::where('student_id', $user->id)->pluck('created_at'),
            ...Attendance::whereIn('student_id', [$user->id])->where('status', 'present')->with('schedule:id,date')->get()->map(fn (Attendance $a) => $a->schedule?->date),
        ])->filter()->map(fn ($date) => substr((string) $date, 0, 10))->unique()->sort()->values();

        $streak = 0;
        $cursor = now()->startOfDay();
        $datesByDay = $activeDates->flip();
        while ($datesByDay->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        // Bugungi kunlik maqsad: bugun topshirilgan audio (5 daq) + davomat (10 daq) + o'qilgan oyat
        $minutesToday = Submission::where('student_id', $user->id)->whereDate('created_at', today())->count() * 5
            + Attendance::where('student_id', $user->id)->where('status', 'present')->whereDate('created_at', today())->count() * 10;

        // Rivojlanish grafigi: haftalar bo'yicha o'rtacha ball
        $since = now()->subDays($days)->startOfDay();
        $rows = (clone $progress)->where('updated_at', '>=', $since)->orderBy('updated_at')->get(['updated_at', 'average_score', 'submissions_count']);
        $progressSeries = [];
        $buckets = max(intdiv($days, 7), 1);
        for ($week = $buckets - 1; $week >= 0; $week--) {
            $from = now()->subWeeks($week)->startOfWeek();
            $to = (clone $from)->endOfWeek();
            $weekRows = $rows->filter(fn ($row) => $row->updated_at->between($from, $to));
            $score = $weekRows->isEmpty() ? null : round($weekRows->avg(fn ($row) => (float) ($row->average_score ?? 0)), 0);
            $progressSeries[] = ['label' => $from->format('d.m'), 'pct' => $score !== null && $score > 0 ? min((int) $score, 100) : ($week === 0 ? 0 : null)];
        }

        // Yutuqlar: to'liq o'zlashtirilgan progress qatorlari (status=completed)
        $achievements = (int) ((clone $progress)->where('status', 'completed')->count() + (clone $progress)->where('average_score', '>=', 90)->count());

        return [
            'ayahs' => $ayahs + $submissionsCount,
            'minutes' => $minutesToday,
            'streak' => $streak,
            'achievements' => $achievements,
            'average' => $avgScore > 0 ? round($avgScore) : null,
            'progressSeries' => $progressSeries,
        ];
    }

    /**
     * O'quvchining davom etayotgan kurslari (real progress bilan).
     */
    public function studentCourses(User $user, int $limit = 3): Collection
    {
        return Course::query()
            ->whereIn('id', Group::query()
                ->select('course_id')
                ->whereHas('students', fn (Builder $students): Builder => $students->where('users.id', $user->id)))
            ->withCount(['lessons'])
            ->limit($limit)
            ->get()
            ->map(function (Course $course) use ($user): Course {
                $lessonIds = $course->lessons()->pluck('id');
                $completed = DB::table('lesson_completions')->whereIn('lesson_id', $lessonIds)->where('user_id', $user->id)->count();
                $total = max($lessonIds->count(), 1);
                $course->progress_pct = (int) round($completed / $total * 100);
                $course->completed_lessons = $completed;

                return $course;
            });
    }
}
