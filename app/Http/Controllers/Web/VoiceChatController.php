<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\View\View;

class VoiceChatController extends BaseController
{
    public function index(): View
    {
        return view('voice-chat');
    }
}
