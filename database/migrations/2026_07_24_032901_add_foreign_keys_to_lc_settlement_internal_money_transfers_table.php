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
        Schema::table('lc_settlement_internal_money_transfers', function (Blueprint $table) {
            $table->foreign(['company_id'])->references(['id'])->on('companies')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['from_bank_id'])->references(['id'])->on('financial_institutions')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['from_account_type_id'], 'qqq2')->references(['id'])->on('account_types')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['to_letter_of_credit_issuance_id'], 'qqq3')->references(['id'])->on('letter_of_credit_issuances')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lc_settlement_internal_money_transfers', function (Blueprint $table) {
            $table->dropForeign('lc_settlement_internal_money_transfers_company_id_foreign');
            $table->dropForeign('lc_settlement_internal_money_transfers_from_bank_id_foreign');
            $table->dropForeign('qqq2');
            $table->dropForeign('qqq3');
        });
    }
};
