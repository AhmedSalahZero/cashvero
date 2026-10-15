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
        Schema::create('letter_of_credit_issuances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lc_facility_id')->nullable();
            $table->string('category_name')->nullable();
            $table->string('source')->nullable()->comment('هو المكان او الطريقه يعني اللي انت انشاتة بيها وانت عندك ثلاث او اربع زراير دول عباره عن المصدر اللي هو قيمة الكولوم دا');
            $table->string('status')->default('running');
            $table->string('financed_by_bank_or_self')->nullable()->default('bank');
            $table->string('transaction_name')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->date('transaction_date')->nullable();
            $table->integer('financial_institution_id')->nullable()->index('letter_of_credit_issuances_financial_institution_id_foreign');
            $table->string('cd_or_td_account_type_id')->nullable();
            $table->string('cd_or_td_id')->nullable();
            $table->decimal('total_lc_outstanding_balance', 14)->default(0);
            $table->string('lc_type')->nullable();
            $table->decimal('lc_type_outstanding_balance', 14)->default(0);
            $table->string('lc_code')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable()->index('letter_of_credit_issuances_partner_id_foreign');
            $table->string('contract_type')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable()->index('letter_of_credit_issuances_contract_id_foreign');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index('letter_of_credit_issuances_purchase_order_id_foreign');
            $table->date('purchase_order_date')->nullable();
            $table->date('issuance_date')->nullable();
            $table->integer('lc_duration_days')->nullable();
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->unsignedBigInteger('payment_account_number_id')->nullable();
            $table->unsignedBigInteger('payment_account_type_id')->nullable();
            $table->string('payment_currency')->nullable();
            $table->unsignedBigInteger('supplier_invoice_id')->nullable();
            $table->decimal('lc_amount', 14)->default(0);
            $table->string('lc_currency')->nullable();
            $table->decimal('issuance_fees', 14)->default(0);
            $table->decimal('min_lc_commission_fees', 14)->default(0);
            $table->decimal('cash_cover_rate', 5)->default(0);
            $table->decimal('cash_cover_amount', 14)->default(0);
            $table->string('cash_cover_deducted_from_account_type')->nullable();
            $table->string('cash_cover_deducted_from_account_id')->nullable();
            $table->unsignedBigInteger('lc_fees_and_commission_account_id')->nullable();
            $table->decimal('lc_commission_rate', 5)->default(0);
            $table->decimal('lc_commission_amount', 14)->default(0);
            $table->string('cash_cover_account_number')->nullable();
            $table->integer('financing_duration')->default(0);
            $table->integer('company_id');
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->string('lc_cash_cover_currency')->nullable();
            $table->decimal('amount_in_main_currency', 14)->nullable();
            $table->decimal('exchange_rate', 14)->nullable();
            $table->text('user_comment')->nullable();
            $table->decimal('interest_amount', 14, 5)->default(0);
            $table->string('interest_currency')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_of_credit_issuances');
    }
};
