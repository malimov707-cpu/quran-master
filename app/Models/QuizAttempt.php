<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['lesson_quiz_id', 'user_id', 'submission_key', 'revision', 'correct_count', 'question_count', 'score', 'passed', 'feedback', 'submitted_at'];

    protected $hidden = ['user_id', 'submission_key'];

    protected function casts(): array
    {
        return ['feedback' => 'array', 'score' => 'float', 'passed' => 'boolean', 'revision' => 'integer', 'submitted_at' => 'datetime'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(LessonQuiz::class, 'lesson_quiz_id');
    }
}
