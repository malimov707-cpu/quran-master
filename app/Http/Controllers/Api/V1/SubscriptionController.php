<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Obuna rejaları va foydalanuvchi holati (web + mobile uchun yagona).
 */
class SubscriptionController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /**
     * Rejalar ro'yxati + foydalanuvchi obuna holati.
     */
    public function index(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('price_tiyin')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price_som' => $plan->price_som,
                'months' => $plan->months,
                'benefits' => array_filter(explode("\n", (string) $plan->benefits_uz)),
            ]);

        $subscription = $this->payments->activeSubscription($request->user());

        $status = $subscription
            ? 'active'
            : ($request->user()->payments()->where('status', 'pending')->exists() ? 'pending' : 'free');

        return response()->json([
            'plans' => $plans,
            'status' => $status,
            'subscription' => $subscription ? [
                'plan' => $subscription->plan->name,
                'expires_at' => $subscription->expires_at->toIso8601String(),
                'days_left' => (int) now()->diffInDays($subscription->expires_at, false),
            ] : null,
        ]);
    }

    /**
     * Cash/nagt to'lov (markaz kassasi) — admin tasdiqlaydi.
     * API'da faqat plan tanlanadi; to'lov markazga to'g'ridan-to'g'ri bajariladi.
     */
    public function subscribeCash(Request $request): JsonResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:subscription_plans,id']]);

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
        $this->payments->createPending($request->user(), $plan, 'cash', (string) $request->user()->id);

        return response()->json([
            'message' => __('Ariza qabul qilindi. Markaz kassasida to‘lovni amalga oshiring — tasdiqlangach obuna faollashadi.'),
        ], 201);
    }
}
