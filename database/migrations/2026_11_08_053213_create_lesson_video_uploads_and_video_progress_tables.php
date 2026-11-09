<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_video_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('duration_seconds');
            $table->boolean('offline_allowed')->default(true);
            $table->timestamp('expires_at')->index();
        });
        Schema::create('video_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_material_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position_seconds')->default(0);
            $table->json('ranges');
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_progress');
        Schema::dropIfExists('lesson_video_uploads');
    }
};
