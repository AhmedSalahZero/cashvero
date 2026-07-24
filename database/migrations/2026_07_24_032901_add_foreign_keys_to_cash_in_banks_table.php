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
        Schema::table('cash_in_banks', function (Blueprint $table) {
            $table->foreign(['money_received_id'])->references(['id'])->on('money_received')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['receiving_bank_id'])->references(['id'])->on('financial_institutions')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_in_banks', function (Blueprint $table) {
            $table->dropForeign('cash_in_banks_money_received_id_foreign');
            $table->dropForeign('cash_in_banks_receiving_bank_id_foreign');
        });
    }
};
