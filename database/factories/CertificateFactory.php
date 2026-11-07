<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Certificate> */
class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => (string) Str::uuid(), 'user_id' => User::factory(), 'source_key' => 'fixture:'.Str::uuid(),
            'type' => 'course', 'recipient_name' => fake()->name(), 'title' => 'Qur’on va tajvid', 'score' => null,
            'evidence' => ['fixture' => true], 'issued_at' => now()];
    }
}
