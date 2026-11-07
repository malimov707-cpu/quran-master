<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class VoiceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audio' => ['required', 'file', 'max:5120', 'mimetypes:audio/webm,video/webm,audio/ogg,application/ogg,audio/mp4,video/mp4,audio/x-m4a,audio/mpeg,audio/wav,audio/x-wav'],
            'history' => ['nullable', 'string', 'json', 'max:18000'],
        ]);
        $history = Validator::make(['messages' => json_decode($data['history'] ?? '[]', true)], [
            'messages' => ['array', 'max:8'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ])->validate()['messages'];

        if (! config('services.openrouter.key')) {
            return response()->json(['message' => 'OpenRouter API kaliti sozlanmagan. Administrator bilan bog‘laning.'], 503);
        }

        $format = match ($data['audio']->getMimeType()) {
            'audio/webm', 'video/webm' => 'webm',
            'audio/ogg', 'application/ogg' => 'ogg',
            'audio/mp4', 'video/mp4', 'audio/x-m4a' => 'm4a',
            'audio/mpeg' => 'mp3',
            default => 'wav',
        };
        $messages = [['role' => 'system', 'content' => <<<'TXT'
Sen Qur'on AI Ustoz ovozli yordamchisisan. O'zbekcha qisqa (2-4 gap) va tushunarli javob ber.
Qur'on oyatlarini to'qima. Diniy hukm va fatvo uchun malakali ustozga yo'naltir.
Tilovatga qat'iy baho qo'yma, ustoz nazorati kerakligini bildir. Tushunmagan tovushlarni taxmin qilma.
Faqat JSON obyekt qaytar: {"transcript":"eshitilgan so'zlar asl tilida", "answer":"savolga javob"}.
Ovoz eshitilmasa yoki gap tushunarsiz bo'lsa transcript bo'sh satr bo'lsin, answer qayta aytishni so'rasin.
TXT]];
        foreach ($history as $message) {
            $messages[] = ['role' => $message['role'], 'content' => $message['content']];
        }
        $messages[] = ['role' => 'user', 'content' => [[
            'type' => 'input_audio',
            'input_audio' => ['data' => base64_encode($data['audio']->getContent()), 'format' => $format],
        ]]];

        try {
            $response = $this->client()->post('/chat/completions', [
                'model' => config('services.openrouter.voice_model'),
                'messages' => $messages,
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 800,
            ]);
        } catch (ConnectionException) {
            return response()->json(['message' => 'OpenRouter bilan bog‘lanib bo‘lmadi. Qayta urinib ko‘ring.'], 502);
        }
        if (! $response->successful()) {
            return response()->json(['message' => $this->providerError($response)], 502);
        }
        $content = $response->json('choices.0.message.content');
        $result = is_string($content) ? json_decode($content, true) : null;
        if (! is_array($result) || ! is_string($result['transcript'] ?? null) || ! is_string($result['answer'] ?? null)
            || trim($result['answer']) === '' || mb_strlen($result['answer']) > 3000 || mb_strlen($result['transcript']) > 4000) {
            return response()->json(['message' => 'OpenRouter yaroqli javob qaytarmadi. Qayta urinib ko‘ring.'], 502);
        }
        $output = ['provider' => 'openrouter', 'transcript' => trim($result['transcript']), 'answer' => trim($result['answer']), 'audio' => null, 'warning' => null];
        try {
            $speech = $this->client()->post('/audio/speech', [
                'model' => config('services.openrouter.voice_speech_model'),
                'input' => $output['answer'],
                'voice' => config('services.openrouter.voice_name'),
                'instructions' => 'O‘zbek tilida, muloyim va ravon gapiring.',
                'response_format' => 'pcm',
            ]);
            $pcm = $speech->body();
            if ($speech->successful() && str_starts_with(strtolower($speech->header('Content-Type')), 'audio/') && strlen($pcm) > 0 && strlen($pcm) <= 10000000 && strlen($pcm) % 2 === 0) {
                $size = strlen($pcm);
                $wav = 'RIFF'.pack('V', 36 + $size).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 24000, 48000, 2, 16).'data'.pack('V', $size).$pcm;
                $output['audio'] = ['data' => base64_encode($wav), 'mime_type' => 'audio/wav'];
            } else {
                $output['warning'] = $speech->successful() ? 'Ovoz yaratilmadi. Javobni matnda o‘qing.' : $this->providerError($speech);
            }
        } catch (ConnectionException) {
            $output['warning'] = 'Ovoz xizmatiga ulanib bo‘lmadi. Javob matnda saqlandi.';
        }

        return response()->json($output)->header('Cache-Control', 'no-store, private');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.openrouter.base_url'), '/'))
            ->withToken((string) config('services.openrouter.key'))
            ->connectTimeout(5)->timeout(45);
    }

    private function providerError(Response $response): string
    {
        return match ($response->status()) {
            402 => 'OpenRouter balansida kredit yetarli emas. Administrator balansni to‘ldirishi kerak.',
            401, 403 => 'OpenRouter kaliti yoki modelga kirish ruxsatini administrator tekshirishi kerak.',
            429 => 'OpenRouter so‘rovlar chegarasiga yetildi. Birozdan so‘ng qayta urinib ko‘ring.',
            default => 'OpenRouter ovoz xizmati javob bermadi. Keyinroq qayta urinib ko‘ring.',
        };
    }
}
