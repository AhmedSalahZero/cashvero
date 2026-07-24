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
        Schema::table('buy_or_sell_currencies', function (Blueprint $table) {
            $table->foreign(['company_id'])->references(['id'])->on('companies')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['from_account_type_id'])->references(['id'])->on('account_types')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['from_bank_id'])->references(['id'])->on('financial_institutions')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['to_account_type_id'])->references(['id'])->on('account_types')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buy_or_sell_currencies', function (Blueprint $table) {
            $table->dropForeign('buy_or_sell_currencies_company_id_foreign');
            $table->dropForeign('buy_or_sell_currencies_from_account_type_id_foreign');
            $table->dropForeign('buy_or_sell_currencies_from_bank_id_foreign');
            $table->dropForeign('buy_or_sell_currencies_to_account_type_id_foreign');
        });
    }
};
