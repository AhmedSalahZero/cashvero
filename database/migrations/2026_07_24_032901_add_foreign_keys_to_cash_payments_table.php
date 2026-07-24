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
        Schema::table('cash_payments', function (Blueprint $table) {
            $table->foreign(['delivery_branch_id'])->references(['id'])->on('branch')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['money_payment_id'])->references(['id'])->on('money_payments')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_payments', function (Blueprint $table) {
            $table->dropForeign('cash_payments_delivery_branch_id_foreign');
            $table->dropForeign('cash_payments_money_payment_id_foreign');
        });
    }
};
