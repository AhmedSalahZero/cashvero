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
        Schema::table('overdraft_against_assignment_of_contract_withdrawals', function (Blueprint $table) {
            $table->foreign(['overdraft_against_assignment_of_contract_bank_statement_id'], 'overdraft_against_assignment_of_contracts_identifier')->references(['id'])->on('overdraft_against_assignment_of_contract_bank_statements')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overdraft_against_assignment_of_contract_withdrawals', function (Blueprint $table) {
            $table->dropForeign('overdraft_against_assignment_of_contracts_identifier');
        });
    }
};
