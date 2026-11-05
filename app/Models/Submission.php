<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    /** @var list<string> */
    protected $fillable = ['assignment_id', 'student_id', 'student_note', 'status', 'attempt', 'final_score', 'submitted_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    /**
     * Topshiriq.
     *
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
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

    /**
     * Audio va boshqa fayllar.
     *
     * @return HasMany<SubmissionFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    /**
     * AI sharhlari.
     *
     * @return HasMany<AiReview, $this>
     */
    public function aiReviews(): HasMany
    {
        return $this->hasMany(AiReview::class);
    }

    /**
     * Mentor sharhlari.
     *
     * @return HasMany<MentorReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(MentorReview::class);
    }
}
