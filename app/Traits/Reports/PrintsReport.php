<?php

namespace App\Traits\Reports;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * PrintsReport
 * ------------------------------------------------------------------
 * The controller half of Pages/Statements/Print.vue.
 *
 * Every report that can be exported to Excel already builds two things:
 * the column headings, and the rows for the WHOLE result set — the
 * export has never been page-bound. Printing reuses exactly that, which
 * is what makes "print all pages, not the one on screen" true by
 * construction rather than by remembering to do it in each report.
 *
 * A report therefore only has to say what it is (title), what was asked
 * for (meta), and which columns are numbers so they align right.
 */
trait PrintsReport
{
    /**
     * @param  array<int, string>  $headings
     * @param  Collection<int, array<string, mixed>>|array  $rows
     * @param  array<int, array{label: string, value: mixed}>  $meta
     * @param  array<int, string>  $numericHeadings
     * @param  array<string, mixed>|null  $totals
     */
    protected function renderReportPrint(
        Company $company,
        string $title,
        array $headings,
        $rows,
        array $meta = [],
        array $numericHeadings = [],
        ?array $totals = null,
    ) {
        return Inertia::render('Statements/Print', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'title' => $title,
            'meta' => array_values(array_filter(
                $meta,
                fn (array $item) => $item['value'] !== null && $item['value'] !== ''
            )),
            'headings' => array_values($headings),
            'rows' => $rows instanceof Collection ? $rows->values()->all() : array_values($rows),
            'numericHeadings' => array_values($numericHeadings),
            'totals' => $totals,
            'printedAt' => Carbon::now()->format('Y-m-d H:i'),
        ]);
    }

    /**
     * The period line every statement shows, in one place so they all
     * word it the same way.
     *
     * @return array{label: string, value: string}
     */
    protected function periodMeta(?string $from, ?string $to): array
    {
        return [
            'label' => __('Period'),
            'value' => trim((string) $from).' → '.trim((string) $to),
        ];
    }

    /**
     * Which of these columns are numbers, and so align right on paper.
     *
     * Matched by the heading's exact label — the same "find the column by
     * its heading" approach AbstractStatementExport already uses to decide
     * which columns get number formatting in the workbook. A report whose
     * columns do not include a given label simply skips it.
     *
     * @param  array<int, string>  $headings
     * @return array<int, string>
     */
    protected function numericHeadingsFor(array $headings): array
    {
        $numeric = [
            'Limit', 'Actual Limit', 'Beginning Balance', 'Debit', 'Credit', 'End Balance',
            'Room', 'Calculated Interest', 'Principle', 'Balance', 'Amount', 'Paid Amount',
            'Exchange Rate', 'Equivalent In Main Currency', 'Invoice Amount', 'Withhold Amount',
            'VAT Amount', 'Total Deductions', 'Net Balance', 'Total Collections', 'Total Payments',
            'Settlement Amount', 'Charge Amount', 'Outstanding Balance', 'Collected Amount',
            'Facility Amount', 'Cash Cover', 'Issuance Amount', 'Aging',
        ];

        return array_values(array_intersect($headings, $numeric));
    }

    /**
     * The criteria line: what was actually asked for, so a printed page
     * still says what it is once it leaves the screen.
     *
     * @return array<int, array{label: string, value: mixed}>
     */
    protected function requestMeta(\Illuminate\Http\Request $request, array $extra = []): array
    {
        $from = $request->get('start_date') ?? $request->get('from');
        $to = $request->get('end_date') ?? $request->get('to');

        $meta = [];

        if ($from || $to) {
            $meta[] = $this->periodMeta($from, $to);
        }

        if ($request->filled('currency')) {
            $meta[] = ['label' => __('Currency'), 'value' => strtoupper((string) $request->get('currency'))];
        }

        return array_merge($meta, $extra);
    }
}
