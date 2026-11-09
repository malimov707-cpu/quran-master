<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Click Merchant API — rasmiy prepare/complete protokoli.
 *
 * Autentifikatsiya: sign_string = md5(click_trans_id + amount + action + sign_time + merchant_trans_id
 *   + merchant_prepare_id|merchant_confirm_id + CLICK_SECRET_KEY)
 */
class ClickController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function handle(Request $request): JsonResponse
    {
        $data = $request->all();
        $action = (int) ($data['action'] ?? 0);

        // 0 = prepare, 1 = complete
        if (! $this->signatureValid($data)) {
            return $this->respond($data, -4, 'SIGN CHECK FAILED');
        }

        return match ($action) {
            0 => $this->prepare($data),
            1 => $this->complete($data),
            default => $this->respond($data, -5, 'Action not found'),
        };
    }

    private function prepare(array $data): JsonResponse
    {
        $user = User::find($data['merchant_trans_id'] ?? null);

        if (! $user) {
            return $this->respond($data, -5, 'User not found');
        }

        $plan = SubscriptionPlan::find($data['service_id'] ?? null)
            ?? SubscriptionPlan::where('is_active', true)->where('price_tiyin', '>', 0)->orderBy('price_tiyin')->first();

        if (! $plan) {
            return $this->respond($data, -5, 'Plan not found');
        }

        $payment = $this->payments->createPending($user, $plan, 'click', (string) ($data['click_trans_id'] ?? ''));

        return $this->respond($data, 0, 'Success', [
            'click_trans_id' => $data['click_trans_id'] ?? null,
            'merchant_trans_id' => $data['merchant_trans_id'] ?? null,
            'merchant_prepare_id' => $payment->id,
        ]);
    }

    private function complete(array $data): JsonResponse
    {
        $payment = Payment::where('provider', 'click')
            ->where('id', $data['merchant_prepare_id'] ?? 0)
            ->first();

        if (! $payment) {
            return $this->respond($data, -6, 'Transaction not found');
        }

        if ((int) ($data['error'] ?? -1) < 0) {
            $this->payments->markFailed($payment, $data);

            return $this->respond($data, -9, 'Transaction cancelled');
        }

        // Idempotent: allaqachon to'langan bo'lsa ham SUCCESS qaytaramiz
        if ($payment->status !== 'paid') {
            $this->payments->markPaid($payment, (string) ($data['click_trans_id'] ?? ''), $data);
        }

        return $this->respond($data, 0, 'Success', [
            'click_trans_id' => $data['click_trans_id'] ?? null,
            'merchant_trans_id' => $data['merchant_trans_id'] ?? null,
            'merchant_confirm_id' => $payment->id,
        ]);
    }

    private function signatureValid(array $data): bool
    {
        $secret = (string) config('services.click.secret_key');
        if ($secret === '') {
            return false; // prod'da secret sozlanmagan bo'lsa to'lov qabul qilinmaydi
        }

        $md5 = md5(
            ($data['click_trans_id'] ?? '').
            ($data['amount'] ?? '').
            ($data['action'] ?? '').
            ($data['sign_time'] ?? '').
            ($data['merchant_trans_id'] ?? '').
            ($data['merchant_prepare_id'] ?? '').
            $secret
        );

        return hash_equals($md5, (string) ($data['sign_string'] ?? ''));
    }

    private function respond(array $data, int $error, string $note, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'click_trans_id' => $data['click_trans_id'] ?? null,
            'merchant_trans_id' => $data['merchant_trans_id'] ?? null,
            'error' => $error,
            'error_note' => $note,
        ], $extra));
    }
}
