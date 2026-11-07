<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

class AuthController extends Controller
{
    public function register(Request $request, RegistrationService $registration): JsonResponse
    {
        $user = $registration->register($request->all());

        return $this->authenticatedResponse($user, $request->input('device_name') ?? 'mobile', 201);
    }

    /**
     * Login — Bearer token qaytaradi (Web/Android/iOS uchun yagona).
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:128'],
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Kiritilgan ma\'lumotlar noto\'g\'ri.'),
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => __('Akkaunt faol emas.')], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->authenticatedResponse($user, $data['device_name'] ?? 'web');
    }

    private function authenticatedResponse(User $user, string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->permissions()->values(),
            ],
        ], $status);
    }

    /**
     * Joriy foydalanuvchi ma'lumotlari.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->permissions()->values(),
        ]);
    }

    /**
     * Logout — joriy tokenni bekor qilish.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        if ($token instanceof TransientToken) {
            $request->session()?->invalidate();
            $request->session()?->flush();
            auth('web')->logout();
        } elseif ($token !== null) {
            $user->tokens()->where('id', $token->getKey())->delete();
        }

        return response()->json(['message' => __('Tizimdan chiqdingiz.')]);
    }
}
