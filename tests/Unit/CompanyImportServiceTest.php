<?php

namespace Tests\Unit;

use App\Enums\LgTypes;
use App\Services\CompanyData\CompanyImportService;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Registry / ordering / remap behaviour for CompanyImportService.
 *
 * Mapping assertions are pure (reflection). Ordering + preflight read the
 * schema of the test databases (cashvero_test as target, cashvero_test_source
 * as mysql_source); the preflight test plants its own fake source company in
 * cashvero_test_source, rolled back with the test (TestCase).
 */
class CompanyImportServiceTest extends TestCase
{
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
        $this->assertSame('partners', $global['shareholder_partner_id']);
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
        $this->assertTrue($this->databasesReachable(), 'mysql / mysql_source unreachable');

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
        $this->assertTrue($this->databasesReachable(), 'mysql / mysql_source unreachable');

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

    /**
     * The planted source company passes preflight: parents order before
     * children, and every local *_id it carries is one the importer maps.
     *
     * The source here has the target's full schema. The factoring module
     * (factoring_* tables) is left out of the copy, as on a source that
     * predates it: settlements.factoring_transaction_id and
     * factoring_transactions.settlement_id point at each other, a cycle
     * orderedTables() cannot break — reported as a bug, not covered here.
     */
    public function test_preflight_passes_for_a_planted_source_company(): void
    {
        $this->assertTrue($this->databasesReachable(), 'mysql / mysql_source unreachable');

        $companyId = $this->plantSourceCompany();

        $svc = $this->service();
        $analysis = $svc->analyze($companyId);
        $this->assertNotNull($analysis['source_company'], 'the planted company must be found on source');
        $this->assertSame(1, $analysis['source_counts']['customer_invoices']);
        $this->assertSame(3, $analysis['source_counts']['partners']);

        $preflight = $svc->preflight($companyId, $this->withoutFactoring($analysis['intersection']));
        $this->assertTrue($preflight['ok'], implode('; ', $preflight['errors']));
        $this->assertSame([], $preflight['order_violations']);
        $this->assertSame([], $preflight['unmapped_local_ids']);
    }

    /**
     * A source row carrying a local *_id the importer has no mapping for is
     * exactly what preflight exists to stop — copied verbatim it would point
     * at whatever owns that id on the target. Without this, the empty
     * unmapped_local_ids above could just mean "never looked".
     */
    public function test_preflight_refuses_an_unmapped_local_id_column_with_data(): void
    {
        $companyId = $this->plantSourceCompany();

        $svc = $this->service();
        $analysis = $svc->analyze($companyId);
        $tables = $this->withoutFactoring($analysis['intersection']);
        $this->invoke($svc, 'primeKnownTables', $tables);

        $this->assertArrayNotHasKey('terms_history_id', $this->invoke($svc, 'fkColumnsFor', 'lending_information'),
            'lending_information.terms_history_id is mapped now — pick another unmapped local *_id column for this probe.');

        DB::connection('mysql_source')->table('lending_information')->insert([
            'company_id' => $companyId,
            'terms_history_id' => 1,
        ]);

        $preflight = $svc->preflight($companyId, $tables);

        $this->assertFalse($preflight['ok']);
        $this->assertContains(
            ['table' => 'lending_information', 'column' => 'terms_history_id', 'rows' => 1],
            $preflight['unmapped_local_ids']
        );
    }

    /** @param  array<int, string>  $tables */
    private function withoutFactoring(array $tables): array
    {
        return array_values(array_filter($tables, fn (string $table) => ! str_starts_with($table, 'factoring_')));
    }

