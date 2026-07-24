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
        Schema::create('current_account_bank_statements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lg_commission_fees_journal_entry_id')->nullable();
            $table->string('odoo_lg_commission_fees_reference')->nullable();
            $table->boolean('is_break_interest')->nullable();
            $table->unsignedBigInteger('is_period_cd_or_td_interest')->default(0);
            $table->string('type')->nullable();
            $table->boolean('is_beginning_balance')->default(false);
            $table->boolean('is_renewal_fees')->default(false);
            $table->boolean('is_commission_fees')->default(false);
            $table->boolean('is_issuance_fees')->default(false);
            $table->boolean('is_td_renewal')->default(false);
            $table->boolean('is_active')->default(true)->comment('الكولوم دا انا ضفته علشان ال commission اللي بتنزل كل ثلاث شهور مثلا .. فا لو لسه ميعاد الكومشن ما جاش يبقي هنعتبر الرو دا اكنه مش موجود اصلا ولما يجي ميعادة هنعدل الكولوم دا وهنحط بواحد علشان يدخل معايا في الحسبة بتاعتي ');
            $table->integer('financial_institution_account_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('money_received_id');
            $table->unsignedBigInteger('money_payment_id');
            $table->unsignedBigInteger('cash_expense_id')->nullable();
            $table->unsignedBigInteger('buy_or_sell_currency_id')->nullable();
            $table->unsignedBigInteger('internal_money_transfer_id')->nullable();
            $table->unsignedBigInteger('lc_settlement_internal_money_transfer_id')->nullable();
            $table->unsignedBigInteger('time_of_deposit_id')->nullable();
            $table->unsignedBigInteger('letter_of_guarantee_issuance_id')->nullable();
            $table->unsignedBigInteger('lg_renewal_date_history_id')->nullable();
            $table->unsignedBigInteger('letter_of_credit_issuance_id')->nullable();
            $table->unsignedBigInteger('lg_advanced_payment_history_id')->nullable();
            $table->unsignedBigInteger('lc_advanced_payment_history_id')->nullable();
            $table->unsignedBigInteger('lc_issuance_expense_id')->nullable();
            $table->unsignedBigInteger('certificate_of_deposit_id')->nullable();
            $table->unsignedBigInteger('loan_schedule_settlement_id')->nullable();
            $table->unsignedBigInteger('contract_loan_schedule_settlement_id')->nullable();
            $table->boolean('is_debit')->default(false);
            $table->boolean('is_credit')->default(true);
            $table->date('date')->nullable();
            $table->dateTime('full_date')->nullable();
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->nullable()->default(0);
            $table->decimal('credit', 14)->nullable()->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->string('interest_type')->nullable();
            $table->decimal('interest_rate_annually', 8, 5)->default(0);
            $table->decimal('interest_rate_daily', 8, 5)->default(0);
            $table->integer('days_count')->default(0);
            $table->decimal('interest_amount', 14)->default(0);
            $table->timestamps();
            $table->string('comment_en')->nullable();
            $table->string('comment_ar')->nullable();
            $table->unsignedBigInteger('interest_account_bank_statement_odoo_id')->nullable();
            $table->unsignedBigInteger('interest_journal_entry_id')->nullable();
            $table->string('interest_odoo_reference')->nullable();
            $table->unsignedBigInteger('factoring_transaction_id')->nullable()->index();
            $table->string('factoring_bank_movement_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('current_account_bank_statements');
    }
};
