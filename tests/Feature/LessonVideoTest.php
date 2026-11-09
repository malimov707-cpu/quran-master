<?php

use App\Models\Group;
use App\Models\LessonMaterial;
use App\Models\LessonQuiz;
use App\Models\LessonVideoUpload;
use App\Models\User;
use App\Models\VideoProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function videoFixture(): array
{
    test()->seed(DatabaseSeeder::class);
    config()->set('lesson_video.disk', 'local');
    Storage::fake('local');
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $teacher = User::where('email', 'ustoz@markaz.uz')->firstOrFail();
    $lesson = LessonQuiz::factory()->create()->lesson;
    $group = Group::create(['name' => 'Video group', 'course_id' => $lesson->course_id, 'status' => 'active']);
    $group->students()->attach($student->id, ['joined_at' => now()]);
    $material = LessonMaterial::create(['lesson_id' => $lesson->id, 'title' => 'Video', 'type' => 'video', 'path' => 'lesson-videos/test.mp4',
        'payload' => ['provider' => 'upload', 'version' => (string) Str::uuid(), 'disk' => 'local', 'mime_type' => 'video/mp4', 'duration_seconds' => 100, 'size_bytes' => 100, 'offline_allowed' => true]]);
    Storage::disk('local')->put($material->path, str_repeat('v', 100));

    return [$student, $teacher, $lesson, $material];
}

function videoReport(User $user, array $ranges = [], int $position = 0): array
{
    return ['owner_id' => $user->id, 'position_seconds' => $position, 'ranges' => $ranges, 'observed_at' => now()->toISOString()];
}

test('video manifests and watch reports require authentication and enrolled access', function () {
    [$student, $teacher, $lesson, $material] = videoFixture();
    $this->getJson("/api/v1/lessons/{$lesson->id}/videos")->assertUnauthorized();
    $this->actingAs($student)->getJson("/api/v1/lessons/{$lesson->id}/videos")->assertOk()->assertJsonPath('videos.0.title', 'Video');
    DB::table('group_students')->where('student_id', $student->id)->update(['left_at' => now()]);
    $this->getJson("/api/v1/lessons/{$lesson->id}/videos")->assertNotFound();
    $this->postJson("/api/v1/videos/{$material->id}/progress", videoReport($student))->assertNotFound();
});

test('signed private videos support range requests and reject altered or expired links', function () {
    [$student, $teacher, $lesson, $material] = videoFixture();
    $url = $this->actingAs($student)->getJson("/api/v1/lessons/{$lesson->id}/videos")->json('videos.0.url');
    $this->withHeader('Range', 'bytes=10-19')->get($url)->assertStatus(206)->assertHeader('Content-Range', 'bytes 10-19/100');
    $this->get($url.'&download=1')->assertForbidden();
    $this->travel(8)->hours();
    $this->get($url)->assertForbidden();
    $this->travelBack();
    $this->get('/lesson-video/'.$material->id.'/'.$student->id)->assertForbidden();
});

test('watch tracking merges viewed coverage without awarding skipped or repeated seconds', function () {
    [$student, $teacher, $lesson, $material] = videoFixture();
    $this->actingAs($student)->postJson("/api/v1/videos/{$material->id}/progress", videoReport($student, [], 99))->assertOk()->assertJsonPath('progress.completed', false)->assertJsonPath('progress.watched_seconds', 0);
    $report = videoReport($student, [[0, 30], [20, 50]], 50);
    $this->postJson("/api/v1/videos/{$material->id}/progress", $report)->assertOk()->assertJsonPath('progress.watched_seconds', 50);
    $this->postJson("/api/v1/videos/{$material->id}/progress", $report)->assertOk()->assertJsonPath('progress.watched_seconds', 50);
    $this->postJson("/api/v1/videos/{$material->id}/progress", videoReport($student, [[50, 90]], 90))->assertOk()->assertJsonPath('progress.completed', true)->assertJsonPath('progress.percent', 90);
    $this->getJson("/api/v1/lessons/{$lesson->id}/videos")->assertJsonPath('videos.0.progress.position_seconds', 90);
    $this->assertDatabaseCount('video_progress', 1);
    $this->assertDatabaseCount('lesson_completions', 0);
});

test('old offline samples merge coverage without replacing a newer resume point', function () {
    [$student, $teacher, $lesson, $material] = videoFixture();
    $this->actingAs($student)->postJson("/api/v1/videos/{$material->id}/progress", videoReport($student, [[80, 90]], 90))->assertOk();
    $old = videoReport($student, [[0, 30]], 30);
    $old['observed_at'] = now()->subDay()->toISOString();
    $this->postJson("/api/v1/videos/{$material->id}/progress", $old)->assertOk()->assertJsonPath('progress.position_seconds', 90)->assertJsonPath('progress.watched_seconds', 40);
});

