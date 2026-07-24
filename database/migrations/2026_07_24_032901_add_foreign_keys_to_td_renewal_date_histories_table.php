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
        Schema::table('td_renewal_date_histories', function (Blueprint $table) {
            $table->foreign(['time_of_deposit_id'], 'td_renewal_foreign')->references(['id'])->on('time_of_deposits')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('td_renewal_date_histories', function (Blueprint $table) {
            $table->dropForeign('td_renewal_foreign');
        });
    }
};
