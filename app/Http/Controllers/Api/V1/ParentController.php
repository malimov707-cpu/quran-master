<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    /**
     * Ota-ona farzandlari ro'yxati.
     */
    public function children(Request $request): JsonResponse
    {
        $children = $request->user()->children()->get(['users.id', 'name', 'email']);

        return response()->json($children);
    }
}