test('invalid reports cannot corrupt progress or cross account boundaries', function () {
    [$student, $teacher, $lesson, $material] = videoFixture();
    foreach ([['ranges' => [[30, 10]]], ['ranges' => [[0, 999999]]], ['owner_id' => $teacher->id], ['observed_at' => now()->addDay()->toISOString()]] as $changes) {
        $this->actingAs($student)->postJson("/api/v1/videos/{$material->id}/progress", $changes + videoReport($student))->assertUnprocessable();
    }
    $this->assertDatabaseCount('video_progress', 0);
});

test('chunked upload bypasses single request size limits and finalization is idempotent', function () {
    [$student, $teacher, $lesson] = videoFixture();
    $bytes = hex2bin('000000206674797069736f6d0000020069736f6d69736f32617663316d703431').str_repeat('a', 524400);
    $this->actingAs($teacher);
    $uploadId = $this->postJson("/api/v1/lessons/{$lesson->id}/video-uploads", ['title' => 'New video', 'size_bytes' => strlen($bytes), 'duration_seconds' => 200, 'offline_allowed' => true])->assertCreated()->json('upload_id');
    $this->postJson("/api/v1/video-uploads/{$uploadId}/finish")->assertUnprocessable();
    foreach (str_split($bytes, 524288) as $index => $part) {
        $this->postJson("/api/v1/video-uploads/{$uploadId}/chunks", ['index' => $index, 'chunk' => UploadedFile::fake()->createWithContent('part.bin', $part)])->assertOk();
    }
    $id = $this->postJson("/api/v1/video-uploads/{$uploadId}/finish")->assertCreated()->json('id');
    $this->postJson("/api/v1/video-uploads/{$uploadId}/finish")->assertCreated()->assertJsonPath('id', $id);
    $material = LessonMaterial::findOrFail($id);
    expect($material->payload['size_bytes'])->toBe(strlen($bytes))->and($material->payload['offline_allowed'])->toBeTrue();
    Storage::disk('local')->assertExists($material->path);
    expect(Storage::disk('local')->get($material->path))->toBe($bytes);
});

