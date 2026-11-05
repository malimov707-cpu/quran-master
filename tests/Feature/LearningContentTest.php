<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('learning content adds twenty lessons and preserves manual changes on rerun', function () {
    $this->seed(DatabaseSeeder::class);
    $this->artisan('learning:seed')->assertSuccessful();
    $this->assertDatabaseCount('lessons', 20);
    $lesson = Lesson::firstOrFail();
    $lesson->update(['description' => 'Ustozning yangi matni']);

    $this->artisan('learning:seed')->assertSuccessful();

    $this->assertDatabaseCount('lessons', 20);
    $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'description' => 'Ustozning yangi matni']);
});

test('student can read published lessons but cannot create edit or read drafts', function () {
    $this->seed(DatabaseSeeder::class);
    $this->artisan('learning:seed')->assertSuccessful();
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $lesson = Lesson::firstOrFail();

    $this->actingAs($student)->get(route('education.lessons'))->assertOk()->assertSee($lesson->title);
    $this->get(route('education.lessons.show', $lesson))->assertOk()->assertSee($lesson->description);
    $this->post(route('education.lessons.store'))->assertForbidden();
    $this->patch(route('education.lessons.update', $lesson), ['title' => 'Spoof'])->assertForbidden();
    $lesson->update(['status' => 'draft']);
    $this->get(route('education.lessons.show', $lesson))->assertNotFound();
    $this->actingAs($student, 'sanctum')->getJson('/api/v1/lessons/'.$lesson->id)->assertNotFound();
    $this->getJson('/api/v1/lessons?course_id='.$lesson->course_id)->assertOk()->assertJsonMissing(['title' => $lesson->title]);
});

test('head teacher can create and update lesson content', function () {
    $this->seed(DatabaseSeeder::class);
    $teacher = User::where('email', 'ustoz@markaz.uz')->firstOrFail();
    $data = ['course_id' => Course::firstOrFail()->id, 'title' => 'Yangi dars', 'description' => 'To‘liq mazmun', 'status' => 'draft', 'sort_order' => 1, 'duration_minutes' => 30];

    $this->actingAs($teacher)->post(route('education.lessons.store'), $data)->assertRedirect();
    $lesson = Lesson::where('title', 'Yangi dars')->firstOrFail();
    $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'created_by' => $teacher->id]);
    $this->patch(route('education.lessons.update', $lesson), array_replace($data, ['description' => 'Yangilangan mazmun', 'status' => 'published']))->assertRedirect();
    $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'description' => 'Yangilangan mazmun', 'status' => 'published']);
});

test('super admin navigation and dashboard exclude personal student mentor and parent controls', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@quronai.uz')->firstOrFail();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertSee(route('education.lessons'))->assertSee(route('assignments.manage'))
        ->assertDontSee(route('homework.index'))->assertDontSee(route('parents.dashboard'))->assertDontSee(route('mentor.submissions'))
        ->assertDontSee('Mening topshirishlarim')->assertDontSee('Toshkent · hisobiy namoz vaqti');
});

test('student still has personal navigation and cannot use management actions', function () {
    $this->seed(DatabaseSeeder::class);
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();

    $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee(route('homework.index'))->assertDontSee(route('assignments.manage'));
    $this->get(route('assignments.manage'))->assertForbidden();
});

test('lesson form rejects missing title and invalid duration without saving', function () {
    $this->seed(DatabaseSeeder::class);
    $teacher = User::where('email', 'ustoz@markaz.uz')->firstOrFail();

    $this->actingAs($teacher)->post(route('education.lessons.store'), ['course_id' => Course::firstOrFail()->id, 'title' => '', 'duration_minutes' => 0, 'sort_order' => 1, 'status' => 'published'])
        ->assertSessionHasErrors(['title', 'duration_minutes']);
    $this->assertDatabaseCount('lessons', 0);
});

test('quran import rejects malformed response before writing content', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://quran-json.risanb.com/text/uthmani/quran.json' => Http::response('[]'),
        'https://tanzil.net/res/text/metadata/quran-data.xml' => Http::response('<quran><suras /></quran>'),
    ]);

    $this->artisan('quran:import')->assertFailed();
    $this->assertDatabaseCount('quran_surahs', 0);
    $this->assertDatabaseCount('quran_ayahs', 0);
    Http::assertSentCount(2);
});

test('quran import rejects http errors without writing content', function () {
    Http::preventStrayRequests();
    Http::fake(['https://quran-json.risanb.com/text/uthmani/quran.json' => Http::response('unavailable', 503)]);

    $this->artisan('quran:import')->assertFailed();
    $this->assertDatabaseCount('quran_surahs', 0);
    Http::assertSentCount(1);
});
