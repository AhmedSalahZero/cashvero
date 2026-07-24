<?php

namespace App\Exports\Statements;

/**
 * LgListReportExport
 * ------------------------------------------------------------------
 * Excel export shared by LG By Beneficiary Name and LG By Bank Name —
 * both produce the exact same flat column set (only row ORDER/grouping
 * differs, decided by the controller before rows ever reach here).
 * No running-balance columns on this report at all.
 */
class LgListReportExport extends AbstractStatementExport
{
    protected function numericColumnLabels(): array
    {
        return ['Amount', 'Cash Cover', 'Commission Rate %'];
    }

    protected function summableColumnLabels(): array
    {
        return ['Amount', 'Cash Cover'];
    }

    protected function conditionalColorColumnLabel(): ?string
    {
        // No running-balance-style column on this report to sign-color.
        return null;
    }
}
