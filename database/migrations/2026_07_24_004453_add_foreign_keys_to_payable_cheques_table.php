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
        Schema::table('payable_cheques', function (Blueprint $table) {
            $table->foreign(['delivery_bank_id'])->references(['id'])->on('financial_institutions')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['money_payment_id'])->references(['id'])->on('money_payments')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payable_cheques', function (Blueprint $table) {
            $table->dropForeign('payable_cheques_delivery_bank_id_foreign');
            $table->dropForeign('payable_cheques_money_payment_id_foreign');
        });
    }
};
