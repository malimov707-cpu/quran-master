<?php

namespace App\Services\Progress;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Individual progress — FAQAT real saqlangan ma'lumotlardan hisoblanadi
 * (baholangan topshirishlar, davomat). Soxta ko'rsatkichlar yo'q.
 */
class ProgressService
{
    /**
     * O'quvchi uchun to'liq progress ko'rinishi.
     *
     * @return array<string, mixed>
     */
    public function forStudent(User $student): array
    {
        $graded = Submission::query()
            ->where('submissions.student_id', $student->id)
            ->where('submissions.status', 'graded')
            ->whereNotNull('submissions.final_score');

        $averageScore = (clone $graded)->avg('final_score');

        $bySurah = (clone $graded)
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->whereNotNull('assignments.surah_number')
            ->groupBy('assignments.surah_number')
            ->selectRaw('assignments.surah_number, count(*) as submissions_count, avg(submissions.final_score) as average_score')
            ->get();

        // Takrorlanadigan xatolar: mentor segmentlaridan eng ko'p uchragan error turlari
        $recurringErrors = DB::table('mentor_review_segments')
            ->join('mentor_reviews', 'mentor_reviews.id', '=', 'mentor_review_segments.mentor_review_id')
            ->join('submissions', 'submissions.id', '=', 'mentor_reviews.submission_id')
            ->where('submissions.student_id', $student->id)
            ->groupBy('mentor_review_segments.error_type')
            ->selectRaw('mentor_review_segments.error_type, count(*) as count')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Haftalik progress (oxirgi 4 hafta baholangan topshirishlar)
        $weekly = (clone $graded)
            ->where('submissions.submitted_at', '>=', now()->subWeeks(4))
            ->selectRaw('strftime("%Y-W%W", submissions.submitted_at) as week, avg(submissions.final_score) as avg_score, count(*) as count')
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        return [
            'submissions_total' => Submission::where('student_id', $student->id)->count(),
            'submissions_graded' => (clone $graded)->count(),
            'average_score' => $averageScore !== null ? round($averageScore, 1) : null,
            'surahs' => $bySurah->map(fn ($r) => [
                'surah_number' => (int) $r->surah_number,
                'submissions_count' => (int) $r->submissions_count,
                'average_score' => round($r->average_score, 1),
            ]),
            'recurring_errors' => $recurringErrors->map(fn ($r) => [
                'error_type' => $r->error_type,
                'count' => (int) $r->count,
            ]),
            'weekly' => $weekly->map(fn ($r) => [
                'week' => $r->week,
                'avg_score' => round($r->avg_score, 1),
                'count' => (int) $r->count,
            ]),
        ];
    }

    /**
     * Davomat statistikasi.
     *
     * @return array<string, mixed>
     */
    public function attendanceStats(User $student): array
    {
        $rows = DB::table('attendance')->where('student_id', $student->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = $rows->sum();

        return [
            'total' => $total,
            'present' => (int) ($rows['present'] ?? 0),
            'absent' => (int) ($rows['absent'] ?? 0),
            'late' => (int) ($rows['late'] ?? 0),
            'excused' => (int) ($rows['excused'] ?? 0),
            'attendance_rate' => $total > 0
                ? round((($rows['present'] ?? 0) + ($rows['late'] ?? 0)) / $total * 100, 1)
                : null,
        ];
    }

    /**
     * Tasdiqlangan xatolar asosida individual mashq tavsiyasi.
     * Faqat mentor tasdiqlagan segmentlardan.
     *
     * @return list<array{surah_number: int, ayah: int, error_type: string}>
     */
    public function recommendedPractice(User $student): array
    {
        $rows = DB::table('mentor_review_segments')
            ->join('mentor_reviews', 'mentor_reviews.id', '=', 'mentor_review_segments.mentor_review_id')
            ->join('submissions', 'submissions.id', '=', 'mentor_reviews.submission_id')
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->where('submissions.student_id', $student->id)
            ->whereNotNull('mentor_review_segments.ayah_from')
            ->orderByDesc('mentor_reviews.reviewed_at')
            ->limit(10)
            ->get([
                'assignments.surah_number',
                'mentor_review_segments.ayah_from',
                'mentor_review_segments.error_type',
            ]);

        return $rows->map(fn ($r) => [
            'surah_number' => (int) $r->surah_number,
            'ayah' => (int) $r->ayah_from,
            'error_type' => $r->error_type,
        ])->all();
    }
}
