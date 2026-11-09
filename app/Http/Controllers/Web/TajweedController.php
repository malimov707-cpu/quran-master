<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Quran\TajweedRule;
use Illuminate\View\View;

class TajweedController extends BaseController
{
    public function index(): View
    {
        $rules = TajweedRule::orderBy('code')->get();

        return view('tajweed.index', compact('rules'));
    }

    public function show(TajweedRule $rule): View
    {
        return view('tajweed.show', ['rule' => $rule]);
    }
}
