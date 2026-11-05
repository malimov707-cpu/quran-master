<?php

use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    // Mini Quran dataset (test uchun)
    $surah = Surah::create([
        'number' => 1, 'name_arabic' => 'الفاتحة', 'name_uz' => 'Al-Fotiha',
        'name_en' => 'Al-Fatihah', 'ayah_count' => 2, 'revelation_type' => 'meccan',
    ]);
    Ayah::create([
        'surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 1,
        'juz' => 1, 'page' => 1,
        'text_arabic' => 'بِسۡمِ ٱللَّهِ', 'text_uz' => 'Rahmanning nomi bilan',
    ]);
    Ayah::create([
        'surah_id' => $surah->id, 'number_in_surah' => 2, 'number_global' => 2,
        'juz' => 1, 'page' => 1,
        'text_arabic' => 'ٱلۡحَمۡدُ لِلَّهِ', 'text_uz' => 'Hamd Allohga',
    ]);
});

test('authenticated user can list surahs via API', function () {
    $user = User::where('email', 'oquvchi@markaz.uz')->first();
    $token = $user->createToken('t')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/quran/surahs')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name_arabic', 'الفاتحة');
});

test('authenticated user can read surah with ayahs and translation', function () {
    $user = User::where('email', 'oquvchi@markaz.uz')->first();
    $token = $user->createToken('t')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/quran/surahs/1')
        ->assertOk()
        ->assertJsonCount(2, 'ayahs');

    expect($response->json('ayahs.0.text_arabic'))->toBe('بِسۡمِ ٱللَّهِ')
        ->and($response->json('ayahs.0.text_uz'))->toBe('Rahmanning nomi bilan');
});

test('quran API requires authentication', function () {
    $this->getJson('/api/v1/quran/surahs')->assertStatus(401);
});

test('quran search finds ayah by translation', function () {
    $user = User::where('email', 'oquvchi@markaz.uz')->first();
    $token = $user->createToken('t')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/quran/search?q=Hamd')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.number_in_surah', 2);
});

test('web reader shows surah with arabic text', function () {
    $user = User::where('email', 'oquvchi@markaz.uz')->first();

    $this->actingAs($user)->get(route('quran.surah', 1))
        ->assertOk()
        ->assertSee('بِسۡمِ ٱللَّهِ', false)
        ->assertSee('Rahmanning nomi bilan');
});

test('web surah list requires login', function () {
    $this->get(route('quran.surahs'))->assertRedirect('/login');
});
