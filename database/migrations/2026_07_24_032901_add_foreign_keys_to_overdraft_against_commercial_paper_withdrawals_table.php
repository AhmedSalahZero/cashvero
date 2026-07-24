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
        Schema::table('overdraft_against_commercial_paper_withdrawals', function (Blueprint $table) {
            $table->foreign(['overdraft_against_commercial_paper_bank_statement_id'], 'overdraft_against_commercial_papers_identifier')->references(['id'])->on('overdraft_against_commercial_paper_bank_statements')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overdraft_against_commercial_paper_withdrawals', function (Blueprint $table) {
            $table->dropForeign('overdraft_against_commercial_papers_identifier');
        });
    }
};
