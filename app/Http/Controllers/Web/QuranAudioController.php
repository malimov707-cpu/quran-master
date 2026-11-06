<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Quran\AudioLibraryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuranAudioController extends Controller
{
    public function index(Request $request, AudioLibraryService $audio): View
    {
        return view('quran.audio', ['catalog' => $audio->catalog($request->user())]);
    }
}
