<?php

use App\Models\Quran\AudioEdition;
use App\Models\Quran\AudioFile;
use App\Models\Quran\AudioTimestamp;
use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** @return array{Surah, AudioEdition, AudioFile, AudioFile} */
function audioFixture(): array
{
    $surah = Surah::create(['number' => 1, 'name_arabic' => 'الفاتحة', 'name_uz' => 'Fotiha', 'name_en' => 'Al-Fatihah', 'ayah_count' => 2, 'revelation_type' => 'meccan']);
    $first = Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 1, 'number_global' => 1, 'juz' => 1, 'page' => 1, 'text_arabic' => 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ']);
    $second = Ayah::create(['surah_id' => $surah->id, 'number_in_surah' => 2, 'number_global' => 2, 'juz' => 1, 'page' => 1, 'text_arabic' => 'الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ']);
    $edition = AudioEdition::factory()->create();
    $file = AudioFile::factory()->create(['edition_id' => $edition->id, 'ayah_id' => $first->id, 'surah_id' => $surah->id]);
    $missing = AudioFile::factory()->create(['edition_id' => $edition->id, 'ayah_id' => $second->id, 'surah_id' => $surah->id, 'number' => 2, 'url' => '', 'status' => 'missing']);

    return [$surah, $edition, $file, $missing];
}

