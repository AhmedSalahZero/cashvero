<?php

namespace App\Traits;

/**
 * LgListReportRows
 * ------------------------------------------------------------------
 * Shared row-shaping for LgByBeneficiaryNameReportController and
 * LgByBankNameReportController — both query letter_of_guarantee_issuances
 * with the exact same joins/select (only the whereIn column differs:
 * partner_id vs financial_institution_id), and both display the exact
 * same set of columns. Extracted here so the two controllers can't
 * silently drift apart on what a row means.
 *
 * Note: `renewal_date` arrives ALREADY FORMATTED from the SQL query
 * itself (a `CASE WHEN status = 'cancelled' THEN 'cancelled' ELSE
 * DATE_FORMAT(...)` expression) — it can legitimately be the literal
 * string "cancelled" instead of a date. This is read as-is, never
 * re-parsed with Carbon.
 */
trait LgListReportRows
{
    /**
     * @param  array  $lgsTypes  LgTypes::getAll() — [type => label]
     * @param  array  $lgsSources  LgSources::getAll() — [source => label]
     */
    protected function mapLgListRow($row, array $lgsTypes, array $lgsSources): array
    {
        return [
            'id' => $row->id,
            'partnerName' => $row->partner_name,
            'financialInstitutionName' => $row->financial_institution_name,
            'lgType' => $lgsTypes[$row->lg_type] ?? $row->lg_type,
            'transactionName' => $row->transaction_name,
            'lgCode' => $row->lg_code,
            'source' => $lgsSources[$row->source] ?? $row->source,
            'lgAmount' => (float) ($row->lg_amount ?? 0),
            'renewalDate' => $row->renewal_date, // already formatted (or "cancelled") by the SQL query itself
            'cashCoverAmount' => (float) ($row->cash_cover_amount ?? 0),
            'lgCommissionRate' => $row->lg_commission_rate,
            'status' => $row->lg_status ?? null, // 'running' | 'expired' | 'cancelled' — computed in fetchRows()
        ];
    }
}