    /**
     * A small fake company on the source side (cashvero_test_source), shaped like a real one:
     * partners, a contract, an invoice of each kind, a receipt with its settlement. The global
     * reference tables both databases must share by id (account_types, banks) are copied over
     * from the target, as on a real source.
     */
    private function plantSourceCompany(): int
    {
        $source = DB::connection('mysql_source');

        foreach (['account_types', 'banks'] as $global) {
            foreach (DB::table($global)->orderBy('id')->get() as $row) {
                $source->table($global)->insert((array) $row);
            }
        }

        $name = 'Source Co '.uniqid();
        $companyId = (int) $source->table('companies')->insertGetId([
            'name' => json_encode(['en' => $name, 'ar' => $name]),
            'sub_of' => '0',
            'is_caching_now' => 0,
            'main_functional_currency' => 'EGP',
        ]);

        $partner = fn (string $partnerName, array $flags) => (int) $source->table('partners')->insertGetId(array_merge([
            'company_id' => $companyId,
            'name' => $partnerName,
        ], $flags));

        $customerId = $partner('Source Customer', ['is_customer' => 1]);
        $supplierId = $partner('Source Supplier', ['is_supplier' => 1]);
        $partner('Source Employee', ['is_employee' => 1]);

        $contractId = (int) $source->table('contracts')->insertGetId([
            'company_id' => $companyId,
            'partner_id' => $customerId,
            'name' => 'Source Contract',
            'model_type' => 'Customer',
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'amount' => 5000,
        ]);

        $invoiceId = (int) $source->table('customer_invoices')->insertGetId([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'customer_name' => 'Source Customer',
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'invoice_amount' => 1000,
            'invoice_number' => 'INV-SRC-1',
            'invoice_date' => '2026-01-10',
            'invoice_due_date' => '2026-02-10',
        ]);
        $source->table('supplier_invoices')->insert([
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'supplier_name' => 'Source Supplier',
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'invoice_amount' => 400,
            'invoice_number' => 'BILL-SRC-1',
            'invoice_date' => '2026-01-12',
            'invoice_due_date' => '2026-02-12',
        ]);

        $receivedId = (int) $source->table('money_received')->insertGetId([
            'company_id' => $companyId,
            'partner_id' => $customerId,
            'contract_id' => $contractId,
            'type' => 'cash-in-safe',
            'money_type' => 'invoice-settlement',
            'receiving_date' => '2026-01-20',
            'received_amount' => 600,
            'currency' => 'EGP',
            'receiving_currency' => 'EGP',
        ]);
        $source->table('settlements')->insert([
            'company_id' => $companyId,
            'invoice_id' => $invoiceId,
            'money_received_id' => $receivedId,
            'settlement_amount' => 600,
            'withhold_amount' => 0,
        ]);

        return $companyId;
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

    /**
     * A raw company copy never runs the issuance model's saving hook.
     * Legacy rows have no activation_date column at all.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('importedLgIssuanceProvider')]
    public function test_an_imported_lg_issuance_gets_a_consistent_activation_state(array $row, ?string $activationDate, int $isActivated): void
    {
        $payload = $this->invoke($this->service(), 'normalizeImportedLgIssuance', $row);

        $this->assertSame($activationDate, $payload['activation_date']);
        $this->assertSame($isActivated, $payload['is_activated']);
    }

    public static function importedLgIssuanceProvider(): array
    {
        return [
            'a legacy advance payment row has no activation column' => [
                ['lg_type' => LgTypes::ADVANCED_PAYMENT_LGS, 'issuance_date' => '2026-01-10'],
                '2026-01-10',
                1,
            ],
            'an advance payment explicitly left unactivated stays that way' => [
                ['lg_type' => LgTypes::ADVANCED_PAYMENT_LGS, 'issuance_date' => '2026-01-10', 'activation_date' => null],
                null,
                0,
            ],
            'an advance payment that already has an activation date keeps it' => [
                ['lg_type' => LgTypes::ADVANCED_PAYMENT_LGS, 'issuance_date' => '2026-01-10', 'activation_date' => '2026-04-10'],
                '2026-04-10',
                1,
            ],
            'any other type drops a stray activation date' => [
                ['lg_type' => LgTypes::FINAL_LGS, 'issuance_date' => '2026-01-10', 'activation_date' => '2026-04-10', 'is_activated' => 0],
                null,
                1,
            ],
        ];
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
