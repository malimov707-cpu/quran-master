<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    /** @var list<string> */
    protected $fillable = ['course_id', 'course_module_id', 'title', 'description', 'learning_objectives', 'sort_order', 'duration_minutes', 'homework', 'status', 'created_by'];

    /**
     * Kurs.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Dars materiallari.
     *
     * @return HasMany<LessonMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class);
    }
}
