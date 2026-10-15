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
        Schema::table('letter_of_guarantee_issuances', function (Blueprint $table) {
            $table->foreign(['contract_id'])->references(['id'])->on('contracts')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['financial_institution_id'])->references(['id'])->on('financial_institutions')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['lg_facility_id'])->references(['id'])->on('letter_of_guarantee_facilities')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['partner_id'])->references(['id'])->on('partners')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['purchase_order_id'])->references(['id'])->on('sales_orders')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letter_of_guarantee_issuances', function (Blueprint $table) {
            $table->dropForeign('letter_of_guarantee_issuances_contract_id_foreign');
            $table->dropForeign('letter_of_guarantee_issuances_financial_institution_id_foreign');
            $table->dropForeign('letter_of_guarantee_issuances_lg_facility_id_foreign');
            $table->dropForeign('letter_of_guarantee_issuances_partner_id_foreign');
            $table->dropForeign('letter_of_guarantee_issuances_purchase_order_id_foreign');
        });
    }
};
