<?php

namespace App\Services\AI\Providers;

use App\Models\AiUsageLog;
use App\Services\AI\Contracts\SpeechRecognitionProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Whisper API orqali transkripsiya.
 * Sozlanmagan bo'lda isConfigured()=false — pipeline aniq "unavailable" holatiga o'tadi.
 */
class WhisperSpeechProvider implements SpeechRecognitionProvider
{
    private const API_URL = 'https://api.openai.com/v1/audio/transcriptions';

    public function isConfigured(): bool
    {
        return (bool) config('services.openai.key');
    }

    public function name(): string
    {
        return 'openai_whisper';
    }

    public function model(): string
    {
        return (string) config('services.openai.whisper_model', 'whisper-1');
    }

    public function transcribe(string $absolutePath, string $language = 'ar'): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $started = microtime(true);
        $response = Http::withToken((string) config('services.openai.key'))
            ->timeout(120)
            ->attach('file', fopen($absolutePath, 'r'), basename($absolutePath))
            ->post(self::API_URL, [
                'model' => $this->model(),
                'language' => $language,
                'response_format' => 'verbose_json',
                'timestamp_granularities' => ['word'],
            ]);

        $duration = (int) ((microtime(true) - $started) * 1000);

        AiUsageLog::create([
            'provider' => $this->name(),
            'model' => $this->model(),
            'operation' => 'transcription',
            'duration_ms' => $duration,
            'success' => $response->successful(),
        ]);

        if (! $response->successful()) {
            Log::warning('Whisper API xato', ['status' => $response->status()]);
            throw new \RuntimeException('Whisper API xato: '.$response->status());
        }

        $json = $response->json();

        $words = [];
        foreach ($json['words'] ?? [] as $w) {
            $words[] = [
                'word' => $w['word'] ?? '',
                'start_ms' => isset($w['start']) ? round($w['start'] * 1000) : null,
                'end_ms' => isset($w['end']) ? round($w['end'] * 1000) : null,
                'confidence' => $w['confidence'] ?? null,
            ];
        }

        return [
            'text' => $json['text'] ?? '',
            'words' => $words,
            'language_confidence' => null,
        ];
    }
}
