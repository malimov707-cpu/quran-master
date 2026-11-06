<?php

namespace App\Services\AI\Providers;

use App\Models\AiUsageLog;
use App\Services\AI\Contracts\AIExplanationProvider;
use Illuminate\Support\Facades\Cache;
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

        $cacheKey = 'ai:openrouter:'.hash('sha256', $this->model().'|'.$question.'|'.implode('|', $allowedContexts));
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && trim($cached) !== '') {
            return trim($cached);
        }

        $started = microtime(true);
        $context = $allowedContexts === [] ? '' : "\nKontekst: ".implode('; ', array_slice($allowedContexts, 0, 3));

        try {
            $response = Http::withToken((string) config('services.openrouter.key'))
                ->acceptJson()
                ->timeout((int) config('ai.timeout_seconds', 60))
                ->post(rtrim((string) config('services.openrouter.base_url'), '/').'/chat/completions', [
                    'model' => $this->model(),
                    'temperature' => 0.2,
                    'max_tokens' => 400,
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

        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($content === '') {
            $content = trim((string) data_get($response->json(), 'choices.0.message.reasoning', ''));
        }

        if ($content === '') {
            return null;
        }

        if (strlen($content) > 900) {
            $content = mb_substr($content, 0, 900)."\u{2026}";
        }

        Cache::put($cacheKey, $content, now()->addHours(24));

        return $content;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
    Siz Qur'on AI Ustoz yordamchisiz. 1-3 qisqa gapda, aniq va ishonchli javob bering. Qur'on/tajvid materialiga asosan javob bering; fatvo yoki qat'iy diniy hukm chiqarmang, ustozga yo'naltiring.
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
