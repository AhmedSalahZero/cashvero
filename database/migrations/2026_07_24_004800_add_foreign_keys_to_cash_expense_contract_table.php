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
        Schema::table('cash_expense_contract', function (Blueprint $table) {
            $table->foreign(['cash_expense_id'])->references(['id'])->on('cash_expenses')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['contract_id'])->references(['id'])->on('contracts')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_expense_contract', function (Blueprint $table) {
            $table->dropForeign('cash_expense_contract_cash_expense_id_foreign');
            $table->dropForeign('cash_expense_contract_contract_id_foreign');
        });
    }
};
