<?php

namespace Database\Factories\Quran;

use App\Models\Quran\AudioEdition;
use App\Models\Quran\Reciter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AudioEdition> */
class AudioEditionFactory extends Factory
{
    public function definition(): array
    {
        return ['reciter_id' => Reciter::factory(), 'identifier' => 'test.'.fake()->unique()->slug(), 'provider' => 'alquran_cloud', 'bitrate' => 128, 'style' => 'Murattal', 'source_url' => 'https://alquran.cloud/cdn',
            'license_url' => 'https://alquran.cloud/terms-and-conditions', 'license' => 'Test educational permission', 'version' => '2026-06-14', 'offline_allowed' => true, 'manifest_sha256' => str_repeat('a', 64), 'catalog_checked_at' => now()];
    }
}
