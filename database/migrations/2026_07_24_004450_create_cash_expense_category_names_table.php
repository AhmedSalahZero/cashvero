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
        Schema::create('cash_expense_category_names', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('odoo_id')->nullable();
            $table->string('odoo_chart_of_account_number')->nullable();
            $table->unsignedBigInteger('company_id')->index('cash_expense_category_names_company_id_foreign');
            $table->unsignedBigInteger('cash_expense_category_id')->index('cash_expense_category_names_cash_expense_category_id_foreign');
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_expense_category_names');
    }
};
