<?php

namespace Database\Factories;

use App\Models\RepetitionCard;
use App\Models\RepetitionReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RepetitionReview> */
class RepetitionReviewFactory extends Factory
{
    public function definition(): array
    {
        return ['repetition_card_id' => RepetitionCard::factory(), 'rating' => 'good', 'version' => 1, 'interval_days' => 1, 'reviewed_at' => now()];
    }
}
