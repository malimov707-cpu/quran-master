<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            // Oylik o'quv to'lovi sozlamalari (markaz boshqaradi)
            $table->unsignedInteger('monthly_fee_tiyin')->default(0)->after('capacity');
            $table->unsignedTinyInteger('payment_due_day')->default(10)->after('monthly_fee_tiyin');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['monthly_fee_tiyin', 'payment_due_day']);
        });
    }
};
