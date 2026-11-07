<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\LessonQuiz;
use App\Services\QuizService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class LessonQuizSeeder extends Seeder
{
    public function run(): void
    {
        Lesson::where('title', 'like', '%tajvid%')->chunkById(100, function (Collection $lessons): void {
            foreach ($lessons as $lesson) {
                LessonQuiz::firstOrCreate(['lesson_id' => $lesson->id], [
                    'title' => 'Tajvid bilimlari', 'status' => 'draft', 'pass_percent' => 70, 'revision' => 1,
                    'questions' => QuizService::tajwidTemplate(),
                ]);
            }
        });
    }
}
