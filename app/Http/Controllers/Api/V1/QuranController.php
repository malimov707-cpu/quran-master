<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuranController extends Controller
{
    /**
     * Surahlar ro'yxati (qidiruv bilan).
     */
    public function surahs(Request $request): JsonResponse
    {
        $surahs = Surah::query()
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('name_uz', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('name_arabic', 'like', "%{$search}%")
                ->orWhere('number', $search)))
            ->orderBy('number')
            ->get(['id', 'number', 'name_arabic', 'name_uz', 'name_en', 'ayah_count', 'revelation_type']);

        return response()->json($surahs);
    }

    /**
     * Bitta surah va oyatlari (tarjima bilan).
     */
    public function surah(Request $request, Surah $surah): JsonResponse
    {
        $ayahs = $surah->ayahs()->get(['id', 'number_in_surah', 'number_global', 'juz', 'page', 'text_arabic', 'text_uz', 'text_ru']);

        return response()->json([
            'surah' => $surah->only(['id', 'number', 'name_arabic', 'name_uz', 'name_en', 'ayah_count', 'revelation_type']),
            'ayahs' => $ayahs,
        ]);
    }

    /**
     * Oyat qidiruvi (arab va tarjimalar bo'yicha).
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->validate(['q' => ['required', 'string', 'min:2']])['q'];

        $ayahs = Ayah::query()
            ->where('text_arabic', 'like', "%{$search}%")
            ->orWhere('text_uz', 'like', "%{$search}%")
            ->orWhere('text_ru', 'like', "%{$search}%")
            ->with('surah:id,number,name_uz,name_arabic')
            ->limit(50)
            ->get(['id', 'surah_id', 'number_in_surah', 'text_arabic', 'text_uz', 'text_ru']);

        return response()->json($ayahs);
    }
}
