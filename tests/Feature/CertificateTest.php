<?php

use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\LessonQuiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return array{User, User, LessonQuiz} */
function certificateFixture(): array
{
    test()->seed(DatabaseSeeder::class);
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $teacher = User::where('email', 'ustoz@markaz.uz')->firstOrFail();
    $quiz = LessonQuiz::factory()->published()->create(['awards_certificate' => true]);
    $quiz->lesson->course->update(['status' => 'published']);
    $group = Group::create(['name' => 'Certificate group', 'course_id' => $quiz->lesson->course_id, 'status' => 'active']);
    $group->students()->attach($student->id, ['joined_at' => now()]);

    return [$student, $teacher, $quiz];
}

function passedCertificateExam(User $student, LessonQuiz $quiz): void
{
    QuizAttempt::factory()->create(['lesson_quiz_id' => $quiz->id, 'user_id' => $student->id, 'score' => 100, 'passed' => true, 'correct_count' => 3]);
}

test('certificate endpoints require authentication while QR verification is public', function () {
    $certificate = Certificate::factory()->create();
    $this->getJson('/api/v1/certificates')->assertUnauthorized();
    $this->getJson("/api/v1/certificates/{$certificate->id}/download?format=pdf")->assertUnauthorized();
    $this->getJson(route('certificates.verify', $certificate->code))->assertOk()->assertJsonPath('valid', true)->assertJsonMissingPath('certificate.user_id')->assertJsonMissingPath('certificate.evidence');
    $this->get(route('certificates.verify', $certificate->code))->assertOk()->assertSee('Sertifikat haqiqiy');
    $this->get('/certificates/verify/not-a-code')->assertNotFound();
});

test('course certificate requires completed lessons and real passed checkpoints', function () {
    [$student, $teacher, $quiz] = certificateFixture();
    $course = $quiz->lesson->course;
    $this->actingAs($student)->getJson("/api/v1/certificates/courses/{$course->id}")->assertOk()->assertJsonPath('eligible', false);
    $this->postJson("/api/v1/certificates/courses/{$course->id}")->assertUnprocessable();
    DB::table('lesson_completions')->insert(['user_id' => $student->id, 'lesson_id' => $quiz->lesson_id, 'completed_at' => now()]);
    $this->postJson("/api/v1/certificates/courses/{$course->id}")->assertUnprocessable();
    passedCertificateExam($student, $quiz);
    $this->getJson("/api/v1/certificates/courses/{$course->id}")->assertJsonPath('eligible', true);
    $this->postJson("/api/v1/certificates/courses/{$course->id}")->assertOk()->assertJsonPath('certificate.type', 'course');
    $this->postJson("/api/v1/certificates/courses/{$course->id}")->assertOk();
    $this->assertDatabaseCount('certificates', 1);
    $this->get(route('certificates.course', $course))->assertOk()->assertSee('Sertifikat talablari');
});

test('read-only lesson completion cannot earn a certificate without a checkpoint', function () {
    [$student, $teacher, $quiz] = certificateFixture();
    $quiz->update(['status' => 'draft']);
    DB::table('lesson_completions')->insert(['user_id' => $student->id, 'lesson_id' => $quiz->lesson_id, 'completed_at' => now()]);
    $this->actingAs($student)->postJson("/api/v1/certificates/courses/{$quiz->lesson->course_id}")->assertUnprocessable();
    $this->assertDatabaseCount('certificates', 0);
});

test('passed certifying tajwid exam produces immutable idempotent certificate', function () {
    [$student, $teacher, $quiz] = certificateFixture();
    $this->actingAs($student)->postJson("/api/v1/certificates/exams/{$quiz->id}")->assertUnprocessable();
    passedCertificateExam($student, $quiz);
    $response = $this->postJson("/api/v1/certificates/exams/{$quiz->id}", ['score' => 999, 'recipient_name' => 'FORGED'])
        ->assertOk()->assertJsonPath('certificate.type', 'tajwid')->assertJsonPath('certificate.score', 100)->assertJsonPath('certificate.recipient_name', $student->name);
    $code = $response->json('certificate.code');
    $original = $student->name;
    $student->update(['name' => 'Changed name']);
    $this->postJson("/api/v1/certificates/exams/{$quiz->id}")->assertOk()->assertJsonPath('certificate.code', $code)->assertJsonPath('certificate.recipient_name', $original);
    $this->assertDatabaseCount('certificates', 1);
    $this->getJson('/api/v1/certificates')->assertOk()->assertJsonCount(1, 'certificates.data');
});

test('stale exam results and quizzes without certificate flag cannot issue credentials', function (bool $enabled, int $revision, int $status) {
    [$student, $teacher, $quiz] = certificateFixture();
    passedCertificateExam($student, $quiz);
    $quiz->update(['awards_certificate' => $enabled, 'revision' => $revision]);
    $this->actingAs($student)->postJson("/api/v1/certificates/exams/{$quiz->id}")->assertStatus($status);
    $this->assertDatabaseCount('certificates', 0);
})->with([[false, 1, 404], [true, 2, 422]]);

