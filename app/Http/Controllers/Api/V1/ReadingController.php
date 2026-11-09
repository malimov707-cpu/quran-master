<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlaybackHistory;
use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use App\Models\ReadingPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O'qish holati: bookmark, oxirgi o'qilgan joy, tinglash tarixi.
 * Web va mobile bir xil API bilan sinxron bo'ladi.
 */
class ReadingController extends Controller
{
    /**
     * Bookmark qo'shish/o'chirish (toggle).
     */
    public function toggleBookmark(Request $request): JsonResponse
    {
        $data = $request->validate([
            'surah' => ['required', 'integer', 'min:1', 'max:114'],
            'ayah' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:512'],
        ]);

        $ayah = Ayah::query()
            ->whereHas('surah', fn ($q) => $q->where('number', $data['surah']))
            ->where('number_in_surah', $data['ayah'])
            ->firstOrFail();

        $existing = $request->user()->quranBookmarks()->where('ayah_id', $ayah->id)->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['bookmarked' => false]);
        }

        $request->user()->quranBookmarks()->create([
            'ayah_id' => $ayah->id,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['bookmarked' => true], 201);
    }

    /**
     * Bookmarklar ro'yxati (surah ma'lumoti bilan).
     */
    public function bookmarks(Request $request): JsonResponse
    {
        $bookmarks = $request->user()->quranBookmarks()
            ->with(['ayah.surah:id,number,name_uz,name_arabic'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($bookmarks->map(fn ($bookmark) => [
            'id' => $bookmark->id,
            'surah_number' => $bookmark->ayah->surah->number,
            'surah_name' => $bookmark->ayah->surah->name_uz,
            'ayah_number' => $bookmark->ayah->number_in_surah,
            'text_arabic' => $bookmark->ayah->text_arabic,
            'text_uz' => $bookmark->ayah->text_uz,
            'note' => $bookmark->note,
            'created_at' => $bookmark->created_at->toIso8601String(),
        ]));
    }

    /**
     * Oxirgi o'qilgan joyni saqlash (avtomatik, o'quvchi o'qiyotganda).
     */
    public function savePosition(Request $request): JsonResponse
    {
        $data = $request->validate([
            'surah' => ['required', 'integer', 'min:1', 'max:114'],
            'ayah' => ['required', 'integer', 'min:1'],
        ]);

        $surah = Surah::where('number', $data['surah'])->firstOrFail();

        ReadingPosition::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['surah_id' => $surah->id, 'ayah_number' => $data['ayah']],
        );

        return response()->json(['saved' => true]);
    }

    /**
     * Oxirgi o'qilgan joy.
     */
    public function position(Request $request): JsonResponse
    {
        $position = $request->user()->readingPosition()->with('surah:id,number,name_uz,name_arabic')->first();

        if (! $position) {
            return response()->json(['position' => null]);
        }

        return response()->json([
            'position' => [
                'surah_number' => $position->surah->number,
                'surah_name' => $position->surah->name_uz,
                'ayah' => $position->ayah_number,
                'updated_at' => $position->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Tinglash tarixi yozuvi (audio player to'xtaganda/oyat o'zgarganda).
     */
    public function logPlayback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'surah' => ['required', 'integer', 'min:1', 'max:114'],
            'ayah_from' => ['nullable', 'integer', 'min:1'],
            'ayah_to' => ['nullable', 'integer', 'min:1'],
            'seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'reciter' => ['nullable', 'string', 'max:64'],
        ]);

        $surah = Surah::where('number', $data['surah'])->firstOrFail();

        PlaybackHistory::create([
            'user_id' => $request->user()->id,
            'surah_id' => $surah->id,
            'ayah_from' => $data['ayah_from'] ?? null,
            'ayah_to' => $data['ayah_to'] ?? null,
            'seconds_listened' => $data['seconds'],
            'reciter' => $data['reciter'] ?? null,
        ]);

        return response()->json(['logged' => true], 201);
    }

    /**
     * Tinglash statistikasi: jami vaqt, bu hafta, eng ko'p suralar.
     */
    public function listeningStats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totals = PlaybackHistory::where('user_id', $userId)
            ->selectRaw('coalesce(sum(seconds_listened),0) as total_seconds, count(*) as sessions')
            ->first();

        $weekSeconds = (int) PlaybackHistory::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('seconds_listened');

        $topSurahs = PlaybackHistory::where('user_id', $userId)
            ->join('quran_surahs', 'quran_surahs.id', '=', 'playback_history.surah_id')
            ->groupBy('quran_surahs.number', 'quran_surahs.name_uz')
            ->selectRaw('quran_surahs.number, quran_surahs.name_uz, sum(seconds_listened) as seconds')
            ->orderByDesc('seconds')
            ->limit(5)
            ->get();

        return response()->json([
            'total_minutes' => (int) round($totals->total_seconds / 60),
            'sessions' => (int) $totals->sessions,
            'week_minutes' => (int) round($weekSeconds / 60),
            'top_surahs' => $topSurahs->map(fn ($row) => [
                'surah' => (int) $row->number,
                'name' => $row->name_uz,
                'minutes' => (int) round($row->seconds / 60),
            ]),
        ]);
    }
}
