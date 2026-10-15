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
        Schema::create('down_payment_money_payment_settlements', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedBigInteger('contract_id')->nullable()->index('down_payment_money_payment_settlements_contract_id_foreign');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index('down_payment_money_payment_settlements_sales_order_id_foreign');
            $table->unsignedBigInteger('supplier_id')->nullable()->index('down_payment_money_payment_settlements_supplier_id_foreign');
            $table->string('down_payment_amount')->nullable();
            $table->decimal('total_down_payment_settlement', 14)->default(0);
            $table->decimal('down_payment_balance', 14)->default(0);
            $table->string('currency')->nullable();
            $table->integer('money_payment_id')->nullable()->index('down_payment_money_payment_settlements_money_payment_id_foreign');
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->integer('company_id')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('down_payment_money_payment_settlements');
    }
};
