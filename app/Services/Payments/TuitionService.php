<?php

namespace App\Services\Payments;

use App\Models\FeeItem;
use App\Models\Group;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Oylik o'quv to'lovi (tuition) mantiqi.
 * Har oy boshlanishida faol guruhlar o'quvchilariga invoice generatsiya qilinadi.
 * Group.payment_due_day — to'lov muddati kuni.
 */
class TuitionService
{
    /**
     * Joriy oy uchun invoice generatsiya (idempotent — unique constraint).
     * Scheduler: har oy 1-kuni.
     */
    public function generateMonthlyInvoices(?int $month = null, ?int $year = null): int
    {
        $month = $month ?? (int) now()->month;
        $year = $year ?? (int) now()->year;

        $created = 0;
        $groups = Group::where('status', 'active')->with('students')->get();

        foreach ($groups as $group) {
            foreach ($group->students as $student) {
                $exists = FeeItem::where([
                    'student_id' => $student->id,
                    'group_id' => $group->id,
                    'year' => $year,
                    'month' => $month,
                ])->exists();

                if ($exists) {
                    continue;
                }

                FeeItem::create([
                    'student_id' => $student->id,
                    'group_id' => $group->id,
                    'course_id' => $group->course_id,
                    'month' => $month,
                    'year' => $year,
                    'amount_tiyin' => $group->monthly_fee_tiyin ?? 0,
                    'due_date' => now()->setDate($year, $month, min($group->payment_due_day ?? 10, 28)),
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * To'lov qabul qilish — invoice to'lash (to'liq yoki qismiy).
     */
    public function pay(FeeItem $feeItem, int $amountTiyin, string $provider = 'cash'): Payment
    {
        return DB::transaction(function () use ($feeItem, $amountTiyin, $provider): Payment {
            $payment = Payment::create([
                'user_id' => $feeItem->student_id,
                'fee_item_id' => $feeItem->id,
                'type' => 'tuition',
                'amount_tiyin' => $amountTiyin,
                'provider' => $provider,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $feeItem->paid_amount_tiyin += $amountTiyin;
            $feeItem->payment_id = $payment->id;
            $feeItem->status = $feeItem->paid_amount_tiyin >= $feeItem->amount_tiyin ? 'paid' : 'partial';
            $feeItem->save();

            // O'quvchiga bildirishnoma
            $feeItem->student->appNotifications()->create([
                'type' => 'payment_received',
                'title' => __('To‘lov qabul qilindi'),
                'body' => __(':month/:year oyi to‘lovi: :amount so‘m', [
                    'month' => $feeItem->month, 'year' => $feeItem->year,
                    'amount' => number_format($amountTiyin / 100),
                ]),
            ]);

            return $payment;
        });
    }

    /**
     * O'quvchining to'lovlar xronologiyasi (dashboard uchun).
     */
    public function history(User $student): array
    {
        $items = FeeItem::with(['course:id,name', 'group:id,name'])
            ->where('student_id', $student->id)
            ->orderByDesc('year')->orderByDesc('month')
            ->limit(12)
            ->get();

        $paid = $items->where('status', 'paid')->sum(fn ($item) => $item->paid_amount_tiyin);
        $pending = $items->whereIn('status', ['pending', 'partial'])->sum(fn ($item) => max($item->amount_tiyin - $item->paid_amount_tiyin, 0));

        return [
            'items' => $items->map(fn (FeeItem $item) => [
                'id' => $item->id,
                'course' => $item->course?->name ?? $item->group?->name,
                'month' => $item->month,
                'year' => $item->year,
                'amount_som' => (int) round($item->amount_tiyin / 100),
                'paid_som' => (int) round($item->paid_amount_tiyin / 100),
                'status' => $item->status,
                'due_date' => $item->due_date?->format('d.m.Y'),
            ]),
            'total_paid_som' => (int) round($paid / 100),
            'total_pending_som' => (int) round($pending / 100),
            'has_overdue' => $items->contains(fn ($item) => in_array($item->status, ['pending', 'partial'], true) && $item->due_date && $item->due_date->isPast()),
        ];
    }
}
