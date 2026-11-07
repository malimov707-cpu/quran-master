<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\Submission;
use App\Services\Progress\StudentJourneyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentJourneyController extends Controller
{
    public function __construct(private StudentJourneyService $journey) {}

    public function lesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->journey->completeLesson($request->user(), $lesson);

        return redirect()->route('dashboard')->with('status', __('Darsni o‘qiganingiz belgilandi. Keyingi qadamga o‘ting.'));
    }

    public function practice(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->journey->completePractice($request->user(), $assignment);

        return redirect()->route('homework.show', $assignment)->with('status', __('Mashq belgilandi. Endi yozuvingizni yuborishingiz mumkin.'));
    }

    public function feedback(Request $request, Submission $submission): RedirectResponse
    {
        $result = $this->journey->acknowledgeFeedback($request->user(), $submission);

        return redirect()->route('learning.index')->with('status', __('Ustoz javobi o‘qildi. Takrorlashga :count ta yangi oyat qo‘shildi.', ['count' => $result['revision_added']]));
    }
}
