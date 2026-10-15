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
        Schema::create('money_received', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('odoo_reference')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('account_bank_statement_line_id')->nullable();
            $table->string('transaction_type')->nullable();
            $table->boolean('has_unapplied_or_down_payment')->default(false);
            $table->text('odoo_error_message')->nullable();
            $table->boolean('synced_with_odoo')->default(true);
            $table->unsignedBigInteger('advanced_opening_balance_id')->nullable();
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->unsignedBigInteger('odoo_move_id')->nullable();
            $table->string('partner_type')->default('is_customer');
            $table->unsignedBigInteger('partner_id')->nullable()->comment('partner_id');
            $table->boolean('is_reviewed')->default(false);
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('المشرف اللي حدد انه راجعه');
            $table->string('money_type')->default('money-received');
            $table->string('down_payment_type')->nullable();
            $table->date('down_payment_settlement_date')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable()->index('money_received_contract_id_foreign')->comment('في حاله لو كان downpayment اي دفعه مقدمة');
            $table->unsignedBigInteger('opening_balance_id')->nullable()->index('money_received_opening_balance_id_foreign');
            $table->string('type')->nullable();
            $table->date('receiving_date')->nullable();
            $table->decimal('received_amount', 14)->nullable();
            $table->decimal('received_amount_in_main_currency')->default(0);
            $table->double('total_withhold_amount')->default(0);
            $table->double('total_withhold_amount_in_main_currency')->nullable()->default(0);
            $table->double('amount_in_invoice_currency')->nullable()->default(0);
            $table->string('currency')->nullable();
            $table->string('receiving_currency')->nullable();
            $table->double('exchange_rate')->nullable()->default(1);
            $table->integer('user_id')->nullable();
            $table->integer('company_id')->nullable();
            $table->string('comment_ar')->nullable();
            $table->string('comment_en')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->text('user_comment')->nullable();

            $table->index(['company_id', 'contract_id'], 'mr_co_contract_idx');
            $table->index(['company_id', 'partner_id'], 'mr_co_partner_idx');
            $table->index(['company_id', 'type', 'receiving_date'], 'mr_co_type_receiving_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('money_received');
    }
};
