<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repetition_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ayah_id')->constrained('quran_ayahs')->cascadeOnDelete();
            $table->timestamp('due_at');
            $table->unsignedInteger('interval_days')->default(0);
            $table->unsignedInteger('successful_reviews')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->string('last_rating', 16)->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'ayah_id']);
            $table->index(['user_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repetition_cards');
    }
};
