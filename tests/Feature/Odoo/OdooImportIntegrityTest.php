<?php

namespace Tests\Feature\Odoo;

use App\Models\Company;
use App\Models\Contract;
use App\Models\CustomerInvoice;
use App\Models\SupplierInvoice;
use App\Services\Api\OdooService;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

require_once __DIR__.'/../../../public/apis/ripcord.php';
require_once __DIR__.'/../../../public/apis/ripcord_client.php';

/**
 * * سلامة الداتا بعد تسريع الاستيراد
 *
 * * التستات اللي فوق (OdooImportBatchingTest) بتتأكد من شكل النداءات.
 * * دي بتتأكد من النتيجة على الداتابيز نفسها: إن الاستيراد بيكتب نفس
 * * اللي كان بيكتبه ، و إن مفيش حاجة بتتمسح غلط.
 *
 * * كل الداتا بتتعمل جوه transaction و بتترجع في tearDown.
 */
class OdooImportIntegrityTest extends TestCase
{
    private ?string $originalDatabase = null;

    private bool $inTransaction = false;

    private int $companyId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
            DB::table('contracts')->limit(1)->get();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }

        DB::beginTransaction();
        $this->inTransaction = true;

        $this->companyId = (int) DB::table('companies')->insertGetId([
            'name' => json_encode(['en' => 'Odoo Import Test Co', 'ar' => 'Odoo Import Test Co']),
            'main_functional_currency' => 'EGP',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->inTransaction) {
            DB::rollBack();
            $this->inTransaction = false;
        }

        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    /* ───────────────────── أدوات ───────────────────── */

    /**
     * * أودو مزيّف كامل: بيغطي المسارين اللي الكود بيكلم بيهم أودو —
     * * $this->models->execute_kw() و readFromOdoo()/execute()
     */
    private function service(array $responses): OdooService
    {
        $client = new FakeRipcordClient;
        $client->responses = $responses;

        $service = new class($responses) extends OdooService
        {
            public FakeRipcordClient $fakeClient;

            public function __construct(private array $responses) {}

            public function boot(FakeRipcordClient $client): self
            {
                $this->fakeClient = $client;
                $this->models = $client;
                $this->db = 'test-db';
                $this->uid = 1;
                $this->password = 'secret';

                return $this;
            }

            public function execute($model, $method, $args, $kwargs = [])
            {
                return $this->fakeClient->reply($model, $method, $args, $kwargs);
            }

            protected function readFromOdoo(string $model, string $method, array $args, array $kwargs = [])
            {
                return $this->fakeClient->reply($model, $method, $args, $kwargs);
            }
        };

        return $service->boot($client);
    }

    private function invokeOn(OdooService $service, string $method, array $args)
    {
        $reflection = new ReflectionMethod(OdooService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($service, $args);
    }

    private function invoice(string $class, int $odooId, string $invoiceDate, string $number): int
    {
        $table = $class === CustomerInvoice::class ? 'customer_invoices' : 'supplier_invoices';
        $partnerColumn = $class === CustomerInvoice::class ? 'customer_id' : 'supplier_id';

        return (int) DB::table($table)->insertGetId([
            'company_id' => $this->companyId,
            $partnerColumn => 1,
            'currency' => 'EGP',
            'odoo_id' => $odooId,
            'invoice_number' => $number,
            'invoice_amount' => 1000,
            'invoice_date' => $invoiceDate,
            'invoice_due_date' => $invoiceDate,
        ]);
    }

    /**
     * * أودو مزيّف بيحترم الدومين: بياخد الأرقام اللي احنا بعتناها و
     * * بيرجّع اللي لسه موجود عنده منها بس — زي أودو الحقيقي بالظبط
     *
     * @param  array<int, int>  $odooStillHas
     */
    private function runDeletedSync(array $odooStillHas, string $endDate = '2026-09-17'): OdooService
    {
        $service = $this->service([
            'account.move.search' => static function (array $args) use ($odooStillHas): array {
                $requestedIds = [];

                foreach ($args[0] as $condition) {
                    if (is_array($condition) && ($condition[0] ?? null) === 'id') {
                        $requestedIds = $condition[2];
                    }
                }

                return array_values(array_intersect($requestedIds, $odooStillHas));
            },
            /**
             * * المسار القديم كان بيعمل search بمدى تواريخ و بعدين read
             * * بالأرقام الراجعة. بنسيبه متعرّف هنا عشان نفس التست يقدر
             * * يتشغّل على الكودين و يبيّن الفرق
             */
            'account.move.read' => static fn (array $args): array => array_map(
                static fn ($id): array => ['id' => $id],
                $args[0]
            ),
        ]);

        $this->invokeOn($service, 'syncDeletedInvoices', [$this->companyId, $endDate]);

        return $service;
    }

    /** @return array<int, array> الدومين بتاع كل نداء بحث اتعمل */
    private function searchDomains(OdooService $service): array
    {
        return array_map(
            static fn (array $call): array => $call[2][0],
            $service->fakeClient->callsFor('account.move', 'search')
        );
    }

    /** @return array<int, int> */
    private function remainingOdooIds(string $table): array
    {
        return DB::table($table)->where('company_id', $this->companyId)->orderBy('odoo_id')->pluck('odoo_id')->map('intval')->all();
    }

    /* ─────────── مزامنة الفواتير المحذوفة ─────────── */

    public function test_only_the_invoices_missing_from_odoo_are_deleted(): void
    {
        $this->invoice(CustomerInvoice::class, 501, '2026-01-10', 'INV/501');
        $this->invoice(CustomerInvoice::class, 502, '2026-01-11', 'INV/502');
        $this->invoice(SupplierInvoice::class, 601, '2026-01-12', 'BILL/601');
        $this->invoice(SupplierInvoice::class, 602, '2026-01-13', 'BILL/602');

        // أودو لسه عنده ٥٠١ و ٦٠٢ بس
        $this->runDeletedSync([501, 602]);

        $this->assertSame([501], $this->remainingOdooIds('customer_invoices'));
        $this->assertSame([602], $this->remainingOdooIds('supplier_invoices'));
    }

    public function test_nothing_is_deleted_when_odoo_still_has_everything(): void
    {
        $this->invoice(CustomerInvoice::class, 501, '2026-01-10', 'INV/501');
        $this->invoice(SupplierInvoice::class, 601, '2026-01-12', 'BILL/601');

        $this->runDeletedSync([501, 601]);

        $this->assertSame([501], $this->remainingOdooIds('customer_invoices'));
        $this->assertSame([601], $this->remainingOdooIds('supplier_invoices'));
    }

    /**
     * * أهم تست في الملف: لو القراءة من أودو فشلت ، الاستيراد ما يمسحش
     * * ولا فاتورة. رد ناقص بيبان زي "الفواتير دي اتمسحت من أودو"
     */
    public function test_a_fault_from_odoo_deletes_absolutely_nothing(): void
    {
        $this->invoice(CustomerInvoice::class, 501, '2026-01-10', 'INV/501');
        $this->invoice(CustomerInvoice::class, 502, '2026-01-11', 'INV/502');
        $this->invoice(SupplierInvoice::class, 601, '2026-01-12', 'BILL/601');

        $service = $this->service([
            'account.move.search' => ['faultCode' => 1, 'faultString' => 'Odoo exploded'],
        ]);

        $this->invokeOn($service, 'syncDeletedInvoices', [$this->companyId, '2026-09-17']);

        $this->assertSame([501, 502], $this->remainingOdooIds('customer_invoices'),
            'رد الـ fault كان array بردو ، و array_column عليه بترجّع [] — فكل فاتورة كانت بتبان محذوفة');
        $this->assertSame([601], $this->remainingOdooIds('supplier_invoices'));
    }

    /**
     * * صمام الأمان: أودو قال إن ولا رقم من بتوعنا موجود. ده على
     * * الأرجح قاعدة غلط أو صلاحيات ناقصة ، مش إن الشركة مسحت كل
     * * فواتيرها — فبنوقف بدل ما نمسح كل حاجة
     */
    /**
     * * الباج اللي اتبلّغ بالظبط: أودو بيرجّع array فيها faultCode بدل
     * * ما يرمي استثناء ، و هي array بردو — فالكود القديم كان بيعدّيها
     * * على إنها داتا ، و array_column عليها بترجّع [] ، فكل فاتورة
     * * محلية كانت بتبان كأنها "اتمسحت من أودو" و تتمسح فعلا.
     *
     * * الـ fault هنا بيترد على أي نداء ، فالتست بيشتغل على أي تنفيذ
     * * للدالة مهما كان بينده على إيه.
     */
    public function test_an_odoo_fault_never_wipes_the_invoice_table(): void
    {
        $fault = ['faultCode' => 1, 'faultString' => 'Odoo exploded'];

        $this->invoice(CustomerInvoice::class, 501, '2026-01-10', 'INV/501');
        $this->invoice(CustomerInvoice::class, 502, '2026-01-11', 'INV/502');
        $this->invoice(SupplierInvoice::class, 601, '2026-01-12', 'BILL/601');
        $this->invoice(SupplierInvoice::class, 602, '2026-01-13', 'BILL/602');

        $service = $this->service([
            'account.move.search' => $fault,
            'account.move.read' => $fault,
        ]);

        $this->invokeOn($service, 'syncDeletedInvoices', [$this->companyId, '2026-09-17']);

        $this->assertSame([501, 502], $this->remainingOdooIds('customer_invoices'),
            'رد غلط من أودو ما ينفعش يتقرا على إنه "الفواتير دي اتمسحت"');
        $this->assertSame([601, 602], $this->remainingOdooIds('supplier_invoices'));
    }

    public function test_odoo_saying_none_of_our_invoices_exist_deletes_nothing(): void
    {
        $this->invoice(CustomerInvoice::class, 501, '2026-01-10', 'INV/501');
        $this->invoice(CustomerInvoice::class, 502, '2026-01-11', 'INV/502');
        $this->invoice(SupplierInvoice::class, 601, '2026-01-12', 'BILL/601');

        $this->runDeletedSync([]);

        $this->assertSame([501, 502], $this->remainingOdooIds('customer_invoices'));
        $this->assertSame([601], $this->remainingOdooIds('supplier_invoices'));
    }

    public function test_a_failed_chunk_never_looks_like_a_deletion(): void
    {
        foreach (range(1, 1500) as $offset) {
            $this->invoice(CustomerInvoice::class, 1000 + $offset, '2026-01-10', 'INV/'.(1000 + $offset));
        }

        $service = $this->service([
            // الدفعة التانية بتفشل ، فالأولى لوحدها كانت هتبان كأن الباقي اتمسح
            'account.move.search' => static function (array $args) {
                $requestedIds = [];

                foreach ($args[0] as $condition) {
                    if (is_array($condition) && ($condition[0] ?? null) === 'id') {
                        $requestedIds = $condition[2];
                    }
                }

                return ($requestedIds[0] ?? 0) > 2000
                    ? ['faultCode' => 1, 'faultString' => 'boom']
                    : $requestedIds;
            },
        ]);

        $this->invokeOn($service, 'syncDeletedInvoices', [$this->companyId, '2026-09-17']);

        $this->assertCount(1500, $this->remainingOdooIds('customer_invoices'), 'مفيش ولا فاتورة اتمسحت');
    }

    public function test_the_existence_check_is_asked_in_chunks(): void
    {
        foreach (range(1, 2500) as $offset) {
            $this->invoice(CustomerInvoice::class, 1000 + $offset, '2026-01-10', 'INV/'.(1000 + $offset));
        }

        $service = $this->runDeletedSync(range(1001, 3500));

        $chunkSizes = array_map(
            static function (array $domain): int {
                foreach ($domain as $condition) {
                    if (($condition[0] ?? null) === 'id') {
                        return count($condition[2]);
                    }
                }

                return 0;
            },
            $this->searchDomains($service)
        );

        $this->assertSame([1000, 1000, 500], $chunkSizes);
        $this->assertCount(2500, $this->remainingOdooIds('customer_invoices'), 'كلها لسه موجودة في أودو');
    }

    /**
     * * الفحص القديم كان بيسأل أودو عن مدى بالـ write_date بينما
     * * الفواتير عندنا متفلترة بالـ invoice_date — عمودين مختلفين.
     * * فاتورة اتعدّلت في أودو بعد نهاية المدى كانت بتختفي من الرد و
     * * تتمسح عندنا و هي موجودة. دلوقتي السؤال بالأرقام مالوش أي تواريخ.
     */
    public function test_the_existence_check_never_filters_by_date(): void
    {
        $this->invoice(CustomerInvoice::class, 501, '2026-01-10', 'INV/501');

        $service = $this->runDeletedSync([501]);

        $domains = $this->searchDomains($service);
        $this->assertNotEmpty($domains);

        foreach ($domains as $domain) {
            $fields = array_map(static fn (array $condition): string => (string) $condition[0], $domain);

            $this->assertSame(['id', 'move_type', 'state'], $fields,
                'مفيش write_date ولا invoice_date في الدومين خالص');
        }
    }

    public function test_an_invoice_modified_after_the_window_is_not_treated_as_deleted(): void
    {
        // الفاتورة دي تاريخها جوه النافذة بس اتعدّلت في أودو النهاردة ،
        // فالفلتر القديم (write_date <= نهاية المدى) كان بيستبعدها
        $this->invoice(CustomerInvoice::class, 501, '2026-01-10', 'INV/501');
        $this->invoice(CustomerInvoice::class, 502, '2026-01-11', 'INV/502');

        $this->runDeletedSync([501, 502]);

        $this->assertSame([501, 502], $this->remainingOdooIds('customer_invoices'));
    }

    public function test_invoices_outside_the_window_are_never_touched(): void
    {
        // خارج نافذة الـ ٤٥٠ يوم اللي بتنتهي في 2026-09-17
        $this->invoice(CustomerInvoice::class, 401, '2024-01-01', 'INV/401');
        $this->invoice(CustomerInvoice::class, 501, '2026-01-10', 'INV/501');
        $this->invoice(CustomerInvoice::class, 502, '2026-01-11', 'INV/502');

        // أودو لسه عنده ٥٠٢ بس — ٥٠١ اتمسحت فعلا
        $service = $this->runDeletedSync([502]);

        $this->assertSame([401, 502], $this->remainingOdooIds('customer_invoices'),
            'القديمة برة النافذة فضلت زي ما هي ، و المحذوفة جوه النافذة راحت');

        foreach ($this->searchDomains($service) as $domain) {
            $askedIds = [];

            foreach ($domain as $condition) {
                if (($condition[0] ?? null) === 'id') {
                    $askedIds = $condition[2];
                }
            }

            $this->assertNotContains(401, $askedIds, 'الفواتير برة النافذة ما بتتسألش عنها أصلا');
        }
    }

    public function test_an_invoice_with_a_settlement_is_kept_and_the_rest_still_run(): void
    {
        $settledId = $this->invoice(CustomerInvoice::class, 501, '2026-01-10', 'INV/501');
        $this->invoice(CustomerInvoice::class, 502, '2026-01-11', 'INV/502');
        $this->invoice(CustomerInvoice::class, 503, '2026-01-12', 'INV/503');

        DB::table('settlements')->insert([
            'invoice_id' => $settledId,
            'company_id' => $this->companyId,
            'settlement_amount' => 250,
            'withhold_amount' => 0,
        ]);

        // أودو لسه عنده ٥٠٣ ، فصمام الأمان ما بيتفعّلش
        $this->runDeletedSync([503]);

        $this->assertSame([501, 503], $this->remainingOdooIds('customer_invoices'),
            'الفاتورة المسوّاة بتفضل ، و اللي من غير تسويات بتتمسح');
    }

    public function test_an_odoo_id_stored_as_text_still_matches(): void
    {
        $this->invoice(CustomerInvoice::class, 501, '2026-01-10', 'INV/501');
        DB::table('customer_invoices')->where('odoo_id', 501)->update(['odoo_id' => '501']);

        $this->runDeletedSync([501]);

        $this->assertSame([501], $this->remainingOdooIds('customer_invoices'),
            'المقارنة بالهاش بتقارن أرقام صحيحة على الجهتين');
    }

    /* ─────────── الاستيراد الكامل للفواتير ─────────── */

    /**
     * @return array<string, mixed>
     */
    private function invoiceImportResponses(int $invoiceCount): array
    {
        $rows = [];

        for ($index = 0; $index < $invoiceCount; $index++) {
            $odooId = 7000 + $index;
            $isCustomer = $index % 2 === 0;

            $rows[] = [
                'id' => $odooId,
                'name' => ($isCustomer ? 'INV/' : 'BILL/').$odooId,
                'move_type' => $isCustomer ? 'out_invoice' : 'in_invoice',
                'invoice_date' => '2026-03-01',
                'invoice_date_due' => '2026-04-01',
                'invoice_origin' => false,
                'invoice_currency_rate' => 1.0,
                'amount_untaxed_in_currency_signed' => 1000 + $index,
                'partner_id' => [8001, 'Imported Partner'],
                'currency_id' => [1, 'EGP'],
                'tax_totals' => ['subtotals' => [
                    ['name' => 'Untaxed Amount', 'tax_amount_currency' => 140, 'tax_amount' => 140],
                    ['name' => 'Subtotal W/O WHTax', 'tax_amount_currency' => -50, 'tax_amount' => -50],
                ]],
                'invoice_payments_widget' => ['content' => [
                    ['ref' => 'BNK1/2026/0001', 'amount' => 200, 'amount_company_currency' => '200'],
                    ['ref' => 'EXCH/2026/0001', 'amount' => 999, 'amount_company_currency' => '999'],
                ]],
            ];
        }

        return [
            'res.partner.search' => [8000, 8001],
            'res.partner.read' => [
                ['id' => 8000, 'name' => 'Odoo Admin', 'customer_rank' => 0, 'supplier_rank' => 0, 'employee_ids' => []],
                ['id' => 8001, 'name' => 'Imported Partner', 'customer_rank' => 1, 'supplier_rank' => 1, 'employee_ids' => []],
            ],
            'account.move.search' => array_column($rows, 'id'),
            'account.move.read' => static function (array $args) use ($rows): array {
                $wanted = array_flip($args[0]);

                return array_values(array_filter($rows, static fn (array $row): bool => isset($wanted[$row['id']])));
            },
        ];
    }

    public function test_a_full_invoice_import_writes_every_invoice_once(): void
    {
        $service = $this->service($this->invoiceImportResponses(450));
        $service->startImportInvoices('2026-01-01', '2026-09-17', $this->companyId);

        $customers = DB::table('customer_invoices')->where('company_id', $this->companyId)->count();
        $suppliers = DB::table('supplier_invoices')->where('company_id', $this->companyId)->count();

        $this->assertSame(225, $customers, 'نصف الفواتير عملاء');
        $this->assertSame(225, $suppliers, 'و النص التاني موردين');
        $this->assertSame(450, $customers + $suppliers, 'مفيش فاتورة ضاعت بين الدفعات');
    }

    public function test_the_imported_amounts_survive_the_batching_untouched(): void
    {
        $service = $this->service($this->invoiceImportResponses(2));
        $service->startImportInvoices('2026-01-01', '2026-09-17', $this->companyId);

        $invoice = DB::table('customer_invoices')->where('company_id', $this->companyId)->where('odoo_id', 7000)->first();

        $this->assertNotNull($invoice);
        $this->assertSame('INV/7000', $invoice->invoice_number);
        $this->assertSame(1000.0, (float) $invoice->invoice_amount);
        $this->assertSame(140.0, (float) $invoice->vat_amount, 'الـ VAT من tax_totals');
        $this->assertSame(50.0, (float) $invoice->odoo_withhold_amount, 'الخصم تحت حساب الضريبة');
        $this->assertSame(200.0, (float) $invoice->odoo_collected_amount, 'فرق العملة (EXCH/) بيتستبعد');
        $this->assertSame('2026-03-01', $invoice->invoice_date);
        $this->assertSame('2026-04-01', $invoice->invoice_due_date);
    }

    public function test_importing_the_same_invoices_twice_updates_instead_of_duplicating(): void
    {
        $responses = $this->invoiceImportResponses(10);

        $this->service($responses)->startImportInvoices('2026-01-01', '2026-09-17', $this->companyId);
        $firstIds = DB::table('customer_invoices')->where('company_id', $this->companyId)->orderBy('odoo_id')->pluck('id')->all();

        $this->service($responses)->startImportInvoices('2026-01-01', '2026-09-17', $this->companyId);
        $secondIds = DB::table('customer_invoices')->where('company_id', $this->companyId)->orderBy('odoo_id')->pluck('id')->all();

        $this->assertSame($firstIds, $secondIds, 'نفس الصفوف اتحدّثت ، ما اتعملش صفوف جديدة');
    }

    /**
     * * الاستيراد بيقرا على دفعات ، و بعدين syncDeletedInvoices بتقرا
     * * الأرقام تاني — لازم الاتنين يشوفوا نفس الفواتير ، و إلا الفواتير
     * * اللي في الدفعات الأخيرة هتتكتب و تتمسح في نفس الرن
     */
    public function test_the_import_does_not_delete_what_it_just_wrote(): void
    {
        $service = $this->service($this->invoiceImportResponses(450));
        $service->startImportInvoices('2026-01-01', '2026-09-17', $this->companyId);

        $this->assertSame(450, DB::table('customer_invoices')->where('company_id', $this->companyId)->count()
            + DB::table('supplier_invoices')->where('company_id', $this->companyId)->count());
    }

    /* ─────────── استيراد العقود بعد التجميع ─────────── */

    /**
     * @return array<string, mixed>
     */
    private function contractResponses(): array
    {
        return [
            'project.project.search' => [1, 2],
            'project.project.read' => [
                ['id' => 1, 'name' => 'Alpha Project', 'account_id' => [71, 'AA71'], 'partner_id' => [9001, 'Customer Alpha'], 'date_start' => '2026-01-01', 'date' => '2026-06-30'],
                ['id' => 2, 'name' => 'Beta Project', 'account_id' => [72, 'AA72'], 'partner_id' => [9002, 'Customer Beta'], 'date_start' => '2026-02-01', 'date' => '2026-07-31'],
            ],
            'res.currency.read' => [['id' => 1, 'rate' => 1.0]],
            'sale.order.search_read' => [
                ['id' => 101, 'name' => 'S00001', 'display_name' => 'S00001', 'currency_id' => [1, 'EGP'], 'amount_total' => 1000, 'project_id' => [1, 'Alpha Project']],
                ['id' => 102, 'name' => 'S00002', 'display_name' => 'S00002', 'currency_id' => [1, 'EGP'], 'amount_total' => 2000, 'project_id' => [2, 'Beta Project']],
            ],
            'purchase.order.line.search_read' => static function (array $args) {
                if ($args[0][0][0] === 'sale_order_id') {
                    return [
                        ['id' => 1, 'order_id' => [901, 'P00001'], 'sale_order_id' => [101, 'S00001']],
                        ['id' => 2, 'order_id' => [902, 'P00002'], 'sale_order_id' => [102, 'S00002']],
                    ];
                }

                return [
                    ['id' => 3, 'order_id' => [903, 'P00003'], 'analytic_distribution' => ['72' => 100.0]],
                ];
            },
            'purchase.order.search_read' => [],
            'purchase.order.read' => static fn (array $args): array => array_values(array_filter([
                ['id' => 901, 'name' => 'P00001', 'partner_id' => [9101, 'Vendor One'], 'currency_id' => [1, 'EGP'], 'amount_total' => 400, 'date_order' => '2026-02-02 10:00:00', 'state' => 'purchase', 'origin' => 'S00001', 'invoice_ids' => []],
                ['id' => 902, 'name' => 'P00002', 'partner_id' => [9102, 'Vendor Two'], 'currency_id' => [1, 'EGP'], 'amount_total' => 500, 'date_order' => '2026-02-03 10:00:00', 'state' => 'purchase', 'origin' => 'S00002', 'invoice_ids' => []],
                ['id' => 903, 'name' => 'P00003', 'partner_id' => [9103, 'Vendor Three'], 'currency_id' => [1, 'EGP'], 'amount_total' => 600, 'date_order' => '2026-02-04 10:00:00', 'state' => 'purchase', 'origin' => false, 'invoice_ids' => []],
            ], static fn (array $row): bool => in_array($row['id'], $args[0], true))),
        ];
    }

    private function importContracts(): OdooService
    {
        $service = $this->service($this->contractResponses());
        $service->getContracts('2026-01-01', '2026-12-31', $this->companyId);

        return $service;
    }

    public function test_each_project_becomes_its_own_customer_contract(): void
    {
        $this->importContracts();

        $contracts = Contract::where('company_id', $this->companyId)
            ->where('model_type', 'Customer')
            ->orderBy('odoo_id')
            ->get();

        $this->assertSame(['Alpha Project', 'Beta Project'], $contracts->pluck('name')->all());
        $this->assertSame([1000.0, 2000.0], $contracts->pluck('amount')->map('floatval')->all());
        $this->assertSame(['EGP', 'EGP'], $contracts->pluck('currency')->all());
    }

    public function test_each_contract_keeps_only_its_own_sales_order(): void
    {
        $this->importContracts();

        $contracts = Contract::where('company_id', $this->companyId)
            ->where('model_type', 'Customer')
            ->orderBy('odoo_id')
            ->get();

        $this->assertSame(['S00001'], $contracts[0]->salesOrders->pluck('so_number')->all());
        $this->assertSame(['S00002'], $contracts[1]->salesOrders->pluck('so_number')->all());
    }

    /**
     * * ده اللي كان ممكن يتلف لما البحث بقى مجمّع لكل المشاريع مرة
     * * واحدة: أمر شراء يروح لمشروع غير بتاعه
     */
    public function test_supplier_contracts_land_under_the_right_customer_contract(): void
    {
        $this->importContracts();

        $contracts = Contract::where('company_id', $this->companyId)
            ->where('model_type', 'Customer')
            ->orderBy('odoo_id')
            ->get();

        $alphaPurchaseOrders = Contract::where('parent_id', $contracts[0]->id)
            ->get()
            ->flatMap(fn (Contract $contract) => $contract->purchasesOrders->pluck('po_number'))
            ->sort()->values()->all();

        $betaPurchaseOrders = Contract::where('parent_id', $contracts[1]->id)
            ->get()
            ->flatMap(fn (Contract $contract) => $contract->purchasesOrders->pluck('po_number'))
            ->sort()->values()->all();

        $this->assertSame(['P00001'], $alphaPurchaseOrders, 'ألفا ليها أمر شراء واحد من أمر البيع بتاعها');
        $this->assertSame(['P00002', 'P00003'], $betaPurchaseOrders, 'بيتا ليها واحد من أمر البيع و واحد من الحساب التحليلي');
    }

    public function test_the_supplier_contracts_carry_the_right_vendor_and_amount(): void
    {
        $this->importContracts();

        $alpha = Contract::where('company_id', $this->companyId)->where('odoo_id', 1)->where('model_type', 'Customer')->first();

        $supplierContract = Contract::where('parent_id', $alpha->id)->first();

        $this->assertNotNull($supplierContract);
        $this->assertSame('Vendor One', $supplierContract->client->name);
        $this->assertSame(400.0, (float) $supplierContract->amount);
        $this->assertSame('Alpha Project', $supplierContract->name, 'اسم عقد المورّد = اسم المشروع');
    }

    public function test_running_the_import_twice_does_not_duplicate_anything(): void
    {
        $this->importContracts();
        $firstCount = Contract::where('company_id', $this->companyId)->count();
        $firstCodes = Contract::where('company_id', $this->companyId)->orderBy('id')->pluck('code')->all();

        $this->importContracts();

        $this->assertSame($firstCount, Contract::where('company_id', $this->companyId)->count(),
            'المزامنة التانية بتحدّث مش بتعمل عقود جديدة');
        $this->assertSame($firstCodes, Contract::where('company_id', $this->companyId)->orderBy('id')->pluck('code')->all(),
            'و الأكواد ما بتتغيرش');
        $this->assertSame(2, \App\Models\SalesOrder::whereIn('contract_id', Contract::where('company_id', $this->companyId)->pluck('id'))->count());
    }

    public function test_a_project_with_no_sales_orders_creates_no_contract(): void
    {
        $responses = $this->contractResponses();
        $responses['sale.order.search_read'] = [];
        $responses['purchase.order.line.search_read'] = static fn (array $args): array => [];

        $service = $this->service($responses);
        $service->getContracts('2026-01-01', '2026-12-31', $this->companyId);

        $this->assertSame(0, Contract::where('company_id', $this->companyId)->count());
    }

    public function test_an_odoo_fault_on_the_projects_read_writes_nothing(): void
    {
        $responses = $this->contractResponses();
        $responses['project.project.read'] = ['faultCode' => 1, 'faultString' => 'nope'];

        $service = $this->service($responses);
        $service->getContracts('2026-01-01', '2026-12-31', $this->companyId);

        $this->assertSame(0, Contract::where('company_id', $this->companyId)->count());
    }
}

/**
 * * بديل Ripcord_Client — الكود بينده عليه execute_kw() مباشرة في
 * * قراءة المشاريع و أسعار الصرف
 */
class FakeRipcordClient extends \Ripcord_Client
{
    public array $responses = [];

    /** @var array<int, array{0:string,1:string,2:array,3:array}> */
    public array $calls = [];

    public function __construct() {}

    public function __call($name, $args)
    {
        // execute_kw($db, $uid, $password, $model, $method, $args, $kwargs)
        return $this->reply($args[3] ?? '', $args[4] ?? '', $args[5] ?? [], $args[6] ?? []);
    }

    /** @return array<int, array{0:string,1:string,2:array,3:array}> */
    public function callsFor(string $model, string $method): array
    {
        return array_values(array_filter(
            $this->calls,
            static fn (array $call): bool => $call[0] === $model && $call[1] === $method
        ));
    }

    public function reply(string $model, string $method, array $args, array $kwargs = [])
    {
        $this->calls[] = [$model, $method, $args, $kwargs];

        $reply = $this->responses[$model.'.'.$method] ?? null;

        return $reply instanceof \Closure ? $reply($args) : $reply;
    }
}
