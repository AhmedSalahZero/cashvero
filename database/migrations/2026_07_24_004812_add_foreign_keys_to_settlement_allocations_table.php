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
        Schema::table('settlement_allocations', function (Blueprint $table) {
            $table->foreign(['contract_id'])->references(['id'])->on('contracts')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['money_payment_id'])->references(['id'])->on('money_payments')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['partner_id'])->references(['id'])->on('partners')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settlement_allocations', function (Blueprint $table) {
            $table->dropForeign('settlement_allocations_contract_id_foreign');
            $table->dropForeign('settlement_allocations_money_payment_id_foreign');
            $table->dropForeign('settlement_allocations_partner_id_foreign');
        });
    }
};
