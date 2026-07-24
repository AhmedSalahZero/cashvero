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
        Schema::table('clean_overdraft_withdrawals', function (Blueprint $table) {
            $table->foreign(['clean_overdraft_bank_statement_id'], 'clean_overdrafts_identifier')->references(['id'])->on('clean_overdraft_bank_statements')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clean_overdraft_withdrawals', function (Blueprint $table) {
            $table->dropForeign('clean_overdrafts_identifier');
        });
    }
};
