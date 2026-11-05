<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('schedules.view'), 403);

        $schedules = Schedule::query()
            ->with(['group:id,name', 'lesson:id,title'])
            ->when($request->filled('group_id'), fn ($query) => $query->where('group_id', $request->integer('group_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('date', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('date', '<=', $request->string('to')))
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate($request->integer('per_page', 15));

        return response()->json($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('schedules.manage'), 403);

        $data = $request->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:64'],
        ]);

        $schedule = Schedule::create($data);

        return response()->json(['message' => 'Dars jadvali yaratildi.', 'schedule' => $schedule], 201);
    }

    public function attendance(Request $request, Schedule $schedule): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('attendance.manage'), 403);

        $data = $request->validate([
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'exists:users,id'],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
            'records.*.note' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($data['records'] as $record) {
            Attendance::updateOrCreate(
                ['schedule_id' => $schedule->id, 'student_id' => $record['student_id']],
                ['status' => $record['status'], 'note' => $record['note'] ?? null, 'marked_by' => $request->user()->id],
            );
        }

        return response()->json([
            'message' => 'Davomat saqlandi.',
            'attendance' => $schedule->attendance()->with('student:id,name')->get(),
        ]);
    }
}
