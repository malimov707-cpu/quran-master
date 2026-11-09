<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends BaseController
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $isParent = $user->hasRole('PARENT');

        // Ota-ona farzandlari uchun boshqaradi; boshqalar o'zi uchun
        $targets = $isParent
            ? $user->children()->select('users.id', 'users.name')->orderBy('users.name')->get()
            : collect([$user]);

        $plans = SubscriptionPlan::where('is_active', true)->orderBy('price_tiyin')->get();

        $states = $targets->mapWithKeys(function (User $target) {
            return [$target->id => [
                'name' => $target->name,
                'active' => $this->payments->activeSubscription($target),
                'pending' => $target->payments()->where('status', 'pending')->exists(),
            ]];
        });

        return view('subscriptions.index', [
            'plans' => $plans,
            'targets' => $targets,
            'states' => $states,
            'isParent' => $isParent,
        ]);
    }

    public function requestCash(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'student_id' => ['nullable', 'exists:users,id'],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
        /** @var User $user */
        $user = $request->user();

        // Ota-ona faqat farzandi uchun, boshqalar o'zi uchun
        $target = $user->hasRole('PARENT')
            ? $user->children()->where('users.id', $data['student_id'] ?? 0)->firstOrFail()
            : $user;

        $this->payments->createPending($target, $plan, 'cash', (string) $target->id);
        $target->appNotifications()->create([
            'type' => 'payment_request',
            'title' => __('To‘lov arizasi yuborildi'),
            'body' => __(':plan rejasi uchun ariza qabul qilindi. Kassada to‘lovdan so‘ng faollashadi.', ['plan' => $plan->name]),
        ]);

        return back()->with('status', __('Ariza qabul qilindi. Markaz kassasida to‘lovni amalga oshiring.'));
    }
}
