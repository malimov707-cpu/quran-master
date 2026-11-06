<?php

namespace App\Services\Quran;

use App\Models\Quran\AudioEdition;
use App\Models\Quran\AudioFile;
use App\Models\Quran\AudioPreference;
use App\Models\Quran\PlaybackHistory;
use App\Models\Quran\Surah;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AudioLibraryService
{
    /** @return Collection<int, AudioEdition> */
    public function editions(): Collection
    {
        return AudioEdition::with('reciter:id,name,licensed')->whereHas('reciter', fn ($query) => $query->where('licensed', true))
            ->withCount(['files as ayah_count' => fn ($query) => $query->where('kind', 'ayah')->whereIn('status', ['listed', 'verified']),
                'files as verified_ayah_count' => fn ($query) => $query->where('kind', 'ayah')->where('status', 'verified'),
                'files as surah_count' => fn ($query) => $query->where('kind', 'surah')->whereIn('status', ['listed', 'verified'])])
            ->orderBy('id')->get();
    }

    /** @return array<string, mixed> */
    public function catalog(User $user): array
    {
        return ['lists' => DB::table('quran_audio_lists')->where('user_id', $user->id)->latest('id')->limit(50)->get(), 'editions' => $this->editions(), 'surahs' => Surah::orderBy('number')->get(['id', 'number', 'name_uz', 'name_arabic', 'ayah_count']),
            'preferences' => AudioPreference::where('user_id', $user->id)->first(),
            'favorites' => DB::table('quran_audio_favorites')->where('user_id', $user->id)->pluck('edition_id'),
            'history' => PlaybackHistory::where('user_id', $user->id)->with('file.edition.reciter', 'file.surah:id,number,name_uz', 'file.ayah:id,number_in_surah')->latest('played_at')->limit(20)->get()];
    }

    /** @return array<string, mixed> */
    public function playlist(AudioEdition $edition, Surah $surah, int $from, int $to, string $mode): array
    {
        abort_unless($edition->reciter->licensed, 404);
        $query = $edition->files()->where('surah_id', $surah->id)->where('kind', $mode)->whereIn('status', ['listed', 'verified'])->orderBy('number');
        if ($mode === 'ayah') {
            $query->whereHas('ayah', fn ($query) => $query->whereBetween('number_in_surah', [$from, $to]));
        }
        $files = $query->with(['ayah:id,number_in_surah,text_arabic', 'alignmentSegments.ayah:id,number_in_surah'])->get();
        $expected = $mode === 'ayah' ? $to - $from + 1 : 1;

        return ['edition' => $edition->load('reciter:id,name'), 'surah' => $surah, 'mode' => $mode,
            'synchronization' => $mode === 'ayah' ? 'ayah_file_boundaries' : ($files->contains(fn (AudioFile $file): bool => $file->alignmentSegments->isNotEmpty()) ? 'verified_segments' : 'none'),
            'expected_count' => $expected, 'available_count' => $files->count(), 'complete' => $files->count() === $expected,
            'tracks' => $files->map(fn (AudioFile $file): array => ['id' => $file->id, 'url' => $file->url, 'kind' => $file->kind, 'number' => $file->number,
                'surah_id' => $surah->id, 'surah_number' => $surah->number, 'surah_name' => $surah->name_uz, 'ayah_number' => $file->ayah?->number_in_surah,
                'text_arabic' => $file->ayah?->text_arabic, 'size_bytes' => $file->size_bytes, 'duration_ms' => $file->duration_ms, 'status' => $file->status,
                'timestamps' => $file->alignmentSegments->sortBy('start_ms')->map(fn ($timestamp): array => [
                    'ayah_number' => $timestamp->ayah->number_in_surah,
                    'start_ms' => $timestamp->start_ms,
                    'end_ms' => $timestamp->end_ms,
                    'source_url' => $timestamp->source_url,
                ])->values(),
                'offline_allowed' => $edition->offline_allowed, 'reciter' => $edition->reciter->name])];
    }

    public function playable(int $fileId): AudioFile
    {
        return AudioFile::whereIn('status', ['listed', 'verified'])->whereHas('edition.reciter', fn ($query) => $query->where('licensed', true))->findOrFail($fileId);
    }
}
