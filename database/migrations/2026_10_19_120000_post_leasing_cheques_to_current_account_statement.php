<?php

use App\Models\ContractLoanSchedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leasing cheques now behave like supplier payable cheques: each
 * installment cheque is posted to its drawee current account as soon as
 * the schedule row exists (dated at the cheque's due date), and "Mark As
 * Paid" later moves that same row to the actual payment date.
 *
 *   - current_account_bank_statements.contract_loan_schedule_id:
 *       links the posted cheque row back to its installment.
 *   - contract_loan_schedules.actual_payment_date:
 *       set by Mark As Paid, cleared by Unmark. Null = cheque not paid yet.
 *   - contract_loan_schedule_settlements.is_cheque_payment:
 *       1 = the settlement Mark As Paid creates. It never posts its own
 *       bank row (the cheque row already exists). 0 = an older partial
 *       settlement that posted its own bank row; the cheque row only
 *       carries what those didn't already cover.
 *
 * The backfill posts every existing installment that isn't fully covered
 * yet. Re-running it is safe: syncChequeStatement() is idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('current_account_bank_statements', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_loan_schedule_id')->nullable()->after('contract_loan_schedule_settlement_id');
            $table->index('contract_loan_schedule_id', 'cabs_contract_loan_schedule_id_index');
        });

        Schema::table('contract_loan_schedules', function (Blueprint $table) {
            $table->date('actual_payment_date')->nullable()->after('status');
        });

        Schema::table('contract_loan_schedule_settlements', function (Blueprint $table) {
            $table->boolean('is_cheque_payment')->default(false)->after('amount');
        });

        ContractLoanSchedule::query()
            ->with('leasingContract.contractLoanSchedules', 'financialInstitutionAccount')
            ->orderBy('id')
            ->each(fn (ContractLoanSchedule $schedule) => $schedule->syncChequeStatement());
    }

    public function down(): void
    {
        ContractLoanSchedule::query()->orderBy('id')->each(fn (ContractLoanSchedule $schedule) => $schedule->deleteChequeStatement());

        Schema::table('contract_loan_schedule_settlements', function (Blueprint $table) {
            $table->dropColumn('is_cheque_payment');
        });

        Schema::table('contract_loan_schedules', function (Blueprint $table) {
            $table->dropColumn('actual_payment_date');
        });

        Schema::table('current_account_bank_statements', function (Blueprint $table) {
            $table->dropIndex('cabs_contract_loan_schedule_id_index');
            $table->dropColumn('contract_loan_schedule_id');
        });
    }
};
