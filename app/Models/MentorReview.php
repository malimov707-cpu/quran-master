<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorReview extends Model
{
    /** @var list<string> */
    protected $fillable = ['submission_id', 'mentor_id', 'score', 'comment', 'audio_note_path', 'action', 'reviewed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /**
     * Topshirish.
     *
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
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
     * Segment xatolari.
     *
     * @return HasMany<MentorReviewSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(MentorReviewSegment::class);
    }
}
