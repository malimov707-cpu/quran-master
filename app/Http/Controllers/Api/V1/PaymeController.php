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
 * Payme Merchant API (JSON-RPC 2.0) — rasmiy protokol.
 *
 * Metodlar: CheckPerformTransaction, CreateTransaction, PerformTransaction,
 * CancelTransaction, CheckTransaction.
 *
 * Autentifikatsiya: Basic auth (PAYME_MERCHANT_ID:PAYME_KEY) — middleware'da tekshiriladi.
 */
class PaymeController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function handle(Request $request): JsonResponse
    {
        $params = (array) $request->input('params', []);
        $id = $request->input('id');

        return match ($request->input('method')) {
            'CheckPerformTransaction' => $this->checkPerform($id, $params),
            'CreateTransaction' => $this->create($id, $params),
            'PerformTransaction' => $this->perform($id, $params),
            'CancelTransaction' => $this->cancel($id, $params),
            'CheckTransaction' => $this->check($id, $params),
            default => $this->error($id, -32601, 'Method not found'),
        };
    }

    /** Hisob raqamida foydalanuvchi borligi va summa to'g'riligini tekshirish. */
    private function checkPerform($id, array $params): JsonResponse
    {
        $account = (array) ($params['account'] ?? []);
        $user = User::find($account['user_id'] ?? null);

        if (! $user) {
            return $this->error($id, -31050, 'Foydalanuvchi topilmadi');
        }

        $planId = $account['plan_id'] ?? null;
        $plan = SubscriptionPlan::find($planId);

        if (! $plan || ! $plan->is_active) {
            return $this->error($id, -31050, 'Tarif topilmadi');
        }

        // Summa tiyinda; 5% oshib ketishga yo'l qo'yilmaydi (rasmiy talab: aniq)
        if ((int) ($params['amount'] ?? 0) !== $plan->price_tiyin) {
            return $this->error($id, -31001, 'Summa noto\u2019g\u2019ri');
        }

        return response()->json([
            'id' => $id,
            'result' => [
                'allow' => true,
                'detail' => [
                    'receipt_type' => 0,
                    'items' => [[
                        'title' => "Qur'on AI Ustoz — {$plan->name}",
                        'price' => $plan->price_tiyin,
                        'count' => 1,
                    ]],
                ],
            ],
        ]);
    }

    /** To'lovni yaratish (holat: qo'lda bajarilmagan) */
    private function create($id, array $params): JsonResponse
    {
        $account = (array) ($params['account'] ?? []);
        $user = User::find($account['user_id'] ?? null);
        $plan = SubscriptionPlan::find($account['plan_id'] ?? null);

        if (! $user || ! $plan) {
            return $this->error($id, -31050, 'Foydalanuvchi yoki tarif topilmadi');
        }

        // Bir xil payme_tr_id qayta kelsa — mavjud to'lovni qaytaramiz (idempotensiya)
        $existing = Payment::where('provider', 'payme')
            ->where('provider_transaction_id', $params['id'])
            ->first();

        if ($existing) {
            return response()->json(['result' => [
                'transaction' => $existing->id,
                'state' => $existing->status === 'paid' ? 1 : ($existing->status === 'cancelled' ? -1 : 0),
                'create_time' => (int) $existing->created_at->getTimestamp() * 1000,
            ]]);
        }

        if ((int) ($params['amount'] ?? 0) !== $plan->price_tiyin) {
            return $this->error($id, -31001, 'Summa noto\u2019g\u2019ri');
        }

        $payment = $this->payments->createPending($user, $plan, 'payme', (string) ($account['user_id'] ?? ''));
        $payment->update([
            'provider_transaction_id' => (string) $params['id'],
            'payload' => ['time' => $params['time'] ?? null, 'amount' => $params['amount'] ?? null],
        ]);

        return response()->json(['result' => [
            'transaction' => $payment->id,
            'state' => 1,
            'create_time' => (int) floor(microtime(true) * 1000),
        ]]);
    }

    /** To'lovni yakunlash — obuna beriladi */
    private function perform($id, array $params): JsonResponse
    {
        $payment = Payment::where('provider', 'payme')->where('provider_transaction_id', $params['id'])->first();

        if (! $payment) {
            return $this->error($id, -31003, 'Tranzaksiya topilmadi');
        }

        if ($payment->status === 'paid') {
            return response()->json(['result' => [
                'transaction' => $payment->id,
                'perform_time' => ($payment->paid_at ? $payment->paid_at->getTimestamp() * 1000 : (int) floor(microtime(true) * 1000)),
                'state' => 1,
            ]]);
        }

        $this->payments->markPaid($payment, (string) $params['id'], $params);

        return response()->json(['result' => [
            'transaction' => $payment->id,
            'perform_time' => (int) floor(microtime(true) * 1000),
            'state' => 1,
        ]]);
    }

    /** Bekor qilish */
    private function cancel($id, array $params): JsonResponse
    {
        $payment = Payment::where('provider', 'payme')->where('provider_transaction_id', $params['id'])->first();

        if (! $payment) {
            return $this->error($id, -31003, 'Tranzaksiya topilmadi');
        }

        if ($payment->status === 'paid') {
            // Yakunlangan to'lovni bekor qilish faqat to'lov boshlanganidan 24 soat ichida; bu yerda rad etamiz (retsept bilan qaytarish alohida)
            return $this->error($id, -31007, 'Yakunlangan to\u2019lovni bekor qilib bo\u2019lmaydi');
        }

        $payment->update(['status' => 'cancelled', 'payload' => $params]);

        return response()->json(['result' => [
            'transaction' => $payment->id,
            'cancel_time' => (int) floor(microtime(true) * 1000),
            'state' => -1,
        ]]);
    }

    /** Holat tekshiruvi */
    private function check($id, array $params): JsonResponse
    {
        $payment = Payment::where('provider', 'payme')->where('provider_transaction_id', $params['id'])->first();

        if (! $payment) {
            return $this->error($id, -31003, 'Tranzaksiya topilmadi');
        }

        return response()->json(['result' => [
            'transaction' => $payment->id,
            'state' => match ($payment->status) {
                'paid' => 1, 'cancelled' => -1, default => 0,
            },
            'create_time' => $payment->created_at->getTimestamp() * 1000,
            'perform_time' => $payment->paid_at ? $payment->paid_at->getTimestamp() * 1000 : null,
            'cancel_time' => null,
            'reason' => null,
        ]]);
    }

    private function error($id, int $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => ['uz' => $message, 'ru' => $message]],
            'id' => $id,
        ]);
    }
}
