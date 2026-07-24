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
        Schema::create('overdraft_against_commercial_papers', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('financial_institution_id')->nullable();
            $table->integer('company_id');
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('account_number')->nullable();
            $table->string('odoo_code')->nullable();
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->string('currency')->nullable();
            $table->string('limit')->nullable();
            $table->string('outstanding_balance')->nullable();
            $table->date('balance_date')->nullable();
            $table->double('highest_debt_balance_rate')->nullable()->default(0);
            $table->double('admin_fees_rate')->nullable()->default(0);
            $table->decimal('max_lending_limit_per_customer', 14)->default(0);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->integer('to_be_setteled_max_within_days')->nullable()->default(0);
            $table->dateTime('start_settlement_from_bank_statement_date')->nullable();
            $table->date('oldest_date')->nullable();
            $table->boolean('origin_update_row_is_debit')->nullable()->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overdraft_against_commercial_papers');
    }
};