test('staff upload sessions reject foreign owners incorrect chunks and non-video payloads', function () {
    [$student, $teacher, $lesson] = videoFixture();
    $upload = LessonVideoUpload::factory()->create(['user_id' => $teacher->id, 'lesson_id' => $lesson->id, 'size_bytes' => 32]);
    $this->actingAs($student)->postJson("/api/v1/video-uploads/{$upload->id}/finish")->assertForbidden();
    $ceo = User::where('email', 'malimov707@gmail.com')->firstOrFail();
    $this->actingAs($ceo)->postJson("/api/v1/video-uploads/{$upload->id}/finish")->assertNotFound();
    $this->actingAs($teacher)->postJson("/api/v1/video-uploads/{$upload->id}/chunks", ['index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('x', 'too short')])->assertUnprocessable();
    $this->postJson("/api/v1/video-uploads/{$upload->id}/chunks", ['index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('x', str_repeat('x', 32))])->assertOk();
    $this->postJson("/api/v1/video-uploads/{$upload->id}/finish")->assertUnprocessable();
    expect($upload->fresh()->lesson_material_id)->toBeNull();
});

test('youtube embeds accept only canonical video IDs and never enable offline download', function () {
    [$student, $teacher, $lesson] = videoFixture();
    $this->actingAs($teacher)->postJson("/api/v1/lessons/{$lesson->id}/videos/youtube", ['title' => 'YouTube', 'url' => 'https://youtu.be/M7lc1UVf-VE', 'duration_seconds' => 180, 'offline_allowed' => true])->assertCreated();
    $this->postJson("/api/v1/lessons/{$lesson->id}/videos/youtube", ['title' => 'Bad host', 'url' => 'https://youtube.com.attacker.test/watch?v=M7lc1UVf-VE', 'duration_seconds' => 180])->assertUnprocessable();
    $this->actingAs($student)->getJson("/api/v1/lessons/{$lesson->id}/videos")->assertOk()->assertJsonPath('videos.1.provider', 'youtube')->assertJsonPath('videos.1.offline_allowed', false)->assertJsonPath('videos.1.download_url', null);
});

test('cleanup removes expired upload parts while keeping published videos', function () {
    [$student, $teacher, $lesson, $material] = videoFixture();
    $upload = LessonVideoUpload::factory()->create(['user_id' => $teacher->id, 'lesson_id' => $lesson->id, 'expires_at' => now()->subHour(), 'lesson_material_id' => $material->id]);
    Storage::disk('local')->put('video-uploads/'.$upload->id.'/0', 'partial');
    $this->artisan('videos:cleanup-uploads')->assertSuccessful();
    Storage::disk('local')->assertMissing('video-uploads/'.$upload->id.'/0');
    Storage::disk('local')->assertExists($material->path);
});

test('private S3 delivery keeps uploaded objects private and uses temporary media URLs', function () {
    [$student, $teacher, $lesson] = videoFixture();
    config()->set('lesson_video.disk', 's3');
    $disk = Storage::fake('s3');
    $disk->buildTemporaryUrlsUsing(fn (string $path, $expiration, array $options): string => 'https://s3.example.test/private-video.mp4?signature=temporary');
    $bytes = hex2bin('000000206674797069736f6d0000020069736f6d69736f32617663316d703431').str_repeat('a', 100);
    $upload = LessonVideoUpload::factory()->create(['user_id' => $teacher->id, 'lesson_id' => $lesson->id, 'size_bytes' => strlen($bytes)]);
    Storage::disk('local')->put('video-uploads/'.$upload->id.'/0', $bytes);
    $id = $this->actingAs($teacher)->postJson("/api/v1/video-uploads/{$upload->id}/finish")->assertCreated()->json('id');
    $material = LessonMaterial::findOrFail($id);
    $disk->assertExists($material->path);
    expect($disk->getVisibility($material->path))->toBe('private');
    $url = $this->actingAs($student)->getJson("/api/v1/lessons/{$lesson->id}/videos")->json('videos.1.url');
    $this->get($url)->assertRedirect('https://s3.example.test/private-video.mp4?signature=temporary');
});

test('video access revocation blocks issued media links and offline permissions are enforced', function () {
    [$student, $teacher, $lesson, $material] = videoFixture();
    $url = $this->actingAs($student)->getJson("/api/v1/lessons/{$lesson->id}/videos")->json('videos.0.download_url');
    $material->update(['payload' => array_replace($material->payload, ['offline_allowed' => false])]);
    $this->get($url)->assertForbidden();
    $this->getJson("/api/v1/lessons/{$lesson->id}/videos")->assertJsonPath('videos.0.download_url', null);
    $student->update(['is_active' => false]);
    $this->get($url)->assertForbidden();
});

test('staff can delete video files and tracking while students cannot', function () {
    [$student, $teacher, $lesson, $material] = videoFixture();
    VideoProgress::factory()->create(['user_id' => $student->id, 'lesson_material_id' => $material->id]);
    $this->actingAs($student)->deleteJson("/api/v1/videos/{$material->id}")->assertForbidden();
    $this->actingAs($teacher)->deleteJson("/api/v1/videos/{$material->id}")->assertOk();
    Storage::disk('local')->assertMissing($material->path);
    $this->assertDatabaseMissing('lesson_materials', ['id' => $material->id]);
    $this->assertDatabaseCount('video_progress', 0);
});

test('watch progress is included without changing mentor grades and the web lesson renders video controls', function () {
    [$student, $teacher, $lesson, $material] = videoFixture();
    $this->actingAs($student)->postJson("/api/v1/videos/{$material->id}/progress", videoReport($student, [[0, 90]], 90))->assertOk();
    $this->getJson('/api/v1/progress/me')->assertOk()->assertJsonPath('videos.completed', 1)->assertJsonPath('videos.watched_seconds', 90)->assertJsonPath('average_score', null);
    $this->get(route('education.lessons.show', $lesson))->assertOk()->assertSee('Video darslar')->assertDontSee('data-video-form', false);
    $this->actingAs($teacher)->get(route('education.lessons.show', $lesson))->assertOk()->assertSee('data-video-form', false);
});

test('video S3 upload rejects public object permissions', function () {
    [$student, $teacher, $lesson] = videoFixture();
    config(['lesson_video.disk' => 's3', 'lesson_video.s3_acl' => 'public-read']);
    Storage::fake('s3');
    $bytes = hex2bin('000000206674797069736f6d0000020069736f6d69736f32617663316d703431').str_repeat('a', 100);
    $upload = LessonVideoUpload::factory()->create(['user_id' => $teacher->id, 'lesson_id' => $lesson->id, 'size_bytes' => strlen($bytes)]);
    Storage::disk('local')->put('video-uploads/'.$upload->id.'/0', $bytes);
    $this->actingAs($teacher)->postJson("/api/v1/video-uploads/{$upload->id}/finish")->assertServiceUnavailable();
    expect($upload->fresh()->lesson_material_id)->toBeNull();
    expect(Storage::disk('s3')->allFiles())->toBe([]);
});
