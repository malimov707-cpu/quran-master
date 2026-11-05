<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\User;
use App\Services\Submissions\SubmissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    public function __construct(private SubmissionService $submissions) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('submissions.create'), 403);

        $assignments = Assignment::query()
            ->where('status', 'published')
            ->whereHas('group.students', fn (Builder $query): Builder => $query->where('users.id', $user->id))
            ->with(['group:id,name', 'submissions' => fn (HasMany $query): HasMany => $query
                ->where('student_id', $user->id)->orderByDesc('attempt')])
            ->orderBy('due_at')
            ->get();

        return response()->json($assignments);
    }

    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('submissions.create'), 403);
        abort_unless($assignment->group->students()->where('users.id', $user->id)->exists(), 403);
        abort_unless($assignment->status === 'published', 404);

        return response()->json($assignment->load(['group:id,name', 'submissions' => fn (HasMany $query): HasMany => $query
            ->where('student_id', $user->id)->orderByDesc('attempt')]));
    }

    public function audio(Request $request, Submission $submission, SubmissionFile $file): BinaryFileResponse|StreamedResponse
    {
        abort_unless($file->submission_id === $submission->id, 404);
        $user = $request->user();
        abort_unless($submission->student_id === $user->id || $user->hasPermission('submissions.review'), 403);
        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404);
        $headers = ['Content-Type' => $file->mime_type, 'Cache-Control' => 'private, no-store'];

        if (config("filesystems.disks.{$file->disk}.driver") === 'local') {
            return response()->file($disk->path($file->path), $headers);
        }

        return $disk->response($file->path, 'recitation', $headers);
    }

    /**
     * Topshiriq yaratish (HEAD_TEACHER / admin).
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('assignments.manage'), 403);

        $data = $request->validate([
            'group_id' => ['required', 'exists:groups,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'surah_number' => ['nullable', 'integer', 'min:1', 'max:114'],
            'ayah_from' => ['nullable', 'integer', 'min:1'],
            'ayah_to' => ['nullable', 'integer', 'min:1', 'gte:ayah_from'],
            'due_at' => ['nullable', 'date', 'after:now'],
            'requires_audio' => ['sometimes', 'boolean'],
        ]);

        $assignment = Assignment::create($data + [
            'created_by' => $request->user()->id,
            'status' => 'published',
        ]);

        return response()->json(['message' => 'Topshiriq yaratildi.', 'assignment' => $assignment], 201);
    }

    /**
     * O'quvchi topshiradi (multipart: audio fayllar).
     */
    public function submit(Request $request, Assignment $assignment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('submissions.create'), 403);

        $audioFiles = $request->file('audio', []);
        if (is_object($audioFiles)) {
            $audioFiles = [$audioFiles];
        }

        $submission = $this->submissions->submit($assignment, $user, [
            'student_note' => $request->input('student_note'),
        ], $audioFiles);

        return response()->json([
            'message' => 'Topshirish qabul qilindi.',
            'submission' => $submission->load('files'),
        ], 201);
    }

    /**
     * O'quvchining topshirish tarixi.
     */
    public function mySubmissions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('submissions.create'), 403);

        $submissions = Submission::with(['assignment:id,title,due_at,surah_number', 'files', 'reviews:id,submission_id,score,comment,action'])
            ->where('student_id', $user->id)
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json($submissions);
    }

    /**
     * Mentor kutayotgan topshirishlar.
     */
    public function pendingReviews(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('submissions.review'), 403);

        $submissions = Submission::query()
            ->whereIn('status', ['submitted', 'in_review', 'resubmit'])
            ->with(['assignment:id,title,surah_number,ayah_from,ayah_to', 'student:id,name', 'files'])
            ->orderBy('submitted_at')
            ->get();

        return response()->json($submissions);
    }

    /**
     * Mentor baho beradi / qayta topshirish talab qiladi.
     */
    public function review(Request $request, Submission $submission): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('submissions.review'), 403);

        $data = $request->validate([
            'score' => ['required_if:action,approve', 'nullable', 'integer', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string'],
            'action' => ['required', 'in:approve,resubmit'],
            'segments' => ['nullable', 'array', 'max:100'],
            'segments.*' => ['array:ayah_from,ayah_to,start_ms,end_ms,error_type,comment'],
            'segments.*.ayah_from' => ['nullable', 'integer', 'min:1', 'max:286', 'required_with:segments.*.ayah_to'],
            'segments.*.ayah_to' => ['nullable', 'integer', 'min:1', 'max:286', 'gte:segments.*.ayah_from'],
            'segments.*.start_ms' => ['nullable', 'numeric', 'min:0', 'required_with:segments.*.end_ms'],
            'segments.*.end_ms' => ['nullable', 'numeric', 'gt:segments.*.start_ms', 'required_with:segments.*.start_ms'],
            'segments.*.error_type' => ['required_with:segments', 'in:missing_word,wrong_word,repetition,wrong_order,pronunciation,tajweed_other'],
            'segments.*.comment' => ['required_with:segments', 'string', 'max:5000'],
        ]);

        $review = $this->submissions->review($submission, $user, $data, $data['segments'] ?? null);

        return response()->json(['message' => 'Tekshiruv saqlandi.', 'review' => $review->load('segments')], 201);
    }

    /**
     * Bitta topshirish to'liq ko'rinish (mentor uchun).
     */
    public function showSubmission(Request $request, Submission $submission): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isOwner = $submission->student_id === $user->id;
        abort_unless($isOwner || $user->hasPermission('submissions.review'), 403);

        return response()->json($submission->load(['assignment', 'student:id,name,email', 'files', 'reviews.mentor:id,name', 'reviews.segments']));
    }
}
