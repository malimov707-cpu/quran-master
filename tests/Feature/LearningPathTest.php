<?php

use App\Models\AiReview;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use App\Models\RepetitionCard;
use App\Models\RepetitionReview;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function learningPathAyah(): Ayah
{
    $surah = Surah::create(['number' => 1, 'name_arabic' => 'الفاتحة', 'name_uz' => 'Fotiha', 'name_en' => 'Al-Fatihah', 'ayah_count' => 7, 'revelation_type' => 'meccan']);

    return Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 1, 'juz' => 1, 'page' => 1, 'text_arabic' => 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ']);
}

test('learning path requires student permission and enrolment does not change canonical text', function () {
    $this->seed(DatabaseSeeder::class);
    $ayah = learningPathAyah();
    $original = $ayah->text_arabic;
    $this->getJson('/api/v1/learning-path')->assertUnauthorized();
    $admin = User::where('email', 'admin@quronai.uz')->firstOrFail();
    $this->actingAs($admin)->getJson('/api/v1/learning-path')->assertForbidden();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $first = $this->actingAs($student)->postJson('/api/v1/learning-path/cards', ['ayah_id' => $ayah->id])->assertCreated()->assertJsonPath('card.version', 0);
    $this->postJson('/api/v1/learning-path/cards', ['ayah_id' => $ayah->id])->assertCreated()->assertJsonPath('card.id', $first->json('card.id'));
    $this->assertDatabaseCount('repetition_cards', 1);
    expect($ayah->fresh()->text_arabic)->toBe($original);
    $this->postJson('/api/v1/learning-path/cards', ['ayah_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('ayah_id');
});

test('self assessment schedules an actual next review and persists one history record', function (string $rating, int $minutes, int $days) {
    $this->travelTo(now()->startOfSecond());
    $this->seed(DatabaseSeeder::class);
    $ayah = learningPathAyah();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $card = RepetitionCard::factory()->create(['user_id' => $student->id, 'ayah_id' => $ayah->id]);
    $this->actingAs($student)->postJson("/api/v1/learning-path/cards/{$card->id}/review", ['rating' => $rating, 'version' => 0])->assertOk();
    expect($card->fresh()->due_at->equalTo(now()->addDays($days)->addMinutes($minutes)))->toBeTrue();
    $this->assertDatabaseHas('repetition_reviews', ['repetition_card_id' => $card->id, 'rating' => $rating, 'version' => 1, 'interval_days' => $days]);
    $this->postJson("/api/v1/learning-path/cards/{$card->id}/review", ['rating' => $rating, 'version' => 0])->assertUnprocessable();
    $this->assertDatabaseCount('repetition_reviews', 1);
})->with([['again', 10, 0], ['hard', 0, 1], ['good', 0, 1], ['easy', 0, 3]]);

test('successful repetition extends intervals and difficulty shortens them', function () {
    $this->seed(DatabaseSeeder::class);
    $ayah = learningPathAyah();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $card = RepetitionCard::factory()->create(['user_id' => $student->id, 'ayah_id' => $ayah->id, 'interval_days' => 3, 'successful_reviews' => 2]);
    $this->actingAs($student)->postJson("/api/v1/learning-path/cards/{$card->id}/review", ['rating' => 'good', 'version' => 0])->assertOk()->assertJsonPath('card.interval_days', 6);
    $this->travel(6)->days();
    $this->postJson("/api/v1/learning-path/cards/{$card->id}/review", ['rating' => 'hard', 'version' => 1])->assertOk()->assertJsonPath('card.interval_days', 1)->assertJsonPath('card.successful_reviews', 0);
});

test('students cannot view or review another students repetition data', function () {
    $this->seed(DatabaseSeeder::class);
    $ayah = learningPathAyah();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $card = RepetitionCard::factory()->create(['ayah_id' => $ayah->id]);
    $this->actingAs($student)->getJson('/api/v1/learning-path')->assertOk()->assertJsonPath('total_cards', 0)->assertJsonPath('due_count', 0);
    $this->postJson("/api/v1/learning-path/cards/{$card->id}/review", ['rating' => 'easy', 'version' => 0])->assertNotFound();
    $this->assertDatabaseCount('repetition_reviews', 0);
    $this->postJson("/api/v1/learning-path/cards/{$card->id}/review", ['rating' => 'mastered', 'version' => 0])->assertUnprocessable()->assertJsonValidationErrors('rating');
});

test('learning path keeps AI hypotheses distinct from mentor errors and renders web review', function () {
    $this->seed(DatabaseSeeder::class);
    $ayah = learningPathAyah();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $assignment = Assignment::create(['title' => 'Own task', 'created_by' => $mentor->id, 'group_id' => Group::firstOrFail()->id, 'surah_number' => 1, 'ayah_from' => 1, 'ayah_to' => 1, 'status' => 'published']);
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'graded', 'attempt' => 1, 'submitted_at' => now()]);
    $review = $submission->reviews()->create(['mentor_id' => $mentor->id, 'score' => 80, 'action' => 'approve', 'reviewed_at' => now()]);
    $review->segments()->create(['ayah_from' => 1, 'ayah_to' => 1, 'error_type' => 'pronunciation', 'comment' => 'Confirmed']);
    $ai = AiReview::create(['submission_id' => $submission->id, 'status' => 'completed', 'provider' => 'test']);
    $ai->segments()->create(['ayah_number' => 1, 'error_type' => 'missing_word', 'is_correct' => false]);
    $response = $this->actingAs($student)->getJson('/api/v1/learning-path')->assertOk()->assertJsonCount(1, 'mentor_errors')->assertJsonCount(1, 'ai_hypotheses');
    expect($response->json('mentor_errors.0.error_type'))->toBe('pronunciation');
    expect($response->json('ai_hypotheses.0.error_type'))->toBe('missing_word');
    $this->post(route('learning.store'), ['ayah_id' => $ayah->id])->assertRedirect(route('learning.index'));
    $this->get(route('learning.index'))->assertOk()->assertSee('Shaxsiy o‘quv rejam')->assertSee($ayah->text_arabic);
    $card = RepetitionCard::firstOrFail();
    $this->from(route('learning.index'))->post(route('learning.review', $card), ['rating' => 'good', 'version' => 0])->assertRedirect(route('learning.index'));
    expect(RepetitionReview::count())->toBe(1);
});

