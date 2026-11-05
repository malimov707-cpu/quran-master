<?php

namespace App\Services\AI\Contracts;

/**
 * AI izoh beruvchi — o'quvchi savollariga dars materiallari asosida javob.
 * Fiqh/fatvo savollarida ustozga yo'naltirish majburiy.
 */
interface AIExplanationProvider
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * Savolga kontekst asosida javob.
     *
     * @param  list<string>  $allowedContexts  Faqat ishonchli dars materiallari
     */
    public function explain(string $question, array $allowedContexts = []): ?string;
}
