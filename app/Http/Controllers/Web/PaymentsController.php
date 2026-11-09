<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\FeeItem;
use App\Models\SubscriptionPlan;
use App\Services\Payments\PaymentService;
use App\Services\Payments\TuitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Student va Parent uchun yagona to'lov bo'limi:
 * Tab 1: Oylik o'quv to'lovi (guruhlar bo'yicha)
 * Tab 2: AI obuna
 */
class PaymentsController extends BaseController
{
    public function __construct(private TuitionService $tuition, private PaymentService $subscriptions) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $isParent = $user->hasRole('PARENT');

        // Ota-ona — farzandlar; o'quvchi — o'zini ko'radi
        $targets = $isParent ? $user->children()->select('users.id', 'users.name')->orderBy('users.name')->get() : collect([$user]);
        $selectedId = $request->integer('student_id') ?: ($targets->first()->id ?? 0);
        $selected = $targets->firstWhere('id', $selectedId) ?? $targets->first();

        $tuition = $selected ? $this->tuition->history($selected) : null;
        $subs = $this->subscriptions->activeSubscription($selected ?? $user);
        $plans = SubscriptionPlan::where('is_active', true)->orderBy('price_tiyin')->get();

        return view('payments.index', [
            'isParent' => $isParent,
            'targets' => $targets,
            'selected' => $selected,
            'tuition' => $tuition,
            'subscription' => $subs,
            'plans' => $plans,
        ]);
    }

    /**
     * Oylik to'lovni qabul qilish (kassa/tasdiqlangan to'lov sifatida ro'yxatga olish talabi admin tomondan).
     * Bu endpoint o'quvchi/ota-ona "to'ladim" deb belgilash uchun ishlatilmaydi —
     * faqat admin tasdiqlaydi. Bu yerda faqat ko'rish va eslatma yuborish.
     */
    public function remind(FeeItem $fee): RedirectResponse
    {
        $fee->load('student');

        $fee->student->appNotifications()->create([
            'type' => 'payment_reminder',
            'title' => __('To‘lov eslatmasi'),
            'body' => __(':month/:year oyi to‘lovi :amount so‘m. Iltimos, to‘lovni o‘z vaqtida amalga oshiring.', [
                'month' => $fee->month, 'year' => $fee->year,
                'amount' => number_format($fee->amount_tiyin / 100),
            ]),
        ]);

        return back()->with('status', __('Eslatma yuborildi.'));
    }
}
