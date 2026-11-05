<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Quran\Surah;
use Illuminate\View\View;
use Illuminate\Http\Request;

class QuranController extends BaseController
{
    public function surahsIndex(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim($filters['q'] ?? '');
        $surahs = Surah::query()->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
            $query->where('name_uz', 'like', '%'.$search.'%')->orWhere('name_en', 'like', '%'.$search.'%')
                ->orWhere('name_arabic', 'like', '%'.$search.'%');
            if (ctype_digit($search)) {
                $query->orWhere('number', (int) $search);
            }
        }))->orderBy('number')->get();

        return view('quran.surahs', compact('surahs'));
    }

    public function surahShow(Surah $surah): View
    {
        $surah->load(['ayahs' => fn ($q) => $q->select(['id', 'surah_id', 'number_in_surah', 'text_arabic', 'text_uz', 'text_ru', 'juz', 'page'])]);

        return view('quran.surah', ['surah' => $surah, 'ayahs' => $surah->ayahs]);
    }
}
