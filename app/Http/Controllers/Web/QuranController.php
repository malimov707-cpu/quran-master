<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Quran\Surah;
use Illuminate\View\View;

class QuranController extends BaseController
{
    public function surahsIndex(): View
    {
        $surahs = Surah::orderBy('number')->get();

        return view('quran.surahs', compact('surahs'));
    }

    public function surahShow(Surah $surah): View
    {
        $surah->load(['ayahs' => fn ($q) => $q->select(['id', 'surah_id', 'number_in_surah', 'text_arabic', 'text_uz', 'text_ru', 'juz', 'page'])]);

        return view('quran.surah', ['surah' => $surah, 'ayahs' => $surah->ayahs]);
    }
}
