<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Progress\ProgressService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentDashboardController extends Controller
{
    public function __construct(private ProgressService $progress) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('children.view'), 403);
        $data = $request->validate(['student' => ['nullable', 'integer', 'min:1']]);
        $children = $request->user()->children()->orderBy('name')->get(['users.id', 'users.name']);
        $studentId = $data['student'] ?? null;
        $selectedChild = $studentId === null ? $children->first() : $children->firstWhere('id', (int) $studentId);
        abort_if($studentId !== null && $selectedChild === null, 404);
        $progress = $selectedChild ? $this->progress->forStudent($selectedChild) + [
            'attendance' => $this->progress->attendanceStats($selectedChild),
        ] : null;

        return view('parent-dashboard', compact('children', 'selectedChild', 'progress'));
    }
}
