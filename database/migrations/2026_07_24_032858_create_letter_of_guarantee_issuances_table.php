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
        Schema::create('letter_of_guarantee_issuances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cancel_journal_entry_id')->nullable();
            $table->string('cash_cover_fees_reference')->nullable();
            $table->string('odoo_issuance_fees_reference')->nullable();
            $table->string('odoo_commission_fees_reference')->nullable();
            $table->string('issuance_fees_account_bank_statement_odoo_id')->nullable();
            $table->unsignedBigInteger('issuance_fees_journal_entry_id')->nullable();
            $table->string('commission_fees_account_bank_statement_odoo_id')->nullable();
            $table->unsignedBigInteger('commission_fees_journal_entry_id')->nullable();
            $table->text('odoo_error_message')->nullable();
            $table->boolean('synced_with_odoo')->default(true);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('account_bank_statement_odoo_id')->nullable();
            $table->integer('lg_facility_id')->nullable()->index('letter_of_guarantee_issuances_lg_facility_id_foreign');
            $table->string('category_name')->default('new-issuance');
            $table->string('source')->nullable()->comment('هو المكان او الطريقه يعني اللي انت انشاتة بيها وانت عندك ثلاث او اربع زراير دول عباره عن المصدر اللي هو قيمة الكولوم دا');
            $table->string('status')->default('running');
            $table->string('transaction_name')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->date('transaction_date')->nullable();
            $table->integer('financial_institution_id')->nullable()->index('letter_of_guarantee_issuances_financial_institution_id_foreign');
            $table->string('cd_or_td_account_type_id')->nullable();
            $table->string('cd_or_td_id')->nullable();
            $table->decimal('total_lg_outstanding_balance', 14)->default(0);
            $table->string('lg_type')->nullable();
            $table->decimal('lg_type_outstanding_balance', 14)->default(0);
            $table->string('lg_code')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable()->index('letter_of_guarantee_issuances_partner_id_foreign');
            $table->unsignedBigInteger('contract_id')->nullable()->index('letter_of_guarantee_issuances_contract_id_foreign');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index('letter_of_guarantee_issuances_purchase_order_id_foreign');
            $table->date('purchase_order_date')->nullable();
            $table->date('issuance_date')->nullable();
            $table->integer('lg_duration_months')->nullable();
            $table->date('renewal_date')->nullable();
            $table->decimal('lg_amount', 14)->default(0);
            $table->string('lg_currency')->nullable();
            $table->decimal('issuance_fees', 14)->default(0);
            $table->decimal('min_lg_commission_fees', 14)->default(0);
            $table->decimal('cash_cover_rate', 5)->default(0);
            $table->decimal('cash_cover_amount', 14)->default(0);
            $table->string('cash_cover_deducted_from_account_type')->nullable();
            $table->string('lg_fees_and_commission_account_type')->nullable();
            $table->string('cash_cover_deducted_from_account_id')->nullable();
            $table->string('lg_fees_and_commission_account_id')->nullable();
            $table->decimal('lg_commission_rate', 5)->default(0);
            $table->decimal('lg_commission_amount', 14)->default(0);
            $table->string('lg_commission_interval')->nullable();
            $table->string('cash_cover_account_number')->nullable();
            $table->integer('company_id');
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->text('user_comment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_of_guarantee_issuances');
    }
};
