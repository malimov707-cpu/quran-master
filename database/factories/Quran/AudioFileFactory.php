<?php

namespace Database\Factories\Quran;

use App\Models\Quran\AudioEdition;
use App\Models\Quran\AudioFile;
use App\Models\Quran\Ayah;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AudioFile> */
class AudioFileFactory extends Factory
{
    public function definition(): array
    {
        return ['edition_id' => AudioEdition::factory(), 'ayah_id' => fn (): int => Ayah::firstOrFail()->id, 'surah_id' => fn (): int => Ayah::firstOrFail()->surah_id,
            'kind' => 'ayah', 'number' => 1, 'url' => 'https://cdn.islamic.network/quran/audio/128/ar.alafasy/1.mp3', 'status' => 'verified', 'size_bytes' => 4096, 'verified_at' => now()];
    }
}
