<?php

namespace App\Services\AI\Contracts;

/**
 * Speech Recognition provayderi — almashtirilishi mumkin.
 * Kalitlar faqat backendda (.env) saqlanadi.
 */
interface SpeechRecognitionProvider
{
    /**
     * Provayder sozlanganmi (API kalit mavjudmi)?
     */
    public function isConfigured(): bool;

    /**
     * Provayder identifikatori (ai_reviews.provider).
     */
    public function name(): string;

    /**
     * Model nomi.
     */
    public function model(): string;

    /**
     * Audio transkripsiya.
     *
     * @param  string  $absolutePath  Audio faylning to'liq yo'li
     * @param  string  $language  Kutilgan til (ar)
     * @return array{
     *     text: string,
     *     words: list<array{word: string, start_ms: float|null, end_ms: float|null, confidence: float|null}>,
     *     language_confidence: float|null
     * }|null  Sozlanmagan bo'lsa null
     *
     * @throws \RuntimeException API xatolari uchun
     */
    public function transcribe(string $absolutePath, string $language = 'ar'): ?array;
}
