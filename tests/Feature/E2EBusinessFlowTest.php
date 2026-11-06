<?php

/**
 * PHASE 9 — E2E biznes zanjiri:
 * Admin → Rahbar → Mentor → O'quvchi → Audio submission → AI analysis
 * → Mentor review → Student result → Parent progress → Head Teacher analytics.
 */
use App\Jobs\AnalyzeRecitationJob;
use App\Models\AiReview;
use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use App\Services\AI\Contracts\SpeechRecognitionProvider;
use App\Services\AI\Contracts\TajweedAnalysisProvider;
use App\Services\AI\QuranAlignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function token(string $email): string
{
    auth()->forgetGuards();

    return User::where('email', $email)->firstOrFail()->createToken('e2e')->plainTextToken;
}

/** Mock ASR — berilgan so'zlarni qaytaradi */
function mockSpeech(array $words): SpeechRecognitionProvider
{
    return new class($words) implements SpeechRecognitionProvider
    {
        public function __construct(private array $words) {}

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
                'text' => implode(' ', array_column($this->words, 'word')),
                'words' => $this->words,
                'language_confidence' => null,
            ];
        }
    };
}

function runPipeline(int $submissionId, SpeechRecognitionProvider $speech): void
{
    $review = AiReview::create(['submission_id' => $submissionId, 'provider' => 'pending']);

    (new AnalyzeRecitationJob($review->id))->handle(
        $speech,
        app(TajweedAnalysisProvider::class),
        app(QuranAlignmentService::class),
    );
}

