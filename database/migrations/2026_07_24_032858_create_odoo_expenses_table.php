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
        Schema::create('odoo_expenses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('odoo_id');
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->string('odoo_currency_id');
            $table->string('state');
            $table->string('payment_state');
            $table->unsignedBigInteger('odoo_employee_id')->comment('الموظف اللي طلع المصروف دا');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('account_move_ids');
            $table->unsignedBigInteger('journal_id');
            $table->unsignedBigInteger('payment_method_line_id');
            $table->string('payment_mode');
            $table->timestamps();
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odoo_expenses');
    }
};
