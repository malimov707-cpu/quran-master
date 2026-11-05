<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Progress\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(private ProgressService $progress) {}

    /**
     * O'quvchi o'z progressini ko'radi.
     */
    public function my(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('progress.view'), 403);

        return response()->json($this->progress->forStudent($user) + [
            'attendance' => $this->progress->attendanceStats($user),
            'recommended_practice' => $this->progress->recommendedPractice($user),
        ]);
    }

    /**
     * Ota-ona farzandi progressini ko'radi (link tekshiruvi bilan).
     */
    public function child(Request $request, User $student): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('progress.view'), 403);

        $isLinked = $user->children()->where('student_id', $student->id)->exists();
        $isSelf = $user->id === $student->id;
        $isMentor = $user->hasPermission('submissions.review');

        abort_unless($isLinked || $isSelf || $isMentor, 403, 'Bu o\'quvchiga ruxsatingiz yo\'q.');

        return response()->json($this->progress->forStudent($student) + [
            'attendance' => $this->progress->attendanceStats($student),
        ]);
    }
}
