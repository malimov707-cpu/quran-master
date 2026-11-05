<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\LessonController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ParentController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\QuranController;
use App\Http\Controllers\Api\V1\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Education management
        Route::apiResource('courses', CourseController::class)
            ->only(['index', 'store', 'show', 'update']);
        Route::post('courses/{course}/archive', [CourseController::class, 'archive']);

        Route::get('groups', [GroupController::class, 'index']);
        Route::post('groups', [GroupController::class, 'store']);
        Route::get('groups/{group}', [GroupController::class, 'show']);
        Route::post('groups/{group}/students', [GroupController::class, 'addStudent']);
        Route::delete('groups/{group}/students/{student}', [GroupController::class, 'removeStudent']);
        Route::post('groups/{group}/mentor', [GroupController::class, 'assignMentor']);
        Route::post('groups/mentor-assignments/auto-distribute', [GroupController::class, 'autoDistribute']);
        Route::get('lessons', [LessonController::class, 'index']);
        Route::post('lessons', [LessonController::class, 'store']);
        Route::get('lessons/{lesson}', [LessonController::class, 'show']);
        Route::get('schedules', [ScheduleController::class, 'index']);
        Route::post('schedules', [ScheduleController::class, 'store']);
        Route::put('schedules/{schedule}/attendance', [ScheduleController::class, 'attendance']);

        // Quran moduli
        Route::get('quran/surahs', [QuranController::class, 'surahs']);
        Route::get('quran/search', [QuranController::class, 'search']);
        Route::get('quran/surahs/{surah}', [QuranController::class, 'surah']);

        // Assignments & submissions
        Route::get('assignments', [AssignmentController::class, 'index']);
        Route::get('assignments/{assignment}', [AssignmentController::class, 'show']);
        Route::get('submissions/{submission}/files/{file}/audio', [AssignmentController::class, 'audio'])->name('api.submissions.audio');
        Route::post('assignments', [AssignmentController::class, 'store']);
        Route::post('assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->middleware('throttle:20,1');
        Route::get('submissions/me', [AssignmentController::class, 'mySubmissions']);
        Route::get('submissions/pending', [AssignmentController::class, 'pendingReviews']);
        Route::get('submissions/{submission}', [AssignmentController::class, 'showSubmission']);
        Route::post('submissions/{submission}/review', [AssignmentController::class, 'review']);

        // AI natijalari
        Route::get('submissions/{submission}/ai-review', [AiController::class, 'show']);

        // Progress, notifications, parents
        Route::get('progress/me', [ProgressController::class, 'my']);
        Route::get('progress/students/{student}', [ProgressController::class, 'child']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('parents/children', [ParentController::class, 'children']);
    });
});
