<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Progress\LearningPathService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearningPathController extends Controller
{
    public function __construct(private LearningPathService $learning) {}

    public function index(Request $request): View
    {
        return view('learning.index', $this->learning->overview($request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->learning->authorizeStudent($request->user());
        $data = $request->validate(['ayah_id' => ['required', 'integer', 'exists:quran_ayahs,id']]);
        $this->learning->enroll($request->user(), $data['ayah_id']);

        return redirect()->route('learning.index')->with('status', __('Oyat shaxsiy takrorlash rejangizga qo‘shildi.'));
    }

    public function review(Request $request, int $card): RedirectResponse
    {
        $this->learning->authorizeStudent($request->user());
        $data = $request->validate(['rating' => ['required', 'in:again,hard,good,easy'], 'version' => ['required', 'integer', 'min:0']]);
        $this->learning->review($request->user(), $card, $data['rating'], $data['version']);

        return back()->with('status', __('Takrorlash natijasi saqlandi.'));
    }
}
