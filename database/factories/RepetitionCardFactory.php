<?php

namespace Database\Factories;

use App\Models\Quran\Ayah;
use App\Models\RepetitionCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RepetitionCard> */
class RepetitionCardFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'ayah_id' => fn (): int => Ayah::query()->firstOrFail()->id, 'due_at' => now()];
    }
}
