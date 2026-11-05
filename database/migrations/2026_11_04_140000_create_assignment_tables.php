<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Homework & Review: assignments, submissions, submission_files,
     * mentor_reviews, mentor_review_segments.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            // Oyat diapazoni: surah 2, 255..257
            $table->unsignedSmallInteger('surah_number')->nullable();
            $table->unsignedSmallInteger('ayah_from')->nullable();
            $table->unsignedSmallInteger('ayah_to')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->enum('status', ['draft', 'published', 'closed'])->default('draft')->index();
            $table->boolean('requires_audio')->default(true);
            $table->timestamps();

            $table->index(['group_id', 'status']);
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->text('student_note')->nullable();
            $table->enum('status', [
                'submitted',      // topshirilgan, mentor kutilmoqda
                'in_review',      // mentorda
                'graded',         // baholandi
                'resubmit',       // qayta topshirish talab qilindi
            ])->default('submitted');
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedTinyInteger('final_score')->nullable(); // 0..100
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id', 'attempt']);
            $table->index('status');
        });

        Schema::create('submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path', 512); // storage/S3 yo'li
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('duration_seconds')->nullable(); // audio uchun
            $table->timestamps();

            $table->index(['submission_id', 'mime_type']);
        });

        Schema::create('mentor_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('score')->nullable(); // 0..100
            $table->text('comment')->nullable();
            $table->string('audio_note_path', 512)->nullable(); // audio izoh
            $table->enum('action', ['approve', 'resubmit'])->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index('submission_id');
        });

        // Mentor segment bo'yicha xatolar (AI xatolarini tasdiqlash/rad etish alohida jadvalda — Phase 5)
        Schema::create('mentor_review_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_review_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ayah_from')->nullable();
            $table->unsignedSmallInteger('ayah_to')->nullable();
            $table->decimal('start_ms', 12, 0)->nullable();
            $table->decimal('end_ms', 12, 0)->nullable();
            $table->string('error_type', 64); // missing_word, wrong_word, repetition, wrong_order, pronunciation, tajweed_...
            $table->text('comment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_review_segments');
        Schema::dropIfExists('mentor_reviews');
        Schema::dropIfExists('submission_files');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('assignments');
    }
};
