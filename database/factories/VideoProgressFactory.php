<?php

namespace Database\Factories;

use App\Models\LessonMaterial;
use App\Models\LessonQuiz;
use App\Models\User;
use App\Models\VideoProgress;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<VideoProgress> */
class VideoProgressFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'lesson_material_id' => fn (): int => LessonMaterial::create([
            'lesson_id' => LessonQuiz::factory()->create()->lesson_id, 'title' => 'Video lesson', 'type' => 'video',
            'payload' => ['provider' => 'youtube', 'youtube_id' => 'M7lc1UVf-VE', 'version' => (string) Str::uuid(), 'duration_seconds' => 100, 'offline_allowed' => false],
        ])->id, 'position_seconds' => 0, 'ranges' => [], 'watched_seconds' => 0, 'completed' => false, 'observed_at' => now()];
    }
}
