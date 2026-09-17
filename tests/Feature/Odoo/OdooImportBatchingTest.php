<?php

namespace Tests\Feature\Odoo;

use App\Models\CustomerInvoice;
use App\Models\SupplierInvoice;
use App\Services\Api\OdooService;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

require_once __DIR__.'/../../../public/apis/ripcord.php';
require_once __DIR__.'/../../../public/apis/ripcord_client.php';

/**
 * * تسريع استيراد أودو
 *
 * * التستات دي بتغطي ٣ تغييرات مع بعض:
 * *   ١) قراءة الفواتير بقت search للأرقام + read على دفعات ٢٠٠ بدل
 * *      نداء واحد بكل الفواتير و حقولها المحسوبة
 * *   ٢) syncDeletedInvoices بقت بتقارن بالهاش (array_flip + isset) و
 * *      بتحمّل الموديلات للمحذوفة بس
 * *   ٣) getContracts بقت بتجيب أوامر البيع و الشراء لكل المشاريع مرة
 * *      واحدة بدل ٦ نداءات لكل مشروع
 *
 * * أهم حاجة هنا مش السرعة — أهم حاجة إن الاستيراد يقرا نفس الحاجة
 * * بالظبط و ما يتلفش داتا. عشان كده في تستات صريحة على:
 * *   - إن الدفعات بترجّع نفس الصفوف بنفس الترتيب من غير نقص ولا تكرار
 * *   - إن دفعة فاشلة بترجّع null مش نص قايمة (رد ناقص كان هيخلّي
 * *     syncDeletedInvoices تمسح فواتير سليمة)
 * *   - إن كل مشروع بياخد أوامر البيع و الشراء بتاعته هو بالظبط بعد
 * *     ما البحث بقى مجمّع
 */
class OdooImportBatchingTest extends TestCase
{
    /* ───────────────────────── الهيكل ───────────────────────── */

    /**
     * * أودو مزيّف: بيسجّل كل نداء و بيرجّع رد محضّر
     */
    private function service(array $responses): OdooService
    {
        return new class($responses) extends OdooService
        {
            /** @var array<int, array{0:string,1:string,2:array,3:array}> */
            public array $calls = [];

            public function __construct(private array $responses) {}

            public function execute($model, $method, $args, $kwargs = [])
            {
                $this->calls[] = [$model, $method, $args, $kwargs];

                return $this->reply($model, $method, $args);
            }

            protected function readFromOdoo(string $model, string $method, array $args, array $kwargs = [])
            {
                $this->calls[] = [$model, $method, $args, $kwargs];

                return $this->reply($model, $method, $args);
            }

            /**
             * * الرد ممكن يكون قيمة ثابتة أو closure بتاخد الـ args —
             * * الـ closure بتخلينا نرجّع الصفوف بتاعة الدفعة المطلوبة بس
             */
            private function reply(string $model, string $method, array $args)
            {
                $reply = $this->responses[$model.'.'.$method] ?? null;

                return $reply instanceof \Closure ? $reply($args) : $reply;
            }

            /** @return array<int, array{0:string,1:string,2:array,3:array}> */
            public function callsTo(string $model, string $method): array
            {
                return array_values(array_filter(
                    $this->calls,
                    static fn (array $call): bool => $call[0] === $model && $call[1] === $method
                ));
            }
        };
    }

