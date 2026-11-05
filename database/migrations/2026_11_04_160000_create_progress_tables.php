<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Progress va parent moduli: student_progress, parent_student_links, mentor_comments.
     */
    public function up(): void
    {
        // Individual o'rganish profili — real saqlangan ma'lumotlardan hisoblanadi
        Schema::create('student_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('surah_number');
            $table->unsignedSmallInteger('ayah_from')->default(1);
            $table->unsignedSmallInteger('ayah_to')->default(1);
            $table->unsignedSmallInteger('lessons_completed')->default(0);
            $table->unsignedSmallInteger('submissions_count')->default(0);
            $table->decimal('average_score', 5, 2)->nullable(); // 0..100 (tasdiqlangan baholar)
            $table->string('status', 32)->default('in_progress'); // in_progress, completed
            $table->timestamps();

            $table->unique(['student_id', 'surah_number', 'ayah_from', 'ayah_to']);
            $table->index('student_id');
        });

        Schema::create('parent_student_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('relation', 32)->default('parent'); // parent, guardian
            $table->timestamps();

            $table->unique(['parent_id', 'student_id']);
        });

        Schema::create('mentor_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->boolean('is_private')->default(false); // faqat mentor/admin ko'radi
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_comments');
        Schema::dropIfExists('parent_student_links');
        Schema::dropIfExists('student_progress');
    }
};
