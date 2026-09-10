<?php

namespace App\Exports\Statements;

/**
 * CashCoverStatementExport
 * ------------------------------------------------------------------
 * Excel export for the Cash Cover Statement.
 *
 * This report was the one statement in the family built without an
 * export at all — every sibling screen offers one, so its absence read
 * as a missing button rather than a deliberate omission.
 *
 * All styling lives in AbstractStatementExport. Cash Cover has no
 * Limit/Room/Interest columns, so those styling steps simply find no
 * matching heading and skip themselves. Its conditional colouring
 * column is "End Balance", which is already the base class's default.
 */
class CashCoverStatementExport extends AbstractStatementExport
{
}
