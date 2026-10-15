<?php

namespace Tests\Feature;

use App\Enums\LgSources;
use App\Enums\LgTypes;
use App\Exports\LgImport\LgIssuanceLookupSheetExport;
use App\Models\Company;
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

    public function test_the_activation_date_column_sits_right_before_the_issuance_date(): void
    {
        foreach (array_keys(LgSources::getAll()) as $source) {
            $columns = LgIssuanceImportTemplateService::columnsBySource($source);
            $this->assertSame(
                array_search('issuance_date', $columns, true) - 1,
                array_search('activation_date', $columns, true),
                $source
            );
        }
    }

    public function test_an_advance_payment_row_may_leave_the_activation_date_empty(): void
    {
        $this->assertArrayNotHasKey('activation_date', $this->rowErrors([
            'lg_type' => LgTypes::ADVANCED_PAYMENT_LGS,
            'issuance_date' => '2026-01-10',
            'activation_date' => null,
        ]));
    }

    public function test_an_activation_date_before_the_issuance_date_is_rejected(): void
    {
        $this->assertArrayHasKey('activation_date', $this->rowErrors([
            'lg_type' => LgTypes::ADVANCED_PAYMENT_LGS,
            'issuance_date' => '2026-01-10',
            'activation_date' => '2026-01-09',
        ]));
    }

    public function test_other_lg_types_ignore_the_activation_date(): void
    {
        $this->assertArrayNotHasKey('activation_date', $this->rowErrors([
            'lg_type' => LgTypes::BID_BOND,
            'issuance_date' => '2026-01-10',
            'activation_date' => '2026-01-09',
        ]));
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

    private function rowErrors(array $row): array
    {
        return LgIssuanceImportTemplateService::validateRow(new Company, LgSources::LG_FACILITY, $row);
    }
}
