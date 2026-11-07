<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\Progress\StudentJourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        abort_unless($request->user()?->hasPermission('lessons.view'), 403);

        $lessons = Lesson::query()
            ->with(['materials', 'course:id,name'])
            ->when(! $request->user()->hasPermission('lessons.create'), fn ($query) => $query->where('status', 'published'))
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 15));

        return JsonResource::collection($lessons);
    }

    public function show(Request $request, Lesson $lesson): JsonResource
    {
        abort_unless($request->user()?->hasPermission('lessons.view'), 403);

        abort_unless($lesson->status === 'published' || $request->user()->hasPermission('lessons.update'), 404);

        $user = $request->user();
        $canComplete = $user->hasRole('STUDENT') && $user->hasPermission('progress.view')
            && app(StudentJourneyService::class)->eligibleLesson($user, $lesson);

        return (new JsonResource($lesson->load(['course:id,name', 'materials'])))->additional([
            'journey' => ['can_complete' => $canComplete, 'completed' => $canComplete && DB::table('lesson_completions')->where('user_id', $user->id)->where('lesson_id', $lesson->id)->exists()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('lessons.create'), 403);

        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'course_module_id' => ['nullable', 'integer', 'exists:course_modules,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'learning_objectives' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'homework' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:draft,published,archived'],
        ]);

        $lesson = Lesson::create($data + ['created_by' => $request->user()->id]);

        return response()->json(['message' => 'Dars yaratildi.', 'lesson' => $lesson], 201);
    }
}
