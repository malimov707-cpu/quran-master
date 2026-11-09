<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('student kpi endpoint returns real metrics for student', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    $response = $this->actingAs($student, 'sanctum')->getJson('/api/v1/student/kpis');

    $response->assertOk()->assertJsonStructure([
        'status',
        'data' => ['ayahs', 'minutes', 'streak', 'achievements', 'progress_series'],
    ]);

    expect($response->json('data.ayahs'))->toBeInt();
    expect($response->json('data.streak'))->toBeInt();
});

test('student kpi endpoint forbidden for non-students', function () {
    $mentor = User::where('email', 'mentor@markaz.uz')->first();

    $this->actingAs($mentor, 'sanctum')->getJson('/api/v1/student/kpis')->assertForbidden();
});

test('student kpi counts submissions in ayah metric', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    DB::table('student_progress')->insert([
        'student_id' => $student->id, 'surah_number' => 1, 'ayah_from' => 1, 'ayah_to' => 7,
        'lessons_completed' => 2, 'submissions_count' => 3, 'status' => 'completed',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $data = $this->actingAs($student, 'sanctum')->getJson('/api/v1/student/kpis')->json('data');

    expect($data['ayahs'])->toBeGreaterThan(0);
});
