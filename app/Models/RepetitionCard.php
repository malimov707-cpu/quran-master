<?php

namespace App\Models;

use App\Models\Quran\Ayah;
use Database\Factories\RepetitionCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepetitionCard extends Model
{
    /** @use HasFactory<RepetitionCardFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'ayah_id', 'due_at', 'interval_days', 'successful_reviews', 'version', 'last_rating', 'last_reviewed_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'last_reviewed_at' => 'datetime', 'interval_days' => 'integer', 'successful_reviews' => 'integer', 'version' => 'integer'];
    }

    /** @return BelongsTo<Ayah, $this> */
    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RepetitionReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(RepetitionReview::class);
    }
}
