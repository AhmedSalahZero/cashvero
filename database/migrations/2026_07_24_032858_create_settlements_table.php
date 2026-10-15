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
        Schema::create('settlements', function (Blueprint $table) {
            $table->integer('id', true);
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->unsignedBigInteger('odoo_move_id')->nullable();
            $table->string('odoo_reference')->nullable();
            $table->unsignedBigInteger('account_bank_statement_line_id')->nullable();
            $table->string('odoo_reference_name')->nullable();
            $table->boolean('is_from_down_payment')->default(false);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('partner_id')->default(0);
            $table->string('withhold_amount')->nullable();
            $table->string('settlement_amount')->nullable();
            $table->integer('money_received_id')->nullable();
            $table->unsignedBigInteger('factoring_transaction_id')->nullable()->index();
            $table->integer('company_id')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();

            $table->index(['money_received_id', 'invoice_id'], 'settlements_mr_invoice_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
