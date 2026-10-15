<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill for the previous migration's new column. Matches each
 * existing contract_loan_schedules row to a real financial_institution_accounts
 * row by (company_id, drawee_bank_id -> financial_institution_id,
 * account_number text, trimmed). Rows with no confident match (typo'd
 * number, account since deleted, blank account_number, etc.) are left
 * with financial_institution_account_id = null; ContractLoanSchedule's
 * account_number accessor falls back to the stored text for those, so
 * nothing breaks — they just don't get the "always current" behavior
 * until someone fixes the row by hand.
 *
 * Safe to re-run: only touches rows still missing the link.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE contract_loan_schedules cls
            JOIN financial_institution_accounts fia
                ON fia.financial_institution_id = cls.drawee_bank_id
                AND fia.company_id = cls.company_id
                AND TRIM(fia.account_number) = TRIM(cls.account_number)
            SET cls.financial_institution_account_id = fia.id
            WHERE cls.financial_institution_account_id IS NULL
                AND cls.drawee_bank_id IS NOT NULL
                AND cls.account_number IS NOT NULL
                AND TRIM(cls.account_number) != ''
        SQL);
    }

    public function down(): void
    {
        // Nothing to reverse — the link column itself is dropped by the
        // schema migration this depends on.
    }
};
