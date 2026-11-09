<?php

use App\Models\Course;
use App\Models\FeeItem;
use App\Models\Group;
use App\Models\User;
use App\Services\Payments\TuitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

function tuitionGroup(): Group
{
    $admin = User::where('email', 'admin@markaz.uz')->first();
    $course = Course::create(['name' => 'Fee Kurs', 'level' => 'beginner']);
    $group = Group::create(['name' => 'Fee Guruh', 'course_id' => $course->id, 'monthly_fee_tiyin' => 500000, 'payment_due_day' => 10]);

    return $group;
}

test('monthly invoice generation is idempotent', function () {
    $group = tuitionGroup();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $group->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);

    $service = app(TuitionService::class);
    $first = $service->generateMonthlyInvoices((int) now()->month, (int) now()->year);

    // Seyderdagi "1-guruh" (o'quvchi a'zo) + "Fee Guruh" = 2 invoice
    expect($first)->toBe(2)
        ->and(FeeItem::count())->toBe(2);

    $second = $service->generateMonthlyInvoices((int) now()->month, (int) now()->year);
    expect($second)->toBe(0)->and(FeeItem::count())->toBe(2);
});

test('pay marks invoice paid and notifies student', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $group = tuitionGroup();
    $fee = FeeItem::create([
        'student_id' => $student->id, 'group_id' => $group->id, 'course_id' => $group->course_id,
        'month' => now()->month, 'year' => now()->year,
        'amount_tiyin' => 500000, 'status' => 'pending',
        'due_date' => now()->addDays(5),
    ]);

    app(TuitionService::class)->pay($fee, 500000);

    expect($fee->refresh()->status)->toBe('paid')
        ->and($student->appNotifications()->where('type', 'payment_received')->exists())->toBeTrue();
});

test('partial payment marks partial status', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $group = tuitionGroup();
    $fee = FeeItem::create([
        'student_id' => $student->id, 'group_id' => $group->id, 'course_id' => $group->course_id,
        'month' => now()->month, 'year' => now()->year, 'amount_tiyin' => 500000, 'status' => 'pending',
    ]);

    app(TuitionService::class)->pay($fee, 200000);

    expect($fee->refresh()->status)->toBe('partial')
        ->and($fee->paid_amount_tiyin)->toBe(200000);
});

test('payments page shows tuition and ai tabs for student', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    $this->actingAs($student)->get(route('payments.index'))
        ->assertOk()
        ->assertSee(__('Oylik o‘quv to‘lovi'))
        ->assertSee(__('AI obunasi'));
});

test('parent selects child via query param', function () {
    $parent = User::where('email', 'ota@markaz.uz')->first();
    $student = User::where('email', 'oquvchi@markaz.uz')->first();

    DB::table('parent_student_links')->insert([
        'parent_id' => $parent->id, 'student_id' => $student->id,
        'relation' => 'parent', 'created_at' => now(), 'updated_at' => now(),
    ]);

    FeeItem::create([
        'student_id' => $student->id, 'group_id' => tuitionGroup()->id, 'course_id' => Course::firstOrFail()->id,
        'month' => now()->month, 'year' => now()->year, 'amount_tiyin' => 500000, 'status' => 'pending',
    ]);

    $this->actingAs($parent)->get(route('payments.index', ['student_id' => $student->id]))
        ->assertOk()
        ->assertSee('5,000', false)
        ->assertViewHas('targets', fn ($targets) => $targets->contains('id', $student->id));
});

test('history sums paid and pending separately', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $group = tuitionGroup();

    FeeItem::create(['student_id' => $student->id, 'group_id' => $group->id, 'course_id' => 1,
        'month' => 1, 'year' => now()->year, 'amount_tiyin' => 500000, 'status' => 'paid', 'paid_amount_tiyin' => 500000]);
    FeeItem::create(['student_id' => $student->id, 'group_id' => $group->id, 'course_id' => 1,
        'month' => 2, 'year' => now()->year, 'amount_tiyin' => 500000, 'status' => 'pending']);

    $history = app(TuitionService::class)->history($student);

    expect($history['total_paid_som'])->toBe(5000)
        ->and($history['total_pending_som'])->toBe(5000)
        ->and(count($history['items']))->toBe(2);
});
