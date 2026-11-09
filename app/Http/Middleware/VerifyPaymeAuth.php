<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payme Merchant API autentifikatsiyasi — Basic auth (merchant_id:key).
 * Rasmiy protokol talabiga muvofiq.
 */
class VerifyPaymeAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $merchantId = (string) config('services.payme.merchant_id');
        $key = (string) config('services.payme.key');

        // Local'da kalitlar sozlanmagan bo'lsa webhook o'chirilgan hisoblanadi
        if ($merchantId === '' || $key === '') {
            return response()->json([
                'error' => ['code' => -32501, 'message' => ['uz' => 'To\u2019lov provayderi sozlanmagan']],
            ], 200);
        }

        $expected = 'Basic '.base64_encode("{$merchantId}:{$key}");
        $provided = (string) $request->header('Authorization', '');

        if (! hash_equals($expected, $provided)) {
            return response()->json([
                'error' => ['code' => -32504, 'message' => ['uz' => 'Avtorizatsiya xato']],
            ]);
        }

        return $next($request);
    }
}
