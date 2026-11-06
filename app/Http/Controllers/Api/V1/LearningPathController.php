<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Progress\LearningPathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningPathController extends Controller
{
    public function __construct(private LearningPathService $learning) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->learning->overview($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $this->learning->authorizeStudent($request->user());
        $data = $request->validate(['ayah_id' => ['required', 'integer', 'exists:quran_ayahs,id']]);

        return response()->json(['card' => $this->learning->enroll($request->user(), $data['ayah_id'])], 201);
    }

    public function review(Request $request, int $card): JsonResponse
    {
        $this->learning->authorizeStudent($request->user());
        $data = $request->validate(['rating' => ['required', 'in:again,hard,good,easy'], 'version' => ['required', 'integer', 'min:0']]);

        return response()->json(['card' => $this->learning->review($request->user(), $card, $data['rating'], $data['version'])]);
    }
}