test('audio library requires authentication and does not offer unlicensed recordings', function () {
    [, $edition] = audioFixture();
    $this->getJson('/api/v1/quran/audio')->assertUnauthorized();
    $this->get(route('quran.audio'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->getJson('/api/v1/quran/audio')->assertOk()->assertJsonCount(1, 'editions')->assertJsonPath('editions.0.ayah_count', 1);
    $edition->reciter->update(['licensed' => false]);
    $this->getJson('/api/v1/quran/audio')->assertOk()->assertJsonCount(0, 'editions');
});

test('playlist returns canonical ayah mapping and reports missing audio without invented timings', function () {
    [$surah, $edition, $file] = audioFixture();
    $this->actingAs(User::factory()->create())->getJson("/api/v1/quran/audio/{$edition->id}/{$surah->id}?from=1&to=2")
        ->assertOk()->assertJsonPath('complete', false)->assertJsonPath('available_count', 1)->assertJsonPath('expected_count', 2)
        ->assertJsonPath('tracks.0.id', $file->id)->assertJsonPath('tracks.0.ayah_number', 1)
        ->assertJsonPath('tracks.0.text_arabic', $file->ayah->text_arabic)->assertJsonPath('synchronization', 'ayah_file_boundaries');
    $this->getJson("/api/v1/quran/audio/{$edition->id}/{$surah->id}?mode=surah")->assertOk()->assertJsonPath('tracks', [])->assertJsonPath('complete', false)->assertJsonPath('synchronization', 'none');
    $this->assertDatabaseCount('quran_audio_timestamps', 0);
});

test('full surah playlists expose only verified ayah timestamps', function () {
    [$surah, $edition, $file] = audioFixture();
    $surahFile = AudioFile::factory()->create([
        'edition_id' => $edition->id,
        'surah_id' => $surah->id,
        'ayah_id' => null,
        'kind' => 'surah',
        'number' => $surah->id,
        'duration_ms' => 8000,
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson("/api/v1/quran/audio/{$edition->id}/{$surah->id}?mode=surah")
        ->assertOk()->assertJsonPath('synchronization', 'none')
        ->assertJsonPath('tracks.0.timestamps', []);

    AudioTimestamp::create([
        'audio_file_id' => $surahFile->id,
        'ayah_id' => $file->ayah_id,
        'start_ms' => 0,
        'end_ms' => 4000,
        'source_url' => 'https://example.test/alignment.json',
    ]);

    $this->getJson("/api/v1/quran/audio/{$edition->id}/{$surah->id}?mode=surah")
        ->assertOk()->assertJsonPath('synchronization', 'verified_segments')
        ->assertJsonPath('tracks.0.timestamps.0.ayah_number', 1)
        ->assertJsonPath('tracks.0.timestamps.0.start_ms', 0)
        ->assertJsonPath('tracks.0.timestamps.0.end_ms', 4000);
});

test('audio playlist rejects reversed or nonexistent ayah ranges', function (string $range) {
    [$surah, $edition] = audioFixture();
    $this->actingAs(User::factory()->create())->getJson("/api/v1/quran/audio/{$edition->id}/{$surah->id}?{$range}")->assertUnprocessable();
})->with(['from=2&to=1', 'from=0&to=2', 'from=1&to=3', 'mode=word']);

test('preferences validate repeat speed and volume and remain private', function () {
    [, $edition] = audioFixture();
    $user = User::factory()->create();
    $this->actingAs($user)->putJson('/api/v1/quran/audio/preferences', ['edition_id' => $edition->id, 'speed' => 1.25, 'repeat_count' => 20, 'gap_seconds' => 5, 'volume' => .5])->assertOk()->assertJsonPath('preferences.speed', 1.25);
    $this->assertDatabaseHas('quran_audio_preferences', ['user_id' => $user->id, 'edition_id' => $edition->id, 'repeat_count' => 20]);
    $this->putJson('/api/v1/quran/audio/preferences', ['speed' => 3, 'repeat_count' => 99, 'volume' => 2])->assertUnprocessable()->assertJsonValidationErrors(['speed', 'repeat_count', 'volume']);
    $this->actingAs(User::factory()->create())->getJson('/api/v1/quran/audio')->assertOk()->assertJsonPath('preferences', null);
});

test('playback history cannot be written for missing audio and is isolated by user', function () {
    [, , $file, $missing] = audioFixture();
    $owner = User::factory()->create();
    $this->actingAs($owner)->postJson('/api/v1/quran/audio/history', ['audio_file_id' => $file->id, 'position_ms' => 12500])->assertOk();
    $this->postJson('/api/v1/quran/audio/history', ['audio_file_id' => $file->id, 'position_ms' => 15000])->assertOk();
    $this->assertDatabaseCount('quran_playback_histories', 1);
    $this->getJson('/api/v1/quran/audio')->assertJsonPath('history.0.position_ms', 15000);
    $this->postJson('/api/v1/quran/audio/history', ['audio_file_id' => $missing->id, 'position_ms' => 0])->assertNotFound();
    $this->actingAs(User::factory()->create())->getJson('/api/v1/quran/audio')->assertJsonPath('history', []);
});

test('favorites and hifz lists persist and foreign lists cannot be deleted', function () {
    [$surah, $edition] = audioFixture();
    $user = User::factory()->create();
    $this->actingAs($user)->postJson("/api/v1/quran/audio/favorites/{$edition->id}", ['favorite' => true])->assertOk();
    $list = $this->postJson('/api/v1/quran/audio/lists', ['name' => 'My revision', 'edition_id' => $edition->id, 'surah_id' => $surah->id, 'ayah_from' => 1, 'ayah_to' => 2])->assertCreated()->json('list.id');
    $this->getJson('/api/v1/quran/audio')->assertJsonCount(1, 'lists')->assertJsonPath('favorites.0', $edition->id);
    $this->actingAs(User::factory()->create())->deleteJson("/api/v1/quran/audio/lists/{$list}")->assertNotFound();
    $this->actingAs($user)->deleteJson("/api/v1/quran/audio/lists/{$list}")->assertOk();
    $this->assertDatabaseCount('quran_audio_lists', 0);
});

test('downloads reject editions without offline permission and record only the signed in user device', function () {
    [, $edition, $file] = audioFixture();
    $user = User::factory()->create();
    $payload = ['audio_file_id' => $file->id, 'device_id' => 'device_123', 'status' => 'downloaded', 'size_bytes' => 4096, 'user_id' => 9999];
    $this->actingAs($user)->postJson('/api/v1/quran/audio/downloads', $payload)->assertOk();
    $this->assertDatabaseHas('quran_audio_downloads', ['user_id' => $user->id, 'audio_file_id' => $file->id, 'device_id' => 'device_123']);
    $edition->update(['offline_allowed' => false]);
    $this->postJson('/api/v1/quran/audio/downloads', $payload)->assertForbidden();
});

test('web library renders source credits and the same selected reciter metadata', function () {
    [, $edition] = audioFixture();
    $this->actingAs(User::factory()->create())->get(route('quran.audio'))->assertOk()->assertSee($edition->reciter->name)->assertSee('AlQuran.cloud / Islamic Network');
});

test('audio import refuses incomplete canonical data before accessing external resources', function () {
    Http::preventStrayRequests();
    $this->artisan('quran:audio:import')->assertFailed();
    Http::assertNothingSent();
});
