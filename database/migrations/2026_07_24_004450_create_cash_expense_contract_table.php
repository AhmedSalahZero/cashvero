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
        Schema::create('cash_expense_contract', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('cash_expense_id')->nullable()->index('cash_expense_contract_cash_expense_id_foreign');
            $table->unsignedBigInteger('contract_id')->nullable()->index('cash_expense_contract_contract_id_foreign');
            $table->decimal('amount', 14);
            $table->timestamps();

            $table->index(['contract_id', 'cash_expense_id'], 'cec_contract_expense_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_expense_contract');
    }
};
