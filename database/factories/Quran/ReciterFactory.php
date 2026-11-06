<?php

namespace Database\Factories\Quran;

use App\Models\Quran\Reciter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reciter> */
class ReciterFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->name(), 'style' => 'Murattal', 'audio_base_url' => 'https://cdn.islamic.network/quran', 'licensed' => true];
    }
}
