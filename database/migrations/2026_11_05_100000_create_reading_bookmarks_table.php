<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O'qish kitobbelgilari, oxirgi o'qilgan joy va tinglash tarixi.
     * Barcha platformalar (web/mobile) bir xil ma'lumotni ko'radi.
     */
    public function up(): void
    {
        Schema::create('quran_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ayah_id')->constrained('quran_ayahs')->cascadeOnDelete();
            $table->string('note', 512)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'ayah_id']);
        });

        Schema::create('reading_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('surah_id')->constrained('quran_surahs')->cascadeOnDelete();
            $table->unsignedSmallInteger('ayah_number');
            $table->timestamps();

            $table->unique('user_id'); // foydalanuvchida bitta "oxirgi o'qilgan joy"
        });

        Schema::create('playback_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('surah_id')->constrained('quran_surahs')->cascadeOnDelete();
            $table->unsignedSmallInteger('ayah_from')->nullable();
            $table->unsignedSmallInteger('ayah_to')->nullable();
            $table->unsignedInteger('seconds_listened')->default(0);
            $table->string('reciter', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playback_history');
        Schema::dropIfExists('reading_positions');
        Schema::dropIfExists('quran_bookmarks');
    }
};
