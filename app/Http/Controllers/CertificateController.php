<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\LessonQuiz;
use App\Services\CertificateRenderer;
use App\Services\CertificateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function __construct(private CertificateService $certificates) {}

    public function index(Request $request): JsonResponse|View
    {
        $user = $request->user();
        $canManage = $user->hasPermission('lessons.publish');
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $records = Certificate::query()->when(! $canManage, fn (Builder $query): Builder => $query->where('user_id', $user->id))
            ->orderByDesc('id')->paginate(15)->withQueryString();
        $courses = $user->hasRole('STUDENT') ? $this->certificates->enrolledCourses($user)->orderBy('name')->limit(50)->get(['id', 'name']) : collect();
        $exams = $user->hasRole('STUDENT') ? LessonQuiz::where('awards_certificate', true)->where('status', 'published')
            ->whereHas('lesson', fn (Builder $query): Builder => $query->where('status', 'published')->whereIn('course_id', $courses->pluck('id')))
            ->whereHas('attempts', fn (Builder $query): Builder => $query->where('user_id', $user->id)->where('passed', true)->whereColumn('quiz_attempts.revision', 'lesson_quizzes.revision'))
            ->orderBy('title')->limit(50)->get(['id', 'title']) : collect();
        if ($request->expectsJson()) {
            $records->through(fn (Certificate $certificate): array => $certificate->toArray() + ['verify_url' => $this->certificates->verificationUrl($certificate)]);
        }
        $data = ['certificates' => $records, 'courses' => $courses, 'exams' => $exams, 'can_manage' => $canManage];

        return $request->expectsJson() ? response()->json($data) : view('education.certificates', $data);
    }

    public function course(Request $request, Course $course): JsonResponse|View
    {
        $data = $this->certificates->courseEligibility($request->user(), $course);

        return $request->expectsJson() ? response()->json($data) : view('education.certificate-course', $data);
    }

    public function claimCourse(Request $request, Course $course): JsonResponse|RedirectResponse
    {
        $certificate = $this->certificates->claimCourse($request->user(), $course);

        return $this->claimed($request, $certificate);
    }

    public function claimExam(Request $request, LessonQuiz $quiz): JsonResponse|RedirectResponse
    {
        $certificate = $this->certificates->claimExam($request->user(), $quiz);

        return $this->claimed($request, $certificate);
    }

    private function claimed(Request $request, Certificate $certificate): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['certificate' => $certificate, 'verify_url' => $this->certificates->verificationUrl($certificate)])
            : redirect()->route('certificates.index')->with('status', 'Sertifikat tayyor. PDF yoki rasmni yuklab oling.');
    }

    public function download(Request $request, Certificate $certificate, CertificateRenderer $renderer): Response
    {
        abort_unless($certificate->user_id === $request->user()->id || $request->user()->hasPermission('lessons.publish'), 404);
        abort_if($certificate->revoked_at !== null, 410, 'Sertifikat bekor qilingan.');
        $format = $request->validate(['format' => ['required', 'in:pdf,post,story']])['format'];
        $bytes = $format === 'pdf' ? $renderer->pdf($certificate) : $renderer->png($certificate, $format);

        return response($bytes)->withHeaders([
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'image/png',
            'Content-Disposition' => 'attachment; filename="certificate-'.$certificate->code.'-'.$format.($format === 'pdf' ? '.pdf' : '.png').'"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verify(Request $request, string $code): JsonResponse|Response
    {
        $certificate = Certificate::where('code', $code)->firstOrFail();
        $data = ['certificate' => $certificate->only('code', 'type', 'recipient_name', 'title', 'score', 'issued_at', 'revoked_at'),
            'valid' => $certificate->revoked_at === null];

        return $request->expectsJson() ? response()->json($data)->header('Cache-Control', 'no-store')
            : response()->view('education.certificate-verify', $data)->header('Cache-Control', 'no-store');
    }

    public function revoke(Request $request, Certificate $certificate): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->hasPermission('lessons.publish'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        Certificate::whereKey($certificate->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id, 'revocation_reason' => $data['reason']]);

        return $request->expectsJson() ? response()->json(['message' => 'Sertifikat bekor qilindi.']) : back()->with('status', 'Sertifikat bekor qilindi.');
    }
}
