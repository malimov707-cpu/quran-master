<?php

namespace App\Services\AI\Contracts;

/**
 * Tajweed tahlili provayderi. HAQIQIY MODEL MAVJUD EMAS —
 * bu interfeys integration-ready. Hech qanday soxta aniqlash taqiqlangan.
 */
interface TajweedAnalysisProvider
{
    /**
     * Provayder sozlanganmi (real model ulanganmi)?
     */
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Audio segment bo'yicha tajvid tahlili.
     *
     * @param  list<array{start_ms: float, end_ms: float, ayah: int}>  $segments
     * @return list<array{
     *     rule: string, ayah: int, start_ms: float, end_ms: float,
     *     detected_issue: string, confidence: float
     * }>|null  Sozlanmagan bo'lsa null — hech qachon soxta natija qaytarmasin
     */
    public function analyze(string $absolutePath, array $segments): ?array;
}
