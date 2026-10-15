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
        Schema::table('money_received', function (Blueprint $table) {
            $table->foreign(['contract_id'])->references(['id'])->on('contracts')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['opening_balance_id'])->references(['id'])->on('opening_balances')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('money_received', function (Blueprint $table) {
            $table->dropForeign('money_received_contract_id_foreign');
            $table->dropForeign('money_received_opening_balance_id_foreign');
        });
    }
};