test('FULL E2E: admin→rahbar→mentor→oquvchi→audio→AI→review→parent→rahbar', function () {
    // ===== 1. ADMIN: o'quvchi yaratadi =====
    token('malimov707@gmail.com');
    $student = User::create(['name' => 'E2E Oquvchi', 'email' => 'e2e-student@test.uz', 'password' => 'Parol!123']);
    $student->roles()->attach(Role::where('name', 'STUDENT')->firstOrFail()->id);
    $studentId = $student->id;

    // ===== 2. RAHBAR: kurs, guruh, dars, topshiriq =====
    $teacherToken = token('ustoz@markaz.uz');

    $courseRes = $this->withToken($teacherToken)->postJson('/api/v1/courses', [
        'name' => 'E2E Kurs', 'level' => 'beginner',
    ]);
    $courseId = $courseRes->assertCreated()->json('course.id');

    $groupRes = $this->withToken($teacherToken)->postJson('/api/v1/groups', [
        'name' => 'E2E Guruh', 'course_id' => $courseId,
    ]);
    $groupId = $groupRes->assertCreated()->json('group.id');

    // O'quvchini guruhga qo'shish
    $addRes = $this->withToken($teacherToken)->postJson("/api/v1/groups/{$groupId}/students", [
        'student_id' => $studentId,
    ]);
    $addRes->assertOk();

    // Guruhda ko'rinadi
    $groupShow = $this->withToken($teacherToken)->getJson("/api/v1/groups/{$groupId}")->assertOk();
    $ids = collect($groupShow->json('students'))->pluck('id')->map(fn ($v) => (int) $v);
    expect($ids)->toContain((int) $studentId);

    // Mentor tayinlash
    $mentor = User::where('email', 'mentor@markaz.uz')->first();
    $this->withToken($teacherToken)->postJson("/api/v1/groups/{$groupId}/mentor", [
        'mentor_id' => $mentor->id,
    ])->assertOk();

    // Dars
    $this->withToken($teacherToken)->postJson('/api/v1/lessons', [
        'course_id' => $courseId, 'title' => 'E2E dars', 'status' => 'published',
    ])->assertCreated();

    // Topshiriq
    $assignmentId = $this->withToken($teacherToken)->postJson('/api/v1/assignments', [
        'group_id' => $groupId, 'title' => 'An-Nos 1-4',
        'surah_number' => 114, 'ayah_from' => 1, 'ayah_to' => 4,
        'due_at' => now()->addWeek()->toIso8601String(),
    ])->assertCreated()->json('assignment.id');

    // ===== 3. O'QUVCHI: audio yuboradi =====
    auth()->forgetGuards();
    $studentToken = User::find($studentId)->createToken('e2e')->plainTextToken;

    // Qur'on ma'lumoti
    $surah = Surah::create([
        'number' => 114, 'name_arabic' => 'الناس', 'name_uz' => 'An-Nos', 'name_en' => 'An-Nas',
        'ayah_count' => 6, 'revelation_type' => 'meccan',
    ]);
    $texts = [
        1 => 'قُلۡ أَعُوذُ بِرَبِّ ٱلنَّاسِ',
        2 => 'مَلِکِ ٱلنَّاسِ',
        3 => 'إِلَٰهِ ٱلنَّاسِ',
        4 => 'مُضَکِّرِ ٱلۡوَسۡوَاسِ ٱلۡخَنَّاسِ',
    ];
    foreach ($texts as $n => $text) {
        Ayah::create([
            'surah_id' => $surah->id, 'number_in_surah' => $n, 'number_global' => 6230 + $n,
            'juz' => 30, 'page' => 604, 'text_arabic' => $text,
        ]);
    }

    $audio = File::fake()->createWithContent('recitation.wav', str_repeat('RIFF', 200));
    $submitRes = $this->withToken($studentToken)
        ->post("/api/v1/assignments/{$assignmentId}/submit", [
            'student_note' => 'E2E topshirish',
            'audio' => [$audio],
        ]);
    $submissionId = $submitRes->assertCreated()->json('submission.id');

    $submission = Submission::with('files')->find($submissionId);
    expect($submission->status)->toBe('submitted')
        ->and($submission->files->isNotEmpty())->toBeTrue();

    // Mentor bildirishnomasi
    expect($mentor->appNotifications()->where('type', 'new_submission')->exists())->toBeTrue();

    // ===== 4. AI ANALYSIS: bitta so'z tushirilgan =====
    $mockWords = [
        ['word' => 'قل', 'start_ms' => 0, 'end_ms' => 400, 'confidence' => 0.95],
        ['word' => 'أعوذ', 'start_ms' => 400, 'end_ms' => 900, 'confidence' => 0.94],
        ['word' => 'الناس', 'start_ms' => 900, 'end_ms' => 1400, 'confidence' => 0.92],
    ];
    runPipeline((int) $submissionId, mockSpeech($mockWords));

    $aiReview = AiReview::where('submission_id', $submissionId)->first();
    expect($aiReview->status)->toBe('completed')
        ->and($aiReview->segments()->where('error_type', 'missing_word')->exists())->toBeTrue()
        ->and($aiReview->summary)->toContain('yakuniy baho emas');

    // ===== 5. MENTOR: ko'radi va baholaydi =====
    $mentorToken = token('mentor@markaz.uz');

    $this->withToken($mentorToken)->getJson('/api/v1/submissions/pending')
        ->assertOk()
        ->assertJsonFragment(['id' => $submissionId]);

    $this->withToken($mentorToken)->getJson("/api/v1/submissions/{$submissionId}")
        ->assertOk()
        ->assertJsonFragment(['status' => 'submitted']);

    $this->withToken($mentorToken)->postJson("/api/v1/submissions/{$submissionId}/review", [
        'score' => 78,
        'action' => 'approve',
        'comment' => "3-so'z tushirilgan, qayta mashq qiling.",
        'segments' => [
            ['ayah_from' => 1, 'error_type' => 'missing_word', 'comment' => 'tushirilgan'],
        ],
    ])->assertCreated();

    $submission->refresh();
    expect($submission->status)->toBe('graded')->and($submission->final_score)->toBe(78);

    // O'quvchiga bildirishnoma
    auth()->forgetGuards();
    expect(User::find($studentId)->appNotifications()->where('type', 'new_grade')->exists())->toBeTrue();

    // ===== 6. O'QUVCHI: natijani ko'radi =====
    $this->withToken($studentToken)->getJson("/api/v1/submissions/{$submissionId}")
        ->assertOk()
        ->assertJsonFragment(['status' => 'graded']);

    $this->withToken($studentToken)->getJson("/api/v1/submissions/{$submissionId}/ai-review")
        ->assertOk()
        ->assertJsonFragment(['status' => 'completed']);

    $progress = $this->withToken($studentToken)->getJson('/api/v1/progress/me')->assertOk()->json();
    expect($progress['submissions_graded'])->toBe(1)
        ->and($progress['average_score'])->toBeIn([78, 78.0])
        ->and($progress['recurring_errors'])->not->toBeEmpty();

    // ===== 7. PARENT: farzand progressi =====
    $parentToken = token('ota@markaz.uz');
    $parentId = User::where('email', 'ota@markaz.uz')->first()->id;

    DB::table('parent_student_links')->insert([
        'parent_id' => $parentId, 'student_id' => $studentId, 'relation' => 'parent',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->withToken($parentToken)->getJson('/api/v1/parents/children')
        ->assertOk()->assertJsonFragment(['id' => $studentId]);

    $childProgress = $this->withToken($parentToken)->getJson("/api/v1/progress/students/{$studentId}")
        ->assertOk()->json();
    expect($childProgress['average_score'])->toBeIn([78, 78.0]);

    // IDOR: boshqa o'quvchiga 403
    $other = User::create(['name' => 'Other', 'email' => 'other@test.uz', 'password' => 'Parol!123']);
    $other->roles()->attach(Role::where('name', 'STUDENT')->firstOrFail()->id);
    $this->withToken($parentToken)->getJson("/api/v1/progress/students/{$other->id}")
        ->assertStatus(403);

    // ===== 8. RAHBAR: analitika =====
    auth()->forgetGuards();
    $this->withToken($teacherToken)->getJson('/api/v1/courses?search=E2E')
        ->assertOk()
        ->assertJsonFragment(['name' => 'E2E Kurs']);

    auth()->forgetGuards();
    $this->withToken($teacherToken)->getJson("/api/v1/groups/{$groupId}")
        ->assertOk()
        ->assertJsonFragment(['id' => $groupId]);
});
