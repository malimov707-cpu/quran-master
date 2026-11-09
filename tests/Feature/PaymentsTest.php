<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->standard = SubscriptionPlan::where('name', 'Standart')->first();
});

test('subscription API lists plans and empty state', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $token = $student->createToken('t')->plainTextToken;

    $res = $this->withToken($token)->getJson('/api/v1/subscriptions')->assertOk();

    expect(count($res->json('plans')))->toBeGreaterThanOrEqual(3)
        ->and($res->json('subscription'))->toBeNull();
});

test('perform transaction activates subscription', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $payment = app(PaymentService::class)->createPending($student, $this->standard, 'payme', '1');
    $payment->update(['provider_transaction_id' => 'tx-001']);

    config(['services.payme.merchant_id' => 'm1', 'services.payme.key' => 'k1']);
    $auth = 'Basic '.base64_encode('m1:k1');

    $res = $this->withHeaders(['Authorization' => $auth])->postJson('/api/v1/payments/payme', [
        'id' => 1, 'method' => 'PerformTransaction',
        'params' => ['id' => 'tx-001'],
    ])->assertOk()->json('result.state');

    expect($res)->toBe(1);

    // DB: payment paid + subscription active
    expect($payment->refresh()->status)->toBe('paid')
        ->and(app(PaymentService::class)->activeSubscription($student))->not->toBeNull();
});

test('perform transaction is idempotent', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $payment = app(PaymentService::class)->createPending($student, $this->standard, 'payme', '1');
    $payment->update(['provider_transaction_id' => 'tx-002']);

    config(['services.payme.merchant_id' => 'm1', 'services.payme.key' => 'k1']);
    $auth = 'Basic '.base64_encode('m1:k1');

    $payload = ['id' => 1, 'method' => 'PerformTransaction', 'params' => ['id' => 'tx-002']];
    $this->withHeaders(['Authorization' => $auth])->postJson('/api/v1/payments/payme', $payload)->assertOk();

    // Ikkinchi marta — ikkita obuna yaratilmaydi
    $this->withHeaders(['Authorization' => $auth])->postJson('/api/v1/payments/payme', $payload)->assertOk();

    expect(Subscription::where('user_id', $student->id)->count())->toBe(1);
});

test('payme rejects unauthorized requests', function () {
    config(['services.payme.merchant_id' => 'm1', 'services.payme.key' => 'k1']);

    $this->postJson('/api/v1/payments/payme', ['id' => 1, 'method' => 'PerformTransaction', 'params' => []])
        ->assertJsonPath('error.code', -32504);
});

test('click prepare and complete flow works with signature', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    config(['services.click.secret_key' => 'clicksecret']);

    $sign = fn (array $data, ?string $prepareId = null) => md5(
        $data['click_trans_id'].$data['amount'].$data['action'].$data['sign_time'].$data['merchant_trans_id'].($prepareId ?? '').'clicksecret'
    );

    $prepare = [
        'click_trans_id' => 'c1', 'amount' => '990', 'action' => '0',
        'sign_time' => '2026-11-05 12:00:00', 'merchant_trans_id' => (string) $student->id,
    ];
    $prepare['sign_string'] = $sign($prepare);

    $res = $this->postJson('/api/v1/payments/click', $prepare)->assertOk()->json();
    $prepareId = $res['merchant_prepare_id'];

    $complete = [
        'click_trans_id' => 'c1', 'amount' => '990', 'action' => '1',
        'sign_time' => '2026-11-05 12:00:10', 'merchant_trans_id' => (string) $student->id,
        'merchant_prepare_id' => (string) $prepareId, 'error' => '0',
    ];
    $complete['sign_string'] = $sign($complete, (string) $prepareId);

    $this->postJson('/api/v1/payments/click', $complete)->assertOk()->assertJsonPath('error', 0);

    expect(app(PaymentService::class)->activeSubscription($student))->not->toBeNull();
});

test('click rejects bad signature', function () {
    config(['services.click.secret_key' => 'clicksecret']);
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    $this->postJson('/api/v1/payments/click', [
        'click_trans_id' => 'c9', 'amount' => '990', 'action' => '0',
        'sign_time' => '2026-11-05 12:00:00', 'merchant_trans_id' => (string) $student->id,
        'sign_string' => 'invalid',
    ])->assertOk()->assertJsonPath('error', -4);
});

test('expire outdated marks expired subscriptions', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    Subscription::create([
        'user_id' => $student->id, 'plan_id' => $this->standard->id,
        'starts_at' => now()->subMonths(2), 'expires_at' => now()->subDay(), 'status' => 'active',
    ]);

    $count = app(PaymentService::class)->expireOutdated();

    expect($count)->toBe(1)
        ->and(Subscription::where('status', 'expired')->count())->toBe(1);
});

test('cash subscription request creates pending payment', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $token = $student->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/subscriptions/cash', ['plan_id' => $this->standard->id])
        ->assertCreated();

    expect(Payment::where('provider', 'cash')->where('status', 'pending')->exists())->toBeTrue();
});

// --- Parent flows ---

test('parent sees subscription page with children statuses', function () {
    $parent = User::where('email', 'ota@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    DB::table('parent_student_links')->insert([
        'parent_id' => $parent->id, 'student_id' => $student->id,
        'relation' => 'parent', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($parent)->get(route('subscriptions.index'))
        ->assertOk()
        ->assertSee($student->name)
        ->assertSee('Bepul');
});

test('parent requests cash subscription for own child', function () {
    $parent = User::where('email', 'ota@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    DB::table('parent_student_links')->insert([
        'parent_id' => $parent->id, 'student_id' => $student->id,
        'relation' => 'parent', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($parent)->post(route('subscriptions.cash'), [
        'plan_id' => $this->standard->id, 'student_id' => $student->id,
    ])->assertRedirect()->assertSessionHas('status');

    // Payment o'quvchi hisobiga yozildi
    expect(Payment::where('user_id', $student->id)->where('provider', 'cash')->exists())->toBeTrue();
});

test('parent cannot pay for unrelated student', function () {
    $parent = User::where('email', 'ota@markaz.uz')->first();
    $outsider = User::create(['name' => 'X', 'email' => 'x@t.uz', 'password' => 'Parol!123']);

    $this->actingAs($parent)->post(route('subscriptions.cash'), [
        'plan_id' => $this->standard->id, 'student_id' => $outsider->id,
    ])->assertStatus(404); // firstOrFail
});

test('child gets notification about payment request', function () {
    $parent = User::where('email', 'ota@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    DB::table('parent_student_links')->insert([
        'parent_id' => $parent->id, 'student_id' => $student->id,
        'relation' => 'parent', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($parent)->post(route('subscriptions.cash'), [
        'plan_id' => $this->standard->id, 'student_id' => $student->id,
    ])->assertRedirect();

    expect($student->appNotifications()->where('type', 'payment_request')->exists())->toBeTrue();
});

test('navigation shows subscription link for student and parent', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $this->actingAs($student)->get(route('dashboard'))
        ->assertOk()->assertSee(__('To‘lovlar'));

    $parent = User::where('email', 'ota@markaz.uz')->first();
    $this->actingAs($parent)->get(route('dashboard'))
        ->assertOk()->assertSee(__('To‘lovlar'));
});
