<?php

namespace App\Interfaces\Models;

/**
 * An account whose Odoo link is resolved from a user-entered Odoo Code
 * against Odoo's chart of accounts.
 *
 * OdooService::syncFinancialInstitutions() takes the code, finds the
 * matching chart-of-account entry, and writes back odoo_id, journal_id
 * and the four payment-method ids. It only ever needed getOdooCode() +
 * the model's own update()/getAttributes(), but was type-hinted to
 * FinancialInstitutionAccount because that was the only account it ran
 * for. This interface widens that hint honestly instead of loosening it
 * to a bare Model.
 */
interface ISyncsWithOdooChartOfAccount
{
    public function getOdooCode();
}
