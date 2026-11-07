<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\User;
use App\Services\AI\Contracts\AIExplanationProvider;
use App\Services\AI\RoleAwareAiAssistant;
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
            'premium' => app(RoleAwareAiAssistant::class)->hasAccess($user),
            'capabilities' => app(RoleAwareAiAssistant::class)->capabilities($user),
            'suggested_prompts' => app(RoleAwareAiAssistant::class)->suggestedPrompts($user),
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

        if (! app(RoleAwareAiAssistant::class)->hasAccess($user)) {
            return response()->json([
                'status' => 'premium_required',
                'message' => 'Bu AI yordamchisi premium yoki yuqori ball darajasiga ega o‘quvchilar uchun ochilgan.',
            ], 402);
        }

        $answer = $provider->explain($data['question'], app(RoleAwareAiAssistant::class)->contexts($user));

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
}
