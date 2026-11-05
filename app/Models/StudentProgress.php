<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProgress extends Model
{
    protected $table = 'student_progress';

    /** @var list<string> */
    protected $fillable = ['student_id', 'surah_number', 'ayah_from', 'ayah_to', 'lessons_completed', 'submissions_count', 'average_score', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['average_score' => 'float'];
    }

    /**
     * O'quvchi.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
