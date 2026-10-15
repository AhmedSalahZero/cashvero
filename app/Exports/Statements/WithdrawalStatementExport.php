<?php

namespace App\Exports\Statements;

/**
 * WithdrawalStatementExport
 * ------------------------------------------------------------------
 * Excel export for the Withdrawal Statement report. No running-
 * balance columns here (no Beginning/End Balance) — instead
 * "Balance" (the still-outstanding amount) gets the sign-based
 * conditional coloring: amber while > 0 (still owed — "needs
 * attention", a genuine fit for the Number Color Rule's amber
 * meaning), green at 0 (fully settled), red if ever negative (an
 * anomaly worth flagging, same as everywhere else this coloring is
 * used).
 */
class WithdrawalStatementExport extends AbstractStatementExport
{
    protected function numericColumnLabels(): array
    {
        return ['Withdrawal Amount', 'Settlement Amount', 'Balance'];
    }

    protected function summableColumnLabels(): array
    {
        return ['Withdrawal Amount', 'Settlement Amount', 'Balance'];
    }

    protected function conditionalColorColumnLabel(): ?string
    {
        return 'Balance';
    }
}
