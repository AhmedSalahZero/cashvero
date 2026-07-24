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
        Schema::create('cash_in_safes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('money_received_id')->index('cash_in_safes_money_received_id_foreign');
            $table->integer('receiving_branch_id')->nullable()->index('cash_in_safes_receiving_branch_id_foreign');
            $table->string('receipt_number')->nullable();
            $table->timestamps();
            $table->integer('company_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_in_safes');
    }
};
