<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LessonWebController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('lessons.view'), 403);
        $canManage = $request->user()->hasPermission('lessons.create');
        $filters = $request->validate(['course_id' => ['nullable', 'integer', 'exists:courses,id']]);
        $lessons = Lesson::with('course:id,name')->when(! $canManage, fn (Builder $query): Builder => $query->where('status', 'published'))
            ->when($filters['course_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('course_id', $id))
            ->orderBy('course_id')->orderBy('sort_order')->paginate(15)->withQueryString();
        $courses = Course::where('status', '!=', 'archived')->orderBy('name')->get(['id', 'name']);

        return view('education.lessons', compact('lessons', 'courses', 'canManage', 'filters'));
    }

    public function show(Request $request, Lesson $lesson): View
    {
        abort_unless($request->user()->hasPermission('lessons.view'), 403);
        $canUpdate = $request->user()->hasPermission('lessons.update');
        abort_unless($lesson->status === 'published' || $canUpdate, 404);
        $lesson->load(['course:id,name', 'materials']);

        return view('education.lesson', compact('lesson', 'canUpdate'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('lessons.create'), 403);
        $data = $request->validate($this->rules() + ['course_id' => ['required', 'integer', 'exists:courses,id']]);
        $lesson = Lesson::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('education.lessons.show', $lesson)->with('status', 'Dars yaratildi.');
    }

    public function update(Request $request, Lesson $lesson): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('lessons.update'), 403);
        $lesson->update($request->validate($this->rules()));

        return redirect()->route('education.lessons.show', $lesson)->with('status', 'Dars yangilandi.');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:30000'],
            'learning_objectives' => ['nullable', 'string', 'max:5000'], 'homework' => ['nullable', 'string', 'max:10000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ];
    }
}
