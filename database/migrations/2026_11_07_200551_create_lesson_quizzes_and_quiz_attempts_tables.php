<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('pass_percent')->default(70);
            $table->unsignedInteger('revision')->default(1);
            $table->json('questions');
            $table->timestamps();
        });
        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('submission_key');
            $table->unsignedInteger('revision');
            $table->unsignedSmallInteger('correct_count');
            $table->unsignedSmallInteger('question_count');
            $table->decimal('score', 5, 2);
            $table->boolean('passed');
            $table->json('feedback');
            $table->timestamp('submitted_at');
            $table->unique(['lesson_quiz_id', 'user_id', 'submission_key'], 'quiz_attempt_submission_unique');
            $table->index(['user_id', 'lesson_quiz_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('lesson_quizzes');
    }
};
