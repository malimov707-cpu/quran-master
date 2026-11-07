<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RegistrationService
{
    /** @param array<string, mixed> $input */
    public function register(array $input): User
    {
        foreach (['name', 'email'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = mb_strtolower($input['email']);
        }
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (User::whereRaw('LOWER(email) = ?', [$value])->exists()) {
                    $fail(__('Bu email allaqachon ro‘yxatdan o‘tgan.'));
                }
            }],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen($value) > 72) {
                    $fail(__('Parol juda uzun. Qisqaroq parol kiriting.'));
                }
            }],
            'device_name' => ['nullable', 'string', 'max:128'],
        ], [
            'name.required' => __('Ismingizni kiriting.'),
            'name.string' => __('Ismingizni matn ko‘rinishida kiriting.'),
            'name.max' => __('Ism 255 ta belgidan oshmasligi kerak.'),
            'email.required' => __('Email manzilingizni kiriting.'),
            'email.email' => __('To‘g‘ri email manzilini kiriting.'),
            'password.required' => __('Parol kiriting.'),
            'password.min' => __('Parol kamida 8 ta belgidan iborat bo‘lsin.'),
            'password.confirmed' => __('Parollar mos kelmadi.'),
        ])->validate();

        return DB::transaction(function () use ($data): User {
            $role = Role::where('name', 'STUDENT')->first();
            abort_unless($role, 503, __('Ro‘yxatdan o‘tish vaqtincha mavjud emas. Keyinroq urinib ko‘ring.'));
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'locale' => 'uz',
                'is_active' => true,
                'ai_premium' => false,
            ]);
            $user->roles()->attach($role->id);

            return $user;
        });
    }
}
