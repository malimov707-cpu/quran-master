<?php

use App\Models\Quran\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('administration screens require an administrator and protect system information', function () {
    $this->seed(DatabaseSeeder::class);
    $this->get(route('administration', 'users'))->assertRedirect(route('login'));
    $student = User::where('email', 'oquvchi@markaz.uz')->firstOrFail();
    $this->actingAs($student)->get(route('administration', 'users'))->assertForbidden();
    $centerAdmin = User::where('email', 'admin@markaz.uz')->firstOrFail();
    $this->actingAs($centerAdmin)->get(route('administration', 'audit'))->assertForbidden();
    $admin = User::where('email', 'admin@quronai.uz')->firstOrFail();
    $this->actingAs($admin)->get(route('administration', 'roles'))->assertOk()->assertSee('Rollar va ruxsatlar');
    $this->get(route('administration', 'missing'))->assertNotFound();
});

test('admin user search applies both search and role filters and escapes names', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@quronai.uz')->firstOrFail();
    $member = User::factory()->create(['name' => '<script>Member</script>']);
    $member->roles()->attach(\App\Models\Role::where('name', 'STUDENT')->firstOrFail());
    $response = $this->actingAs($admin)->get(route('administration', ['section' => 'users', 'q' => 'Member', 'role' => 'STUDENT']))
        ->assertOk()->assertSee('&lt;script&gt;Member&lt;/script&gt;', false)->assertDontSee('<script>Member</script>', false);
    expect($response->viewData('users')->total())->toBe(1);
    $this->get(route('administration', ['section' => 'users', 'q' => 'Member', 'role' => 'MENTOR']))->assertOk()->assertViewHas('users', fn ($users) => $users->isEmpty());
});

test('interface locale persists only supported languages and renders translated controls', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->from(route('dashboard'))->post(route('interface.locale'), ['locale' => 'ru'])
        ->assertRedirect(route('dashboard'))->assertSessionHas('locale', 'ru');
    expect($user->fresh()->locale)->toBe('ru');
    $this->get(route('dashboard'))->assertOk()->assertSee('Ассаламу алейкум!')->assertSee('Уведомления');
    $this->post(route('interface.locale'), ['locale' => '../secrets'])->assertSessionHasErrors('locale');
    expect($user->fresh()->locale)->toBe('ru');
});

test('quran search matches a surah number and shows an empty state for missing names', function () {
    $this->seed(DatabaseSeeder::class);
    $user = User::factory()->create();
    $surah = Surah::create(['number' => 1, 'name_arabic' => 'الفاتحة', 'name_uz' => 'Fotiha', 'name_en' => 'Al-Fatihah', 'ayah_count' => 7, 'revelation_type' => 'meccan']);
    Surah::create(['number' => 2, 'name_arabic' => 'البقرة', 'name_uz' => 'Baqara', 'name_en' => 'Al-Baqarah', 'ayah_count' => 286, 'revelation_type' => 'medinan']);
    $this->actingAs($user)->get(route('quran.surahs', ['q' => (string) $surah->number]))
        ->assertOk()->assertViewHas('surahs', fn ($surahs) => $surahs->count() === 1 && $surahs->first()->id === $surah->id);
    $this->get(route('quran.surahs', ['q' => 'NoMatchingSurah']))->assertOk()->assertSee('Sura topilmadi.');
});

test('monthly dashboard uses thirty scoped days and rejects unsupported periods', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard', ['period' => 'month']))->assertOk()
        ->assertViewHas('attendanceChart', fn ($chart) => count($chart) === 30)
        ->assertViewHas('newUsers', fn ($users) => $users->isEmpty());
    $this->get(route('dashboard', ['period' => 'all-time']))->assertSessionHasErrors('period');
});
