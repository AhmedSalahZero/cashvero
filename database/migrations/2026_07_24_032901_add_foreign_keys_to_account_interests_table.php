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
        Schema::table('account_interests', function (Blueprint $table) {
            $table->foreign(['financial_institution_account_id'], 'account_foreign')->references(['id'])->on('financial_institution_accounts')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_interests', function (Blueprint $table) {
            $table->dropForeign('account_foreign');
        });
    }
};
