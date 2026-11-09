<?php

use App\Models\Course;
use App\Models\FeeItem;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@markaz.uz')->first();
    $this->student = User::where('email', 'oquvchi@markaz.uz')->first();

    $course = Course::create(['name' => 'K', 'level' => 'beginner']);
    $this->group = Group::create(['name' => 'G', 'course_id' => $course->id, 'monthly_fee_tiyin' => 500000, 'payment_due_day' => 10]);
    $this->group->students()->syncWithoutDetaching([$this->student->id => ['joined_at' => now()]]);
});

test('admin can generate current month invoices via one button', function () {
    $this->actingAs($this->admin)
        ->post(route('administration.payments.generate'))
        ->assertRedirect()->assertSessionHas('status');

    // Guruhdagi 1 o'quvchi + seyder guruhlari
    expect(FeeItem::where('group_id', $this->group->id)->count())->toBe(1)
        ->and(FeeItem::where('group_id', $this->group->id)->first()->amount_tiyin)->toBe(500000);
});

test('admin can receive full payment for a fee', function () {
    $fee = FeeItem::create([
        'student_id' => $this->student->id, 'group_id' => $this->group->id,
        'course_id' => $this->group->course_id,
        'month' => now()->month, 'year' => now()->year,
        'amount_tiyin' => 500000, 'status' => 'pending', 'due_date' => now()->addDays(5),
    ]);

    $this->actingAs($this->admin)
        ->post(route('administration.payments.receive', $fee), ['amount_som' => 5000])
        ->assertRedirect()->assertSessionHas('status');

    expect($fee->refresh()->status)->toBe('paid')
        ->and($this->student->appNotifications()->where('type', 'payment_received')->exists())->toBeTrue();
});

test('partial payment marks partial', function () {
    $fee = FeeItem::create([
        'student_id' => $this->student->id, 'group_id' => $this->group->id,
        'course_id' => $this->group->course_id,
        'month' => now()->month, 'year' => now()->year,
        'amount_tiyin' => 500000, 'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->post(route('administration.payments.receive', $fee), ['amount_som' => 2000])
        ->assertRedirect();

    expect($fee->refresh()->status)->toBe('partial');
});

test('receive rejects amount bigger than remaining', function () {
    $fee = FeeItem::create([
        'student_id' => $this->student->id, 'group_id' => $this->group->id,
        'course_id' => $this->group->course_id,
        'month' => now()->month, 'year' => now()->year,
        'amount_tiyin' => 500000, 'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->post(route('administration.payments.receive', $fee), ['amount_som' => 6000])
        ->assertSessionHasErrors('amount_som');

    expect($fee->refresh()->status)->toBe('pending');
});

test('admin can send payment reminder to student', function () {
    $fee = FeeItem::create([
        'student_id' => $this->student->id, 'group_id' => $this->group->id,
        'course_id' => $this->group->course_id,
        'month' => now()->month, 'year' => now()->year,
        'amount_tiyin' => 500000, 'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->post(route('administration.payments.remind', $fee))
        ->assertRedirect()->assertSessionHas('status');

    expect($this->student->appNotifications()->where('type', 'payment_reminder')->exists())->toBeTrue();
});

test('mentor cannot access payment admin actions', function () {
    $mentor = User::where('email', 'mentor@markaz.uz')->first();
    $fee = FeeItem::create([
        'student_id' => $this->student->id, 'group_id' => $this->group->id,
        'course_id' => $this->group->course_id,
        'month' => now()->month, 'year' => now()->year,
        'amount_tiyin' => 500000, 'status' => 'pending',
    ]);

    $this->actingAs($mentor)->post(route('administration.payments.generate'))->assertStatus(302); // redirect to dashboard/403 page
    $this->actingAs($mentor)->post(route('administration.payments.receive', $fee), ['amount_som' => 1000])->assertStatus(302);
});

test('payments admin page shows fees table with actions', function () {
    $fee = FeeItem::create([
        'student_id' => $this->student->id, 'group_id' => $this->group->id,
        'course_id' => $this->group->course_id,
        'month' => now()->month, 'year' => now()->year,
        'amount_tiyin' => 500000, 'status' => 'pending', 'due_date' => now()->addDays(3),
    ]);

    $this->actingAs($this->admin)->get('/administration/payments')
        ->assertOk()
        ->assertSee($this->student->name)
        ->assertSee(__('Joriy oy invoyslarini yaratish'))
        ->assertSee('/administration/payments/'.$fee->id.'/receive', false);
});
