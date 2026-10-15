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
        Schema::table('outgoing_transfers', function (Blueprint $table) {
            $table->foreign(['delivery_bank_id'])->references(['id'])->on('financial_institutions')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['money_payment_id'])->references(['id'])->on('money_payments')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outgoing_transfers', function (Blueprint $table) {
            $table->dropForeign('outgoing_transfers_delivery_bank_id_foreign');
            $table->dropForeign('outgoing_transfers_money_payment_id_foreign');
        });
    }
};
