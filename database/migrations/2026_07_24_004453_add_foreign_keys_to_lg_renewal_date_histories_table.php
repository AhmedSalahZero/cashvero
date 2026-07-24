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
        Schema::table('lg_renewal_date_histories', function (Blueprint $table) {
            $table->foreign(['letter_of_guarantee_issuance_id'], 'lg_renewal_foreign')->references(['id'])->on('letter_of_guarantee_issuances')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lg_renewal_date_histories', function (Blueprint $table) {
            $table->dropForeign('lg_renewal_foreign');
        });
    }
};
