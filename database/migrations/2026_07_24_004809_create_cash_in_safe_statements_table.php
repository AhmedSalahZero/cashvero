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
        Schema::create('cash_in_safe_statements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('exchange_rate', 14)->default(1);
            $table->boolean('is_debit')->default(false);
            $table->boolean('is_credit')->default(true);
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('money_received_id');
            $table->unsignedBigInteger('money_payment_id')->nullable();
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->unsignedBigInteger('buy_or_sell_currency_id')->nullable();
            $table->unsignedBigInteger('internal_money_transfer_id')->nullable();
            $table->unsignedBigInteger('opening_balance_id')->nullable();
            $table->date('date')->nullable();
            $table->dateTime('full_date')->nullable();
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->nullable()->default(0);
            $table->decimal('credit', 14)->nullable()->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_in_safe_statements');
    }
};
