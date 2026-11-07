<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['code', 'user_id', 'course_id', 'lesson_quiz_id', 'source_key', 'type', 'recipient_name', 'title', 'score', 'evidence', 'issued_at', 'revoked_at', 'revoked_by', 'revocation_reason'];

    protected $hidden = ['user_id', 'evidence', 'revoked_by', 'revocation_reason', 'source_key'];

    protected function casts(): array
    {
        return ['score' => 'float', 'evidence' => 'array', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
