<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiReview extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'submission_id', 'provider', 'model', 'status', 'attempts', 'overall_confidence',
        'transcript', 'summary', 'needs_mentor_review_reason', 'started_at', 'finished_at', 'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overall_confidence' => 'float',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
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
     * Segmentlar.
     *
     * @return HasMany<AiReviewSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(AiReviewSegment::class);
    }
}
