<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'provider', 'model', 'operation', 'tokens_used', 'duration_ms', 'success'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['success' => 'boolean'];
    }

    /**
     * Foydalanuvchi.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
