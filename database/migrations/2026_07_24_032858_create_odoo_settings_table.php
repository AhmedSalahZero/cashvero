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
        Schema::create('odoo_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('cheques_receivable_code');
            $table->unsignedBigInteger('cheques_receivable_id');
            $table->string('liquidity_transfer_account_code');
            $table->unsignedBigInteger('liquidity_transfer_account_id');
            $table->string('cheques_payable_code');
            $table->unsignedBigInteger('cheques_payable_id');
            $table->string('bid_lg_cash_cover_code');
            $table->unsignedBigInteger('bid_lg_cash_cover_id');
            $table->string('final_lg_cash_cover_code');
            $table->unsignedBigInteger('final_lg_cash_cover_id');
            $table->string('advanced_lg_cash_cover_code');
            $table->unsignedBigInteger('advanced_lg_cash_cover_id');
            $table->string('performance_lg_cash_cover_code');
            $table->unsignedBigInteger('performance_lg_cash_cover_id');
            $table->string('sight_lc_cash_cover_code');
            $table->unsignedBigInteger('sight_lc_cash_cover_id');
            $table->string('deferred_lc_cash_cover_code');
            $table->unsignedBigInteger('deferred_lc_cash_cover_id');
            $table->unsignedBigInteger('company_id');
            $table->timestamps();
            $table->string('interest_revenue_code');
            $table->unsignedBigInteger('interest_revenue_id');
            $table->string('custody_account_code')->nullable();
            $table->string('custody_account_id')->nullable();
            $table->string('employee_loans_account_code');
            $table->unsignedBigInteger('employee_loans_account_id');
            $table->string('dividend_payable_account_code');
            $table->unsignedBigInteger('dividend_payable_account_id');
            $table->string('shareholder_account_code');
            $table->unsignedBigInteger('shareholder_account_id');
            $table->string('insurance_from_account_code');
            $table->string('insurance_from_account_id');
            $table->string('insurance_to_account_code');
            $table->string('insurance_to_account_id');
            $table->unsignedBigInteger('letter_of_guarantee_commission_fees_code')->nullable();
            $table->unsignedBigInteger('letter_of_guarantee_commission_fees_id')->nullable();
            $table->unsignedBigInteger('letter_of_guarantee_issuance_fees_code')->nullable();
            $table->unsignedBigInteger('letter_of_guarantee_issuance_fees_id')->nullable();
            $table->unsignedBigInteger('letter_of_credit_commission_fees_code')->nullable();
            $table->unsignedBigInteger('letter_of_credit_commission_fees_id')->nullable();
            $table->unsignedBigInteger('letter_of_credit_other_fees_code')->nullable();
            $table->unsignedBigInteger('letter_of_credit_other_fees_id')->nullable();
            $table->unsignedBigInteger('fully_secured_overdraft_interest_expense_code')->nullable();
            $table->unsignedBigInteger('fully_secured_overdraft_interest_expense_id')->nullable();
            $table->unsignedBigInteger('clean_overdraft_interest_expense_code')->nullable();
            $table->unsignedBigInteger('clean_overdraft_interest_expense_id')->nullable();
            $table->unsignedBigInteger('overdraft_against_commercial_paper_interest_expense_code')->nullable();
            $table->unsignedBigInteger('overdraft_against_commercial_paper_interest_expense_id')->nullable();
            $table->unsignedBigInteger('overdraft_against_contract_assignment_interest_expense_code')->nullable();
            $table->unsignedBigInteger('overdraft_against_contract_assignment_interest_expense_id')->nullable();
            $table->unsignedBigInteger('medium_term_loan_interest_expense_code')->nullable();
            $table->unsignedBigInteger('medium_term_loan_interest_expense_id')->nullable();
            $table->unsignedBigInteger('vat_taxes_code')->nullable();
            $table->unsignedBigInteger('vat_taxes_id')->nullable();
            $table->unsignedBigInteger('credit_withhold_taxes_code')->nullable();
            $table->unsignedBigInteger('credit_withhold_taxes_id')->nullable();
            $table->unsignedBigInteger('salary_taxes_code')->nullable();
            $table->unsignedBigInteger('salary_taxes_id')->nullable();
            $table->unsignedBigInteger('social_insurance_code')->nullable();
            $table->unsignedBigInteger('social_insurance_id')->nullable();
            $table->unsignedBigInteger('income_taxes_code')->nullable();
            $table->unsignedBigInteger('income_taxes_id')->nullable();
            $table->unsignedBigInteger('real_estate_taxes_code')->nullable();
            $table->unsignedBigInteger('real_estate_taxes_id')->nullable();
            $table->unsignedBigInteger('stamp_duty_taxes_code')->nullable();
            $table->unsignedBigInteger('stamp_duty_taxes_id')->nullable();
            $table->unsignedBigInteger('other_taxes_code')->nullable();
            $table->unsignedBigInteger('other_taxes_id')->nullable();
            $table->unsignedBigInteger('takaful_code')->nullable();
            $table->unsignedBigInteger('takaful_id')->nullable();
            $table->unsignedBigInteger('tax_for_victims_code')->nullable();
            $table->unsignedBigInteger('tax_for_victims_id')->nullable();
            $table->string('advances_to_suppliers_code')->nullable();
            $table->string('advances_to_suppliers_id')->nullable();
            $table->string('advances_from_customers_code')->nullable();
            $table->string('advances_from_customers_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odoo_settings');
    }
};
