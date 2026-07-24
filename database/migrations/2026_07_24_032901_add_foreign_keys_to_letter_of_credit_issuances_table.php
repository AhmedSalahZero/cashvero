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
        Schema::table('letter_of_credit_issuances', function (Blueprint $table) {
            $table->foreign(['contract_id'])->references(['id'])->on('contracts')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['financial_institution_id'])->references(['id'])->on('financial_institutions')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['partner_id'])->references(['id'])->on('partners')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letter_of_credit_issuances', function (Blueprint $table) {
            $table->dropForeign('letter_of_credit_issuances_contract_id_foreign');
            $table->dropForeign('letter_of_credit_issuances_financial_institution_id_foreign');
            $table->dropForeign('letter_of_credit_issuances_partner_id_foreign');
        });
    }
};
