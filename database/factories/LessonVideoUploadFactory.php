<?php

namespace Database\Factories;

use App\Models\LessonQuiz;
use App\Models\LessonVideoUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<LessonVideoUpload> */
class LessonVideoUploadFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => (string) Str::uuid(), 'user_id' => User::factory(), 'lesson_id' => fn (): int => LessonQuiz::factory()->create()->lesson_id,
            'title' => 'Video upload', 'size_bytes' => 100, 'duration_seconds' => 100, 'offline_allowed' => true, 'expires_at' => now()->addDay()];
    }
}
