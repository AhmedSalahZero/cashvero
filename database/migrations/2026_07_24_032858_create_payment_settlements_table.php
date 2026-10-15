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
        Schema::create('payment_settlements', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->unsignedBigInteger('odoo_move_id')->nullable();
            $table->string('odoo_reference')->nullable();
            $table->unsignedBigInteger('account_bank_statement_line_id')->nullable();
            $table->string('odoo_reference_name')->nullable();
            $table->boolean('is_from_down_payment')->default(false);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('partner_id')->default(0);
            $table->string('withhold_amount')->nullable();
            $table->string('settlement_amount')->nullable();
            $table->integer('money_payment_id')->nullable();
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->unsignedBigInteger('letter_of_credit_issuance_id');
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
        Schema::dropIfExists('payment_settlements');
    }
};
