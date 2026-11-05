<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Submission;
use Illuminate\View\View;

class ReviewStudioController extends BaseController
{
    /**
     * Mentor Review Studio — bitta sahifada audio, topshiriq, tarix.
     */
    public function show(Submission $submission): View
    {
        abort_unless(auth()->user()->hasPermission('submissions.review'), 403);

        $submission->load(['assignment', 'student', 'files', 'reviews.mentor', 'reviews.segments', 'aiReviews.segments']);

        $audioUrls = $submission->files->map(fn ($f) => [
            'name' => $f->original_name,
            'url' => route('submissions.audio', [$submission, $f]),
            'size' => $f->size_bytes,
        ]);

        return view('mentor.review-studio', [
            'submission' => $submission,
            'audioUrls' => $audioUrls,
            'comparisonAyahs' => \App\Models\Quran\Ayah::whereHas('surah', fn ($query) => $query->where('number', $submission->assignment->surah_number))
                ->whereBetween('number_in_surah', [$submission->assignment->ayah_from ?? 1, $submission->assignment->ayah_to ?? 1])->get(),
        ]);
    }

    /**
     * Mentor kutayotgan topshirishlar ro'yxati.
     */
    public function index(): View
    {
        abort_unless(auth()->user()->hasPermission('submissions.review'), 403);

        $submissions = Submission::query()
            ->whereIn('status', ['submitted', 'in_review', 'resubmit'])
            ->with(['assignment:id,title,surah_number,ayah_from,ayah_to', 'student:id,name'])
            ->orderBy('submitted_at')
            ->get();

        return view('mentor.submissions', compact('submissions'));
    }
}
