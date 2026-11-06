<?php

use App\Jobs\AnalyzeRecitationJob;
use App\Models\AiReview;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use App\Models\Submission;
use App\Models\User;
use App\Services\AI\Contracts\SpeechRecognitionProvider;
use App\Services\AI\Contracts\TajweedAnalysisProvider;
use App\Services\AI\QuranAlignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Config::set('services.openai.key', null);
});

// --- Alignment algoritm testlari (soxta emas — haqiqiy hisoblash) ---

test('arabic normalization removes diacritics', function () {
    $svc = new QuranAlignmentService;

    $with = 'بِسۡمِ ٱللَّهِ ٱلرَّحۡمَٰنِ';
    $without = 'بسم الله الرحمن';

    expect($svc->normalizeArabic($with))->toBe($svc->normalizeArabic($without));
});

test('expected words extracted from ayah range', function () {
    $svc = new QuranAlignmentService;

    $surah = Surah::create([
        'number' => 114, 'name_arabic' => 'الناس', 'name_uz' => 'An-Nos', 'name_en' => 'An-Nas',
        'ayah_count' => 2, 'revelation_type' => 'meccan',
    ]);
    Ayah::create([
        'surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 6230, 'juz' => 30, 'page' => 604,
        'text_arabic' => 'قُلۡ أَعُوذُ بِرَبِّ ٱلنَّاسِ',
    ]);
    Ayah::create([
        'surah_id' => $surah->id, 'number_in_surah' => 2, 'number_global' => 6231, 'juz' => 30, 'page' => 604,
        'text_arabic' => 'مَلِكِ ٱلنَّاسِ',
    ]);

    $words = $svc->expectedWords(114, [1, 2]);

    expect(count($words))->toBe(6)
        ->and($words[0]['ayah'])->toBe(1)
        ->and($words[4]['ayah'])->toBe(2);
});

test('alignment detects missing words', function () {
    $svc = new QuranAlignmentService;

    $expected = [
        ['ayah' => 1, 'word' => 'قُلۡ'], ['ayah' => 1, 'word' => 'أَعُوذُ'],
        ['ayah' => 1, 'word' => 'بِرَبِّ'], ['ayah' => 1, 'word' => 'ٱلنَّاسِ'],
    ];
    // O'quvchi 3-so'zni (بِرَبِّ) tushirib yuborgan
    $heard = [
        ['word' => 'قُلۡ', 'start_ms' => 0, 'end_ms' => 500, 'confidence' => 0.95],
        ['word' => 'أَعُوذُ', 'start_ms' => 500, 'end_ms' => 1000, 'confidence' => 0.93],
        ['word' => 'ٱلنَّاسِ', 'start_ms' => 1000, 'end_ms' => 1500, 'confidence' => 0.9],
    ];

    $result = $svc->align($expected, $heard);

    expect(count($result['missing']))->toBe(1)
        ->and($result['missing'][0]['word'])->toContain('ر')
        ->and($result['similarity'])->toBe(0.75);
});

test('alignment detects repetition', function () {
    $svc = new QuranAlignmentService;

    $expected = [
        ['ayah' => 1, 'word' => 'ٱلۡحَمۡدُ'], ['ayah' => 1, 'word' => 'لِلَّهِ'],
    ];
    $heard = [
        ['word' => 'ٱلۡحَمۡدُ', 'start_ms' => 0, 'end_ms' => 500, 'confidence' => 0.9],
        ['word' => 'ٱلۡحَمۡدُ', 'start_ms' => 500, 'end_ms' => 900, 'confidence' => 0.88],
        ['word' => 'لِلَّهِ', 'start_ms' => 900, 'end_ms' => 1400, 'confidence' => 0.92],
    ];

    $result = $svc->align($expected, $heard);

    expect(count($result['repetitions']))->toBe(1);
});

