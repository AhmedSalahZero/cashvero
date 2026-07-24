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
        Schema::create('cash_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('company_id');
            $table->integer('money_payment_id')->nullable()->index('cash_payments_money_payment_id_foreign');
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->integer('delivery_branch_id')->nullable()->index('cash_payments_delivery_branch_id_foreign');
            $table->string('receipt_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_payments');
    }
};
