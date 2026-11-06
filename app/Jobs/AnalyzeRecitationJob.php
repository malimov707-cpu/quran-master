<?php

namespace App\Jobs;

use App\Models\AiReview;
use App\Models\Assignment;
use App\Models\Submission;
use App\Services\AI\Contracts\SpeechRecognitionProvider;
use App\Services\AI\Contracts\TajweedAnalysisProvider;
use App\Services\AI\QuranAlignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * AI Qiroat Pipeline:
 * 1. Audio validatsiya  2. ASR (Whisper)  3. Quran alignment
 * 4. Xatolarni aniqlash  5. Confidence bo'yicha mentor tekshiruviga yo'naltirish.
 *
 * AI tahlili va mentor bahosi ALOHIDA saqlanadi (ai_reviews vs mentor_reviews).
 */
class AnalyzeRecitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120]; // retry 30s, 120s

    public function __construct(public int $aiReviewId) {}

    public function handle(
        SpeechRecognitionProvider $speech,
        TajweedAnalysisProvider $tajweed,
        QuranAlignmentService $alignment,
    ): void {
        $review = AiReview::with('submission.assignment')->findOrFail($this->aiReviewId);

        /** @var Submission $submission */
        $submission = $review->submission;

        // 1. Audio faylni olish
        $file = $submission->files()->first();
        if (! $file) {
            $this->failReview($review, 'Audio fayl topilmadi.');

            return;
        }

        if (! $speech->isConfigured()) {
            // Soxta natija chiqarmaymiz — aniq holat bilan mentor tekshiruviga
            $review->update([
                'status' => 'unavailable',
                'needs_mentor_review_reason' => 'AI provayder sozlanmagan — to\'g\'ridan-to\'g\'ri mentor tekshiruvi talab qilinadi.',
                'finished_at' => now(),
            ]);

            return;
        }

        $review->update(['status' => 'processing', 'started_at' => now(), 'attempts' => $review->attempts + 1]);

        try {
            // 2. ASR
            $localPath = Storage::disk($file->disk)->path($file->path);
            $transcription = $speech->transcribe($localPath, 'ar');

            $review->update(['transcript' => $transcription['text']]);

            // 3. Kutilgan oyatlar (topshiriq diapazonidan)
            /** @var Assignment $assignment */
            $assignment = $submission->assignment;
            $ayahRange = range($assignment->ayah_from ?? 1, $assignment->ayah_to ?? 1);

            $expected = $alignment->expectedWords((int) $assignment->surah_number, $ayahRange);

            // 4. Alignment + xatolar
            $result = $alignment->align($expected, $transcription['words']);

            // Segmentlarni saqlash
            $segments = [];
            foreach ($result['matches'] as $match) {
                $segments[] = [
                    'ayah_number' => $match['expected_ayah'],
                    'expected_text' => $match['expected'],
                    'heard_text' => $match['heard'],
                    'start_ms' => $match['start_ms'],
                    'end_ms' => $match['end_ms'],
                    'confidence' => $match['confidence'],
                    'is_correct' => true,
                ];
            }
            foreach ($result['missing'] as $miss) {
                $segments[] = [
                    'ayah_number' => $miss['ayah'],
                    'expected_text' => $miss['word'],
                    'heard_text' => null,
                    'error_type' => 'missing_word',
                    'is_correct' => false,
                ];
            }
            foreach ($result['repetitions'] as $rep) {
                $segments[] = [
                    'expected_text' => $rep['word'],
                    'heard_text' => $rep['word'],
                    'error_type' => 'repetition',
                    'start_ms' => $rep['start_ms'] ?? null,
                    'is_correct' => false,
                ];
            }
            foreach ($result['extra'] as $extra) {
                $segments[] = [
                    'heard_text' => $extra['word'],
                    'error_type' => 'wrong_word',
                    'start_ms' => $extra['start_ms'] ?? null,
                    'is_correct' => false,
                ];
            }
            $review->segments()->createMany($segments);

            // 5. Confidence — ASR so'z ishonchlari o'rtachasi va similarity kombinatsiyasi
            $confidences = array_filter(array_column($transcription['words'], 'confidence'), fn ($confidence): bool => is_numeric($confidence) && $confidence >= 0 && $confidence <= 1);
            $asrConfidence = $confidences ? array_sum($confidences) / count($confidences) : null;
            $overall = $asrConfidence !== null
                ? round(($asrConfidence + $result['similarity']) / 2, 3)
                : null;

            // Ishonch past bo'lsa avtomatik mentor tekshiruviga
            $incompleteConfidence = count($confidences) < count($transcription['words']);
            $needsMentor = $overall === null || $incompleteConfidence || $overall < config('ai.min_confidence', 0.85) || $result['similarity'] < 0.7;

            $summary = $this->buildSummary($result, $overall);

            $review->update([
                'status' => 'completed',
                'overall_confidence' => $overall,
                'summary' => $summary,
                'needs_mentor_review_reason' => $needsMentor
                    ? ($overall === null || $incompleteConfidence ? 'Nutq ishonchliligi ma’lumoti yetarli emas — mentor tekshiruvi talab qilinadi.' : 'AI ishonchliligi past — mentor tekshiruvi talab qilinadi.')
                    : null,
                'finished_at' => now(),
            ]);

            // Tajweed: faqat real model bo'lsa (hozircha yo'q — integration-ready)
            if ($tajweed->isConfigured()) {
                $tajweed->analyze($localPath, []);
            }
        } catch (\Throwable $e) {
            if ($review->attempts >= $this->tries) {
                $this->failReview($review, $e->getMessage());

                return;
            }

            throw $e; // retry
        }
    }

    /**
     * Mentor uchun strukturali xulosa.
     *
     * @param  array{missing: list<array{ayah: int, word: string}>, extra: list<array{word: string}>, repetitions: list<array{word: string}>, similarity: float}  $result
     */
    private function buildSummary(array $result, ?float $overall): string
    {
        $parts = [$overall === null
            ? sprintf('AI o‘xshashlik: %.0f%%. Nutq ishonchliligi berilmagan.', $result['similarity'] * 100)
            : sprintf('AI o‘xshashlik: %.0f%%, taxminiy ishonch: %.0f%%.', $result['similarity'] * 100, $overall * 100)];

        if ($result['missing'] !== []) {
            $words = implode(', ', array_slice(array_column($result['missing'], 'word'), 0, 5));
            $parts[] = sprintf('%d ta so\'z tushirilgan (%s...).', count($result['missing']), mb_substr($words, 0, 40));
        }

        if ($result['repetitions'] !== []) {
            $parts[] = sprintf('%d ta takrorlangan so\'z.', count($result['repetitions']));
        }

        if ($result['extra'] !== []) {
            $parts[] = sprintf('%d ta kutilmagan so\'z.', count($result['extra']));
        }

        $parts[] = 'Diqqat: AI natijasi yakuniy baho emas — tajvid bo\'yicha yakuniy xulosa ustozga tegishli.';

        return implode(' ', $parts);
    }

    private function failReview(AiReview $review, string $message): void
    {
        $review->update([
            'status' => 'failed',
            'error_message' => $message,
            'needs_mentor_review_reason' => 'AI tahlil bajarilmadi — mentor tekshiruvi talab qilinadi.',
            'finished_at' => now(),
        ]);
    }
}
