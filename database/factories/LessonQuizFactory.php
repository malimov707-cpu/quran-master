<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonQuiz;
use App\Services\QuizService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonQuiz> */
class LessonQuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_id' => fn (): int => Lesson::create(['course_id' => Course::create(['name' => fake()->sentence(3), 'level' => 'beginner'])->id, 'title' => 'Tajvid', 'status' => 'published'])->id,
            'title' => 'Tajvid bilimlari', 'status' => 'draft', 'pass_percent' => 70, 'revision' => 1,
            'questions' => QuizService::tajwidTemplate(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published']);
    }
}
