<?php

use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\RealtimeController;
use App\Http\Controllers\Api\V1\VoiceController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\LessonQuizController;
use App\Http\Controllers\LessonVideoController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\PrayerTimesController;
use App\Http\Controllers\Web\AdministrationController;
use App\Http\Controllers\Web\AssignmentManagementController;
use App\Http\Controllers\Web\EducationController;
use App\Http\Controllers\Web\HomeworkController;
use App\Http\Controllers\Web\LearningPathController;
use App\Http\Controllers\Web\LessonWebController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\MentorReviewWebController;
use App\Http\Controllers\Web\NotificationWebController;
use App\Http\Controllers\Web\ParentDashboardController;
use App\Http\Controllers\Web\PaymentsAdminController;
use App\Http\Controllers\Web\PaymentsController;
use App\Http\Controllers\Web\QuranAudioController;
use App\Http\Controllers\Web\QuranController;
use App\Http\Controllers\Web\ReviewStudioController;
use App\Http\Controllers\Web\ScheduleController;
use App\Http\Controllers\Web\StudentJourneyController;
use App\Http\Controllers\Web\SubscriptionController;
use App\Http\Controllers\Web\TajweedController;
use App\Http\Controllers\Web\UserManagementController;
use App\Http\Controllers\Web\VoiceChatController;
use App\Http\Controllers\Web\WebAuthController as WebAuth;
use Illuminate\Support\Facades\Route;

Route::post('/interface-locale', LocaleController::class)->name('interface.locale');

Route::get('/namoz-vaqtlari', [PrayerTimesController::class, 'index'])->name('prayer-times');

Route::get('/certificates/verify/{code}', [CertificateController::class, 'verify'])->whereUuid('code')->middleware('throttle:60,1')->name('certificates.verify');

Route::get('/lesson-video/{material}/{user}', [LessonVideoController::class, 'stream'])->middleware('signed:relative')->name('lesson-video.stream');

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function (): void {
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'send'])->name('password.email')->middleware('throttle:password-recovery');
    Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'update'])->name('password.update')->middleware('throttle:password-recovery');
    Route::get('/register', [WebAuth::class, 'showRegister'])->name('register');
    Route::post('/register', [WebAuth::class, 'register'])->middleware('throttle:registration');
    Route::get('/login', [WebAuth::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuth::class, 'login'])->middleware('throttle:sign-in');
});

