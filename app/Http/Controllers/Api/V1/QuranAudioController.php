<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quran\AudioEdition;
use App\Models\Quran\AudioPreference;
use App\Models\Quran\PlaybackHistory;
use App\Models\Quran\Surah;
use App\Services\Quran\AudioLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuranAudioController extends Controller
{
    public function __construct(private AudioLibraryService $audio) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->audio->catalog($request->user()));
    }

    public function playlist(Request $request, AudioEdition $edition, Surah $surah): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'integer', 'min:1', 'max:'.$surah->ayah_count], 'to' => ['nullable', 'integer', 'min:1', 'max:'.$surah->ayah_count, 'gte:from'], 'mode' => ['nullable', 'in:ayah,surah']]);
        $from = (int) ($data['from'] ?? 1);
        $to = (int) ($data['to'] ?? $surah->ayah_count);
        abort_if($to < $from, 422);

        return response()->json($this->audio->playlist($edition, $surah, $from, $to, $data['mode'] ?? 'ayah'));
    }

    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate(['edition_id' => ['nullable', 'integer', 'exists:quran_audio_editions,id'], 'speed' => ['sometimes', Rule::in([0.75, 1, 1.25, 1.5, 2])],
            'volume' => ['sometimes', 'numeric', 'between:0,1'], 'repeat_count' => ['sometimes', Rule::in([1, 3, 5, 10, 20])], 'gap_seconds' => ['sometimes', 'integer', 'between:0,60']]);
        if (isset($data['edition_id'])) {
            abort_unless(AudioEdition::findOrFail($data['edition_id'])->reciter->licensed, 422);
        }
        $preferences = AudioPreference::updateOrCreate(['user_id' => $request->user()->id], $data);

        return response()->json(['preferences' => $preferences->refresh()]);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate(['audio_file_id' => ['required', 'integer'], 'position_ms' => ['required', 'integer', 'min:0', 'max:86400000']]);
        $this->audio->playable($data['audio_file_id']);
        $history = PlaybackHistory::updateOrCreate(['user_id' => $request->user()->id, 'audio_file_id' => $data['audio_file_id']], ['position_ms' => $data['position_ms'], 'played_at' => now()]);

        return response()->json(['history' => $history]);
    }

    public function favorite(Request $request, AudioEdition $edition): JsonResponse
    {
        abort_unless($edition->reciter->licensed, 404);
        $data = $request->validate(['favorite' => ['required', 'boolean']]);
        $query = DB::table('quran_audio_favorites')->where('user_id', $request->user()->id)->where('edition_id', $edition->id);
        if ($data['favorite']) {
            DB::table('quran_audio_favorites')->insertOrIgnore(['user_id' => $request->user()->id, 'edition_id' => $edition->id]);
        } else {
            $query->delete();
        }

        return response()->json(['favorite' => $data['favorite']]);
    }

    public function download(Request $request): JsonResponse
    {
        $data = $request->validate(['audio_file_id' => ['required', 'integer'], 'device_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'], 'status' => ['required', 'in:downloaded,deleted'], 'size_bytes' => ['required', 'integer', 'min:0', 'max:2147483648']]);
        $file = $this->audio->playable($data['audio_file_id']);
        abort_unless($file->edition->offline_allowed, 403);
        DB::table('quran_audio_downloads')->updateOrInsert(['user_id' => $request->user()->id, 'audio_file_id' => $file->id, 'device_id' => $data['device_id']], ['status' => $data['status'], 'size_bytes' => $data['size_bytes'], 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['recorded' => true]);
    }

    public function storeList(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'edition_id' => ['required', 'integer', 'exists:quran_audio_editions,id'], 'surah_id' => ['required', 'integer', 'exists:quran_surahs,id'], 'ayah_from' => ['required', 'integer', 'min:1'], 'ayah_to' => ['required', 'integer', 'gte:ayah_from']]);
        $surah = Surah::findOrFail($data['surah_id']);
        abort_if($data['ayah_to'] > $surah->ayah_count, 422);
        abort_unless(AudioEdition::findOrFail($data['edition_id'])->reciter->licensed, 422);
        $id = DB::table('quran_audio_lists')->insertGetId($data + ['user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['list' => DB::table('quran_audio_lists')->find($id)], 201);
    }

    public function destroyList(Request $request, int $list): JsonResponse
    {
        $query = DB::table('quran_audio_lists')->where('user_id', $request->user()->id)->where('id', $list);
        abort_unless($query->exists(), 404);
        $query->delete();

        return response()->json(['deleted' => true]);
    }
}
