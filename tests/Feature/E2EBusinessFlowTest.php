<?php

/**
 * PHASE 9 — E2E biznes zanjiri:
 * Admin → Rahbar → Mentor → O'quvchi → Audio submission → AI analysis
 * → Mentor review → Student result → Parent progress → Head Teacher analytics.
 */
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\Submission;
use App\Models\User;
use App\Services\AI\Contracts\SpeechRecognitionProvider;
use App\Services\AI\QuranAlignmentService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function token(string $email): string
{
    auth()->forgetGuards(); // har rol almashinuvida guard cache tozalanadi

    return User::where('email', $email)->firstOrFail()->createToken('e2e')->plainTextToken;
}

/** Mock ASR: berilgan so'zlarni qaytaradi */
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
    $review = App\Models\AiReview::create(['submission_id' => $submissionId, 'provider' => 'pending']);
    Storage::fake('local');

    (new App\Jobs\AnalyzeRecitationJob($review->id))->handle(
        $speech,
        app(App\Services\AI\Contracts\TajweedAnalysisProvider::class),
        app(QuranAlignmentService::class),
    );
}

test('FULL E2E: admin→rahbar→mentor→oquvchi→audio→AI→review→parent→rahbar analytics', function () {
    // ========== 1. ADMIN: o'quvchi yaratish (web orqali ro'yxatdan o'tish = seeds'da bor) ==========
    $adminToken = token('admin@quronai.uz');

    // E2E o'quvchi (direct create, admin imkoniyati)
    $student = User::create(['name' => 'E2E Oquvchi', 'email' => 'e2e-student@test.uz', 'password' => 'Parol!123']);
    $student->roles()->attach(App\Models\Role::where('name', 'STUDENT')->firstOrFail()->id);
    $studentId = $student->id;

    // ========== 2. RAHBAR (HEAD_TEACHER): kurs, guruh, dars, topshiriq ==========
    $teacherToken = token('ustoz@markaz.uz');

    $courseId = auth()->forgetGuards();
    $this->withToken($teacherToken)->postJson('/api/v1/courses', [
        'name' => 'E2E Kurs', 'level' => 'beginner',
    ])->assertCreated()->json('course.id');

    $groupId = auth()->forgetGuards();
    $this->withToken($teacherToken)->postJson('/api/v1/groups', [
        'name' => 'E2E Guruh', 'course_id' => $courseId,
    ])->assertCreated()->json('group.id');

    // O'quvchini guruhga qo'shish
    $addRes = auth()->forgetGuards();
    $this->withToken($teacherToken)->postJson("/api/v1/groups/{$groupId}/students", [
        'student_id' => $studentId,
    ]);
    if (! $addRes->isOk()) {
        dump('ADD STUDENT FAIL: '.$addRes->getStatusCode().' '.json_encode($addRes->json()));
    }
    $addRes->assertOk();

    // O'quvchi guruhda ko'rinadi
    $groupShow = auth()->forgetGuards();
    $this->withToken($teacherToken)->getJson("/api/v1/groups/{$groupId}")->assertOk();
    $studentIds = collect($groupShow->json('students'))->pluck('id')->map(fn ($v) => (int) $v);
    expect($studentIds)->toContain((int) $studentId);

    // Mentor tayinlash (mentor_assignment yaratiladi)
    $mentorToken = token('mentor@markaz.uz');
    $mentorId = User::where('email', 'mentor@markaz.uz')->first()->id;
    auth()->forgetGuards();
    $this->withToken($teacherToken)->postJson("/api/v1/groups/{$groupId}/mentor", [
        'mentor_id' => $mentorId,
    ])->assertOk();

    // Dars yaratish
    auth()->forgetGuards();
    $this->withToken($teacherToken)->postJson('/api/v1/lessons', [
        'course_id' => $courseId, 'title' => 'E2E dars', 'status' => 'published',
    ])->assertCreated();

    // Topshiriq (An-Nos 1-4)
    $assignmentId = auth()->forgetGuards();
    $this->withToken($teacherToken)->postJson('/api/v1/assignments', [
        'group_id' => $groupId, 'title' => 'An-Nos 1-4',
        'surah_number' => 114, 'ayah_from' => 1, 'ayah_to' => 4,
        'due_at' => now()->addWeek()->toIso8601String(),
    ])->assertCreated()->json('assignment.id');

    // ========== 3. O'QUVCHI: audio yuboradi ==========
    auth()->forgetGuards(); // RequestGuard user'ni cache qiladi — foydalanuvchi almashganda tozalash shart
    $studentToken = User::find($studentId)->fresh()->createToken('e2e')->plainTextToken;

    // Qur'on ma'lumoti: An-Nos 1-4 (test uchun)
    $surah = App\Models\Quran\Surah::create([
        'number' => 114, 'name_arabic' => 'الناس', 'name_uz' => 'An-Nos', 'name_en' => 'An-Nas',
        'ayah_count' => 6, 'revelation_type' => 'meccan',
    ]);
    $texts = [
        1 => 'قُلۡ أَعُوذُ بِرَبِّ ٱلنَّاسِ',
        2 => 'مَلِكِ ٱلنَّاسِ',
        3 => 'إِلَٰهِ ٱلنَّاسِ',
        4 => 'مُضَکِّرِ ٱلۡوَسۡوَاسِ ٱلۡخَنَّاسِ',
    ];
    foreach ($texts as $n => $text) {
        App\Models\Quran\Ayah::create([
            'surah_id' => $surah->id, 'number_in_surah' => $n, 'number_global' => 6230 + $n,
            'juz' => 30, 'page' => 604, 'text_arabic' => $text,
        ]);
    }

    // Audio topshirish
    $meRes = auth()->forgetGuards();
    $this->withToken($studentToken)->getJson('/api/v1/auth/me');
    if (! in_array('submissions.create', $meRes->json('permissions') ?? [], true)) {
        $this->fail('PERMISSION MISSING: '.json_encode($meRes->json('permissions')).' roles: '.json_encode($meRes->json('roles')));
    }
    $audio = Illuminate\Http\Testing\File::fake()->createWithContent('recitation.wav', str_repeat('RIFF', 200));
    $submitRes = auth()->forgetGuards();
    $this->withToken($studentToken)
        ->post("/api/v1/assignments/{$assignmentId}/submit", [
            'student_note' => 'E2E topshirish',
            'audio' => [$audio],
        ]);
    if ($submitRes->getStatusCode() !== 201) {
        $this->fail('SUBMIT FAIL '.$submitRes->getStatusCode().': '.$submitRes->json('message'));
    }
    $submissionId = $submitRes->assertCreated()->json('submission.id');

    // DB: submission yozildi
    $submission = Submission::with('files')->find($submissionId);
    expect($submission->status)->toBe('submitted')
        ->and($submission->files->isNotEmpty())->toBeTrue();

    // DB: mentor bildirishnomasi
    expect(User::find($mentorId)->appNotifications()->where('type', 'new_submission')->exists())->toBeTrue();

    // ========== 4. AI ANALYSIS (mock ASR — bitta so'z tushirilgan) ==========
    $mockWords = [
        ['word' => 'قل', 'start_ms' => 0, 'end_ms' => 400, 'confidence' => 0.95],
        ['word' => 'أعوذ', 'start_ms' => 400, 'end_ms' => 900, 'confidence' => 0.94],
        // برب (بِرَبِّ) TUSHIRILGAN
        ['word' => 'الناس', 'start_ms' => 900, 'end_ms' => 1400, 'confidence' => 0.92],
    ];
    runPipeline($submissionId, mockSpeech($mockWords));

    $aiReview = App\Models\AiReview::where('submission_id', $submissionId)->first();
    expect($aiReview->status)->toBe('completed')
        // missing_word aniqlandi
        ->and($aiReview->segments()->where('error_type', 'missing_word')->exists())->toBeTrue()
        ->and($aiReview->summary)->toContain('yakuniy baho emas');

    // ========== 5. MENTOR: ko'radi, tinglaydi, baholaydi ==========
    auth()->forgetGuards();
    // Pending ro'yxatida bor
    $pendingRes = auth()->forgetGuards();
    $this->withToken($mentorToken)->getJson('/api/v1/submissions/pending');
    if (! $pendingRes->isOk()) {
        dump('PENDING FAIL '.$pendingRes->getStatusCode().': '.json_encode($pendingRes->json()));
    }
    $pendingRes->assertOk()
        ->assertJsonFragment(['id' => $submissionId]);

    // Batafsil
    auth()->forgetGuards();
    $this->withToken($mentorToken)->getJson("/api/v1/submissions/{$submissionId}")
        ->assertOk()
        ->assertJsonFragment(['status' => 'submitted']);

    // Baholash + segment tasdiqlash
    auth()->forgetGuards();
    $this->withToken($mentorToken)->postJson("/api/v1/submissions/{$submissionId}/review", [
        'score' => 78,
        'action' => 'approve',
        'comment' => '3-so\'z tushirilgan, qayta mashq qiling.',
        'segments' => [
            ['ayah_from' => 1, 'error_type' => 'missing_word', 'comment' => 'بِرَبِّ tushirilgan'],
        ],
    ])->assertCreated();

    // DB: submission graded
    $submission->refresh();
    expect($submission->status)->toBe('graded')->and($submission->final_score)->toBe(78);

    // DB: o'quvchiga bildirishnoma
    expect(User::find($studentId)->appNotifications()->where('type', 'new_grade')->exists())->toBeTrue();

    // ========== 6. O'QUVCHI: natijani va AI tahlilini ko'radi ==========
    auth()->forgetGuards();
    $this->withToken($studentToken)->getJson("/api/v1/submissions/{$submissionId}")
        ->assertOk()
        ->assertJsonFragment(['status' => 'graded']);

    auth()->forgetGuards();
    $this->withToken($studentToken)->getJson("/api/v1/submissions/{$submissionId}/ai-review")
        ->assertOk()
        ->assertJsonFragment(['status' => 'completed']);

    // Progress yangilandi
    $progress = auth()->forgetGuards();
    $this->withToken($studentToken)->getJson('/api/v1/progress/me')->assertOk()->json();
    expect($progress['submissions_graded'])->toBe(1)
        ->and($progress['average_score'])->toBe(78.0)
        ->and($progress['recurring_errors'])->not->toBeEmpty();

    // ========== 7. PARENT: farzand progressini ko'radi ==========
    $parentToken = token('ota@markaz.uz');
    $parentId = User::where('email', 'ota@markaz.uz')->first()->id;

    // Admin parent bog'laydi (yoki parent panel)
    DB::table('parent_student_links')->insert([
        'parent_id' => $parentId, 'student_id' => $studentId, 'relation' => 'parent',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    auth()->forgetGuards();
    $this->withToken($parentToken)->getJson('/api/v1/parents/children')
        ->assertOk()->assertJsonFragment(['id' => $studentId]);

    $childProgress = auth()->forgetGuards();
    $this->withToken($parentToken)->getJson("/api/v1/progress/students/{$studentId}")
        ->assertOk()->json();
    expect($childProgress['average_score'])->toBe(78.0);

    // Boshqa o'quvchiga KIRISH TAQIQLANGAN (IDOR)
    $otherStudent = User::factory()->create();
    $otherStudent->roles()->attach(5);
    auth()->forgetGuards();
    $this->withToken($parentToken)->getJson("/api/v1/progress/students/{$otherStudent->id}")
        ->assertStatus(403);

    // ========== 8. RAHBAR: analitika (kurs ko'rinishi, statistikalar) ==========
    auth()->forgetGuards();
    $this->withToken($teacherToken)->getJson('/api/v1/courses?search=E2E')
        ->assertOk()
        ->assertJsonFragment(['name' => 'E2E Kurs']);

    auth()->forgetGuards();
    $this->withToken($teacherToken)->getJson("/api/v1/groups/{$groupId}")
        ->assertOk()
        // Guruhda o'quvchi bor
        ->assertJsonFragment(['id' => $studentId]);
});
