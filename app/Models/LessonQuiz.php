<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonQuiz extends Model
{
    use HasFactory;

    protected $fillable = ['lesson_id', 'created_by', 'title', 'status', 'pass_percent', 'revision', 'questions', 'awards_certificate'];

    protected $hidden = ['questions'];

    protected function casts(): array
    {
        return ['awards_certificate' => 'boolean', 'questions' => 'array', 'revision' => 'integer', 'pass_percent' => 'integer'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
