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
 * itself. LG By Bank Name always sends the formatted renewal date, plus
 * `cancellation_date` when the row is cancelled. LG By Beneficiary Name
 * still sends the literal string "cancelled" in place of that date.
 * This is read as-is, never re-parsed with Carbon.
 */
trait LgListReportRows
{
    /**
     * An unactivated Advance Payment LG is listed like any other, but
     * no cash cover has been taken for it yet — so both the row and the
     * Cash Cover total read 0 for it.
     */
    protected const CASH_COVER_SQL = 'CASE WHEN letter_of_guarantee_issuances.is_activated = 1 THEN letter_of_guarantee_issuances.cash_cover_amount ELSE 0 END';

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
            'isActivated' => (bool) ($row->is_activated ?? true),
            'source' => $lgsSources[$row->source] ?? $row->source,
            'lgAmount' => (float) ($row->lg_amount ?? 0),
            'renewalDate' => $row->renewal_date, // already formatted (or "cancelled") by the SQL query itself
            'cancellationDate' => $row->cancellation_date ?? null,
            'cashCoverAmount' => (float) ($row->cash_cover_amount ?? 0),
            'lgCommissionRate' => $row->lg_commission_rate,
            'status' => $row->lg_status ?? null, // 'running' | 'expired' | 'cancelled' — computed in fetchRows()
        ];
    }

    /**
     * The workbook and the printout have no badges, so the flag rides
     * along with the code itself.
     */
    protected function lgCodeForExport(array $mappedRow): string
    {
        return $mappedRow['isActivated']
            ? (string) $mappedRow['lgCode']
            : $mappedRow['lgCode'].' ('.__('Unactivated').')';
    }
}