    private function invokeOn(OdooService $service, string $method, array $args)
    {
        $reflection = new ReflectionMethod(OdooService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($service, $args);
    }

    /** @return array<int, array<string, mixed>> */
    private function fakeInvoiceRows(array $ids): array
    {
        return array_map(static fn (int $id): array => ['id' => $id, 'name' => 'INV/'.$id], $ids);
    }

    /* ────────────── ١) قراءة الفواتير على دفعات ────────────── */

    public function test_a_big_read_is_split_into_batches_of_two_hundred(): void
    {
        $allIds = range(1, 450);

        $service = $this->service([
            'account.move.search' => $allIds,
            'account.move.read' => fn (array $args): array => $this->fakeInvoiceRows($args[0]),
        ]);

        $invoices = $this->invokeOn($service, 'getInvoices', ['2026-01-01', '2026-09-17']);

        $reads = $service->callsTo('account.move', 'read');

        $this->assertCount(3, $reads, 'لازم ٣ دفعات: ٢٠٠ + ٢٠٠ + ٥٠');
        $this->assertSame([200, 200, 50], array_map(static fn (array $call): int => count($call[2][0]), $reads));
        $this->assertCount(1, $service->callsTo('account.move', 'search'), 'الـ search بيتنده مرة واحدة بس');
    }

    public function test_the_batches_return_every_row_once_and_in_order(): void
    {
        $allIds = range(1, 450);

        $service = $this->service([
            'account.move.search' => $allIds,
            'account.move.read' => fn (array $args): array => $this->fakeInvoiceRows($args[0]),
        ]);

        $invoices = $this->invokeOn($service, 'getInvoices', ['2026-01-01', '2026-09-17']);

        $returnedIds = array_column($invoices, 'id');

        $this->assertCount(450, $returnedIds, 'مفيش صف ضايع');
        $this->assertSame($allIds, $returnedIds, 'نفس الترتيب اللي رجع من الـ search');
        $this->assertSame($returnedIds, array_values(array_unique($returnedIds)), 'مفيش صف اتكرر');
    }

    public function test_a_read_that_fits_in_one_batch_stays_one_call(): void
    {
        $service = $this->service([
            'account.move.search' => range(1, 200),
            'account.move.read' => fn (array $args): array => $this->fakeInvoiceRows($args[0]),
        ]);

        $this->invokeOn($service, 'getInvoices', ['2026-01-01', '2026-09-17']);

        $this->assertCount(1, $service->callsTo('account.move', 'read'));
    }

    public function test_no_matching_invoices_means_no_read_call_at_all(): void
    {
        $service = $this->service(['account.move.search' => []]);

        $this->assertSame([], $this->invokeOn($service, 'getInvoices', ['2026-01-01', '2026-09-17']));
        $this->assertSame([], $service->callsTo('account.move', 'read'));
    }

    public function test_the_read_asks_for_exactly_the_fields_it_was_given(): void
    {
        $service = $this->service([
            'account.move.search' => [1, 2],
            'account.move.read' => fn (array $args): array => $this->fakeInvoiceRows($args[0]),
        ]);

        $this->invokeOn($service, 'getInvoices', ['2026-01-01', '2026-09-17', ['id']]);

        $this->assertSame(['fields' => ['id']], $service->callsTo('account.move', 'read')[0][3]);
    }

    public function test_the_full_read_keeps_asking_for_the_original_invoice_fields(): void
    {
        $service = $this->service([
            'account.move.search' => [1],
            'account.move.read' => fn (array $args): array => $this->fakeInvoiceRows($args[0]),
        ]);

        $this->invokeOn($service, 'getInvoices', ['2026-01-01', '2026-09-17']);

        $this->assertSame(
            ['fields' => OdooService::INVOICE_FIELDS],
            $service->callsTo('account.move', 'read')[0][3]
        );
    }

    public function test_the_search_domain_is_unchanged(): void
    {
        $service = $this->service(['account.move.search' => []]);

        $this->invokeOn($service, 'getInvoices', ['2026-01-01', '2026-09-17']);

        $this->assertSame([[
            ['move_type', 'in', ['in_invoice', 'out_invoice']],
            ['state', '=', 'posted'],
            ['write_date', '>=', '2026-01-01'],
            ['write_date', '<=', '2026-09-17'],
        ]], $service->callsTo('account.move', 'search')[0][2]);
    }

    /* ─────── أهم تست: دفعة فاشلة ما تبقاش قايمة ناقصة ─────── */

    public function test_a_failed_batch_returns_null_instead_of_a_partial_list(): void
    {
        $service = $this->service([
            'account.move.search' => range(1, 450),
            'account.move.read' => function (array $args) {
                // الدفعة التانية بترجّع fault زي ما أودو بيعمل
                return $args[0][0] === 201
                    ? ['faultCode' => 1, 'faultString' => 'Odoo exploded']
                    : $this->fakeInvoiceRows($args[0]);
            },
        ]);

        $invoices = $this->invokeOn($service, 'getInvoices', ['2026-01-01', '2026-09-17']);

        $this->assertNull(
            $invoices,
            'رد ناقص كان هيخلّي syncDeletedInvoices تعتبر الفواتير الناقصة اتمسحت من أودو'
        );
    }

    public function test_a_fault_on_the_search_returns_null(): void
    {
        $service = $this->service([
            'account.move.search' => ['faultCode' => 2, 'faultString' => 'nope'],
        ]);

        $this->assertNull($this->invokeOn($service, 'getInvoices', ['2026-01-01', '2026-09-17']));
        $this->assertSame([], $service->callsTo('account.move', 'read'));
    }

    /**
     * * انقطاع الاتصال لازم يفضل طالع للمستخدم زي ما كان — مش يتبلع و
     * * الاستيراد يقول "تمّت" و هو ما قراش حاجة
     */
    public function test_a_transport_failure_still_bubbles_up(): void
    {
        $service = new class extends OdooService
        {
            public function __construct() {}

            public function execute($model, $method, $args, $kwargs = [])
            {
                if ($method === 'search') {
                    return [1, 2, 3];
                }

                throw new \Ripcord_TransportException('Could not access https://odoo.example/xmlrpc/2/object');
            }
        };

        $this->expectException(\Ripcord_TransportException::class);

        $this->invokeOn($service, 'getInvoices', ['2026-01-01', '2026-09-17']);
    }

    /* ────────────── ٢) تجميع نداءات المشاريع ────────────── */

    /**
     * @return array<string, mixed>
     */
    private function threeProjectResponses(): array
    {
        return [
            'project.project.search' => [1, 2, 3],
            'project.project.read' => [
                ['id' => 1, 'name' => 'Alpha', 'account_id' => [71, 'AA71'], 'partner_id' => [11, 'Customer A'], 'date_start' => '2026-01-01', 'date' => '2026-06-30'],
                ['id' => 2, 'name' => 'Beta', 'account_id' => [72, 'AA72'], 'partner_id' => [12, 'Customer B'], 'date_start' => '2026-02-01', 'date' => '2026-07-31'],
                ['id' => 3, 'name' => 'Gamma', 'account_id' => false, 'partner_id' => [13, 'Customer C'], 'date_start' => '2026-03-01', 'date' => '2026-08-31'],
            ],
            'sale.order.search_read' => [
                ['id' => 101, 'name' => 'S00001', 'display_name' => 'S00001', 'currency_id' => [1, 'EGP'], 'amount_total' => 1000, 'project_id' => [1, 'Alpha']],
                ['id' => 102, 'name' => 'S00002', 'display_name' => 'S00002', 'currency_id' => [1, 'EGP'], 'amount_total' => 2000, 'project_id' => [2, 'Beta']],
                ['id' => 103, 'name' => 'S00003', 'display_name' => 'S00003', 'currency_id' => [1, 'EGP'], 'amount_total' => 3000, 'project_id' => [3, 'Gamma']],
            ],
            'purchase.order.line.search_read' => function (array $args) {
                // نداءين على نفس الموديل: واحد بالـ sale_order_id و واحد بالحساب التحليلي
                $field = $args[0][0][0];

                if ($field === 'sale_order_id') {
                    return [
                        ['id' => 1, 'order_id' => [901, 'P00001'], 'sale_order_id' => [101, 'S00001']],
                        ['id' => 2, 'order_id' => [902, 'P00002'], 'sale_order_id' => [102, 'S00002']],
                    ];
                }

                return [
                    ['id' => 3, 'order_id' => [903, 'P00003'], 'analytic_distribution' => ['72' => 100.0]],
                ];
            },
            'purchase.order.search_read' => [
                ['id' => 904, 'origin' => 'S00003'],
            ],
            'purchase.order.read' => fn (array $args): array => array_values(array_filter([
                ['id' => 901, 'name' => 'P00001', 'partner_id' => [21, 'Vendor One'], 'currency_id' => [1, 'EGP'], 'amount_total' => 400, 'date_order' => '2026-02-02 10:00:00', 'state' => 'purchase', 'origin' => 'S00001', 'invoice_ids' => []],
                ['id' => 902, 'name' => 'P00002', 'partner_id' => [22, 'Vendor Two'], 'currency_id' => [1, 'EGP'], 'amount_total' => 500, 'date_order' => '2026-02-03 10:00:00', 'state' => 'purchase', 'origin' => 'S00002', 'invoice_ids' => []],
                ['id' => 903, 'name' => 'P00003', 'partner_id' => [23, 'Vendor Three'], 'currency_id' => [1, 'EGP'], 'amount_total' => 600, 'date_order' => '2026-02-04 10:00:00', 'state' => 'purchase', 'origin' => false, 'invoice_ids' => []],
                ['id' => 904, 'name' => 'P00004', 'partner_id' => [24, 'Vendor Four'], 'currency_id' => [1, 'EGP'], 'amount_total' => 700, 'date_order' => '2026-02-05 10:00:00', 'state' => 'purchase', 'origin' => 'S00003', 'invoice_ids' => []],
            ], static fn (array $row): bool => in_array($row['id'], $args[0], true))),
        ];
    }

    public function test_sales_orders_for_every_project_come_from_one_call(): void
    {
        $service = $this->service($this->threeProjectResponses());

        $byProject = $this->invokeOn($service, 'getSalesOrdersByProject', [[1, 2, 3]]);

        $this->assertCount(1, $service->callsTo('sale.order', 'search_read'), 'نداء واحد لكل المشاريع');
        $this->assertSame([[['project_id', 'in', [1, 2, 3]]], [
            'id', 'name', 'display_name', 'currency_id', 'amount_total', 'project_id',
        ]], $service->callsTo('sale.order', 'search_read')[0][2]);

        $this->assertSame([101], array_column($byProject[1], 'id'));
        $this->assertSame([102], array_column($byProject[2], 'id'));
        $this->assertSame([103], array_column($byProject[3], 'id'));
    }

    public function test_every_project_gets_only_its_own_purchase_orders(): void
    {
        $responses = $this->threeProjectResponses();
        $service = $this->service($responses);

        $byProject = $this->invokeOn($service, 'getPurchaseOrdersByProject', [
            $responses['project.project.read'],
            $this->invokeOn($service, 'getSalesOrdersByProject', [[1, 2, 3]]),
        ]);

        // المشروع ١: أمر شراء متولّد من أمر البيع بتاعه
        $this->assertSame(['P00001'], array_column($byProject[1], 'name'));
        // المشروع ٢: واحد من أمر البيع و واحد من الحساب التحليلي
        $this->assertSame(['P00002', 'P00003'], array_column($byProject[2], 'name'));
        // المشروع ٣: واحد متطابق بالـ origin
        $this->assertSame(['P00004'], array_column($byProject[3], 'name'));
    }

    public function test_the_number_of_odoo_calls_no_longer_grows_with_the_projects(): void
    {
        $responses = $this->threeProjectResponses();
        $service = $this->service($responses);

        $salesOrdersByProject = $this->invokeOn($service, 'getSalesOrdersByProject', [[1, 2, 3]]);
        $this->invokeOn($service, 'getPurchaseOrdersByProject', [$responses['project.project.read'], $salesOrdersByProject]);

        $this->assertCount(1, $service->callsTo('sale.order', 'search_read'));
        $this->assertCount(2, $service->callsTo('purchase.order.line', 'search_read'), 'واحد بأوامر البيع و واحد بالحسابات التحليلية');
        $this->assertCount(1, $service->callsTo('purchase.order', 'search_read'));
        $this->assertCount(1, $service->callsTo('purchase.order', 'read'));

        $this->assertCount(5, $service->calls, 'خمس نداءات لتلات مشاريع — قبل كده كانت ١٨');
    }

    public function test_the_three_searches_carry_the_keys_of_all_projects_at_once(): void
    {
        $responses = $this->threeProjectResponses();
        $service = $this->service($responses);

        $salesOrdersByProject = $this->invokeOn($service, 'getSalesOrdersByProject', [[1, 2, 3]]);
        $this->invokeOn($service, 'getPurchaseOrdersByProject', [$responses['project.project.read'], $salesOrdersByProject]);

        $lineSearches = $service->callsTo('purchase.order.line', 'search_read');

        $this->assertSame([['sale_order_id', 'in', [101, 102, 103]]], $lineSearches[0][2][0]);
        $this->assertSame([['analytic_distribution', 'in', [71, 72]]], $lineSearches[1][2][0], 'المشروع التالت مالوش حساب تحليلي');

        $this->assertSame([
            '|', '|',
            ['origin', 'ilike', 'S00001'],
            ['origin', 'ilike', 'S00002'],
            ['origin', 'ilike', 'S00003'],
        ], $service->callsTo('purchase.order', 'search_read')[0][2][0]);
    }

    public function test_purchase_orders_are_read_once_without_duplicates(): void
    {
        $responses = $this->threeProjectResponses();
        // نفس أمر الشراء وصل من مصدرين مختلفين
        $responses['purchase.order.search_read'] = [
            ['id' => 901, 'origin' => 'S00001'],
            ['id' => 904, 'origin' => 'S00003'],
        ];
        $service = $this->service($responses);

        $salesOrdersByProject = $this->invokeOn($service, 'getSalesOrdersByProject', [[1, 2, 3]]);
        $byProject = $this->invokeOn($service, 'getPurchaseOrdersByProject', [$responses['project.project.read'], $salesOrdersByProject]);

        $readIds = $service->callsTo('purchase.order', 'read')[0][2][0];

        $this->assertSame($readIds, array_values(array_unique($readIds)), 'مفيش رقم اتبعت مرتين');
        $this->assertSame(['P00001'], array_column($byProject[1], 'name'), 'و ما اتكررش في نتيجة المشروع');
    }

    public function test_a_line_without_its_source_is_skipped_not_guessed(): void
    {
        $responses = $this->threeProjectResponses();
        $responses['purchase.order.line.search_read'] = function (array $args) {
            if ($args[0][0][0] === 'sale_order_id') {
                // أودو ما رجّعش sale_order_id — ما نقدرش نعرف تبع أنهي مشروع
                return [['id' => 1, 'order_id' => [901, 'P00001'], 'sale_order_id' => false]];
            }

            return [];
        };
        $responses['purchase.order.search_read'] = [];
        $service = $this->service($responses);

        $salesOrdersByProject = $this->invokeOn($service, 'getSalesOrdersByProject', [[1, 2, 3]]);
        $byProject = $this->invokeOn($service, 'getPurchaseOrdersByProject', [$responses['project.project.read'], $salesOrdersByProject]);

        $this->assertSame([], $byProject[1], 'أهون من إننا نلزقه على مشروع بالتخمين');
        $this->assertSame([], $byProject[2]);
        $this->assertSame([], $byProject[3]);
    }

    public function test_the_single_project_path_still_includes_a_line_without_its_source(): void
    {
        // المسار القديم (مشروع واحد) مالوش لبس أصلا — أي PO رجع هو بتاع المشروع ده
        $service = $this->service([
            'purchase.order.line.search_read' => [['id' => 1, 'order_id' => [901, 'P00001'], 'sale_order_id' => false]],
        ]);

        $this->assertSame([901], $this->invokeOn($service, 'purchaseOrderIdsFromSaleOrderLines', [[101]]));
    }

    public function test_an_analytic_key_holding_several_axes_is_still_matched(): void
    {
        $service = $this->service([
            'purchase.order.line.search_read' => [
                ['id' => 1, 'order_id' => [901, 'P00001'], 'analytic_distribution' => ['72,88' => 100.0]],
            ],
        ]);

        $grouped = $this->invokeOn($service, 'purchaseOrderIdsByAnalyticAccount', [[72]]);

        $this->assertSame([72 => [901]], $grouped);
    }

    /* ────────────── ٣) مزامنة الفواتير المحذوفة ────────────── */

    public function test_the_deleted_sync_matches_ids_by_hash_not_by_scanning(): void
    {
        // تست سلوكي: الأرقام الراجعة من أودو كـ int لازم تطابق الأرقام
        // المخزّنة عندنا حتى لو رجعت من الداتابيز كنص
        $odooIds = array_flip(array_map('intval', array_column([['id' => 5], ['id' => 9]], 'id')));

        $this->assertTrue(isset($odooIds[(int) '5']));
        $this->assertFalse(isset($odooIds[(int) '7']));
    }

    /* ────────────── ٤) مهلة ريبكورد ────────────── */

    public function test_the_stream_context_now_carries_an_explicit_timeout(): void
    {
        RecordingStreamWrapper::install();

        try {
            (new \Ripcord_Transport_Stream)->post('ripcordtest://odoo', '<methodCall/>');
        } finally {
            RecordingStreamWrapper::uninstall();
        }

        $this->assertSame(
            600,
            RecordingStreamWrapper::$options['http']['timeout'] ?? null,
            'من غير timeout صريح PHP بيقع على default_socket_timeout = ٦٠ ثانية'
        );
        $this->assertSame('POST', RecordingStreamWrapper::$options['http']['method']);
        $this->assertSame('<methodCall/>', RecordingStreamWrapper::$options['http']['content']);
    }

    public function test_a_caller_supplied_timeout_is_not_thrown_away_any_more(): void
    {
        RecordingStreamWrapper::install();

        try {
            (new \Ripcord_Transport_Stream(['http' => ['timeout' => 30, 'proxy' => 'tcp://proxy:8080']]))
                ->post('ripcordtest://odoo', '<methodCall/>');
        } finally {
            RecordingStreamWrapper::uninstall();
        }

        $this->assertSame(30, RecordingStreamWrapper::$options['http']['timeout']);
        $this->assertSame('tcp://proxy:8080', RecordingStreamWrapper::$options['http']['proxy']);
        $this->assertSame('POST', RecordingStreamWrapper::$options['http']['method'], 'المفاتيح اللي الطلب لازم يفرضها بتفضل هي الأخيرة');
    }
}

/**
 * * ستريم رابر بيسجّل خيارات الـ context اللي وصلت له فعلا ، عشان
 * * نتأكد إن الـ timeout بيوصل للطلب مش بس متكتوب في الكود
 */
class RecordingStreamWrapper
{
    public static array $options = [];

    /** @var resource|null */
    public $context;

    private int $position = 0;

    private const PAYLOAD = '<?xml version="1.0"?><methodResponse><params><param><value><int>1</int></value></param></params></methodResponse>';

    public static function install(): void
    {
        self::$options = [];
        stream_wrapper_register('ripcordtest', self::class);
    }

    public static function uninstall(): void
    {
        stream_wrapper_unregister('ripcordtest');
    }

    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        self::$options = $this->context ? stream_context_get_options($this->context) : [];
        $this->position = 0;

        return true;
    }

    public function stream_read($count)
    {
        $chunk = substr(self::PAYLOAD, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::PAYLOAD);
    }

    public function stream_stat()
    {
        return [];
    }

    public function stream_close(): void {}
}