test('unenrolled students and staff cannot claim course or exam certificates', function () {
    [$student, $teacher, $quiz] = certificateFixture();
    DB::table('group_students')->where('student_id', $student->id)->update(['left_at' => now()]);
    $this->actingAs($student)->postJson("/api/v1/certificates/courses/{$quiz->lesson->course_id}")->assertNotFound();
    $this->postJson("/api/v1/certificates/exams/{$quiz->id}")->assertNotFound();
    $this->actingAs($teacher)->postJson("/api/v1/certificates/exams/{$quiz->id}")->assertForbidden();
    $this->assertDatabaseCount('certificates', 0);
});

test('certificate downloads are private and revocation changes public verification', function () {
    [$student, $teacher] = certificateFixture();
    $certificate = Certificate::factory()->create(['user_id' => $student->id]);
    $foreign = User::factory()->create();
    $this->actingAs($foreign)->getJson("/api/v1/certificates/{$certificate->id}/download?format=post")->assertNotFound();
    $this->actingAs($student)->postJson("/api/v1/certificates/{$certificate->id}/revoke", ['reason' => 'Fake'])->assertForbidden();
    $this->actingAs($teacher)->postJson("/api/v1/certificates/{$certificate->id}/revoke", ['reason' => 'private-reason'])->assertOk();
    $this->getJson(route('certificates.verify', $certificate->code))->assertOk()->assertJsonPath('valid', false)->assertDontSee('private-reason');
    $this->actingAs($student)->getJson("/api/v1/certificates/{$certificate->id}/download?format=pdf")->assertStatus(410);
});

test('certificate renderer returns real PDF and properly sized social images', function () {
    $student = User::factory()->create();
    $certificate = Certificate::factory()->create(['user_id' => $student->id, 'recipient_name' => 'Mashhurbek O‘quvchi']);
    $this->actingAs($student);
    foreach (['post' => [1080, 1350], 'story' => [1080, 1920]] as $format => $dimensions) {
        $response = $this->get("/api/v1/certificates/{$certificate->id}/download?format=$format")->assertOk()->assertHeader('Content-Type', 'image/png');
        $info = getimagesizefromstring($response->getContent());
        expect([$info[0], $info[1]])->toBe($dimensions);
    }
    $response = $this->get("/api/v1/certificates/{$certificate->id}/download?format=pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

test('QR verification URL uses the trusted application origin and hides private student data', function () {
    config()->set('app.url', 'https://quran.example.test');
    $certificate = Certificate::factory()->create();
    expect(app(CertificateService::class)->verificationUrl($certificate))->toBe('https://quran.example.test/certificates/verify/'.$certificate->code);
    $this->withServerVariables(['HTTP_HOST' => 'attacker.example'])->getJson('/certificates/verify/'.$certificate->code)
        ->assertOk()->assertJsonMissingPath('certificate.email')->assertJsonMissingPath('certificate.revocation_reason');
});

test('certificate list scopes recipients and renders web downloads', function () {
    [$student, $teacher] = certificateFixture();
    $own = Certificate::factory()->create(['user_id' => $student->id]);
    Certificate::factory()->create(['recipient_name' => 'Foreign recipient']);
    $this->actingAs($student)->getJson('/api/v1/certificates')->assertOk()->assertJsonCount(1, 'certificates.data')->assertDontSee('Foreign recipient');
    $this->get(route('certificates.index'))->assertOk()->assertSee('PDF yuklab olish')->assertSee($own->code);
    $this->actingAs($teacher)->getJson('/api/v1/certificates')->assertOk()->assertJsonCount(2, 'certificates.data')->assertJsonPath('can_manage', true);
    $this->getJson("/api/v1/certificates/{$own->id}/download?format=invalid")->assertUnprocessable();
});

test('course certificate requires mentor approved assignments in addition to quiz scores', function () {
    [$student, $teacher, $quiz] = certificateFixture();
    passedCertificateExam($student, $quiz);
    DB::table('lesson_completions')->insert(['user_id' => $student->id, 'lesson_id' => $quiz->lesson_id, 'completed_at' => now()]);
    $group = Group::where('course_id', $quiz->lesson->course_id)->firstOrFail();
    $assignment = Assignment::create(['group_id' => $group->id, 'lesson_id' => $quiz->lesson_id, 'created_by' => $teacher->id, 'title' => 'Final recitation', 'status' => 'published']);
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'attempt' => 1, 'status' => 'graded', 'final_score' => 95, 'submitted_at' => now()]);
    $this->actingAs($student)->postJson("/api/v1/certificates/courses/{$quiz->lesson->course_id}")->assertUnprocessable();
    $submission->reviews()->create(['mentor_id' => $teacher->id, 'action' => 'approve', 'score' => 95, 'reviewed_at' => now()]);
    $submission->update(['final_score' => 69]);
    $this->postJson("/api/v1/certificates/courses/{$quiz->lesson->course_id}")->assertUnprocessable();
    $submission->update(['final_score' => 70]);
    $group->update(['status' => 'completed']);
    $this->postJson("/api/v1/certificates/courses/{$quiz->lesson->course_id}")->assertOk();
});

test('untrusted certificate text is escaped in verification HTML', function () {
    $certificate = Certificate::factory()->create(['recipient_name' => '<script>bad()</script>']);
    $this->get(route('certificates.verify', $certificate->code))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>bad()</script>', false);
});
