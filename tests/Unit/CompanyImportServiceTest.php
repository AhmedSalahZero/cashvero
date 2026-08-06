<?php

namespace Tests\Unit;

use App\Services\CompanyData\CompanyImportService;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Registry / ordering / remap behaviour for CompanyImportService.
 *
 * Mapping assertions are pure (reflection). Ordering + preflight hit
 * mysql_source / cash-vero when available and skip otherwise.
 */
class CompanyImportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mirror PaginationSmokeTest: phpunit.xml points at a missing test DB.
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cash-vero')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function service(): CompanyImportService
    {
        return new CompanyImportService();
    }

    private function const(string $name): mixed
    {
        return (new ReflectionClass(CompanyImportService::class))->getConstant($name);
    }

    private function invoke(object $obj, string $method, mixed ...$args): mixed
    {
        $m = new ReflectionMethod($obj, $method);
        $m->setAccessible(true);

        return $m->invoke($obj, ...$args);
    }

    private function setProp(object $obj, string $name, mixed $value): void
    {
        $p = (new ReflectionClass($obj))->getProperty($name);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    }

    private function getProp(object $obj, string $name): mixed
    {
        $p = (new ReflectionClass($obj))->getProperty($name);
        $p->setAccessible(true);

        return $p->getValue($obj);
    }

    /**
     * Seed a service whose only copied parent is money_received, with one row
     * mapped (5 -> 500). Everything the unresolved-FK path needs is cached so
     * the assertions stay pure.
     */
    private function serviceWithCopySet(array $nullableCache): CompanyImportService
    {
        $svc = $this->service();
        $this->setProp($svc, 'idMaps', ['money_received' => [5 => 500]]);
        $this->setProp($svc, 'fkCache', [
            'employee_statements' => [
                'money_received_id' => 'money_received',
                'drawee_bank_id' => 'banks',
            ],
        ]);
        $this->setProp($svc, 'copySet', ['money_received' => true]);
        $this->setProp($svc, 'nullableCache', $nullableCache);
        // 272 exists on source (another company's row), 999 does not exist at all.
        $this->setProp($svc, 'sourceIdCache', ['money_received' => ['272' => 0]]);

        return $svc;
    }

    public function test_invoice_id_maps_differ_by_table(): void
    {
        $byTable = $this->const('FK_COLUMN_MAP_BY_TABLE');

        $this->assertSame('customer_invoices', $byTable['settlements']['invoice_id']);
        $this->assertSame('supplier_invoices', $byTable['payment_settlements']['invoice_id']);
        $this->assertSame('supplier_invoices', $byTable['settlement_allocations']['invoice_id']);
        $this->assertArrayNotHasKey('po_allocations', $byTable);
    }

    public function test_partner_and_facility_mappings_are_registered(): void
    {
        $global = $this->const('FK_COLUMN_MAP');
        $byTable = $this->const('FK_COLUMN_MAP_BY_TABLE');

        $this->assertSame('partners', $global['customer_id']);
        $this->assertSame('partners', $global['supplier_id']);
        $this->assertSame('partners', $byTable['customer_invoices']['customer_id']);
        $this->assertSame('partners', $byTable['supplier_invoices']['supplier_id']);
        $this->assertSame('letter_of_guarantee_facilities', $global['lg_facility_id']);
        $this->assertSame('lg_issuance_advanced_payment_histories', $global['lg_advanced_payment_history_id']);
        $this->assertSame(
            'letter_of_guarantee_facilities',
            $byTable['letter_of_guarantee_statements']['lg_facility_id']
        );
    }

    public function test_polymorphic_and_external_id_registry(): void
    {
        $poly = $this->const('POLYMORPHIC_ACCOUNT_FK');
        $external = $this->const('EXTERNAL_ID_COLUMNS');
        $morph = $this->const('MORPH_TABLES');

        $this->assertArrayHasKey('cd_or_td_id', $poly);
        $this->assertArrayHasKey('cd_or_td_account_id', $poly);
        $this->assertArrayHasKey('cash_cover_deducted_from_account_id', $poly);
        $this->assertContains('project_account_id', $external);
        $this->assertContains('odoo_id', $external);
        $this->assertContains('journal_entry_id', $external);
        $this->assertSame('customer_invoices', $morph['CustomerInvoice']);
        $this->assertSame('supplier_invoices', $morph['SupplierInvoice']);
    }

    public function test_indirect_settlement_allocations_point_at_supplier_invoices(): void
    {
        $indirect = $this->const('INDIRECT');
        $this->assertSame(
            'invoice_id',
            $indirect['settlement_allocations']['parent_tables']['supplier_invoices']
        );
        $this->assertArrayNotHasKey('customer_invoices', $indirect['settlement_allocations']['parent_tables']);
        $this->assertArrayNotHasKey('supplier_invoices', $indirect['po_allocations']['parent_tables']);
    }

    public function test_global_invoice_id_is_not_in_ambiguous_global_map(): void
    {
        $global = $this->const('FK_COLUMN_MAP');
        $this->assertArrayNotHasKey('invoice_id', $global);
    }

    public function test_guess_referenced_table_uses_per_table_overrides(): void
    {
        if (! $this->databasesReachable()) {
            $this->markTestSkipped('mysql / mysql_source unreachable');
        }

        $svc = $this->service();
        $this->invoke($svc, 'primeKnownTables', [
            'settlements',
            'payment_settlements',
            'settlement_allocations',
            'customer_invoices',
            'supplier_invoices',
            'partners',
        ]);

        $this->assertSame(
            'customer_invoices',
            $this->invoke($svc, 'guessReferencedTable', 'invoice_id', 'settlements')
        );
        $this->assertSame(
            'supplier_invoices',
            $this->invoke($svc, 'guessReferencedTable', 'invoice_id', 'payment_settlements')
        );
        $this->assertSame(
            'partners',
            $this->invoke($svc, 'guessReferencedTable', 'customer_id', 'customer_invoices')
        );
        $this->assertSame(
            'partners',
            $this->invoke($svc, 'guessReferencedTable', 'supplier_id', 'supplier_invoices')
        );
    }

    public function test_import_order_places_parent_before_child_despite_alphabet_and_bootstrap(): void
    {
        if (! $this->databasesReachable()) {
            $this->markTestSkipped('mysql / mysql_source unreachable');
        }

        $svc = $this->service();
        $tables = [
            'incoming_transfers',
            'money_received',
            'contracts',
            'overdraft_against_assignment_of_contracts',
            'customer_invoices',
            'partners',
            'payment_settlements',
            'supplier_invoices',
            'settlements',
            'letter_of_guarantee_statements',
            'letter_of_guarantee_facilities',
        ];
        $this->invoke($svc, 'primeKnownTables', $tables);
        $ordered = $this->invoke($svc, 'orderedTables', $tables);
        $pos = array_flip($ordered);

        // PHPUnit assertLessThan($expected, $actual) means actual < expected.
        $this->assertLessThan($pos['incoming_transfers'], $pos['money_received']);
        $this->assertLessThan($pos['contracts'], $pos['overdraft_against_assignment_of_contracts']);
        $this->assertLessThan($pos['customer_invoices'], $pos['partners']);
        $this->assertLessThan($pos['settlements'], $pos['customer_invoices']);
        $this->assertLessThan($pos['payment_settlements'], $pos['supplier_invoices']);
        $this->assertLessThan($pos['letter_of_guarantee_statements'], $pos['letter_of_guarantee_facilities']);

        $violations = $this->invoke($svc, 'findOrderViolations', $ordered);
        $this->assertSame([], $violations);
    }

    public function test_remap_foreign_keys_rewrites_partner_and_invoice_ids(): void
    {
        $svc = $this->service();
        $maps = (new ReflectionClass($svc))->getProperty('idMaps');
        $maps->setAccessible(true);
        $maps->setValue($svc, [
            'partners' => [10 => 110],
            'customer_invoices' => [20 => 220],
            'supplier_invoices' => [30 => 330],
            'time_of_deposits' => [51 => 551],
        ]);

        // Bypass fkColumnsFor DB lookup by seeding fkCache for every table remapped below.
        $cache = (new ReflectionClass($svc))->getProperty('fkCache');
        $cache->setAccessible(true);
        $cache->setValue($svc, [
            'customer_invoices' => ['customer_id' => 'partners'],
            'payment_settlements' => ['invoice_id' => 'supplier_invoices'],
            'settlements' => ['invoice_id' => 'customer_invoices'],
            'invoice_deductions' => [],
            'letter_of_guarantee_statements' => [],
        ]);

        $ci = $this->invoke($svc, 'remapForeignKeys', 'customer_invoices', [
            'customer_id' => 10,
            'invoice_number' => 'X',
        ]);
        $this->assertSame(110, $ci['customer_id']);

        $ps = $this->invoke($svc, 'remapForeignKeys', 'payment_settlements', [
            'invoice_id' => 30,
        ]);
        $this->assertSame(330, $ps['invoice_id']);

        $st = $this->invoke($svc, 'remapForeignKeys', 'settlements', [
            'invoice_id' => 20,
        ]);
        $this->assertSame(220, $st['invoice_id']);

        $ded = $this->invoke($svc, 'remapForeignKeys', 'invoice_deductions', [
            'invoice_id' => 20,
            'invoice_type' => 'CustomerInvoice',
        ]);
        $this->assertSame(220, $ded['invoice_id']);

        $lg = $this->invoke($svc, 'remapForeignKeys', 'letter_of_guarantee_statements', [
            'cd_or_td_id' => 51,
        ]);
        $this->assertSame(551, $lg['cd_or_td_id']);
    }

    public function test_external_id_columns_are_not_treated_as_local_fks(): void
    {
        $svc = $this->service();
        $this->assertTrue($this->invoke($svc, 'isExternalIdColumn', 'project_account_id'));
        $this->assertTrue($this->invoke($svc, 'isExternalIdColumn', 'odoo_id'));
        $this->assertTrue($this->invoke($svc, 'isExternalIdColumn', 'journal_entry_id'));
        $this->assertFalse($this->invoke($svc, 'isExternalIdColumn', 'customer_id'));
        $this->assertFalse($this->invoke($svc, 'isExternalIdColumn', 'lg_facility_id'));
    }

    public function test_preflight_passes_for_company_92_when_source_available(): void
    {
        if (! $this->databasesReachable()) {
            $this->markTestSkipped('mysql / mysql_source unreachable');
        }

        $svc = $this->service();
        $analysis = $svc->analyze(92);
        if (! $analysis['source_company']) {
            $this->markTestSkipped('company 92 missing on source');
        }

        $preflight = $svc->preflight(92, $analysis['intersection']);
        $this->assertTrue($preflight['ok'], implode('; ', $preflight['errors']));
        $this->assertSame([], $preflight['order_violations']);
        $this->assertSame([], $preflight['unmapped_local_ids']);
    }

    public function test_foreign_key_outside_the_copied_company_is_blanked_not_copied_verbatim(): void
    {
        $svc = $this->serviceWithCopySet([
            'employee_statements' => ['money_received_id' => true],
        ]);

        $mapped = $this->invoke($svc, 'remapForeignKeys', 'employee_statements', ['money_received_id' => 5]);
        $this->assertSame(500, $mapped['money_received_id'], 'a copied parent must still remap');

        // 272 belongs to another company on source — keeping it would attach this
        // row to whatever happens to own id 272 on target.
        $foreign = $this->invoke($svc, 'remapForeignKeys', 'employee_statements', ['money_received_id' => 272]);
        $this->assertNull($foreign['money_received_id']);

        $recorded = $this->getProp($svc, 'unresolvedFks');
        $this->assertArrayHasKey('employee_statements.money_received_id', $recorded);
        $entry = $recorded['employee_statements.money_received_id'];
        $this->assertSame(1, $entry['nulled']);
        $this->assertSame(1, $entry['other_company_on_source']);
        $this->assertSame(0, $entry['dangling_on_source']);
    }

    public function test_unresolvable_foreign_key_is_kept_when_the_column_is_not_nullable(): void
    {
        $svc = $this->serviceWithCopySet([
            'employee_statements' => ['money_received_id' => false],
        ]);

        $row = $this->invoke($svc, 'remapForeignKeys', 'employee_statements', ['money_received_id' => 999]);
        $this->assertSame(999, $row['money_received_id']);

        $entry = $this->getProp($svc, 'unresolvedFks')['employee_statements.money_received_id'];
        $this->assertSame(1, $entry['kept']);
        $this->assertSame(0, $entry['nulled']);
        // 999 is absent from source too — already broken there, not a bad link we made.
        $this->assertSame(1, $entry['dangling_on_source']);
    }

    public function test_global_reference_ids_are_never_blanked(): void
    {
        $svc = $this->serviceWithCopySet([
            'employee_statements' => ['drawee_bank_id' => true],
        ]);

        // banks is outside the copy set: source and target share its ids.
        $row = $this->invoke($svc, 'remapForeignKeys', 'employee_statements', ['drawee_bank_id' => 7]);
        $this->assertSame(7, $row['drawee_bank_id']);
        $this->assertSame([], $this->getProp($svc, 'unresolvedFks'));
    }

    public function test_relink_pass_does_not_blank_ids_it_cannot_map(): void
    {
        $svc = $this->serviceWithCopySet([
            'employee_statements' => ['money_received_id' => true],
        ]);
        // While re-pointing target-only tables an unmapped id means "target-only
        // parent", which must survive untouched.
        $this->setProp($svc, 'nullUnresolvedFks', false);

        $row = $this->invoke($svc, 'remapForeignKeys', 'employee_statements', ['money_received_id' => 272]);
        $this->assertSame(272, $row['money_received_id']);
        $this->assertSame([], $this->getProp($svc, 'unresolvedFks'));
    }

    public function test_fingerprint_tracks_content_and_distinguishes_null_from_empty(): void
    {
        $svc = $this->service();
        $columns = ['amount', 'note'];

        $a = $this->invoke($svc, 'fingerprint', ['id' => 1, 'amount' => '10.00', 'note' => 'x'], $columns);
        $b = $this->invoke($svc, 'fingerprint', ['id' => 99, 'amount' => '10.00', 'note' => 'x'], $columns);
        $this->assertSame($a, $b, 'the primary key must not affect the fingerprint');

        $changed = $this->invoke($svc, 'fingerprint', ['amount' => '10.01', 'note' => 'x'], $columns);
        $this->assertNotSame($a, $changed);

        $null = $this->invoke($svc, 'fingerprint', ['amount' => null, 'note' => 'x'], $columns);
        $empty = $this->invoke($svc, 'fingerprint', ['amount' => '', 'note' => 'x'], $columns);
        $this->assertNotSame($null, $empty);
    }

    public function test_media_morph_registry_covers_users(): void
    {
        $morph = $this->const('MORPH_TABLES');

        $this->assertSame('users', $morph['App\\Models\\User']);
        $this->assertSame('users', $morph['User']);
        $this->assertSame('companies', $morph['App\\Models\\Company']);
    }

    private function databasesReachable(): bool
    {
        try {
            DB::connection('mysql')->getPdo();
            DB::connection('mysql_source')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
