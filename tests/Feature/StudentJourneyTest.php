<?php

use App\Jobs\AnalyzeRecitationJob;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use App\Models\RepetitionCard;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** @return array{User, User, Lesson, Assignment, Ayah} */
function studentJourneyFixture(): array
{
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $mentor = User::where('email', 'mentor@markaz.uz')->firstOrFail();
    $course = Course::create(['name' => 'Journey course', 'level' => 'beginner']);
    $group = Group::create(['name' => 'Journey group', 'course_id' => $course->id, 'mentor_id' => $mentor->id, 'status' => 'active']);
    $group->students()->attach($student, ['joined_at' => now()]);
    $lesson = Lesson::create(['course_id' => $course->id, 'title' => 'Journey lesson', 'status' => 'published']);
    $assignment = Assignment::create(['group_id' => $group->id, 'lesson_id' => $lesson->id, 'created_by' => $mentor->id, 'title' => 'Journey homework', 'status' => 'published', 'requires_audio' => true, 'surah_number' => 1, 'ayah_from' => 1, 'ayah_to' => 2]);
    $surah = Surah::create(['number' => 1, 'name_arabic' => 'الفاتحة', 'name_uz' => 'Fotiha', 'name_en' => 'Al-Fatihah', 'ayah_count' => 7, 'revelation_type' => 'meccan']);
    $ayah = Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 1, 'juz' => 1, 'page' => 1, 'text_arabic' => 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ']);
    Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 2, 'number_global' => 2, 'juz' => 1, 'page' => 1, 'text_arabic' => 'الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ']);

    return [$student, $mentor, $lesson, $assignment, $ayah];
}

test('student follows a persisted lesson practice submission feedback and repetition journey', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, $mentor, $lesson, $assignment, $ayah] = studentJourneyFixture();
    Queue::fake([AnalyzeRecitationJob::class]);
    Storage::fake('local');
    config(['filesystems.default' => 'local']);
    $original = $ayah->text_arabic;

    $this->actingAs($student)->getJson('/api/v1/student/journey')->assertOk()->assertJsonPath('stage', 'learn')->assertJsonPath('action.id', $lesson->id);
    $this->getJson("/api/v1/lessons/{$lesson->id}")->assertOk()->assertJsonPath('journey.can_complete', true)->assertJsonPath('journey.completed', false);
    $this->postJson("/api/v1/student/lessons/{$lesson->id}/complete")->assertOk();
    $this->postJson("/api/v1/student/lessons/{$lesson->id}/complete")->assertOk();
    $this->assertDatabaseCount('lesson_completions', 1);
    $this->getJson('/api/v1/student/journey')->assertJsonPath('stage', 'practice')->assertJsonPath('steps.0.state', 'done');
    $this->postJson("/api/v1/student/assignments/{$assignment->id}/practice")->assertOk();
    $this->postJson("/api/v1/student/assignments/{$assignment->id}/practice")->assertOk();
    $this->assertDatabaseCount('assignment_practices', 1);
    $this->getJson("/api/v1/assignments/{$assignment->id}")->assertOk()->assertJsonPath('journey.practiced', true)->assertJsonPath('journey.can_practice', true);
    $this->getJson('/api/v1/student/journey')->assertJsonPath('stage', 'submit');
    $upload = $this->postJson("/api/v1/assignments/{$assignment->id}/submit", ['audio' => File::fake()->createWithContent('recitation.wav', str_repeat('RIFF', 100))])->assertCreated();
    $id = $upload->json('submission.id');
    $this->getJson('/api/v1/student/journey')->assertJsonPath('stage', 'await_review')->assertJsonPath('action.id', $id)->assertJsonPath('counts.waiting_reviews', 1);
    $this->postJson("/api/v1/student/submissions/{$id}/feedback-read")->assertUnprocessable();
    $this->actingAs($mentor)->postJson("/api/v1/submissions/{$id}/review", ['action' => 'resubmit', 'comment' => 'Talaffuzni takrorlang', 'segments' => [['ayah_from' => 1, 'ayah_to' => 1, 'error_type' => 'pronunciation', 'comment' => 'Qayta mashq']]])->assertCreated();
    $this->actingAs($student)->getJson('/api/v1/student/journey')->assertJsonPath('stage', 'feedback');
    $this->postJson("/api/v1/student/submissions/{$id}/feedback-read")->assertOk()->assertJsonPath('revision_added', 1);
    $this->postJson("/api/v1/student/submissions/{$id}/feedback-read")->assertOk()->assertJsonPath('revision_added', 0);
    $this->getJson('/api/v1/student/journey')->assertJsonPath('stage', 'submit')->assertJsonPath('counts.due_revision', 1);
    $this->getJson('/api/v1/learning-path')->assertOk()->assertJsonPath('assignments.0.id', $assignment->id);
    $second = $this->postJson("/api/v1/assignments/{$assignment->id}/submit", ['audio' => File::fake()->createWithContent('retry.wav', str_repeat('RIFF', 100))])->assertCreated()->assertJsonPath('submission.attempt', 2)->json('submission.id');
    $this->actingAs($mentor)->postJson("/api/v1/submissions/{$second}/review", ['action' => 'approve', 'score' => 95, 'comment' => 'Yaxshi'])->assertCreated();
    $this->actingAs($student)->getJson('/api/v1/student/journey')->assertJsonPath('stage', 'feedback')->assertJsonPath('submission.score', 95);
    $this->postJson("/api/v1/student/submissions/{$second}/feedback-read")->assertOk()->assertJsonPath('revision_added', 0);
    $this->getJson('/api/v1/student/journey')->assertJsonPath('stage', 'repeat')->assertJsonPath('counts.pending_assignments', 0);
    $card = RepetitionCard::firstOrFail();
    $this->postJson("/api/v1/learning-path/cards/{$card->id}/review", ['rating' => 'good', 'version' => 0])->assertOk();
    expect($card->fresh()->due_at->isFuture())->toBeTrue();
    $this->getJson('/api/v1/student/journey')->assertJsonPath('counts.due_revision', 0);
    expect($ayah->fresh()->text_arabic)->toBe($original);
    $this->assertDatabaseCount('submission_feedback_reads', 2);
    Queue::assertPushed(AnalyzeRecitationJob::class, 2);
    Storage::disk('local')->assertExists(Submission::findOrFail($second)->files()->firstOrFail()->path);
});