test('alignment similarity is 1.0 for perfect recitation', function () {
    $svc = new QuranAlignmentService;

    $expected = [['ayah' => 1, 'word' => 'ٱلۡحَمۡدُ'], ['ayah' => 1, 'word' => 'لِلَّهِ']];
    $heard = [
        ['word' => 'ٱلۡحَمۡدُ', 'start_ms' => 0, 'end_ms' => 500, 'confidence' => 0.98],
        ['word' => 'لِلَّهِ', 'start_ms' => 500, 'end_ms' => 1000, 'confidence' => 0.97],
    ];

    expect($svc->align($expected, $heard)['similarity'])->toBe(1.0);
});

// --- Pipeline testlari ---

function aiSubmissionFixture(): array
{
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    $course = Course::create(['name' => 'K', 'level' => 'beginner']);
    $group = Group::create(['name' => 'G', 'course_id' => $course->id]);
    $group->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);

    $assignment = Assignment::create([
        'group_id' => $group->id, 'created_by' => $admin->id,
        'title' => 'An-Nos', 'surah_number' => 114, 'ayah_from' => 1, 'ayah_to' => 1,
        'status' => 'published',
    ]);

    $submission = Submission::create([
        'assignment_id' => $assignment->id, 'student_id' => $student->id,
        'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now(),
    ]);

    return [$student, $submission];
}

test('submitting audio dispatches AI analysis job', function () {
    Bus::fake();
    [$student, $submission] = aiSubmissionFixture();

    $aiReview = AiReview::create(['submission_id' => $submission->id, 'provider' => 'pending']);
    AnalyzeRecitationJob::dispatch($aiReview->id);

    Bus::assertDispatched(AnalyzeRecitationJob::class);
});

test('pipeline marks unavailable when provider not configured (no fake results)', function () {
    [, $submission] = aiSubmissionFixture();

    Storage::fake('local');
    $submission->files()->create([
        'disk' => 'local', 'path' => 'submissions/1/test.wav',
        'mime_type' => 'audio/wav', 'size_bytes' => 1000,
    ]);

    $review = AiReview::create(['submission_id' => $submission->id, 'provider' => 'pending']);

    (new AnalyzeRecitationJob($review->id))->handle(
        app(SpeechRecognitionProvider::class),
        app(TajweedAnalysisProvider::class),
        app(QuranAlignmentService::class),
    );

    expect($review->refresh()->status)->toBe('unavailable')
        ->and($review->needs_mentor_review_reason)->toContain('sozlanmagan')
        // Soxta natija YO'Q:
        ->and($review->transcript)->toBeNull()
        ->and($review->overall_confidence)->toBeNull();
});

test('pipeline runs with mock provider and saves real alignment results', function () {
    [, $submission] = aiSubmissionFixture();

    // Ayah yaratish
    $surah = Surah::create([
        'number' => 114, 'name_arabic' => 'الناس', 'name_uz' => 'An-Nos', 'name_en' => 'An-Nas',
        'ayah_count' => 1, 'revelation_type' => 'meccan',
    ]);
    Ayah::create([
        'surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 6230, 'juz' => 30, 'page' => 604,
        'text_arabic' => 'قُلۡ أَعُوذُ بِرَبِّ ٱلنَّاسِ',
    ]);

    // Mock ASR provayderi (o'quvchi bitta so'zni tushirib yuborgan)
    $mockSpeech = new class implements SpeechRecognitionProvider
    {
        public function isConfigured(): bool
        {
            return true;
        }

        public function name(): string
        {
            return 'mock';
        }

        public function model(): string
        {
            return 'mock-1';
        }

        public function transcribe(string $absolutePath, string $language = 'ar'): ?array
        {
            return [
                'text' => 'قل أعوذ الناس',
                'words' => [
                    ['word' => 'قل', 'start_ms' => 0, 'end_ms' => 400, 'confidence' => 0.95],
                    ['word' => 'أعوذ', 'start_ms' => 400, 'end_ms' => 900, 'confidence' => 0.93],
                    ['word' => 'الناس', 'start_ms' => 900, 'end_ms' => 1400, 'confidence' => 0.9],
                ],
                'language_confidence' => null,
            ];
        }
    };

    Storage::fake('local');
    $submission->files()->create([
        'disk' => 'local', 'path' => 'submissions/1/test.wav',
        'mime_type' => 'audio/wav', 'size_bytes' => 1000,
    ]);

    $review = AiReview::create(['submission_id' => $submission->id, 'provider' => 'pending']);

    (new AnalyzeRecitationJob($review->id))->handle(
        $mockSpeech,
        app(TajweedAnalysisProvider::class),
        app(QuranAlignmentService::class),
    );

    $review->refresh();

    expect($review->status)->toBe('completed')
        ->and($review->transcript)->toBe('قل أعوذ الناس')
        ->and($review->segments()->where('error_type', 'missing_word')->exists())->toBeTrue()
        ->and($review->summary)->toContain('yakuniy baho emas');
});

