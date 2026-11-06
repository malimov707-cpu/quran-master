<?php

// AI konfiguratsiyasi — kalitlar faqat backendda (.env)
return [
    // Ishonchlilik shu qiymatdan past bo'lsa avtomatik mentor tekshiruviga o'tadi
    'min_confidence' => env('AI_MIN_CONFIDENCE', 0.85),
    'timeout_seconds' => env('AI_TIMEOUT_SECONDS', 15),
    'premium_score_threshold' => env('AI_PREMIUM_SCORE_THRESHOLD', 85),
];
