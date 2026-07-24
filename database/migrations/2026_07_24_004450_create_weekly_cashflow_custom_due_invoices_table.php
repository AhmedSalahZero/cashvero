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
        Schema::create('weekly_cashflow_custom_due_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('is_contract')->default(false);
            $table->bigInteger('cashflow_report_id')->default(0);
            $table->integer('invoice_id');
            $table->string('invoice_type')->default('CustomerInvoice');
            $table->date('week_start_date');
            $table->decimal('percentage', 5)->default(100);
            $table->decimal('amount', 14, 5)->default(0);
            $table->integer('company_id');
            $table->timestamps();

            $table->index(['company_id', 'cashflow_report_id', 'is_contract', 'invoice_type'], 'wccfi_co_report_contract_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_cashflow_custom_due_invoices');
    }
};
