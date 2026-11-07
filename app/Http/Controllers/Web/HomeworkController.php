<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Submission;
use App\Services\Submissions\SubmissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HomeworkController extends Controller
{
    private const STATUS_LABELS = [
        'submitted' => 'Tekshirish kutilmoqda',
        'in_review' => 'Tekshirilmoqda',
        'graded' => 'Baholangan',
        'resubmit' => 'Qayta topshirish kerak',
    ];

    public function __construct(private SubmissionService $submissions) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('submissions.create'), 403);
        $assignments = Assignment::query()
            ->where('status', 'published')
            ->whereHas('group.students', fn (Builder $query): Builder => $query->where('users.id', $user->id))
            ->with('group:id,name')->orderBy('due_at')->get();
        $submissions = Submission::query()->where('student_id', $user->id)
            ->with('assignment:id,title')->latest('submitted_at')->orderByDesc('id')->paginate(20);
        $statusLabels = self::STATUS_LABELS;

        return view('homework.index', compact('assignments', 'submissions', 'statusLabels'));
    }

    public function show(Request $request, Assignment $assignment): View
    {
        abort_unless($request->user()->hasPermission('submissions.create'), 403);
        abort_unless($assignment->group->students()->where('users.id', $request->user()->id)->exists(), 404);
        abort_if($assignment->status === 'draft', 404);
        $assignment->load('group:id,name');
        $submissions = $assignment->submissions()->where('student_id', $request->user()->id)->orderByDesc('attempt')->get();
        $statusLabels = self::STATUS_LABELS;

        $practiced = DB::table('assignment_practices')->where('user_id', $request->user()->id)->where('assignment_id', $assignment->id)->exists();

        return view('homework.show', compact('assignment', 'submissions', 'statusLabels', 'practiced'));
    }

    public function submit(Request $request, Assignment $assignment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('submissions.create'), 403);
        abort_unless($assignment->group->students()->where('users.id', $user->id)->exists(), 404);
        $data = $request->validate([
            'student_note' => ['nullable', 'string', 'max:5000'],
            'audio' => [Rule::requiredIf($assignment->requires_audio), 'nullable', 'file', 'max:20480', 'mimetypes:audio/mpeg,audio/mp4,audio/aac,audio/ogg,audio/wav,audio/webm,audio/x-m4a,audio/m4a'],
        ]);
        $audio = $request->file('audio');
        $submission = $this->submissions->submit($assignment, $user, [
            'student_note' => $data['student_note'] ?? null,
        ], $audio ? [$audio] : []);

        return redirect()->route('homework.submission', $submission)->with('status', 'Topshirish qabul qilindi. Natijani shu sahifada kuzating.');
    }

    public function submission(Request $request, Submission $submission): View
    {
        abort_unless($submission->student_id === $request->user()->id || $request->user()->hasPermission('submissions.review'), 404);
        $submission->load(['assignment', 'files', 'reviews.mentor:id,name', 'reviews.segments', 'aiReviews']);
        $aiReview = $submission->aiReviews->sortByDesc('id')->first();
        $statusLabels = self::STATUS_LABELS;
        $canResubmit = $submission->student_id === $request->user()->id
            && $submission->status === 'resubmit' && $submission->assignment->status === 'published'
            && $submission->assignment->group->students()->where('users.id', $request->user()->id)->exists();

        $canAcknowledge = $submission->student_id === $request->user()->id && $request->user()->hasRole('STUDENT') && $request->user()->hasPermission('progress.view') && in_array($submission->status, ['graded', 'resubmit'], true) && $submission->reviews->isNotEmpty();

        return view('homework.submission', compact('submission', 'aiReview', 'statusLabels', 'canResubmit', 'canAcknowledge'));
    }
}
