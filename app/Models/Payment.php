<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'plan_id', 'amount_tiyin', 'provider', 'provider_transaction_id', 'account_number', 'status', 'payload', 'paid_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'paid_at' => 'datetime', 'amount_tiyin' => 'integer'];
    }

    /**
     * To'lov egasi.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Reja.
     *
     * @return BelongsTo<SubscriptionPlan|null, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
