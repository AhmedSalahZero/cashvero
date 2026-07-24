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
        Schema::create('time_of_deposits', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('store_break_journal_entry_id')->nullable();
            $table->string('inbound_break_odoo_reference')->nullable();
            $table->string('inbound_odoo_reference')->nullable();
            $table->boolean('is_at_maturity')->default(true);
            $table->unsignedBigInteger('inbound_break_journal_entry_id')->nullable();
            $table->unsignedBigInteger('outbound_break_journal_entry_id')->nullable();
            $table->unsignedBigInteger('break_account_bank_statement_line_id')->nullable();
            $table->unsignedBigInteger('break_journal_entry_id')->nullable();
            $table->unsignedBigInteger('renewal_account_bank_statement_line_id')->nullable();
            $table->unsignedBigInteger('renewal_journal_entry_id')->nullable();
            $table->unsignedBigInteger('interest_account_bank_statement_line_id')->nullable();
            $table->unsignedBigInteger('interest_journal_entry_id')->nullable();
            $table->unsignedBigInteger('maturity_account_bank_statement_line_id')->nullable();
            $table->unsignedBigInteger('maturity_journal_entry_id')->nullable();
            $table->unsignedBigInteger('store_account_bank_statement_line_id')->nullable();
            $table->unsignedBigInteger('store_journal_entry_id')->nullable();
            $table->unsignedBigInteger('inbound_journal_entry_id')->nullable();
            $table->unsignedBigInteger('outbound_journal_entry_id')->nullable();
            $table->unsignedBigInteger('deducted_from_account_id')->nullable();
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->string('odoo_code')->nullable();
            $table->string('status')->default('running');
            $table->integer('financial_institution_id');
            $table->string('account_number')->nullable();
            $table->decimal('amount', 12)->nullable();
            $table->string('currency')->nullable();
            $table->decimal('interest_rate', 5)->default(0);
            $table->decimal('interest_amount', 14)->default(0);
            $table->decimal('actual_interest_amount', 14)->nullable();
            $table->date('deposit_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('maturity_amount_added_to_account_id')->nullable();
            $table->integer('company_id')->nullable();
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->integer('created_by')->nullable();
            $table->integer('update_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->decimal('updated_at', 10, 0)->nullable();
            $table->date('break_date')->nullable()->comment('هو عباره عن التاريخ اللي قررت فية تكسر شهادة الايداع');
            $table->decimal('break_interest_amount', 14)->nullable()->comment('عباره عن الفايدة اللي نزلت علي الحساب بسبب كسرك الشهادة');
            $table->decimal('break_charge_amount', 14)->nullable()->comment('عبارة عن رسوم ادارية بسبب كسر الشهادة');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_of_deposits');
    }
};
