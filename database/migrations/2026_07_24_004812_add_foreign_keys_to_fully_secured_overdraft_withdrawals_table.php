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
        Schema::table('fully_secured_overdraft_withdrawals', function (Blueprint $table) {
            $table->foreign(['fully_secured_overdraft_bank_statement_id'], 'fully_secured_overdrafts_identifier')->references(['id'])->on('fully_secured_overdraft_bank_statements')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fully_secured_overdraft_withdrawals', function (Blueprint $table) {
            $table->dropForeign('fully_secured_overdrafts_identifier');
        });
    }
};
