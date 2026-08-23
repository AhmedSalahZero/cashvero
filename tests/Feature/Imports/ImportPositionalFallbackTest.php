<?php

namespace Tests\Feature\Imports;

use App\Imports\ImportData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A column the uploaded file does not have must never be filled from
 * the column next to it.
 *
 * The real incident: a Supplier Invoices file for company 148 had no
 * "Supplier Code" and no "Discount Amount" column, so every model field
 * after them lined up one column short —
 *
 *     discount_amount  ← "Total Invoice Amount"    (90,000.00)
 *     withhold_amount  ← "Contracted Payment Days" (90)
 *
 * — and the BEFORE INSERT trigger, doing exactly what it was told,
 * turned 14 invoices worth 6,143,242.77 into a net of 0.00 and a
 * balance of −1,260.00.
 *
 * @see \App\Imports\ImportData::dataCustomizationImport()
 */
class ImportPositionalFallbackTest extends TestCase
{
    /**
     * The company's configured field order for SupplierInvoice, which
     * is what the positional fallback walks. Note supplier_code and
     * discount_amount — neither has a column in the file below.
     */
    private const MODEL_FIELDS = [
        'supplier_code' => 'Supplier Code',
        'supplier_name' => 'Supplier Name',
        'contract_name' => 'Contract Name',
        'contract_code' => 'Contract Code',
        'purchases_order_number' => 'Purchases Order Number',
        'purchases_order_date' => 'Purchases Order Date',
        'invoice_date' => 'Invoice Date',
        'invoice_number' => 'Invoice Number',
        'currency' => 'Currency',
        'exchange_rate' => 'Exchange Rate',
        'invoice_amount' => 'Invoice Amount',
        'discount_amount' => 'Discount Amount',
        'vat_amount' => 'VAT Amount',
        'withhold_amount' => 'Withhold Amount',
        'net_invoice_amount' => 'Net Invoice Amount',
        'contracted_payment_days' => 'Contracted Payment Days',
        'invoice_due_date' => 'Invoice Due Date',
    ];

    /** The real file's header row, in its real order. */
    private const FILE_HEADERS = [
        'Supplier Name', 'Contract Name', 'Contract Code',
        'Purchases Order Number', 'Purchases Order Date',
        'Invoice Date', 'Invoice Number', 'Currency', 'Exchange Rate',
        'Invoice Amount', 'VAT Amount', 'Total Invoice Amount',
        'Previous Payments', 'Contracted Payment Days', 'Invoice Due Date',
    ];

