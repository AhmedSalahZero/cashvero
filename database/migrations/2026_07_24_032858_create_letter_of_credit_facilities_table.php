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
        Schema::create('letter_of_credit_facilities', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('type')->nullable()->default('unsecured')->comment('هل هو عادي ولا فولي سيكيورد');
            $table->string('name')->nullable();
            $table->integer('financial_institution_id')->nullable();
            $table->integer('company_id');
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('currency')->nullable();
            $table->string('cd_or_td_currency')->nullable();
            $table->string('limit')->nullable();
            $table->string('financing_duration')->nullable();
            $table->unsignedBigInteger('cd_or_td_account_type_id')->nullable();
            $table->unsignedBigInteger('cd_or_td_id')->nullable();
            $table->decimal('cd_or_td_amount', 14)->nullable();
            $table->string('cd_or_td_interest')->nullable();
            $table->string('cd_or_td_lending_percentage')->nullable();
            $table->string('borrowing_rate')->nullable();
            $table->string('bank_margin_rate')->nullable();
            $table->string('interest_rate')->nullable();
            $table->string('min_interest_rate')->nullable();
            $table->string('highest_debt_balance_rate')->nullable();
            $table->string('admin_fees_rate')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->date('oldest_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_of_credit_facilities');
    }
};
