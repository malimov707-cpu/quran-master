<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * To'lov biznes-mantiqi — Payme/Click webhooklari va obuna boshqaruvi.
 * Provayderga bog'lanmaydigan yadro: har provayder o'z adapterida.
 */
class PaymentService
{
    /**
     * Yangi to'lov yozuvi (provayder to'lovni boshlaganda).
     */
    public function createPending(User $user, SubscriptionPlan $plan, string $provider, string $accountNumber): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount_tiyin' => $plan->price_tiyin,
            'provider' => $provider,
            'account_number' => $accountNumber,
            'status' => 'pending',
        ]);
    }

    /**
     * To'lov muvaffaqiyatli — tranzaksiya ichida obuna berish.
     * Idempotent: bir xil provider_transaction_id ikki marta kelmadi deb tekshiriladi.
     */
    public function markPaid(Payment $payment, string $providerTransactionId, array $payload = []): Payment
    {
        return DB::transaction(function () use ($payment, $providerTransactionId, $payload): Payment {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            // Idempotensiya
            if ($payment->status === 'paid') {
                return $payment;
            }

            $payment->update([
                'status' => 'paid',
                'provider_transaction_id' => $providerTransactionId,
                'payload' => $payload,
                'paid_at' => now(),
            ]);

            // Joriy faol obunani UZAYTIRISH (stack qilinmaydi — muddat uzaytiriladi)
            $plan = $payment->plan;
            $current = $payment->user->activeSubscription();
            $startsAt = ($current && $current->expires_at->isFuture()) ? $current->expires_at : now();

            Subscription::create([
                'user_id' => $payment->user_id,
                'plan_id' => $plan->id,
                'starts_at' => $startsAt,
                'expires_at' => $startsAt->copy()->addMonths($plan->months),
                'status' => 'active',
            ]);

            return $payment->refresh();
        });
    }

    /**
     * To'lovni rad etish.
     */
    public function markFailed(Payment $payment, array $payload = []): void
    {
        $payment->update(['status' => 'failed', 'payload' => $payload]);
    }

    /**
     * Foydalanuvchining faol obunasi (eng so'nggi muddatli).
     */
    public function activeSubscription(User $user): ?Subscription
    {
        return Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->orderByDesc('expires_at')
            ->first();
    }

    /**
     * Muddati o'tgan obunalarni belgilash (scheduler uchun).
     */
    public function expireOutdated(): int
    {
        return Subscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Moliyaviy xulosa (admin panel uchun).
     */
    public function monthlySummary(): array
    {
        $rows = Payment::where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("strftime('%Y-%m', paid_at) as month, sum(amount_tiyin) as total, count(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $thisMonth = Payment::where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth());

        return [
            'monthly' => $rows->map(fn ($row) => ['month' => $row->month, 'total_som' => (int) round($row->total / 100), 'count' => (int) $row->count]),
            'this_month_som' => (int) round($thisMonth->sum('amount_tiyin') / 100),
            'this_month_count' => (int) $thisMonth->count(),
            'active_subscriptions' => Subscription::where('status', 'active')->where('expires_at', '>', now())->count(),
        ];
    }
}
