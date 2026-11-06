<?php

namespace App\Services\Progress;

use App\Models\Assignment;
use App\Models\RepetitionCard;
use App\Models\RepetitionReview;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningPathService
{
    public function authorizeStudent(User $user): void
    {
        abort_unless($user->hasRole('STUDENT') && $user->hasPermission('progress.view'), 403);
    }

    /** @return array<string, mixed> */
    public function overview(User $user): array
    {
        $this->authorizeStudent($user);
        $cards = RepetitionCard::where('user_id', $user->id);
        $due = (clone $cards)->with('ayah.surah:id,number,name_uz')->where('due_at', '<=', now())->orderBy('due_at')->limit(20)->get();
        $upcoming = (clone $cards)->with('ayah.surah:id,number,name_uz')->where('due_at', '>', now())->orderBy('due_at')->limit(7)->get();
        $assignments = Assignment::where('status', 'published')
            ->whereHas('group.students', fn ($query) => $query->where('users.id', $user->id))
            ->whereDoesntHave('submissions', fn ($query) => $query->where('student_id', $user->id)->whereIn('status', ['submitted', 'in_review', 'graded']))
            ->with('group:id,name')->orderBy('due_at')->limit(5)->get();
        $mentorErrors = DB::table('mentor_review_segments as segments')
            ->join('mentor_reviews as reviews', 'reviews.id', '=', 'segments.mentor_review_id')
            ->join('submissions', 'submissions.id', '=', 'reviews.submission_id')
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->where('submissions.student_id', $user->id)->where('reviews.reviewed_at', '>=', now()->subDays(30));
        $aiErrors = DB::table('ai_review_segments as segments')
            ->join('ai_reviews as reviews', 'reviews.id', '=', 'segments.ai_review_id')
            ->join('submissions', 'submissions.id', '=', 'reviews.submission_id')
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->where('submissions.student_id', $user->id)->where('reviews.created_at', '>=', now()->subDays(30))->where('segments.is_correct', false);

        return [
            'due_cards' => $due, 'upcoming_cards' => $upcoming,
            'due_count' => (clone $cards)->where('due_at', '<=', now())->count(),
            'total_cards' => (clone $cards)->count(),
            'reviews_this_week' => RepetitionReview::whereHas('card', fn ($query) => $query->where('user_id', $user->id))->where('reviewed_at', '>=', now()->startOfWeek())->count(),
            'assignments' => $assignments,
            'mentor_errors' => $this->errorGroups($mentorErrors, 'ayah_from'),
            'ai_hypotheses' => $this->errorGroups($aiErrors, 'ayah_number'),
            'practice' => app(ProgressService::class)->recommendedPractice($user),
            'assessment_source' => 'self_reported',
        ];
    }

    /** @return list<object> */
    private function errorGroups(Builder $query, string $ayahColumn): array
    {
        return $query->select('assignments.surah_number', "segments.{$ayahColumn} as ayah", 'segments.error_type')
            ->selectRaw('COUNT(*) as occurrences')->groupBy('assignments.surah_number', "segments.{$ayahColumn}", 'segments.error_type')
            ->orderByDesc('occurrences')->limit(20)->get()->all();
    }

    public function enroll(User $user, int $ayahId): RepetitionCard
    {
        $this->authorizeStudent($user);

        return RepetitionCard::firstOrCreate(['user_id' => $user->id, 'ayah_id' => $ayahId], ['due_at' => now()])->refresh();
    }

    public function review(User $user, int $cardId, string $rating, int $version): RepetitionCard
    {
        $this->authorizeStudent($user);

        return DB::transaction(function () use ($user, $cardId, $rating, $version): RepetitionCard {
            $card = RepetitionCard::where('user_id', $user->id)->lockForUpdate()->findOrFail($cardId);
            if ($card->version !== $version || $card->due_at->isFuture()) {
                throw ValidationException::withMessages(['rating' => __('Bu mashq yangilangan yoki takrorlash vaqti hali kelmagan. Sahifani yangilang.')]);
            }
            $successful = in_array($rating, ['good', 'easy'], true) ? $card->successful_reviews + 1 : 0;
            $days = match ($rating) {
                'again' => 0,
                'hard' => 1,
                'good' => $successful === 1 ? 1 : ($successful === 2 ? 3 : min(180, max(3, $card->interval_days * 2))),
                'easy' => min(365, max(3, (int) ceil($card->interval_days * 2.5))),
            };
            $card->update(['interval_days' => $days, 'successful_reviews' => $successful, 'version' => $version + 1, 'last_rating' => $rating,
                'last_reviewed_at' => now(), 'due_at' => $rating === 'again' ? now()->addMinutes(10) : now()->addDays($days)]);
            $card->reviews()->create(['rating' => $rating, 'version' => $version + 1, 'interval_days' => $days, 'reviewed_at' => now()]);

            return $card->refresh();
        }, attempts: 3);
    }
}