test('journey excludes foreign unpublished inactive and departed group content', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, $mentor, $lesson, $assignment] = studentJourneyFixture();
    $this->getJson('/api/v1/student/journey')->assertUnauthorized();
    $this->actingAs($mentor)->getJson('/api/v1/student/journey')->assertForbidden();
    $this->postJson("/api/v1/student/lessons/{$lesson->id}/complete")->assertForbidden();
    $foreign = User::factory()->create();
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $foreign->id, 'status' => 'resubmit', 'attempt' => 1, 'submitted_at' => now()]);
    $this->actingAs($student)->postJson("/api/v1/student/submissions/{$submission->id}/feedback-read")->assertNotFound();
    $assignment->group->students()->updateExistingPivot($student->id, ['left_at' => now()]);
    $this->getJson('/api/v1/student/journey')->assertOk()->assertJsonPath('assignment', null)->assertJsonPath('lesson', null);
    $this->postJson("/api/v1/student/lessons/{$lesson->id}/complete")->assertNotFound();
    $this->postJson("/api/v1/student/assignments/{$assignment->id}/practice")->assertNotFound();
    $assignment->group->students()->updateExistingPivot($student->id, ['left_at' => null]);
    $assignment->group->update(['status' => 'archived']);
    $this->postJson("/api/v1/student/assignments/{$assignment->id}/practice")->assertNotFound();
    $assignment->group->update(['status' => 'active']);
    $assignment->update(['status' => 'draft']);
    $lesson->update(['status' => 'draft']);
    $this->getJson('/api/v1/student/journey')->assertJsonPath('assignment', null)->assertJsonPath('lesson', null);
    $this->postJson("/api/v1/student/lessons/{$lesson->id}/complete")->assertNotFound();
    $this->postJson("/api/v1/student/assignments/{$assignment->id}/practice")->assertNotFound();
    $this->assertDatabaseCount('lesson_completions', 0);
    $this->assertDatabaseCount('assignment_practices', 0);
    $this->assertDatabaseCount('submission_feedback_reads', 0);
});

test('latest attempt determines pending assignments even when older attempts were graded', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, , , $assignment] = studentJourneyFixture();
    foreach (['graded', 'resubmit'] as $index => $status) {
        Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => $status, 'attempt' => $index + 1, 'submitted_at' => now()]);
    }
    $this->actingAs($student)->getJson('/api/v1/student/journey')->assertOk()->assertJsonPath('stage', 'submit')->assertJsonPath('counts.pending_assignments', 1);
    $this->getJson('/api/v1/learning-path')->assertOk()->assertJsonPath('assignments.0.id', $assignment->id);
    Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'submitted', 'attempt' => 3, 'submitted_at' => now()]);
    $this->getJson('/api/v1/student/journey')->assertJsonPath('stage', 'await_review')->assertJsonPath('counts.pending_assignments', 0);
    $this->getJson('/api/v1/learning-path')->assertJsonCount(0, 'assignments');
});

