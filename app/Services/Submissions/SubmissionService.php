<?php

namespace App\Services\Submissions;

use App\Jobs\AnalyzeRecitationJob;
use App\Models\AiReview;
use App\Models\Assignment;
use App\Models\MentorReview;
use App\Models\Submission;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Topshiriq va mentor tekshiruv biznes-mantiqi.
 */
class SubmissionService
{
    /**
     * O'quvchi topshiriqni yuboradi (audio fayl bilan).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>|null  $audioFiles
     */
    public function submit(Assignment $assignment, User $student, array $data, ?array $audioFiles = null): Submission
    {
        if ($assignment->status !== 'published') {
            abort(422, 'Topshiriq yopilgan yoki hali e\'lon qilinmagan.');
        }

        // Guruh a'zoligi tekshiruvi
        if (! $assignment->group->students()->where('users.id', $student->id)->exists()) {
            abort(403, 'Siz bu guruh o\'quvchisi emassiz.');
        }

        if ($assignment->requires_audio && empty($audioFiles)) {
            abort(422, 'Audio yozuv talab qilinadi.');
        }

        return DB::transaction(function () use ($assignment, $student, $data, $audioFiles) {
            // Avvalgi urinishlar soni
            $attempt = $assignment->submissions()->where('student_id', $student->id)->max('attempt') + 1;

            $submission = Submission::create([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'student_note' => $data['student_note'] ?? null,
                'status' => 'submitted',
                'attempt' => $attempt,
                'submitted_at' => now(),
            ]);

            foreach ($audioFiles ?? [] as $file) {
                $this->storeAudio($submission, $file);
            }

            // AI tahlil navbatga (async) — natija ai_reviews jadvalida alohida saqlanadi
            $aiReview = AiReview::create([
                'submission_id' => $submission->id,
                'provider' => 'pending',
                'status' => 'pending',
            ]);
            AnalyzeRecitationJob::dispatch($aiReview->id);

            // Guruh mentorlariga bildirishnoma
            $mentor = $assignment->group()->with('mentor')->first()?->mentor;
            if ($mentor) {
                app(NotificationService::class)->send(
                    $mentor,
                    'new_submission',
                    'Yangi audio topshirig\'i',
                    "{$student->name} \"{$assignment->title}\" topshirig\'ini yubordi.",
                    ['submission_id' => $submission->id],
                );
            }

            return $submission;
        });
    }

    /**
     * Audio faylni validatsiya qilib saqlash (S3-ready: disk configdan keladi).
     */
    public function storeAudio(Submission $submission, $file): void
    {
        if (! $file->isValid()) {
            abort(422, 'Fayl yuklanmadi.');
        }

        // MIME tekshiruvi — faqat audio
        $allowed = ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/wav', 'audio/webm', 'audio/x-m4a', 'audio/m4a'];
        $mime = $file->getMimeType();
        if (! in_array($mime, $allowed, true)) {
            abort(422, "Ruxsat etilmagan fayl turi: {$mime}. Faqat audio.");
        }

        // Maksimal 20 MB
        if ($file->getSize() > 20 * 1024 * 1024) {
            abort(422, 'Fayl hajmi 20 MB dan katta.');
        }

        $disk = config('filesystems.default');
        $path = $file->store("submissions/{$submission->id}", $disk);

        $submission->files()->create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
        ]);
    }

    /**
     * Mentor tekshiruvi: baho, izoh, qayta topshirish.
     *
     * @param  array<int, array<string, mixed>>|null  $segments
     */
    public function review(Submission $submission, User $mentor, array $data, ?array $segments = null): MentorReview
    {
        if (in_array($submission->status, ['graded'], true)) {
            abort(422, 'Bu topshirish allaqachon baholangan.');
        }

        return DB::transaction(function () use ($submission, $mentor, $data, $segments) {
            $review = MentorReview::create([
                'submission_id' => $submission->id,
                'mentor_id' => $mentor->id,
                'score' => $data['score'] ?? null,
                'comment' => $data['comment'] ?? null,
                'action' => $data['action'],
                'reviewed_at' => now(),
            ]);

            foreach ($segments ?? [] as $segment) {
                $review->segments()->create($segment);
            }

            if ($data['action'] === 'resubmit') {
                $submission->update(['status' => 'resubmit']);
            } else {
                $submission->update(['status' => 'graded', 'final_score' => $data['score'] ?? null]);
            }

            // O'quvchiga bildirishnoma
            app(NotificationService::class)->send(
                $submission->student,
                $data['action'] === 'resubmit' ? 'resubmit_required' : 'new_grade',
                $data['action'] === 'resubmit' ? 'Qayta topshirish talab qilindi' : 'Yangi baho',
                $data['comment'],
                ['submission_id' => $submission->id, 'score' => $data['score'] ?? null],
            );

            return $review;
        });
    }

    /**
     * Yozuvni tinglash uchun vaqtinchalik imzolangan havola (S3 signed URL / local).
     */
    public function audioUrl(Submission $submission): ?string
    {
        $file = $submission->files()->first();

        if (! $file) {
            return null;
        }

        return Storage::disk($file->disk)->temporaryUrl
            ? null // S3 temporary URL production uchun
            : asset('storage/'.$file->path);
    }
}
