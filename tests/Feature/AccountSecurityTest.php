<?php

use App\Models\User;
use App\Services\PasswordRecoveryService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['mail.default' => 'smtp']);
});

test('forgot password and reset pages are accessible from login', function () {
    $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
    $this->get(route('password.request'))->assertOk()->assertSee('Tiklash havolasini yuborish');
    $this->get(route('password.reset', ['token' => 'sample-token', 'email' => 'person@example.com']))->assertOk()->assertSee('person@example.com')->assertSee('Yangi parol belgilang');
});

test('forgot response does not disclose whether an account exists and only sends active accounts links', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'active@example.com']);
    User::factory()->create(['email' => 'inactive@example.com', 'is_active' => false]);
    foreach (['ACTIVE@EXAMPLE.COM', 'unknown@example.com', 'inactive@example.com'] as $email) {
        $this->postJson('/api/v1/auth/forgot-password', compact('email'))->assertOk()->assertExactJson(['message' => PasswordRecoveryService::SENT_MESSAGE]);
    }
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        expect(DB::table('password_reset_tokens')->where('email', $user->email)->value('token'))->not->toBe($notification->token);
        $mail = $notification->toMail($user);
        expect($mail->actionUrl)->toContain('/reset-password/')->toContain('email=active%40example.com');
        expect($mail->subject)->toBe('Parolni tiklash');

        return true;
    });
    Notification::assertCount(1);
});

test('web forgot form preserves email and returns a generic status', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('password.request'))->assertSessionHas('status', PasswordRecoveryService::SENT_MESSAGE);
    Notification::assertSentTo($user, ResetPassword::class);
});

test('valid reset rotates the password revokes every old session and consumes the token', function () {
    $user = User::factory()->create(['email' => 'person@example.com', 'password' => 'Original!707', 'remember_token' => 'old-remember']);
    $token = Password::createToken($user);
    $user->createToken('phone');
    $user->createToken('tablet');
    DB::table('sessions')->insert(['id' => 'old-web-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $payload = ['email' => 'PERSON@EXAMPLE.COM', 'token' => $token, 'password' => 'Updated!707', 'password_confirmation' => 'Updated!707'];
    $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk()->assertExactJson(['message' => PasswordRecoveryService::RESET_MESSAGE]);
    expect(Hash::check('Updated!707', $user->fresh()->password))->toBeTrue();
    expect($user->fresh()->remember_token)->not->toBe('old-remember');
    expect($user->tokens()->count())->toBe(0);
    $this->assertDatabaseMissing('sessions', ['id' => 'old-web-session']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->postJson('/api/v1/auth/reset-password', array_replace($payload, ['password' => 'Other!70788', 'password_confirmation' => 'Other!70788']))->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Original!707'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Updated!707'])->assertOk();
});

test('web reset returns to login with a success message', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);
    $this->post(route('password.update'), ['email' => $user->email, 'token' => $token, 'password' => 'Updated!707', 'password_confirmation' => 'Updated!707'])
        ->assertRedirect(route('login'))->assertSessionHas('status', PasswordRecoveryService::RESET_MESSAGE);
    $this->get(route('login'))->assertOk()->assertSee(PasswordRecoveryService::RESET_MESSAGE);
    $this->assertGuest();
});

test('invalid expired and foreign reset tokens leave credentials unchanged', function (string $scenario) {
    $user = User::factory()->create(['password' => 'Original!707']);
    $other = User::factory()->create();
    $token = Password::createToken($scenario === 'foreign' ? $other : $user);
    if ($scenario === 'expired') {
        $this->travel(61)->minutes();
    }
    $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $scenario === 'invalid' ? 'bad-token' : $token, 'password' => 'Updated!707', 'password_confirmation' => 'Updated!707'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    expect(Hash::check('Original!707', $user->fresh()->password))->toBeTrue();
})->with(['invalid', 'expired', 'foreign']);

test('password validation prevents weak or mismatched changes', function (string $password, string $confirmation) {
    $user = User::factory()->create(['password' => 'Original!707']);
    $token = Password::createToken($user);
    $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $confirmation])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(Hash::check('Original!707', $user->fresh()->password))->toBeTrue();
})->with([['short', 'short'], ['Updated!707', 'mismatch'], [str_repeat('ў', 40), str_repeat('ў', 40)]]);

test('inactive accounts cannot use previously valid reset tokens', function () {
    $user = User::factory()->create(['password' => 'Original!707']);
    $token = Password::createToken($user);
    $user->update(['is_active' => false]);
    $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Updated!707', 'password_confirmation' => 'Updated!707'])->assertUnprocessable();
    expect(Hash::check('Original!707', $user->fresh()->password))->toBeTrue();
});

test('inactive bearer sessions are refused and their token is revoked', function () {
    $user = User::factory()->create(['is_active' => false]);
    $token = $user->createToken('phone')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('message', 'Akkaunt faol emas.');
    expect($user->tokens()->count())->toBe(0);
});

test('inactive web sessions are signed out while public prayer times remain accessible', function () {
    $user = User::factory()->create(['is_active' => false]);
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->get(route('prayer-times'))->assertOk();
});

test('recovery limits repeated attempts across web and API and validates email', function () {
    Notification::fake();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
    for ($i = 0; $i < 4; $i++) {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])->assertOk();
    }
    $this->postJson(route('password.email'), ['email' => 'unknown@example.com'])->assertTooManyRequests();
    Notification::assertNothingSent();
});

test('password reset emails use the configured application URL instead of an incoming host', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->withServerVariables(['HTTP_HOST' => 'untrusted.example'])->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        expect($notification->toMail($user)->actionUrl)->toStartWith(rtrim(config('app.url'), '/').'/reset-password/')->not->toContain('untrusted.example');

        return true;
    });
});

test('sign in and registration rate limits are independent and email case cannot bypass sign in limits', function () {
    User::factory()->create(['email' => 'person@example.com']);
    for ($i = 0; $i < 4; $i++) {
        $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
    }
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => $i % 2 === 0 ? 'PERSON@EXAMPLE.COM' : 'person@example.com', 'password' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson('/api/v1/auth/login', ['email' => 'person@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
});

test('log only email transport is shown as unavailable rather than claiming delivery', function () {
    Notification::fake();
    config(['mail.default' => 'log']);
    $user = User::factory()->create();
    $this->get(route('password.request'))->assertOk()->assertSee('Email orqali tiklash vaqtincha mavjud emas. Administratorga murojaat qiling.');
    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertStatus(503);
    Notification::assertNothingSent();
});
