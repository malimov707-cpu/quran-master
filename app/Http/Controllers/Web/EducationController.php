<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Course;
use App\Models\Group;
use App\Models\User;
use App\Services\Courses\CourseService;
use App\Services\Groups\GroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Education management web UI (admin / bosh ustoz uchun).
 */
class EducationController extends BaseController
{
    public function __construct(private CourseService $courses) {}

    public function coursesIndex(Request $request): View
    {
        $courses = Course::with('headTeacher:id,name')->withCount(['groups', 'lessons'])->orderByDesc('created_at')->paginate(12);

        $canViewLessons = $request->user()->hasPermission('lessons.view');

        return view('education.courses', compact('courses', 'canViewLessons'));
    }

    public function coursesStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'in:beginner,intermediate,advanced'],
            'description' => ['nullable', 'string'],
            'duration_weeks' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->courses->create($data + ['status' => 'draft']);

        return back()->with('status', 'Kurs yaratildi.');
    }

    public function groupsIndex(): View
    {
        $groups = Group::with(['course:id,name', 'teacher:id,name', 'mentor:id,name'])
            ->withCount('students')
            ->orderBy('name')
            ->paginate(12);

        $courses = Course::where('status', '!=', 'archived')->pluck('name', 'id');
        $mentors = User::whereHas('roles', fn ($q) => $q->where('name', 'MENTOR'))->pluck('name', 'id');
        $students = User::whereHas('roles', fn ($q) => $q->where('name', 'STUDENT'))->orderBy('name')->get(['id', 'name']);

        return view('education.groups', compact('groups', 'courses', 'mentors', 'students'));
    }

    public function groupsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'course_id' => ['required', 'exists:courses,id'],
            'mentor_id' => ['nullable', 'exists:users,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        Group::create($data + ['status' => 'active']);

        return back()->with('status', 'Guruh yaratildi.');
    }

    public function groupsAddStudent(Request $request, Group $group): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', 'exists:users,id']]);

        app(GroupService::class)->addStudent($group, User::findOrFail($data['student_id']));

        return back()->with('status', 'O\'quvchi qo\'shildi.');
    }

    public function groupsAddStudentFromSelect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'student_id' => ['required', 'exists:users,id'],
        ]);

        $group = Group::findOrFail($data['group_id']);
        app(GroupService::class)->addStudent($group, User::findOrFail($data['student_id']));

        return back()->with('status', 'O\'quvchi qo\'shildi.');
    }

    public function groupsAssignMentor(Request $request, Group $group): RedirectResponse
    {
        $data = $request->validate(['mentor_id' => ['required', 'exists:users,id']]);

        app(GroupService::class)->assignMentor($group, User::findOrFail($data['mentor_id']));

        return back()->with('status', 'Mentor tayinlandi.');
    }
}
