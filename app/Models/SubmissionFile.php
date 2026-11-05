<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionFile extends Model
{
    /** @var list<string> */
    protected $fillable = ['submission_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'duration_seconds'];

    /**
     * Topshirish.
     *
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
