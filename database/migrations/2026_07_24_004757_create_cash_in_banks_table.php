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
        Schema::create('cash_in_banks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('money_received_id')->index('cash_in_banks_money_received_id_foreign');
            $table->integer('receiving_bank_id')->nullable()->index('cash_in_banks_receiving_bank_id_foreign');
            $table->string('account_type')->nullable();
            $table->string('account_number')->nullable();
            $table->timestamps();
            $table->integer('company_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_in_banks');
    }
};
