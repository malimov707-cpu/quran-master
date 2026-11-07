<?php

namespace Database\Factories;

use App\Models\LessonQuiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<QuizAttempt> */
class QuizAttemptFactory extends Factory
{
    public function definition(): array
    {
        return ['lesson_quiz_id' => LessonQuiz::factory(), 'user_id' => User::factory(), 'submission_key' => (string) Str::uuid(),
            'revision' => 1, 'correct_count' => 0, 'question_count' => 3, 'score' => 0, 'passed' => false, 'feedback' => [], 'submitted_at' => now()];
    }
}
