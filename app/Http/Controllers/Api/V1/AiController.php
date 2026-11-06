<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\User;
use App\Services\Submissions\SubmissionAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
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
