<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReviewSegment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'ai_review_id', 'ayah_number', 'word_position', 'expected_text', 'heard_text',
        'error_type', 'start_ms', 'end_ms', 'confidence', 'is_correct',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'is_correct' => 'boolean',
            'start_ms' => 'float',
            'end_ms' => 'float',
        ];
    }

    /**
     * AI sharhi.
     *
     * @return BelongsTo<AiReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(AiReview::class, 'ai_review_id');
    }
}
