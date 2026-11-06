<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\User;
use App\Services\AI\Contracts\AIExplanationProvider;
use App\Services\Submissions\SubmissionAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function capabilities(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $provider = app(AIExplanationProvider::class);

        return response()->json([
            'configured' => $provider->isConfigured(),
            'provider' => $provider->name(),
            'model' => $provider->model(),
            'premium' => $this->hasPremiumAccess($user),
            'capabilities' => $this->capabilitiesFor($user),
        ]);
    }

    public function chat(Request $request, AIExplanationProvider $provider): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['question' => ['required', 'string', 'max:4000']]);

        if (! $provider->isConfigured()) {
            return response()->json(['status' => 'unavailable', 'message' => 'OpenRouter API kaliti sozlanmagan.'], 503);
        }

        if (! $this->hasPremiumAccess($user)) {
            return response()->json([
                'status' => 'premium_required',
                'message' => 'Bu AI yordamchisi premium yoki yuqori ball darajasiga ega o‘quvchilar uchun ochilgan.',
            ], 402);
        }

        $answer = $provider->explain($data['question'], $this->contextsFor($user));

        if (! $answer) {
            return response()->json(['status' => 'unavailable', 'message' => 'AI provider javob qaytarmadi.'], 502);
        }

        return response()->json(['status' => 'completed', 'answer' => $answer]);
    }

    public function setPremium(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->hasRole('CEO', 'SUPER_ADMIN', 'CENTER_ADMIN'), 403);

        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $user->update(['ai_premium' => $data['enabled']]);

        return response()->json(['status' => 'ok', 'user_id' => $user->id, 'ai_premium' => $user->ai_premium]);
    }

    /**
     * AI tahlil natijasi (o'quvchi yoki mentor ko'radi).
     */
    public function show(Request $request, Submission $submission): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        app(SubmissionAccess::class)->authorizeView($user, $submission);

        $review = $submission->aiReviews()->with('segments')->latest()->first();

        if (! $review) {
            return response()->json(['status' => 'none', 'message' => 'AI tahlil hali boshlanmagan.']);
        }

        return response()->json([
            'status' => $review->status,
            'provider' => $review->provider,
            'model' => $review->model,
            'overall_confidence' => $review->overall_confidence,
            'transcript' => $review->transcript,
            'summary' => $review->summary,
            'needs_mentor_review_reason' => $review->needs_mentor_review_reason,
            'error_message' => $review->error_message,
            'segments' => $review->segments->map(fn ($s) => [
                'ayah_number' => $s->ayah_number,
                'expected_text' => $s->expected_text,
                'heard_text' => $s->heard_text,
                'error_type' => $s->error_type,
                'start_ms' => $s->start_ms,
                'end_ms' => $s->end_ms,
                'confidence' => $s->confidence,
                'is_correct' => $s->is_correct,
            ]),
        ]);
    }

    private function hasPremiumAccess(User $user): bool
    {
        if (! $user->hasRole('STUDENT')) {
            return $user->hasRole('CEO', 'SUPER_ADMIN', 'CENTER_ADMIN', 'HEAD_TEACHER', 'MENTOR', 'PARENT');
        }

        if ($user->ai_premium) {
            return true;
        }

        $score = (float) $user->studentProgress()->avg('average_score');

        return $score >= (float) config('ai.premium_score_threshold', 85);
    }

    /** @return list<string> */
    private function capabilitiesFor(User $user): array
    {
        if ($user->hasRole('CEO', 'SUPER_ADMIN', 'CENTER_ADMIN')) {
            return ['Boshqaruv hisobotlari', 'KPI va risklar', 'Premium boshqaruvi', 'Hodimlar va o‘quv operatsiyalari'];
        }
        if ($user->hasRole('HEAD_TEACHER')) {
            return ['Dars rejalari', 'Tajvid tushuntirishlari', 'Mavzu tayyorlash', 'O‘quvchi risklarini tahlil qilish'];
        }
        if ($user->hasRole('MENTOR')) {
            return ['Yozuvni qayta ko‘rib chiqish', 'Shaxsiy feedback', 'Talaba rivojlanishi', 'Mashg‘ulot tavsiyasi'];
        }
        if ($user->hasRole('PARENT')) {
            return ['Farzand rivojlanishi', 'Xavfsiz o‘quv tavsiyasi', 'Davomat va progress xulosasi'];
        }

        return ['Tajvid savollari', 'Dars yordamchisi', 'Xatolarni tushuntirish', 'Shaxsiy o‘quv rejasi'];
    }

    /** @return list<string> */
    private function contextsFor(User $user): array
    {
        if ($user->hasRole('CEO', 'SUPER_ADMIN')) {
            return ['Rol: CEO/Super Admin', 'Markaz strategiyasi, KPI va operatsion qarorlar bo‘yicha yordam ber.', 'Tasdiqlanmagan ma\'lumotni fakt sifatida ko\'rsatma.'];
        }
        if ($user->hasRole('CENTER_ADMIN')) {
            return ['Rol: markaz administratori', 'Guruhlar, hodimlar, kurslar va operatsiyalarga yordam ber.'];
        }
        if ($user->hasRole('HEAD_TEACHER')) {
            return ['Rol: bosh ustoz', 'Dars rejasi, tajvid kontenti va o‘qituvchi boshqaruvi bo‘yicha yordam ber.'];
        }
        if ($user->hasRole('MENTOR')) {
            return ['Rol: mentor', 'O‘quvchi topshiriqlari va qiroati bo‘yicha konstruktiv feedback tayyorlashga yordam ber.'];
        }
        if ($user->hasRole('PARENT')) {
            return ['Rol: ota-ona', 'Faqat xavfsiz, umumiy va progressga oid o‘zaro o‘quv tavsiyasini ber.'];
        }

        return ['Rol: o‘quvchi', 'Oson tushuntirish, Qur’on savollari va o‘qishni takomillashtirishga yordam ber.', 'Muqim diniy hukmlarda ustozga murojaat qilishni ayt.'];
    }
}
