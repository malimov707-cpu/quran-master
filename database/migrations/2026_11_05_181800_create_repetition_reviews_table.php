<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repetition_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repetition_card_id')->constrained()->cascadeOnDelete();
            $table->string('rating', 16);
            $table->unsignedInteger('version');
            $table->unsignedInteger('interval_days');
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->unique(['repetition_card_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repetition_reviews');
    }
};