    /** The real ONTECH row from that file. */
    private const FILE_ROW = [
        'ONTECH', null, null, null, '2025-07-22',
        '2025-10-22', '4137', 'EGP', '1',
        '78947.37', '11052.6318', '90000.0018',
        '0', '90', '2026-01-20',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        // Only the columns the defaults are read from.
        Schema::dropIfExists('supplier_invoices');
        Schema::create('supplier_invoices', function ($table) {
            $table->bigIncrements('id');
            $table->string('supplier_code')->nullable();
            $table->string('supplier_name')->nullable();
            $table->decimal('invoice_amount', 18, 5)->nullable()->default(0);
            $table->decimal('vat_amount', 18, 5)->nullable()->default(0);
            $table->decimal('discount_amount', 18, 2)->nullable()->default(0.00);
            $table->decimal('withhold_amount', 18, 5)->nullable()->default(0);
            $table->decimal('net_invoice_amount', 18, 5)->nullable()->default(0);
            $table->integer('contracted_payment_days')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('supplier_invoices');

        parent::tearDown();
    }

    private function import(): ImportData
    {
        // The second argument is the DATE FORMAT the file uses, not the file type.
        return new ImportData(148, 'Y-m-d', 'SalesGatheringTest', self::MODEL_FIELDS, 1, 1, 'SupplierInvoice');
    }

    /** The row as Laravel Excel hands it over: headers as keys. */
    private function headedRow(): array
    {
        return array_combine(self::FILE_HEADERS, self::FILE_ROW);
    }

    private function map(array $row): array
    {
        return $this->import()->dataCustomizationImport($row, 2);
    }

    // ---------------------------------------------------------------
    // the incident
    // ---------------------------------------------------------------

    /**
     * The two columns that were mis-filled, named explicitly so a
     * failure says which one came back.
     *
     * @dataProvider misfilledColumnProvider
     */
    public function test_a_column_the_file_lacks_is_not_taken_from_its_neighbour(string $field, string $wrongValue): void
    {
        $data = $this->map($this->headedRow());

        $this->assertNotEquals($wrongValue, (string) $data[$field], sprintf(
            '%s was filled from the column next to it — that is the bug this test exists for.',
            $field
        ));
    }

    public static function misfilledColumnProvider(): array
    {
        return [
            'discount_amount took "Total Invoice Amount"' => ['discount_amount', '90000.0018'],
            'withhold_amount took "Contracted Payment Days"' => ['withhold_amount', '90'],
            'supplier_code took "Supplier Name"' => ['supplier_code', 'ONTECH'],
        ];
    }

    /**
     * ...and instead keeps whatever the table says it should be.
     */
    public function test_a_missing_column_falls_back_to_the_table_default(): void
    {
        $data = $this->map($this->headedRow());

        $this->assertSame('0.00', (string) $data['discount_amount'], 'the table default for discount_amount');
        $this->assertSame('0.00000', (string) $data['withhold_amount'], 'the table default for withhold_amount');
        $this->assertNull($data['supplier_code'], 'no default on that column, so it stays null');
    }

    /**
     * The columns that DO have a header must still land correctly —
     * the fix must not have thrown out the header matching with the
     * positional guessing.
     */
    public function test_every_column_the_file_does_have_is_read_correctly(): void
    {
        $data = $this->map($this->headedRow());

        $this->assertSame('ONTECH', $data['supplier_name']);
        $this->assertSame('4137', $data['invoice_number']);
        $this->assertSame('EGP', $data['currency']);
        $this->assertSame('1', $data['exchange_rate']);
        $this->assertSame('78947.37', $data['invoice_amount']);
        $this->assertSame('11052.6318', $data['vat_amount']);
        $this->assertSame('90', $data['contracted_payment_days']);
        $this->assertSame('2025-10-22', $data['invoice_date']);
        $this->assertSame('2026-01-20', $data['invoice_due_date']);
    }

    /**
     * The whole point of the incident: net = amount + vat − discount.
     * With discount defaulted rather than guessed, the trigger's own
     * arithmetic comes out right.
     */
    public function test_the_trigger_arithmetic_now_comes_out_right(): void
    {
        $data = $this->map($this->headedRow());

        $net = (float) $data['invoice_amount'] + (float) $data['vat_amount'] - (float) $data['discount_amount'];

        $this->assertEqualsWithDelta(90000.0018, $net, 0.0001,
            'This is the 0.0018 that 90,000.0018 was reduced to.');
    }

    // ---------------------------------------------------------------
    // a file with no headers still works by position
    // ---------------------------------------------------------------

    /**
     * Position is still the only thing a header-less file has, so that
     * path must keep working — the fix narrows the fallback, it does
     * not delete it.
     *
     * The values here are in the MODEL's field order, which is the only
     * order a header-less file can meaningfully be in.
     */
    public function test_a_file_without_headers_still_maps_by_position(): void
    {
        $inModelOrder = [
            'SUP-01',       // supplier_code
            'ONTECH',       // supplier_name
            null,           // contract_name
            null,           // contract_code
            null,           // purchases_order_number
            null,           // purchases_order_date
            '2025-10-22',   // invoice_date
            '4137',         // invoice_number
            'EGP',          // currency
            '1',            // exchange_rate
            '78947.37',     // invoice_amount
            null,           // discount_amount
            '11052.6318',   // vat_amount
        ];

        $row = [];
        foreach ($inModelOrder as $index => $value) {
            $row['col_'.$index] = $value;   // meaningless keys: no header matches
        }

        $data = $this->map($row);

        $this->assertSame('SUP-01', $data['supplier_code'], 'position 0, by position');
        $this->assertSame('ONTECH', $data['supplier_name'], 'position 1, by position');
        $this->assertSame('2025-10-22', $data['invoice_date'], 'position 6, by position');
        $this->assertSame('78947.37', $data['invoice_amount'], 'position 10, by position');
    }

    /**
     * One recognised header is enough to say the file is header-driven:
     * a file cannot be half named and half positional.
     */
    public function test_one_recognised_header_switches_the_whole_file_to_header_mode(): void
    {
        $row = [];
        foreach (array_values(self::FILE_ROW) as $index => $value) {
            $row['col_'.$index] = $value;
        }
        // Rename just one column to something the import knows.
        $row['Invoice Number'] = $row['col_6'];
        unset($row['col_6']);

        $data = $this->map($row);

        $this->assertSame('4137', $data['invoice_number'], 'matched by its header');
        $this->assertNull($data['supplier_code'], 'and nothing else is guessed by position any more');
    }

    /**
     * The header/positional decision is made from the row's KEYS, so a
     * data row that happens to be blank is still recognised as coming
     * from a file that has headers.
     *
     * Asserted on the decision itself: a blank row never reaches the
     * mapping — it is rejected by the "required column is empty"
     * validation first — so the mapped output cannot show this.
     */
    public function test_the_header_decision_comes_from_the_keys_not_the_values(): void
    {
        $import = $this->import();
        $method = new \ReflectionMethod($import, 'fileHasRecognisableHeaders');
        $method->setAccessible(true);

        $blankRowWithHeaders = array_fill_keys(self::FILE_HEADERS, null);

        $this->assertTrue($method->invoke($import, $blankRowWithHeaders),
            'A blank row from a headed file is still a headed file.');
    }

    public function test_a_row_with_meaningless_keys_is_recognised_as_headerless(): void
    {
        $import = $this->import();
        $method = new \ReflectionMethod($import, 'fileHasRecognisableHeaders');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($import, ['col_0' => 'ONTECH', 'col_1' => '4137']));
    }
}
