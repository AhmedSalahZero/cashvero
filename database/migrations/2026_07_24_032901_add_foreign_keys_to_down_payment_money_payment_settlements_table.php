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
        Schema::table('down_payment_money_payment_settlements', function (Blueprint $table) {
            $table->foreign(['contract_id'])->references(['id'])->on('contracts')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['money_payment_id'])->references(['id'])->on('money_payments')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['purchase_order_id'])->references(['id'])->on('purchase_orders')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['supplier_id'])->references(['id'])->on('partners')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('down_payment_money_payment_settlements', function (Blueprint $table) {
            $table->dropForeign('down_payment_money_payment_settlements_contract_id_foreign');
            $table->dropForeign('down_payment_money_payment_settlements_money_payment_id_foreign');
            $table->dropForeign('down_payment_money_payment_settlements_purchase_order_id_foreign');
            $table->dropForeign('down_payment_money_payment_settlements_supplier_id_foreign');
        });
    }
};
