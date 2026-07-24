<?php

namespace App\Exports\Statements;

/**
 * LgLcStatementExport
 * ------------------------------------------------------------------
 * Excel export for the LG & LC Statement report. All styling lives in
 * AbstractStatementExport, and its DEFAULT column labels (Limit,
 * Beginning Balance, Debit, Credit, Room, End Balance) already match
 * this report's exact shape — no overrides needed. This class exists
 * purely so the file name on disk/in a stack trace clearly says
 * "LG & LC Statement", matching its Bank/Safe Statement siblings.
 */
class LgLcStatementExport extends AbstractStatementExport
{
}
