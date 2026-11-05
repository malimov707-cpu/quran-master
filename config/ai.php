<?php

// AI konfiguratsiyasi — kalitlar faqat backendda (.env)
return [
    // Ishonchlilik shu qiymatdan past bo'lsa avtomatik mentor tekshiruviga o'tadi
    'min_confidence' => env('AI_MIN_CONFIDENCE', 0.85),
];
