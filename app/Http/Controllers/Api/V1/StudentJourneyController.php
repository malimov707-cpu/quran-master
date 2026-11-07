<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\Submission;
use App\Services\Progress\StudentJourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentJourneyController extends Controller
{
    public function __construct(private StudentJourneyService $journey) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->journey->overview($request->user()));
    }

    public function lesson(Request $request, Lesson $lesson): JsonResponse
    {
        $this->journey->completeLesson($request->user(), $lesson);

        return response()->json(['completed' => true]);
    }

    public function practice(Request $request, Assignment $assignment): JsonResponse
    {
        $this->journey->completePractice($request->user(), $assignment);

        return response()->json(['completed' => true]);
    }

    public function feedback(Request $request, Submission $submission): JsonResponse
    {
        return response()->json($this->journey->acknowledgeFeedback($request->user(), $submission));
    }
}
