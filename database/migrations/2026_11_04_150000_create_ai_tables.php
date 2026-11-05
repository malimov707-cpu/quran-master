<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI moduli: ai_reviews, ai_review_segments, ai_usage_logs.
     * AI tahlili va mentor bahosi alohida saqlanadi (ai_reviews != mentor_reviews).
     */
    public function up(): void
    {
        Schema::create('ai_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 64); // whisper_openai, local_whisper, none...
            $table->string('model', 64)->nullable();
            $table->enum('status', [
                'pending',       // navbatda
                'processing',    // ishlanmoqda
                'completed',     // tayyor
                'failed',        // xato
                'unavailable',   // provayder sozlanmagan
            ])->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->decimal('overall_confidence', 4, 3)->nullable(); // 0..1
            $table->text('transcript')->nullable(); // ASR natijasi
            $table->text('summary')->nullable(); // mentor uchun tavsiya
            $table->string('needs_mentor_review_reason', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'status']);
        });

        Schema::create('ai_review_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_review_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ayah_number')->nullable();
            $table->unsignedSmallInteger('word_position')->nullable();
            $table->string('expected_text', 512)->nullable(); // oyatdagi so'z
            $table->string('heard_text', 512)->nullable();    // ASR eshitgan
            $table->enum('error_type', [
                'missing_word', 'wrong_word', 'repetition', 'wrong_order', 'unclear',
            ])->nullable();
            $table->decimal('start_ms', 12, 0)->nullable();
            $table->decimal('end_ms', 12, 0)->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->boolean('is_correct')->default(true); // shu segment to'g'ri o'qilganmi
            $table->timestamps();

            $table->index('ai_review_id');
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 64);
            $table->string('model', 64)->nullable();
            $table->string('operation', 64); // transcription, alignment, chat...
            $table->unsignedInteger('tokens_used')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('success')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_review_segments');
        Schema::dropIfExists('ai_reviews');
    }
};