Route::middleware(['auth', 'active-account'])->group(function (): void {
    Route::get('/administration/users/create', [UserManagementController::class, 'create'])->name('administration.users.create');
    Route::post('/administration/users', [UserManagementController::class, 'store'])->name('administration.users.store');
    Route::get('/administration/users/{user}/edit', [UserManagementController::class, 'edit'])->name('administration.users.edit');
    Route::patch('/administration/users/{user}/roles', [UserManagementController::class, 'roles'])->name('administration.users.roles');
    Route::put('/administration/users/{user}/children', [UserManagementController::class, 'children'])->name('administration.users.children');
    Route::put('/administration/users/{user}/mentor-groups', [UserManagementController::class, 'mentorGroups'])->name('administration.users.mentor-groups');
    Route::get('/administration/{section}', [AdministrationController::class, 'index'])->name('administration');
    Route::post('/administration/payments/generate-invoices', [PaymentsAdminController::class, 'generateInvoices'])->name('administration.payments.generate');
    Route::post('/administration/payments/{fee}/receive', [PaymentsAdminController::class, 'receive'])->name('administration.payments.receive');
    Route::post('/administration/payments/{fee}/remind', [PaymentsAdminController::class, 'remind'])->name('administration.payments.remind');
    Route::post('/administration/ai/chat', [AdministrationController::class, 'chat'])->name('administration.ai.chat')->middleware('throttle:20,1');
    Route::patch('/administration/ai/users/{user}/premium', [AdministrationController::class, 'setAiPremium'])->name('administration.ai.premium');
    Route::get('/learning-path', [LearningPathController::class, 'index'])->name('learning.index');
    Route::post('/learning-path/cards', [LearningPathController::class, 'store'])->name('learning.store');
    Route::post('/learning-path/cards/{card}/review', [LearningPathController::class, 'review'])->whereNumber('card')->name('learning.review');
    Route::post('/student/lessons/{lesson}/complete', [StudentJourneyController::class, 'lesson'])->name('student.lesson.complete');
    Route::post('/student/assignments/{assignment}/practice', [StudentJourneyController::class, 'practice'])->name('student.practice.complete');
    Route::post('/student/submissions/{submission}/feedback-read', [StudentJourneyController::class, 'feedback'])->name('student.feedback.read');
    Route::get('/dashboard', [WebAuth::class, 'dashboard'])->name('dashboard');
    Route::get('/lessons/{lesson}/quiz/edit', [LessonQuizController::class, 'edit'])->name('lessons.quiz.edit');
    Route::put('/lessons/{lesson}/quiz', [LessonQuizController::class, 'update'])->name('lessons.quiz.update');
    Route::post('/lessons/{lesson}/quiz/attempts', [LessonQuizController::class, 'attempt'])->middleware('throttle:10,1')->name('lessons.quiz.attempt');
    Route::get('/lessons/{lesson}/quiz', [LessonQuizController::class, 'show'])->name('lessons.quiz.show');
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('/certificates/courses/{course}', [CertificateController::class, 'course'])->name('certificates.course');
    Route::post('/certificates/courses/{course}', [CertificateController::class, 'claimCourse'])->middleware('throttle:15,1')->name('certificates.course.claim');
    Route::post('/certificates/exams/{quiz}', [CertificateController::class, 'claimExam'])->middleware('throttle:15,1')->name('certificates.exam.claim');
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->middleware('throttle:15,1')->name('certificates.download');
    Route::post('/certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])->middleware('throttle:15,1')->name('certificates.revoke');
    Route::get('/lessons/{lesson}/videos', [LessonVideoController::class, 'index']);
    Route::post('/lessons/{lesson}/videos/youtube', [LessonVideoController::class, 'youtube'])->middleware('throttle:15,1');
    Route::post('/lessons/{lesson}/video-uploads', [LessonVideoController::class, 'beginUpload'])->middleware('throttle:15,1');
    Route::post('/video-uploads/{upload}/chunks', [LessonVideoController::class, 'chunk'])->middleware('throttle:600,1');
    Route::post('/video-uploads/{upload}/finish', [LessonVideoController::class, 'finishUpload'])->middleware('throttle:15,1');
    Route::delete('/video-uploads/{upload}', [LessonVideoController::class, 'cancelUpload'])->middleware('throttle:30,1');
    Route::delete('/videos/{material}', [LessonVideoController::class, 'destroy'])->middleware('throttle:30,1');
    Route::post('/videos/{material}/progress', [LessonVideoController::class, 'progress'])->middleware('throttle:120,1');
    Route::get('/lessons', [LessonWebController::class, 'index'])->name('education.lessons');
    Route::post('/lessons', [LessonWebController::class, 'store'])->name('education.lessons.store');
    Route::get('/lessons/{lesson}', [LessonWebController::class, 'show'])->name('education.lessons.show');
    Route::patch('/lessons/{lesson}', [LessonWebController::class, 'update'])->name('education.lessons.update');

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

    Route::prefix('/audio')->group(function (): void {
        Route::post('/lists', [App\Http\Controllers\Api\V1\QuranAudioController::class, 'storeList'])->middleware('throttle:20,1');
        Route::delete('/lists/{list}', [App\Http\Controllers\Api\V1\QuranAudioController::class, 'destroyList'])->whereNumber('list');
        Route::get('/catalog', [App\Http\Controllers\Api\V1\QuranAudioController::class, 'index'])->name('audio.catalog');
        Route::get('/playlist/{edition}/{surah}', [App\Http\Controllers\Api\V1\QuranAudioController::class, 'playlist'])->name('audio.playlist');
        Route::put('/preferences', [App\Http\Controllers\Api\V1\QuranAudioController::class, 'preferences'])->name('audio.preferences');
        Route::post('/history', [App\Http\Controllers\Api\V1\QuranAudioController::class, 'history'])->middleware('throttle:60,1')->name('audio.history');
        Route::post('/favorites/{edition}', [App\Http\Controllers\Api\V1\QuranAudioController::class, 'favorite'])->name('audio.favorite');
    });

    // Qur'on moduli
    Route::get('/quran/audio-library', [QuranAudioController::class, 'index'])->name('quran.audio');
    Route::get('/quran', [QuranController::class, 'surahsIndex'])->name('quran.surahs');
    Route::get('/quran/{surah}', [QuranController::class, 'surahShow'])->name('quran.surah');

    Route::get('/submissions/{submission}/files/{file}/audio', [AssignmentController::class, 'audio'])->name('submissions.audio');

    // Mentor Review Studio
    Route::get('/mentor/submissions', [ReviewStudioController::class, 'index'])->name('mentor.submissions');
    Route::get('/mentor/review/{submission}', [ReviewStudioController::class, 'show'])->name('mentor.review-studio');

    // AI ovozli ustoz (web session bilan ham ishlaydi — telefonda/JS uchun)
    Route::get('/ai/voice', [VoiceChatController::class, 'index'])->name('ai.voice');

    // Tajvid akademiyasi
    Route::get('/payments', [PaymentsController::class, 'index'])->name('payments.index');
    Route::post('/payments/{fee}/remind', [PaymentsController::class, 'remind'])->name('payments.remind');
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('/subscriptions/cash', [SubscriptionController::class, 'requestCash'])->name('subscriptions.cash');

    Route::get('/tajweed', [TajweedController::class, 'index'])->name('tajweed.index');
    Route::get('/tajweed/{rule}', [TajweedController::class, 'show'])->name('tajweed.show');
    Route::post('/ai/voice', [VoiceController::class, 'store'])->middleware('throttle:10,1')->name('ai.voice.store');
    Route::post('/ai/realtime/token', [RealtimeController::class, 'token'])->name('ai.realtime.token');
    Route::post('/mentor/review/{submission}', [MentorReviewWebController::class, 'store'])->name('mentor.review');
});
