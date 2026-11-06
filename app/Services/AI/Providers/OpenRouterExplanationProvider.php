<?php

namespace App\Services\AI\Providers;

use App\Models\AiUsageLog;
use App\Services\AI\Contracts\AIExplanationProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenRouterExplanationProvider implements AIExplanationProvider
{
    public function isConfigured(): bool
    {
        return (bool) config('services.openrouter.key');
    }

    public function name(): string
    {
        return 'openrouter';
    }

    public function model(): string
    {
        return (string) config('services.openrouter.model');
    }

    public function explain(string $question, array $allowedContexts = []): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $started = microtime(true);
        $context = $allowedContexts === [] ? '' : "\n\nMa'qullangan o'quv konteksti:\n".implode("\n", $allowedContexts);

        try {
            $response = Http::withToken((string) config('services.openrouter.key'))
                ->acceptJson()
                ->timeout((int) config('ai.timeout_seconds', 60))
                ->retry([500, 1500, 3000], 2, throw: false)
                ->post(rtrim((string) config('services.openrouter.base_url'), '/').'/chat/completions', [
                    'model' => $this->model(),
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt().$context],
                        ['role' => 'user', 'content' => $question],
                    ],
                ]);
        } catch (\Throwable $exception) {
            $this->usageLog(null, $started, false);
            Log::error('OpenRouter ulanishida xato.', ['message' => $exception->getMessage()]);

            throw new RuntimeException('AI provider hozir javob bera olmadi.');
        }

        $success = $response->successful();
        $this->usageLog(null, $started, $success);

        if (! $success) {
            Log::warning('OpenRouter API xatosi.', ['status' => $response->status()]);

            throw new RuntimeException('AI provider xatosi: '.$response->status());
        }

        return trim((string) data_get($response->json(), 'choices.0.message.content', '')) ?: null;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
    Siz Qur'on AI Ustoz platformasining yordamchisiz. Yaxshilik, ilmi asoslovchi manbalar, o'quvchi xavfsizligi va ustoz nazoratini birinchi o'ringa qo'ying.
    Faqat ishonchli dars kontekstini ishlating. Qur'on yoki tajvidga oid fikrni ishonchli dalil bilan ajrating.
    Fiqh, fatvo yoki diniy e'tiqod savollarida qat'iy hukm chiqarmang va malakali ustozga yo'naltiring.
    PROMPT;
    }

    private function usageLog(?int $userId, float $started, bool $success): void
    {
        AiUsageLog::create([
            'user_id' => $userId,
            'provider' => $this->name(),
            'model' => $this->model(),
            'operation' => 'chat',
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            'success' => $success,
        ]);
    }
}
