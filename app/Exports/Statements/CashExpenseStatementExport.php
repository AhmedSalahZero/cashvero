<?php

namespace App\Exports\Statements;

/**
 * CashExpenseStatementExport
 * ------------------------------------------------------------------
 * Excel export for the Cash Expense Statement report. Unlike Bank/Safe
 * Statement, this report has no running-balance columns at all — no
 * Beginning/End Balance — so the totals row only needs real column
 * sums (Paid Amount, Withhold Amount), and the base class's default
 * "Beginning/End Balance" totals-cell logic simply finds no matching
 * heading and no-ops automatically (no override needed for that part).
 */
class CashExpenseStatementExport extends AbstractStatementExport
{
    protected function numericColumnLabels(): array
    {
        return ['Paid Amount', 'Withhold Amount', 'Amount In Paying Currency', 'Exchange Rate'];
    }

    protected function summableColumnLabels(): array
    {
        return ['Paid Amount', 'Withhold Amount'];
    }

    protected function conditionalColorColumnLabel(): ?string
    {
        // No running-balance-style column on this report to sign-color.
        return null;
    }
}
