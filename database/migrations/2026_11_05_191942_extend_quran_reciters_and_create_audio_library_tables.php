<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_audio_editions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reciter_id')->constrained('quran_reciters')->cascadeOnDelete();
            $table->string('identifier')->unique();
            $table->string('provider');
            $table->unsignedSmallInteger('bitrate');
            $table->string('style');
            $table->string('source_url', 1024);
            $table->string('license_url', 1024);
            $table->text('license');
            $table->string('version');
            $table->boolean('offline_allowed')->default(false);
            $table->string('manifest_sha256', 64);
            $table->timestamp('catalog_checked_at');
            $table->timestamps();
        });
        Schema::create('quran_audio_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('edition_id')->constrained('quran_audio_editions')->cascadeOnDelete();
            $table->foreignId('surah_id')->constrained('quran_surahs')->cascadeOnDelete();
            $table->foreignId('ayah_id')->nullable()->constrained('quran_ayahs')->cascadeOnDelete();
            $table->string('kind', 8);
            $table->unsignedInteger('number');
            $table->string('url', 1024);
            $table->string('status', 24)->default('listed');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('etag')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamps();
            $table->unique(['edition_id', 'kind', 'number']);
            $table->index(['edition_id', 'surah_id', 'kind', 'status']);
        });
        Schema::create('quran_audio_timestamps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audio_file_id')->constrained('quran_audio_files')->cascadeOnDelete();
            $table->foreignId('ayah_id')->constrained('quran_ayahs')->cascadeOnDelete();
            $table->unsignedInteger('start_ms');
            $table->unsignedInteger('end_ms');
            $table->string('source_url', 1024);
            $table->unique(['audio_file_id', 'ayah_id']);
        });
        Schema::create('quran_audio_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('edition_id')->nullable()->constrained('quran_audio_editions')->nullOnDelete();
            $table->decimal('speed', 3, 2)->default(1);
            $table->decimal('volume', 3, 2)->default(1);
            $table->unsignedSmallInteger('repeat_count')->default(1);
            $table->unsignedSmallInteger('gap_seconds')->default(0);
            $table->timestamps();
        });
        Schema::create('quran_playback_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audio_file_id')->constrained('quran_audio_files')->cascadeOnDelete();
            $table->unsignedInteger('position_ms')->default(0);
            $table->timestamp('played_at');
            $table->timestamps();
            $table->unique(['user_id', 'audio_file_id']);
            $table->index(['user_id', 'played_at']);
        });
        Schema::create('quran_audio_downloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audio_file_id')->constrained('quran_audio_files')->cascadeOnDelete();
            $table->string('device_id', 64);
            $table->string('status', 16);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'audio_file_id', 'device_id'], 'quran_download_device_unique');
        });
        Schema::create('quran_audio_favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edition_id')->constrained('quran_audio_editions')->cascadeOnDelete();
            $table->unique(['user_id', 'edition_id']);
        });
    }

    public function down(): void
    {
        foreach (['quran_audio_favorites', 'quran_audio_downloads', 'quran_playback_histories', 'quran_audio_preferences', 'quran_audio_timestamps', 'quran_audio_files', 'quran_audio_editions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
