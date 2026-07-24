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
        Schema::table('financial_institution_accounts', function (Blueprint $table) {
            $table->foreign(['financial_institution_id'])->references(['id'])->on('financial_institutions')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_institution_accounts', function (Blueprint $table) {
            $table->dropForeign('financial_institution_accounts_financial_institution_id_foreign');
        });
    }
};
