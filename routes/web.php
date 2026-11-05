<?php

use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Web\AssignmentManagementController;
use App\Http\Controllers\Web\EducationController;
use App\Http\Controllers\Web\HomeworkController;
use App\Http\Controllers\Web\MentorReviewWebController;
use App\Http\Controllers\Web\NotificationWebController;
use App\Http\Controllers\Web\ParentDashboardController;
use App\Http\Controllers\Web\QuranController;
use App\Http\Controllers\Web\ReviewStudioController;
use App\Http\Controllers\Web\ScheduleController;
use App\Http\Controllers\Web\WebAuthController as WebAuth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [WebAuth::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuth::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [WebAuth::class, 'dashboard'])->name('dashboard');
    Route::get('/schedules', [ScheduleController::class, 'index'])->name('education.schedules');
    Route::post('/schedules', [ScheduleController::class, 'store'])->name('education.schedules.store');
    Route::get('/schedules/{schedule}/attendance', [ScheduleController::class, 'attendance'])->name('education.attendance');
    Route::post('/schedules/{schedule}/attendance', [ScheduleController::class, 'saveAttendance'])->name('education.attendance.store');

    Route::get('/assignments', [AssignmentManagementController::class, 'index'])->name('assignments.manage');
    Route::post('/assignments', [AssignmentManagementController::class, 'store'])->name('assignments.store');
    Route::patch('/assignments/{assignment}/status', [AssignmentManagementController::class, 'updateStatus'])->name('assignments.status');

    Route::get('/homework', [HomeworkController::class, 'index'])->name('homework.index');
    Route::get('/homework/{assignment}', [HomeworkController::class, 'show'])->name('homework.show');
    Route::post('/homework/{assignment}/submit', [HomeworkController::class, 'submit'])->middleware('throttle:20,1')->name('homework.submit');
    Route::get('/my-submissions/{submission}', [HomeworkController::class, 'submission'])->name('homework.submission');

    Route::get('/parents', [ParentDashboardController::class, 'index'])->name('parents.dashboard');
    Route::get('/notifications', [NotificationWebController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationWebController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationWebController::class, 'markRead'])->name('notifications.read');

    Route::post('/logout', [WebAuth::class, 'logout'])->name('logout');

    Route::get('/courses', [EducationController::class, 'coursesIndex'])->name('education.courses');
    Route::post('/courses', [EducationController::class, 'coursesStore'])->name('education.courses.store');
    Route::get('/groups', [EducationController::class, 'groupsIndex'])->name('education.groups');
    Route::post('/groups', [EducationController::class, 'groupsStore'])->name('education.groups.store');
    Route::post('/groups/add-student', [EducationController::class, 'groupsAddStudentFromSelect'])->name('education.groups.add-student.select');
    Route::post('/groups/{group}/mentor', [EducationController::class, 'groupsAssignMentor'])->name('education.groups.assign-mentor');

    // Qur'on moduli
    Route::get('/quran', [QuranController::class, 'surahsIndex'])->name('quran.surahs');
    Route::get('/quran/{surah}', [QuranController::class, 'surahShow'])->name('quran.surah');

    Route::get('/submissions/{submission}/files/{file}/audio', [AssignmentController::class, 'audio'])->name('submissions.audio');

    // Mentor Review Studio
    Route::get('/mentor/submissions', [ReviewStudioController::class, 'index'])->name('mentor.submissions');
    Route::get('/mentor/review/{submission}', [ReviewStudioController::class, 'show'])->name('mentor.review-studio');
    Route::post('/mentor/review/{submission}', [MentorReviewWebController::class, 'store'])->name('mentor.review');
});
