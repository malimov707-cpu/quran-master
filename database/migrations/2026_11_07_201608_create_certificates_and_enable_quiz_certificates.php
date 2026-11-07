<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_quizzes', function (Blueprint $table): void {
            $table->boolean('awards_certificate')->default(false);
        });
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('code')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key');
            $table->string('type');
            $table->string('recipient_name');
            $table->string('title');
            $table->decimal('score', 5, 2)->nullable();
            $table->json('evidence');
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 1000)->nullable();
            $table->unique(['user_id', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::table('lesson_quizzes', function (Blueprint $table): void {
            $table->dropColumn('awards_certificate');
        });
    }
};
