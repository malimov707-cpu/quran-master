<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quran moduli: surahs, ayahs, juz, reciters, tajweed_rules.
     * Qur'on matni o'zgartirilmaydi — import checksum bilan nazorat qilinadi.
     */
    public function up(): void
    {
        Schema::create('quran_surahs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number')->unique(); // 1..114
            $table->string('name_arabic', 64);
            $table->string('name_uz', 128);
            $table->string('name_en', 128);
            $table->unsignedSmallInteger('ayah_count');
            $table->enum('revelation_type', ['meccan', 'medinan']);
            $table->timestamps();
        });

        Schema::create('quran_ayahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surah_id')->constrained('quran_surahs')->cascadeOnDelete();
            $table->unsignedSmallInteger('number_in_surah');
            $table->unsignedMediumInteger('number_global')->index(); // 1..6236
            $table->unsignedSmallInteger('juz')->index();
            $table->unsignedSmallInteger('page')->index();
            $table->unsignedSmallInteger('hizb')->nullable();
            $table->text('text_arabic');
            $table->text('text_uz')->nullable();  // tarjima (Alisher Navoiy / yangi asr)
            $table->text('text_ru')->nullable();  // tarjima (Kuliev)
            $table->timestamps();

            $table->unique(['surah_id', 'number_in_surah']);
        });

        // Matn manbasi va butunligi — faqat import jarayonida yoziladi
        Schema::create('quran_text_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128); // Tanzil Uthmani, Tanzil Simple...
            $table->string('version', 32);
            $table->string('url', 512)->nullable();
            $table->string('checksum_sha256', 64);
            $table->timestamp('imported_at');
            $table->unsignedInteger('ayah_count');
            $table->timestamps();
        });

        Schema::create('quran_juz', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number')->unique(); // 1..30
            $table->unsignedSmallInteger('first_ayah_global');
            $table->unsignedSmallInteger('last_ayah_global');
            $table->timestamps();
        });

        Schema::create('quran_reciters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('style', 64)->nullable(); // murattal, mujawwad...
            $table->string('audio_base_url', 512)->nullable();
            $table->boolean('licensed')->default(false); // litsenziya tekshirilgani
            $table->timestamps();
        });

        Schema::create('tajweed_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // madd, ghunnah, qalqalah...
            $table->string('name_uz', 128);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tajweed_rules');
        Schema::dropIfExists('quran_reciters');
        Schema::dropIfExists('quran_juz');
        Schema::dropIfExists('quran_text_sources');
        Schema::dropIfExists('quran_ayahs');
        Schema::dropIfExists('quran_surahs');
    }
};