test('low confidence routes to mentor review', function () {
    [, $submission] = aiSubmissionFixture();

    $review = AiReview::create([
        'submission_id' => $submission->id, 'provider' => 'mock',
        'status' => 'completed', 'overall_confidence' => 0.5,
        'needs_mentor_review_reason' => 'AI ishonchliligi past — mentor tekshiruvi talab qilinadi.',
    ]);

    expect($review->overall_confidence)->toBeLessThan(config('ai.min_confidence'))
        ->and($review->needs_mentor_review_reason)->not->toBeNull();
});

test('ai review api returns results for owner', function () {
    [$student, $submission] = aiSubmissionFixture();

    AiReview::create([
        'submission_id' => $submission->id, 'provider' => 'mock',
        'status' => 'completed', 'overall_confidence' => 0.92,
        'summary' => 'Yaxshi o\'qilgan.',
    ]);

    $token = $student->createToken('t')->plainTextToken;
    $this->withToken($token)->getJson("/api/v1/submissions/{$submission->id}/ai-review")
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('overall_confidence', 0.92);
});

test('tajweed provider returns null (no fake tajweed results)', function () {
    $provider = app(TajweedAnalysisProvider::class);

    expect($provider->isConfigured())->toBeFalse()
        ->and($provider->analyze('/tmp/x.wav', []))->toBeNull();
});

test('pipeline does not discard zero confidence or invent confidence from text similarity', function (?float $confidence, ?float $expectedConfidence) {
    [, $submission] = aiSubmissionFixture();
    $surah = Surah::create(['number' => 114, 'name_arabic' => 'الناس', 'name_uz' => 'An-Nos', 'name_en' => 'An-Nas', 'ayah_count' => 6, 'revelation_type' => 'meccan']);
    Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 6231, 'juz' => 30, 'page' => 604, 'text_arabic' => 'قُلْ أَعُوذُ بِرَبِّ النَّاسِ']);
    $words = array_map(fn ($word) => ['word' => $word, 'start_ms' => null, 'end_ms' => null, 'confidence' => $confidence], ['قل', 'أعوذ', 'برب', 'الناس']);
    $provider = Mockery::mock(SpeechRecognitionProvider::class);
    $provider->shouldReceive('isConfigured')->andReturnTrue();
    $provider->shouldReceive('name')->andReturn('test');
    $provider->shouldReceive('model')->andReturn('test');
    $provider->shouldReceive('transcribe')->once()->andReturn(['text' => 'قل أعوذ برب الناس', 'words' => $words, 'language_confidence' => null]);
    Storage::fake('local');
    Storage::disk('local')->put('test.wav', 'test');
    $submission->files()->create(['disk' => 'local', 'path' => 'test.wav', 'mime_type' => 'audio/wav', 'size_bytes' => 4]);
    $review = AiReview::create(['submission_id' => $submission->id, 'provider' => 'pending']);
    (new AnalyzeRecitationJob($review->id))->handle($provider, app(TajweedAnalysisProvider::class), app(QuranAlignmentService::class));
    expect($review->fresh()->status)->toBe('completed');
    expect($review->fresh()->overall_confidence)->toBe($expectedConfidence);
    expect($review->fresh()->needs_mentor_review_reason)->not->toBeNull();
})->with([[0.0, 0.5], [null, null], [-1.0, null], [1.5, null]]);
