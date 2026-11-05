<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorComment extends Model
{
    /** @var list<string> */
    protected $fillable = ['mentor_id', 'student_id', 'submission_id', 'comment', 'is_private'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_private' => 'boolean'];
    }

    /**
     * Mentor.
     *
     * @return BelongsTo<User, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
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
