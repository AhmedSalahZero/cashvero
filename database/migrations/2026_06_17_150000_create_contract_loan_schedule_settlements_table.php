<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_loan_schedule_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('contract_loan_schedule_id');
            $table->string('current_account_number');
            $table->date('date');
            $table->decimal('amount', 20, 2);
            $table->timestamps();

            $table->index(['company_id', 'contract_loan_schedule_id'], 'cls_settlements_company_schedule_idx');
        });

        Schema::table('current_account_bank_statements', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_loan_schedule_settlement_id')->nullable()->after('loan_schedule_settlement_id');
        });

        Schema::table('loan_statements', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_loan_schedule_settlement_id')->nullable()->after('loan_schedule_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::table('loan_statements', function (Blueprint $table) {
            $table->dropColumn('contract_loan_schedule_settlement_id');
        });

        Schema::table('current_account_bank_statements', function (Blueprint $table) {
            $table->dropColumn('contract_loan_schedule_settlement_id');
        });

        Schema::dropIfExists('contract_loan_schedule_settlements');
    }
};
