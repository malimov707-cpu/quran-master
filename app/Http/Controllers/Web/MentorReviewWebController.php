<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Submission;
use App\Models\User;
use App\Services\Submissions\SubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MentorReviewWebController extends BaseController
{
    public function __construct(private SubmissionService $submissions) {}

    public function store(Request $request, Submission $submission): RedirectResponse
    {
        /** @var User $mentor */
        $mentor = $request->user();
        abort_unless($mentor->hasPermission('submissions.review'), 403);

        $data = $request->validate([
            'score' => ['required_if:action,approve', 'nullable', 'integer', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string'],
            'action' => ['required', 'in:approve,resubmit'],
        ]);

        $this->submissions->review($submission, $mentor, $data);

        return redirect()->route('mentor.submissions')->with('status', 'Tekshiruv saqlandi.');
    }
}
