<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Group;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssignmentManagementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('assignments.manage'), 403);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'published', 'closed'])],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);
        $assignments = Assignment::query()->with('group:id,name')
            ->withCount(['submissions', 'submissions as graded_count' => fn (Builder $query): Builder => $query->where('status', 'graded')])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['group_id'] ?? null, fn (Builder $query, int|string $groupId): Builder => $query->where('group_id', $groupId))
            ->latest('id')->paginate(15)->withQueryString();
        $groups = Group::orderBy('name')->get(['id', 'name', 'status']);

        return view('education.assignments', compact('assignments', 'groups', 'filters'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('assignments.manage'), 403);
        $data = $request->validate([
            'group_id' => ['required', 'integer', Rule::exists('groups', 'id')->where('status', 'active')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'surah_number' => ['nullable', 'integer', 'min:1', 'max:114', 'required_with:ayah_from,ayah_to'],
            'ayah_from' => ['nullable', 'integer', 'min:1', 'max:286', 'required_with:ayah_to'],
            'ayah_to' => ['nullable', 'integer', 'min:1', 'max:286', 'gte:ayah_from', 'required_with:ayah_from'],
            'due_at' => ['bail', 'nullable', 'date_format:Y-m-d\\TH:i', function (string $attribute, mixed $value, Closure $fail): void {
                if (CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $value, config('app.display_timezone'))->isPast()) {
                    $fail('Topshirish muddati kelajakdagi vaqt bo‘lishi kerak.');
                }
            }],
            'requires_audio' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
        $data['due_at'] = empty($data['due_at']) ? null : CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $data['due_at'], config('app.display_timezone'))->setTimezone(config('app.timezone'));
        Assignment::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('assignments.manage')->with('status', 'Topshiriq yaratildi.');
    }

    public function updateStatus(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('assignments.manage'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'closed'])]]);
        abort_if($assignment->status === 'draft' && $data['status'] === 'closed', 422);
        if ($data['status'] === 'published') {
            abort_unless($assignment->group->status === 'active', 422, 'Faqat faol guruhga topshiriq berish mumkin.');
        }
        $assignment->update(['status' => $data['status']]);

        return redirect()->route('assignments.manage')->with('status', 'Topshiriq holati yangilandi.');
    }
}
