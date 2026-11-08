<?php

namespace App\Exports\Statements;

use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One sheet of BankChargesAnalysisExport. All three sheets share the
 * statement styling; only the money columns differ from the base.
 */
class BankChargesAnalysisSheet extends AbstractStatementExport implements WithTitle
{
    public function __construct(private string $sheetTitle, array $headings, iterable $rows)
    {
        parent::__construct($headings, $rows);
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    protected function numericColumnLabels(): array
    {
        return ['Interest', 'Commission', 'Charges', 'Total', 'Share %', 'Amount', 'Exchange Rate', 'Amount In Main Currency'];
    }

    /**
     * The original-currency Amount is only summable when every line is in
     * one currency — i.e. when there is no converted column next to it.
     */
    protected function summableColumnLabels(): array
    {
        $labels = ['Interest', 'Commission', 'Charges', 'Total', 'Share %', 'Amount In Main Currency'];

        if (! in_array('Amount In Main Currency', $this->headingsList, true)) {
            $labels[] = 'Amount';
        }

        return $labels;
    }

    protected function conditionalColorColumnLabel(): ?string
    {
        return null;
    }
}
