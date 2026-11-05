<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Courses\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CourseController extends Controller
{
    public function __construct(private CourseService $courses) {}

    /**
     * Kurslar ro'yxati (pagination, filtr, qidiruv).
     */
    public function index(Request $request): ResourceCollection
    {
        $this->authorizePermission($request, 'courses.view');

        $courses = $this->courses->list($request->only(['status', 'level', 'search', 'per_page']));

        return JsonResource::collection($courses);
    }

    /**
     * Yangi kurs yaratish.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'courses.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'level' => ['required', 'in:beginner,intermediate,advanced'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:104'],
            'status' => ['sometimes', 'in:draft,published,archived'],
        ]);

        $course = $this->courses->create($data);

        return response()->json([
            'message' => 'Kurs yaratildi.',
            'course' => $course,
        ], 201);
    }

    /**
     * Bitta kurs.
     */
    public function show(Request $request, Course $course): JsonResource
    {
        $this->authorizePermission($request, 'courses.view');

        return new JsonResource($course->load(['headTeacher:id,name', 'modules', 'lessons']));
    }

    /**
     * Kursni yangilash.
     */
    public function update(Request $request, Course $course): JsonResponse
    {
        $this->authorizePermission($request, 'courses.update');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'level' => ['sometimes', 'in:beginner,intermediate,advanced'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:104'],
            'status' => ['sometimes', 'in:draft,published,archived'],
        ]);

        $course = $this->courses->update($course, $data);

        return response()->json(['message' => 'Kurs yangilandi.', 'course' => $course]);
    }

    /**
     * Kursni arxivlash.
     */
    public function archive(Request $request, Course $course): JsonResponse
    {
        $this->authorizePermission($request, 'courses.archive');

        $course = $this->courses->archive($course);

        return response()->json(['message' => 'Kurs arxivlandi.', 'course' => $course]);
    }

    /**
     * Backend darajasida permission tekshiruvi.
     */
    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403, "Ruxsat yo'q: {$permission}");
    }
}
