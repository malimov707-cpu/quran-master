<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * To'lov tizimi: tarif rejalari, obunalar, tranzaksiyalar (Payme/Click).
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->unsignedInteger('price_tiyin'); // 1 so'm = 100 tiyin
            $table->unsignedTinyInteger('months'); // davomiylik
            $table->text('benefits_uz')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->index();
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active')->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->unsignedInteger('amount_tiyin');
            $table->string('provider', 16)->index(); // payme, click, cash
            $table->string('provider_transaction_id', 128)->nullable();
            $table->string('account_number', 64)->nullable(); // click order_id / payme account
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'cancelled'])->default('pending')->index();
            $table->json('payload')->nullable(); // webhook raw
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
