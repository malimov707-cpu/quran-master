<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\FeeItem;
use App\Services\Payments\TuitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin: oylik o'quv to'lovlari boshqaruvi.
 * Invoys generatsiya, to'lov qabul qilish (kassa), eslatma yuborish.
 */
class PaymentsAdminController extends BaseController
{
    public function __construct(private TuitionService $tuition) {}

    /**
     * Joriy oy invoyslarini generatsiya qilish (bir tugma).
     */
    public function generateInvoices(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2024', 'max:2100'],
        ]);

        $created = $this->tuition->generateMonthlyInvoices($data['month'] ?? null, $data['year'] ?? null);

        return back()->with('status', __(':count ta yangi oylik to‘lov yozuvi yaratildi.', ['count' => $created]));
    }

    /**
     * Kassada to'lov qabul qilish (to'liq yoki qismiy summa).
     */
    public function receive(Request $request, FeeItem $fee): RedirectResponse
    {
        $data = $request->validate([
            'amount_som' => ['required', 'integer', 'min:1'],
        ]);

        $remaining = $fee->amount_tiyin - $fee->paid_amount_tiyin;

        if ($remaining < $data['amount_som'] * 100) {
            return back()->withErrors(['amount_som' => __('Summa qoldiqdan katta (qoldiq: :amount so‘m).', ['amount' => number_format($remaining / 100)])]);
        }

        $this->tuition->pay($fee, $data['amount_som'] * 100, 'cash');

        return back()->with('status', __('To‘lov qabul qilindi: :amount so‘m', ['amount' => number_format($data['amount_som'])]));
    }

    /**
     * O'quvchiga to'lov eslatmasi yuborish.
     */
    public function remind(FeeItem $fee): RedirectResponse
    {
        $fee->load('student');

        $fee->student->appNotifications()->create([
            'type' => 'payment_reminder',
            'title' => __('To‘lov eslatmasi'),
            'body' => __(':course — :month/:year oyi to‘lovi :amount so‘m. Iltimos, :due gacha to‘lovni amalga oshiring.', [
                'course' => $fee->course?->name ?? $fee->group?->name,
                'month' => $fee->month, 'year' => $fee->year,
                'amount' => number_format(($fee->amount_tiyin - $fee->paid_amount_tiyin) / 100),
                'due' => $fee->due_date?->format('d.m.Y') ?? __('belgilanmagan'),
            ]),
        ]);

        return back()->with('status', __('Eslatma yuborildi: :name', ['name' => $fee->student->name]));
    }
}
