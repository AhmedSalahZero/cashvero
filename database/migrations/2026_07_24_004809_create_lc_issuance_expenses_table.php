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
        Schema::create('lc_issuance_expenses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('expense_name')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('lc_issuance_id');
            $table->date('date');
            $table->decimal('amount', 14)->default(0);
            $table->string('currency');
            $table->decimal('exchange_rate', 6, 3)->default(0);
            $table->decimal('amount_in_main_currency')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lc_issuance_expenses');
    }
};
