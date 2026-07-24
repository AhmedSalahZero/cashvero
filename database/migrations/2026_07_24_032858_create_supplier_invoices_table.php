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
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->integer('company_id');
            $table->unsignedBigInteger('opening_balance_id')->nullable();
            $table->string('supplier_code')->nullable();
            $table->string('sales_person')->nullable();
            $table->integer('supplier_id');
            $table->string('supplier_name')->nullable();
            $table->string('business_sector')->nullable();
            $table->string('project_name')->nullable();
            $table->string('site_name')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('invoice_month', 2)->nullable();
            $table->mediumInteger('invoice_year')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('invoice_amount')->nullable()->default('0');
            $table->string('currency', 10);
            $table->decimal('exchange_rate', 10, 5)->default(1);
            $table->double('invoice_amount_in_main_currency')->nullable()->default(0);
            $table->string('vat_amount')->nullable()->default('0');
            $table->double('vat_amount_in_main_currency')->nullable()->default(0);
            $table->decimal('odoo_withhold_amount', 14, 5)->default(0);
            $table->decimal('odoo_withhold_amount_in_main_currency', 14, 5)->default(0);
            $table->string('withhold_amount')->nullable()->default('0');
            $table->double('withhold_amount_in_main_currency')->nullable()->default(0);
            $table->decimal('total_withhold_amount', 14, 5)->default(0);
            $table->decimal('total_withhold_amount_in_main_currency', 14, 5)->default(0);
            $table->string('net_invoice_amount')->nullable()->default('0');
            $table->double('net_invoice_amount_in_main_currency')->nullable()->default(0);
            $table->string('contracted_payment_days')->nullable();
            $table->date('invoice_due_date')->nullable();
            $table->string('invoice_status')->nullable();
            $table->decimal('odoo_paid_amount', 14, 5)->default(0);
            $table->decimal('odoo_paid_amount_in_main_currency', 14, 5)->default(0);
            $table->decimal('excel_paid_amount', 14, 5)->nullable()->default(0);
            $table->decimal('excel_paid_amount_in_main_currency', 14, 5)->nullable()->default(0);
            $table->string('paid_amount')->nullable()->default('0');
            $table->double('paid_amount_in_main_currency')->nullable()->default(0);
            $table->decimal('total_paid_amount', 14, 5)->default(0);
            $table->decimal('total_paid_amount_in_main_currency', 14, 5)->default(0);
            $table->decimal('total_deductions', 14)->default(0);
            $table->decimal('total_deductions_in_main_currency', 14)->default(0);
            $table->string('net_balance')->nullable()->default('0');
            $table->double('net_balance_in_main_currency')->nullable()->default(0);
            $table->boolean('is_period_closed')->nullable()->default(false);
            $table->boolean('is_canceled')->nullable()->default(false);
            $table->date('contract_date')->nullable();
            $table->string('contract_code')->nullable()->index('si_contract_code_idx');
            $table->string('contract_name')->nullable();
            $table->decimal('contract_amount', 14)->nullable()->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->decimal('discount_amount', 14)->nullable()->default(0);
            $table->decimal('discount_amount_in_main_currency', 14)->nullable()->default(0);
            $table->string('purchases_order_number')->nullable();
            $table->date('purchases_order_date')->nullable();

            $table->index(['company_id', 'contract_code'], 'si_co_contract_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
