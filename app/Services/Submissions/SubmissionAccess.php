<?php

namespace App\Services\Submissions;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SubmissionAccess
{
    /** @return Builder<Submission> */
    public function reviewable(User $user): Builder
    {
        $query = Submission::query();
        if (! $user->hasPermission('submissions.review')) {
            return $query->whereRaw('1 = 0');
        }
        if (! $user->hasPermission('reports.view')) {
            $query->whereHas('assignment.group', fn (Builder $groups): Builder => $groups->where('mentor_id', $user->id)->orWhere('teacher_id', $user->id));
        }

        return $query;
    }

    public function authorizeReview(User $user, Submission $submission): void
    {
        abort_unless($this->reviewable($user)->whereKey($submission->id)->exists(), 403);
    }

    public function authorizeView(User $user, Submission $submission): void
    {
        if ($submission->student_id !== $user->id) {
            $this->authorizeReview($user, $submission);
        }
    }
}
