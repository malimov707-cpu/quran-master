<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryService
{
    public const SENT_MESSAGE = 'Agar bu email bilan faol akkaunt mavjud bo‘lsa, parolni tiklash havolasi yuboriladi.';

    public const RESET_MESSAGE = 'Parolingiz yangilandi. Yangi parol bilan tizimga kiring.';

    public function emailAvailable(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    /** @param array<string, mixed> $input */
    public function send(array $input): void
    {
        $data = Validator::make($input, ['email' => ['required', 'string', 'email', 'max:255']], ['email.required' => __('Email manzilingizni kiriting.'), 'email.email' => __('To‘g‘ri email manzilini kiriting.')])->validate();
        abort_unless($this->emailAvailable(), 503, __('Email orqali tiklash vaqtincha mavjud emas. Administratorga murojaat qiling.'));
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->where('is_active', true)->first();
        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }
    }

    /** @param array<string, mixed> $input */
    public function reset(array $input): void
    {
        $data = Validator::make($input, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen($value) > 72) {
                    $fail(__('Parol juda uzun. Qisqaroq parol kiriting.'));
                }
            }],
        ], ['password.min' => __('Parol kamida 8 ta belgidan iborat bo‘lsin.'), 'password.confirmed' => __('Parollar mos kelmadi.')])->validate();
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();
        if (! $user) {
            $this->invalidToken();
        }
        $status = DB::transaction(function () use ($user, $data): string {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_active) {
                $this->invalidToken();
            }
            $data['email'] = $locked->email;

            return Password::reset($data, function (User $account, string $password): void {
                $account->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $account->tokens()->delete();
                DB::table('sessions')->where('user_id', $account->id)->delete();
                event(new PasswordReset($account));
            });
        });
        if ($status !== Password::PasswordReset) {
            $this->invalidToken();
        }
    }

    private function invalidToken(): never
    {
        throw ValidationException::withMessages(['email' => __('Tiklash havolasi yaroqsiz yoki muddati tugagan. Yangi havola so‘rang.')]);
    }
}
