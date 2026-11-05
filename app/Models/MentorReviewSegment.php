<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorReviewSegment extends Model
{
    /** @var list<string> */
    protected $fillable = ['mentor_review_id', 'ayah_from', 'ayah_to', 'start_ms', 'end_ms', 'error_type', 'comment'];

    /**
     * Sharh.
     *
     * @return BelongsTo<MentorReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(MentorReview::class, 'mentor_review_id');
    }
}
