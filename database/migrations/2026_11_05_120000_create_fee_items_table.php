<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O'quv markazi to'lov tizimi:
     * - fee_items: oylik o'quv to'lovi (kurs/guruh asosida) — har oy har o'quvchi uchun invoice
     * - payments uzaytiriladi: type = tuition | ai_subscription
     */
    public function up(): void
    {
        Schema::create('fee_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('month'); // 1..12
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('amount_tiyin'); // oylik to'lov
            $table->enum('status', ['pending', 'paid', 'partial', 'cancelled'])->default('pending')->index();
            $table->unsignedInteger('paid_amount_tiyin')->default(0);
            $table->timestamp('due_date')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'group_id', 'year', 'month']);
            $table->index(['status', 'due_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('type', ['tuition', 'ai_subscription'])->default('ai_subscription')->after('provider')->index();
            $table->foreignId('fee_item_id')->nullable()->constrained()->nullOnDelete()->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['fee_item_id']);
            $table->dropColumn(['type', 'fee_item_id']);
        });
        Schema::dropIfExists('fee_items');
    }
};
