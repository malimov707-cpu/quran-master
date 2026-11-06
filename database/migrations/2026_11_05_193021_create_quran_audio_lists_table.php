<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_audio_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edition_id')->constrained('quran_audio_editions')->cascadeOnDelete();
            $table->foreignId('surah_id')->constrained('quran_surahs')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('ayah_from');
            $table->unsignedSmallInteger('ayah_to');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_audio_lists');
    }
};
