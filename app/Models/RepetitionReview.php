<?php

namespace App\Models;

use Database\Factories\RepetitionReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepetitionReview extends Model
{
    /** @use HasFactory<RepetitionReviewFactory> */
    use HasFactory;

    protected $fillable = ['rating', 'version', 'interval_days', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'version' => 'integer', 'interval_days' => 'integer'];
    }

    /** @return BelongsTo<RepetitionCard, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(RepetitionCard::class, 'repetition_card_id');
    }
}
