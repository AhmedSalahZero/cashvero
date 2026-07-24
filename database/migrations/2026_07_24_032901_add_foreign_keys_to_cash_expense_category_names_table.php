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
        Schema::table('cash_expense_category_names', function (Blueprint $table) {
            $table->foreign(['cash_expense_category_id'])->references(['id'])->on('cash_expense_categories')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['company_id'])->references(['id'])->on('companies')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_expense_category_names', function (Blueprint $table) {
            $table->dropForeign('cash_expense_category_names_cash_expense_category_id_foreign');
            $table->dropForeign('cash_expense_category_names_company_id_foreign');
        });
    }
};
