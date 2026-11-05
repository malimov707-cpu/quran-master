<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Services\Groups\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(private GroupService $groups) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('groups.view'), 403);

        $groups = Group::query()
            ->with(['course:id,name', 'teacher:id,name', 'mentor:id,name'])
            ->withCount('students')
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->paginate($request->integer('per_page', 15));

        return response()->json($groups);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('groups.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'course_id' => ['required', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'mentor_id' => ['nullable', 'exists:users,id'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $group = Group::create($data + ['status' => 'active']);

        return response()->json(['message' => 'Guruh yaratildi.', 'group' => $group], 201);
    }

    public function show(Request $request, Group $group): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('groups.view'), 403);

        return response()->json($group->load(['course', 'teacher:id,name', 'mentor:id,name', 'students:id,name,email']));
    }

    public function addStudent(Request $request, Group $group): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('groups.update'), 403);

        $data = $request->validate(['student_id' => ['required', 'exists:users,id']]);

        $student = User::findOrFail($data['student_id']);
        $this->groups->addStudent($group, $student);

        return response()->json(['message' => 'O\'quvchi guruhga qo\'shildi.']);
    }

    public function removeStudent(Request $request, Group $group, User $student): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('groups.update'), 403);

        $this->groups->removeStudent($group, $student);

        return response()->json(['message' => 'O\'quvchi guruhdan chiqarildi.']);
    }

    public function assignMentor(Request $request, Group $group): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('groups.update'), 403);

        $data = $request->validate(['mentor_id' => ['required', 'exists:users,id']]);

        $mentor = User::findOrFail($data['mentor_id']);
        $group = $this->groups->assignMentor($group, $mentor);

        return response()->json(['message' => 'Mentor tayinlandi.', 'group' => $group]);
    }

    /**
     * O'quvchilarni mentorlar orasida avtomatik taqsimlash.
     */
    public function autoDistribute(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('groups.update'), 403);

        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['exists:users,id'],
        ]);

        $students = User::whereIn('id', $data['student_ids'])->get();
        $distribution = $this->groups->autoDistribute($students->all());

        return response()->json([
            'message' => 'O\'quvchilar mentorlarga taqsimlandi.',
            'distribution' => $distribution,
        ]);
    }
}
