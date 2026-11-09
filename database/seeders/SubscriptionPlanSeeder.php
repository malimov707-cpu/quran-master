<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Bepul', 'price_tiyin' => 0, 'months' => 1, 'benefits_uz' => "AI chat — kunda 20 savol\nQur'on o'qish va tinglash\nDarslar va topshiriqlar"],
            ['name' => 'Standart', 'price_tiyin' => 99000, 'months' => 1, 'benefits_uz' => "AI chat cheklovsiz\nAI Ustoz ovozli muloqot\nBatafsil qiroat tahlili\nOffline audio download"],
            ['name' => 'Premium (3 oy)', 'price_tiyin' => 249000, 'months' => 3, 'benefits_uz' => "Barcha Standart imkoniyatlar\nShaxsiy o'quv rejasi\nUstoz bilan alohida konsultatsiya\nSertifikatlar"],
        ] as $plan) {
            SubscriptionPlan::updateOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
