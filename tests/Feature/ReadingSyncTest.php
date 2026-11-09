<?php

use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $surah = Surah::create(['number' => 2, 'name_arabic' => 'البقرة', 'name_uz' => 'Baqara', 'name_en' => 'Al-Baqarah', 'ayah_count' => 3, 'revelation_type' => 'medinan']);
    Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 255, 'number_global' => 300, 'juz' => 3, 'page' => 42, 'text_arabic' => 'ٱللَّهُ لَآ إِلَٰهَ']);
    Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 250, 'juz' => 3, 'page' => 42, 'text_arabic' => 'المّ']);
});

test('user can bookmark an ayah via API', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $token = $student->createToken('t')->plainTextToken;

    $res = $this->withToken($token)->postJson('/api/v1/reading/bookmarks/toggle', [
        'surah' => 2, 'ayah' => 255, 'note' => 'Ayatul Kursi',
    ])->assertCreated()->json('bookmarked');

    expect($res)->toBeTrue();

    // Bookmarklar ro'yxatida bor
    $list = $this->withToken($token)->getJson('/api/v1/reading/bookmarks')->assertOk();
    expect($list->json('0.surah_number'))->toBe(2)->and($list->json('0.note'))->toBe('Ayatul Kursi');

    // Qayta toggle = o'chirish
    $this->withToken($token)->postJson('/api/v1/reading/bookmarks/toggle', ['surah' => 2, 'ayah' => 255])
        ->assertOk()->assertJson(['bookmarked' => false]);
});

test('user can save and restore last reading position', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $token = $student->createToken('t')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/reading/position')->assertOk()->assertJson(['position' => null]);

    $this->withToken($token)->postJson('/api/v1/reading/position', ['surah' => 2, 'ayah' => 255])->assertOk();

    $pos = $this->withToken($token)->getJson('/api/v1/reading/position')->assertOk()->json('position');
    expect($pos['surah_number'])->toBe(2)->and($pos['ayah'])->toBe(255);
});

test('playback logging and stats work', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $token = $student->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/reading/playback', [
        'surah' => 2, 'ayah_from' => 255, 'ayah_to' => 255, 'seconds' => 90, 'reciter' => 'husary',
    ])->assertCreated();

    $stats = $this->withToken($token)->getJson('/api/v1/reading/stats')->assertOk()->json();
    expect($stats['total_minutes'])->toBeGreaterThanOrEqual(1)
        ->and($stats['top_surahs'][0]['surah'])->toBe(2);
});

test('invalid ayah rejected', function () {
    $student = User::where('email', 'oquvchi@markaz.uz')->first();
    $token = $student->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/reading/bookmarks/toggle', ['surah' => 2, 'ayah' => 9999])
        ->assertStatus(404);
});