test('unassigned mentors cannot read audio AI or grade a foreign group submission', function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local');
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $group = Group::create(['name' => 'Foreign group', 'course_id' => Course::firstOrFail()->id]);
    $assignment = Assignment::create(['title' => 'Foreign task', 'group_id' => $group->id, 'created_by' => $mentor->id, 'status' => 'published']);
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'submitted', 'attempt' => 1, 'submitted_at' => now()]);
    Storage::disk('local')->put('private.wav', 'private audio');
    $file = $submission->files()->create(['disk' => 'local', 'path' => 'private.wav', 'mime_type' => 'audio/wav', 'original_name' => 'private.wav', 'size_bytes' => 13]);
    $this->actingAs($mentor)->getJson("/api/v1/submissions/{$submission->id}")->assertForbidden();
    $this->getJson("/api/v1/submissions/{$submission->id}/ai-review")->assertForbidden();
    $this->get(route('api.submissions.audio', [$submission, $file]))->assertForbidden();
    $this->get(route('mentor.review-studio', $submission))->assertForbidden();
    $this->postJson("/api/v1/submissions/{$submission->id}/review", ['action' => 'approve', 'score' => 99])->assertForbidden();
    $this->post(route('mentor.review', $submission), ['action' => 'approve', 'score' => 99])->assertForbidden();
    $this->getJson('/api/v1/submissions/pending')->assertOk()->assertJsonCount(0);
    $this->assertDatabaseCount('mentor_reviews', 0);
    $this->actingAs($student)->get(route('api.submissions.audio', [$submission, $file]))->assertOk();
});
