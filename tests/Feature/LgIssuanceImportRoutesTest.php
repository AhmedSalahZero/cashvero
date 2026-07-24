<?php

namespace Tests\Feature;

use App\Enums\LgSources;
use App\Exports\LgImport\LgIssuanceLookupSheetExport;
use App\Support\LgImport\LgIssuanceImportTemplateService;
use Tests\TestCase;

class LgIssuanceImportRoutesTest extends TestCase
{
    public function test_lg_issuance_import_routes_are_registered(): void
    {
        $this->assertTrue(\Route::has('download.letter.of.guarantee.issuance.template'));
        $this->assertTrue(\Route::has('import.letter.of.guarantee.issuance'));
        $this->assertTrue(\Route::has('status.letter.of.guarantee.issuance.import'));
        $this->assertTrue(\Route::has('errors.letter.of.guarantee.issuance.import'));
    }

    public function test_template_columns_exist_for_all_sources(): void
    {
        foreach (array_keys(LgSources::getAll()) as $source) {
            $columns = LgIssuanceImportTemplateService::columnsBySource($source);
            $this->assertNotEmpty($columns);
            $this->assertContains('transaction_name', $columns);
            $this->assertContains('lg_type', $columns);
            $this->assertContains('issuance_date', $columns);
            $this->assertContains('partner_name', $columns);
            $this->assertContains('contract_name', $columns);
            $this->assertContains('purchase_order_number', $columns);
            $this->assertNotContains('partner_id', $columns);
            $this->assertNotContains('contract_id', $columns);
            $this->assertNotContains('purchase_order_id', $columns);
        }
    }

    public function test_lookup_sheet_uses_display_values_not_keys(): void
    {
        $rows = (new LgIssuanceLookupSheetExport([
            'partner_name' => ['1' => 'Acme', '2' => 'Beta'],
        ]))->array();

        $this->assertSame(['partner_name'], $rows[0]);
        $this->assertSame(['Acme'], $rows[1]);
        $this->assertSame(['Beta'], $rows[2]);
    }

    public function test_template_to_canonical_mapping_contains_readable_fields(): void
    {
        $map = LgIssuanceImportTemplateService::templateToCanonicalColumnMap();
        $this->assertSame('partner_id', $map['partner_name']);
        $this->assertSame('contract_id', $map['contract_name']);
        $this->assertSame('purchase_order_id', $map['purchase_order_number']);
    }
}
