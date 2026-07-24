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
        Schema::create('overdraft_against_commercial_paper_bank_statements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type');
            $table->boolean('is_debit')->default(false);
            $table->boolean('is_credit')->default(true);
            $table->integer('priority')->default(3);
            $table->unsignedInteger('overdraft_against_commercial_paper_id');
            $table->unsignedBigInteger('money_received_id');
            $table->unsignedBigInteger('money_payment_id')->nullable();
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->unsignedBigInteger('internal_money_transfer_id')->nullable();
            $table->unsignedBigInteger('buy_or_sell_currency_id')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('overdraft_against_commercial_paper_limit_id')->nullable();
            $table->date('date');
            $table->decimal('limit', 14)->default(0);
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->nullable()->default(0);
            $table->decimal('credit', 14)->nullable()->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->decimal('room', 14)->default(0);
            $table->enum('interest_type', ['normal', 'end_of_month'])->default('normal');
            $table->decimal('interest_rate_annually', 8, 5)->default(0);
            $table->decimal('interest_rate_daily', 8, 5)->default(0);
            $table->integer('days_count')->default(0);
            $table->decimal('interest_amount', 14)->default(0);
            $table->timestamps();
            $table->dateTime('full_date')->nullable();
            $table->string('comment_en')->nullable();
            $table->string('comment_ar')->nullable();
            $table->date('outstanding_withdrawal_date')->nullable();
            $table->unsignedBigInteger('factoring_transaction_id')->nullable()->index('odcp_bs_factoring_tx_idx');
            $table->string('factoring_bank_movement_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overdraft_against_commercial_paper_bank_statements');
    }
};
