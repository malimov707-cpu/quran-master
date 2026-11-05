<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\TajweedAnalysisProvider;

/**
 * Tajweed modeli HOZIRCHA MAVJUD EMAS.
 * Bu stub hech qachon soxta natija qaytarmaydi — faqat null (integration-ready).
 * Haqiqiy model ulanganda bu class almashtiriladi.
 */
class UnavailableTajweedProvider implements TajweedAnalysisProvider
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'none';
    }

    public function analyze(string $absolutePath, array $segments): ?array
    {
        // Soxta tajvid xulosasi TAQIQLANGAN — har doim null qaytaradi.
        return null;
    }
}
