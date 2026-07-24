<?php

namespace App\Exports\Statements;

/**
 * BankStatementExport
 * ------------------------------------------------------------------
 * Excel export for the Bank Statement report. All styling (header
 * color, banded rows, End Balance sign-coloring, totals row) lives in
 * AbstractStatementExport — this class only exists so the file name
 * on disk/in a stack trace clearly says "Bank Statement", matching
 * SafeStatementExport's sibling class.
 */
class BankStatementExport extends AbstractStatementExport
{
}
