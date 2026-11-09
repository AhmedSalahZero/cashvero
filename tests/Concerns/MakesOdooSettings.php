<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\OdooSetting;
use Illuminate\Support\Facades\Schema;

/**
 * A company's Odoo account mapping (odoo_settings), filled in completely with FAKE Odoo ids.
 *
 *   $setting = $this->odooSetting($company);                                   // every account mapped
 *   $setting = $this->odooSetting($company, ['advances_from_customers_id' => 4711]);
 *
 * An odoo_settings row has dozens of NOT NULL "<account>_id" / "<account>_code" pairs. Every one
 * of them gets a distinct made-up value here (ids from 900001 up, codes "T-<column>"), so a test
 * only names the account it is actually about. The ids point at nothing: no test talks to a real
 * Odoo (see FakeOdooClientFactory).
 */
trait MakesOdooSettings
{
    protected function odooSetting(Company $company, array $overrides = []): OdooSetting
    {
        $row = [];
        $nextId = 900001;

        foreach (Schema::getColumnListing('odoo_settings') as $column) {
            if (in_array($column, ['id', 'company_id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            if (str_ends_with($column, '_id')) {
                $row[$column] = $nextId++;
            } elseif (str_ends_with($column, '_code')) {
                // Some *_code columns are numeric (bigint) — a number fits both kinds.
                $row[$column] = (string) ($nextId++);
            }
        }

        $setting = new OdooSetting;
        $setting->forceFill(array_merge($row, ['company_id' => $company->id], $overrides))->save();

        $company->unsetRelation('odooSetting');

        return $setting->refresh();
    }
}
