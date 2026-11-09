<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentKpiController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('STUDENT')) {
            return response()->json(['message' => 'Bu endpoint faqat o‘quvchilar uchun.'], 403);
        }

        $kpis = $dashboard->studentKpis($user, 30);

        return response()->json([
            'status' => 'completed',
            'data' => [
                'ayahs' => $kpis['ayahs'],
                'minutes' => $kpis['minutes'],
                'streak' => $kpis['streak'],
                'achievements' => $kpis['achievements'],
                'average' => $kpis['average'],
                'progress_series' => $kpis['progressSeries'],
            ],
        ]);
    }
}
