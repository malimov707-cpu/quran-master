<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RealtimeController extends Controller
{
    public function token(): JsonResponse
    {
        return response()->json([
            'error' => 'voice_client_update_required',
            'message' => 'Ovozli chat OpenRouter’ga o‘tkazildi. Sahifani yoki ilovani yangilang.',
        ], 410);
    }
}
