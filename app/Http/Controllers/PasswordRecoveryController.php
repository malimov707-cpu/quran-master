<?php

namespace App\Http\Controllers;

use App\Services\PasswordRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    public function forgot(PasswordRecoveryService $recovery): View
    {
        return view('auth.forgot-password', ['mailReady' => $recovery->emailAvailable()]);
    }

    public function edit(Request $request, string $token): View
    {
        $email = $request->validate(['email' => ['sometimes', 'nullable', 'string', 'email', 'max:255']])['email'] ?? '';

        return view('auth.reset-password', compact('token', 'email'));
    }

    public function send(Request $request, PasswordRecoveryService $recovery): JsonResponse|RedirectResponse
    {
        $recovery->send($request->all());
        $message = __(PasswordRecoveryService::SENT_MESSAGE);

        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }

    public function update(Request $request, PasswordRecoveryService $recovery): JsonResponse|RedirectResponse
    {
        $recovery->reset($request->all());
        $message = __(PasswordRecoveryService::RESET_MESSAGE);

        return $request->expectsJson() ? response()->json(['message' => $message]) : redirect()->route('login')->with('status', $message);
    }
}