test('feedback enrolment preserves existing repetition schedules and ignores AI hypotheses', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, $mentor, , $assignment, $ayah] = studentJourneyFixture();
    $card = RepetitionCard::factory()->create(['user_id' => $student->id, 'ayah_id' => $ayah->id, 'due_at' => now()->addDays(6), 'interval_days' => 6, 'version' => 3]);
    $due = $card->due_at;
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'resubmit', 'attempt' => 1, 'submitted_at' => now()]);
    $ai = $submission->aiReviews()->create(['status' => 'completed', 'provider' => 'test']);
    $ai->segments()->create(['ayah_number' => 2, 'error_type' => 'missing_word', 'is_correct' => false]);
    $review = $submission->reviews()->create(['mentor_id' => $mentor->id, 'action' => 'resubmit', 'reviewed_at' => now()]);
    $review->segments()->create(['ayah_from' => 1, 'ayah_to' => 1, 'error_type' => 'pronunciation', 'comment' => 'Practice']);
    $this->actingAs($student)->postJson("/api/v1/student/submissions/{$submission->id}/feedback-read")->assertOk()->assertJsonPath('revision_added', 0);
    expect($card->fresh()->due_at->equalTo($due))->toBeTrue();
    expect($card->fresh()->version)->toBe(3);
    $this->assertDatabaseCount('repetition_cards', 1);
    $newReview = $submission->reviews()->create(['mentor_id' => $mentor->id, 'action' => 'resubmit', 'reviewed_at' => now()->addMinute()]);
    $newReview->segments()->create(['ayah_from' => 2, 'error_type' => 'pronunciation', 'comment' => 'New feedback']);
    $this->getJson('/api/v1/student/journey')->assertJsonPath('stage', 'feedback');
    $this->postJson("/api/v1/student/submissions/{$submission->id}/feedback-read")->assertOk()->assertJsonPath('revision_added', 1);
    $this->assertDatabaseCount('submission_feedback_reads', 2);
});

test('revision alone does not mark unrelated learning milestones as completed', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, , , $assignment, $ayah] = studentJourneyFixture();
    $assignment->group->students()->detach($student);
    RepetitionCard::factory()->create(['user_id' => $student->id, 'ayah_id' => $ayah->id, 'due_at' => now()->subMinute()]);
    $this->actingAs($student)->getJson('/api/v1/student/journey')->assertOk()->assertJsonPath('stage', 'repeat')
        ->assertJsonPath('steps.0.state', 'pending')->assertJsonPath('steps.1.state', 'pending')->assertJsonPath('steps.2.state', 'pending')->assertJsonPath('steps.3.state', 'pending');
});

test('web journey actions persist and return to the next useful screen', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, $mentor, $lesson, $assignment] = studentJourneyFixture();
    $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee('Bugungi o‘quv yo‘lingiz')->assertSee(route('education.lessons.show', $lesson));
    $this->get(route('education.lessons.show', $lesson))->assertOk()->assertSee(route('student.lesson.complete', $lesson));
    $this->post(route('student.lesson.complete', $lesson))->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk()->assertSee('Endi mashq qiling');
    $this->get(route('homework.show', $assignment))->assertOk()->assertSee(route('student.practice.complete', $assignment));
    $this->post(route('student.practice.complete', $assignment))->assertRedirect(route('homework.show', $assignment));
    $submission = Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'graded', 'attempt' => 1, 'submitted_at' => now()]);
    $submission->reviews()->create(['mentor_id' => $mentor->id, 'score' => 95, 'action' => 'approve', 'reviewed_at' => now()]);
    $this->get(route('homework.submission', $submission))->assertOk()->assertSee(route('student.feedback.read', $submission));
    $this->post(route('student.feedback.read', $submission))->assertRedirect(route('learning.index'));
    $this->assertDatabaseCount('lesson_completions', 1);
    $this->assertDatabaseCount('assignment_practices', 1);
    $this->assertDatabaseCount('submission_feedback_reads', 1);
});

test('unread feedback on one assignment takes priority without losing latest attempts on other assignments', function () {
    $this->seed(DatabaseSeeder::class);
    [$student, $mentor, , $first] = studentJourneyFixture();
    $second = $first->replicate();
    $second->title = 'Another assignment';
    $second->save();
    foreach ([$first, $second] as $assignment) {
        Submission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'graded', 'attempt' => 1, 'submitted_at' => now()]);
    }
    $waiting = Submission::create(['assignment_id' => $first->id, 'student_id' => $student->id, 'status' => 'submitted', 'attempt' => 2, 'submitted_at' => now()]);
    $feedback = Submission::create(['assignment_id' => $second->id, 'student_id' => $student->id, 'status' => 'graded', 'attempt' => 2, 'submitted_at' => now()]);
    $feedback->reviews()->create(['mentor_id' => $mentor->id, 'action' => 'approve', 'score' => 90, 'reviewed_at' => now()]);
    $this->actingAs($student)->getJson('/api/v1/student/journey')->assertOk()->assertJsonPath('stage', 'feedback')->assertJsonPath('action.id', $feedback->id)->assertJsonPath('counts.waiting_reviews', 1)->assertJsonPath('counts.pending_assignments', 0);
    $this->postJson("/api/v1/student/submissions/{$feedback->id}/feedback-read")->assertOk();
    $this->getJson('/api/v1/student/journey')->assertJsonPath('stage', 'await_review')->assertJsonPath('action.id', $waiting->id);
});
