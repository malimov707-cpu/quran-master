<?php

namespace App\Http\Controllers;

use App\Services\UzbekPrayerTimesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrayerTimesController extends Controller
{
    public function index(): View
    {
        return view('prayer-times');
    }

    public function data(Request $request, UzbekPrayerTimesService $prayers): JsonResponse
    {
        $data = $request->validate(['city' => ['sometimes', 'required', 'string', Rule::in(array_keys(config('prayer_times.cities')))]]);

        return response()->json($prayers->overview($data['city'] ?? config('prayer_times.default_city')));
    }
}
