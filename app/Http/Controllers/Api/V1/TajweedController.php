<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quran\TajweedRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tajvid akademiyasi — qoidalar ro'yxati va interaktiv tahlil.
 */
class TajweedController extends Controller
{
    /**
     * Barcha tajvid qoidalari (o'quv materiali).
     */
    public function index(Request $request): JsonResponse
    {
        $rules = TajweedRule::query()
            ->when($request->get('q'), fn ($query, $search) => $query->where(fn ($w) => $w
                ->where('name_uz', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('code')
            ->get(['id', 'code', 'name_uz', 'description']);

        return response()->json($rules);
    }

    /**
     * Bitta qoida to'liq ma'lumoti (misollar bilan).
     */
    public function show(Request $request, TajweedRule $rule): JsonResponse
    {
        return response()->json([
            'code' => $rule->code,
            'name' => $rule->name_uz,
            'description' => $rule->description,
        ]);
    }
}
