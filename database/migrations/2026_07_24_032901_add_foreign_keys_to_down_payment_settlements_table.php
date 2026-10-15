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
        Schema::table('down_payment_settlements', function (Blueprint $table) {
            $table->foreign(['contract_id'])->references(['id'])->on('contracts')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['customer_id'])->references(['id'])->on('partners')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['money_received_id'])->references(['id'])->on('money_received')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['sales_order_id'])->references(['id'])->on('sales_orders')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('down_payment_settlements', function (Blueprint $table) {
            $table->dropForeign('down_payment_settlements_contract_id_foreign');
            $table->dropForeign('down_payment_settlements_customer_id_foreign');
            $table->dropForeign('down_payment_settlements_money_received_id_foreign');
            $table->dropForeign('down_payment_settlements_sales_order_id_foreign');
        });
    }
};
