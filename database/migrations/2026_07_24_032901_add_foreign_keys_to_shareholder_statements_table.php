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
        Schema::table('shareholder_statements', function (Blueprint $table) {
            $table->foreign(['partner_id'])->references(['id'])->on('partners')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shareholder_statements', function (Blueprint $table) {
            $table->dropForeign('shareholder_statements_partner_id_foreign');
        });
    }
};
