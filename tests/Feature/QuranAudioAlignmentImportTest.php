<?php

use App\Models\Quran\AudioEdition;
use App\Models\Quran\AudioFile;
use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function writeAlignmentManifest(array $manifest): string
{
    $path = tempnam(sys_get_temp_dir(), 'quran-alignment-');
    file_put_contents($path, json_encode($manifest, JSON_THROW_ON_ERROR));

    return $path;
}

test('alignment import accepts verified segments and replaces prior segments atomically', function () {
    $surah = Surah::create(['number' => 1, 'name_arabic' => 'الفاتحة', 'name_uz' => 'Fotiha', 'name_en' => 'Al-Fatihah', 'ayah_count' => 2, 'revelation_type' => 'meccan']);
    $first = Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 1, 'juz' => 1, 'page' => 1, 'text_arabic' => 'first']);
    $second = Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 2, 'number_global' => 2, 'juz' => 1, 'page' => 1, 'text_arabic' => 'second']);
    $edition = AudioEdition::factory()->create(['identifier' => 'verified-edition']);
    $file = AudioFile::factory()->create([
        'edition_id' => $edition->id, 'surah_id' => $surah->id, 'ayah_id' => null, 'kind' => 'surah',
        'number' => $surah->id, 'duration_ms' => 8000, 'status' => 'listed',
    ]);
    $path = writeAlignmentManifest([
        'edition' => $edition->identifier,
        'surah' => $surah->number,
        'audio_file_id' => $file->id,
        'source_url' => 'https://alignment.example.test/source.json',
        'segments' => [
            ['ayah' => 1, 'start_ms' => 0, 'end_ms' => 4000],
            ['ayah' => 2, 'start_ms' => 4000, 'end_ms' => 8000],
        ],
    ]);

    $this->artisan('quran:audio:alignment-import', ['file' => $path])->assertSuccessful();
    $this->assertDatabaseCount('quran_audio_timestamps', 2);
    $this->assertDatabaseHas('quran_audio_timestamps', ['audio_file_id' => $file->id, 'ayah_id' => $first->id, 'start_ms' => 0, 'end_ms' => 4000]);
    $this->assertDatabaseHas('quran_audio_timestamps', ['audio_file_id' => $file->id, 'ayah_id' => $second->id, 'start_ms' => 4000, 'end_ms' => 8000]);
    $this->assertSame('verified', $file->fresh()->status);
    @unlink($path);
});

test('alignment import rejects overlap and coordinates beyond audio duration', function (array $segments) {
    $surah = Surah::create(['number' => 1, 'name_arabic' => 'الفاتحة', 'name_uz' => 'Fotiha', 'name_en' => 'Al-Fatihah', 'ayah_count' => 1, 'revelation_type' => 'meccan']);
    Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 1, 'juz' => 1, 'page' => 1, 'text_arabic' => 'first']);
    $edition = AudioEdition::factory()->create(['identifier' => 'verified-edition']);
    $file = AudioFile::factory()->create([
        'edition_id' => $edition->id, 'surah_id' => $surah->id, 'ayah_id' => null, 'kind' => 'surah',
        'number' => $surah->id, 'duration_ms' => 5000, 'status' => 'verified',
    ]);
    $path = writeAlignmentManifest([
        'edition' => $edition->identifier,
        'surah' => $surah->number,
        'audio_file_id' => $file->id,
        'source_url' => 'https://alignment.example.test/source.json',
        'segments' => $segments,
    ]);

    $this->artisan('quran:audio:alignment-import', ['file' => $path])->assertFailed();
    $this->assertDatabaseCount('quran_audio_timestamps', 0);
    @unlink($path);
})->with([
    'overlap' => [[['ayah' => 1, 'start_ms' => 0, 'end_ms' => 3000], ['ayah' => 1, 'start_ms' => 2000, 'end_ms' => 4000]]],
    'past duration' => [[['ayah' => 1, 'start_ms' => 0, 'end_ms' => 6000]]],
]);

test('alignment import rejects duplicate and descending ayah segments', function (array $segments) {
    $surah = Surah::create(['number' => 1, 'name_arabic' => 'الفاتحة', 'name_uz' => 'Fotiha', 'name_en' => 'Al-Fatihah', 'ayah_count' => 2, 'revelation_type' => 'meccan']);
    Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 1, 'juz' => 1, 'page' => 1, 'text_arabic' => 'first']);
    Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 2, 'number_global' => 2, 'juz' => 1, 'page' => 1, 'text_arabic' => 'second']);
    $edition = AudioEdition::factory()->create(['identifier' => 'verified-edition']);
    $file = AudioFile::factory()->create([
        'edition_id' => $edition->id, 'surah_id' => $surah->id, 'ayah_id' => null, 'kind' => 'surah',
        'number' => $surah->id, 'duration_ms' => 5000, 'status' => 'verified',
    ]);
    $path = writeAlignmentManifest([
        'edition' => $edition->identifier,
        'surah' => $surah->number,
        'audio_file_id' => $file->id,
        'source_url' => 'https://alignment.example.test/source.json',
        'segments' => $segments,
    ]);

    $this->artisan('quran:audio:alignment-import', ['file' => $path])->assertFailed();
    $this->assertDatabaseCount('quran_audio_timestamps', 0);
    @unlink($path);
})->with([
    'duplicate ayah' => [[['ayah' => 1, 'start_ms' => 0, 'end_ms' => 2000], ['ayah' => 1, 'start_ms' => 2000, 'end_ms' => 4000]]],
    'descending ayah' => [[['ayah' => 2, 'start_ms' => 0, 'end_ms' => 2000], ['ayah' => 1, 'start_ms' => 2000, 'end_ms' => 4000]]],
]);
