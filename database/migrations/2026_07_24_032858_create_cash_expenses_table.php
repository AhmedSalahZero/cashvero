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
        Schema::create('cash_expenses', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('odoo_reference')->nullable();
            $table->text('odoo_error_message')->nullable();
            $table->boolean('synced_with_odoo')->default(true);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('account_bank_statement_line_id')->nullable();
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->boolean('is_reviewed')->default(false);
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('المشرف اللي حدد انه راجعه');
            $table->unsignedBigInteger('cash_expense_category_name_id')->nullable();
            $table->unsignedBigInteger('opening_balance_id')->nullable()->index('cash_expenses_opening_balance_id_foreign');
            $table->string('type')->nullable();
            $table->string('supplier_name')->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('paid_amount', 14)->nullable();
            $table->double('total_withhold_amount')->default(0);
            $table->double('total_withhold_amount_in_main_currency')->nullable()->default(0);
            $table->double('amount_in_paying_currency')->nullable()->default(0);
            $table->string('currency')->nullable();
            $table->double('exchange_rate')->nullable()->default(1);
            $table->integer('user_id')->nullable();
            $table->integer('company_id')->nullable();
            $table->string('comment_ar')->nullable();
            $table->string('comment_en')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->text('user_comment')->nullable();

            $table->index(['company_id', 'type', 'payment_date'], 'ce_co_type_payment_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_expenses');
    }
};
