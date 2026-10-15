<?php

namespace App\Exports\Statements;

/**
 * SafeStatementExport
 * ------------------------------------------------------------------
 * Excel export for the Safe Statement report. All styling (header
 * color, banded rows, End Balance sign-coloring, totals row) lives in
 * AbstractStatementExport — this class only exists so the file name
 * on disk/in a stack trace clearly says "Safe Statement", matching
 * BankStatementExport's sibling class. Safe Statement has no
 * Limit/Room/Interest columns, so those styling steps in the shared
 * base class simply find no matching heading and skip themselves —
 * no special-casing needed here.
 */
class SafeStatementExport extends AbstractStatementExport
{
}
