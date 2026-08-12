<?php

namespace Tests\Feature;

use App\Services\Api\OdooService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * بيختبر منطق لمّ الـ POs المربوطة بالـ SOs من غير ما نكلم أودو:
 * بنبدّل readFromOdoo بردود مسجّلة بنفس شكل رد أودو.
 */
class OdooPurchaseOrderLookupTest extends TestCase
{
    private function service(array $responses): OdooService
    {
        return new class($responses) extends OdooService
        {
            public array $calls = [];

            public function __construct(private array $responses) {}

            protected function readFromOdoo(string $model, string $method, array $args, array $kwargs = [])
            {
                $this->calls[] = [$model, $method, $args];

                return $this->responses[$model.'.'.$method] ?? null;
            }
        };
    }

    private function invokeOn(OdooService $service, string $method, array $args)
    {
        $reflection = new ReflectionMethod(OdooService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($service, $args);
    }

    public function test_it_reads_purchase_orders_through_sale_order_id_on_the_po_line(): void
    {
        $service = $this->service([
            'purchase.order.line.search_read' => [
                ['id' => 1, 'order_id' => [901, 'P00042']],
                ['id' => 2, 'order_id' => [901, 'P00042']], // نفس الـ PO من سطرين
                ['id' => 3, 'order_id' => [902, 'P00043']],
            ],
        ]);

        $this->assertSame([901, 902], $this->invokeOn($service, 'purchaseOrderIdsFromSaleOrderLines', [[11, 12]]));
        $this->assertSame([[['sale_order_id', 'in', [11, 12]]], ['order_id']], $service->calls[0][2]);
        $this->assertSame('purchase.order.line', $service->calls[0][0]);
    }

    public function test_origin_matching_is_exact_and_handles_multiple_origins(): void
    {
        $service = $this->service([
            'purchase.order.search_read' => [
                ['id' => 901, 'origin' => 'S00001'],
                ['id' => 902, 'origin' => 'S00001, S00099'],     // أكتر من مستند
                ['id' => 903, 'origin' => 'S000012'],            // مش نفس الـ SO — لازم يتستبعد
                ['id' => 904, 'origin' => false],                // أودو بيرجّع false للفاضي
                ['id' => 905, 'origin' => 'PO ref S00001 extra'],// جوه نص تاني — لازم يتستبعد
            ],
        ]);

        $this->assertSame([901, 902], $this->invokeOn($service, 'purchaseOrderIdsFromOrigin', [['S00001']]));
    }

    public function test_origin_domain_is_a_proper_or_chain(): void
    {
        $service = $this->service(['purchase.order.search_read' => []]);
        $this->invokeOn($service, 'purchaseOrderIdsFromOrigin', [['S00001', 'S00002', 'S00003']]);

        $this->assertSame([
            '|', '|',
            ['origin', 'ilike', 'S00001'],
            ['origin', 'ilike', 'S00002'],
            ['origin', 'ilike', 'S00003'],
        ], $service->calls[0][2][0]);
    }

    public function test_it_merges_both_link_methods_without_duplicates(): void
    {
        $service = $this->service([
            'purchase.order.line.search_read' => [['id' => 1, 'order_id' => [901, 'P00042']]],
            'purchase.order.search_read' => [['id' => 901, 'origin' => 'S00001'], ['id' => 903, 'origin' => 'S00001']],
            'purchase.order.read' => [
                ['id' => 901, 'name' => 'P00042'],
                ['id' => 903, 'name' => 'P00044'],
            ],
        ]);

        $purchaseOrders = $this->invokeOn($service, 'getPurchaseOrdersForProject', [[11], ['S00001'], null]);

        $this->assertSame(['P00042', 'P00044'], array_column($purchaseOrders, 'name'));
        $readCall = $service->calls[2];
        $this->assertSame('purchase.order', $readCall[0]);
        $this->assertSame([901, 903], $readCall[2][0], 'الـ read لازم يتنده مرة واحدة بالـ ids من غير تكرار');
    }

    public function test_it_reads_purchase_orders_booked_on_the_project_analytic_account(): void
    {
        $service = $this->service([
            'purchase.order.line.search_read' => [
                ['id' => 7, 'order_id' => [905, 'P00022']],
                ['id' => 8, 'order_id' => [905, 'P00022']],
            ],
        ]);

        $this->assertSame([905], $this->invokeOn($service, 'purchaseOrderIdsFromProjectAnalyticAccount', [129]));
        $this->assertSame([[['analytic_distribution', 'in', [129]]], ['order_id']], $service->calls[0][2]);
    }

    public function test_a_project_without_an_analytic_account_is_not_queried(): void
    {
        $service = $this->service([]);

        $this->assertSame([], $this->invokeOn($service, 'purchaseOrderIdsFromProjectAnalyticAccount', [null]));
        $this->assertSame([], $service->calls);
    }

    public function test_a_fault_from_odoo_does_not_poison_the_result(): void
    {
        $service = $this->service([]); // readFromOdoo بيرجّع null زي حالة الـ fault

        $this->assertSame([], $this->invokeOn($service, 'purchaseOrderIdsFromSaleOrderLines', [[11]]));
        $this->assertSame([], $this->invokeOn($service, 'purchaseOrderIdsFromOrigin', [['S00001']]));
        $this->assertSame([], $this->invokeOn($service, 'getPurchaseOrdersForProject', [[11], ['S00001'], null]));
    }

    public function test_no_sales_orders_means_no_odoo_calls_at_all(): void
    {
        $service = $this->service([]);

        $this->assertSame([], $this->invokeOn($service, 'getPurchaseOrdersForProject', [[], [], null]));
        $this->assertSame([], $service->calls);
    }

    public function test_read_from_odoo_treats_an_odoo_fault_as_no_data(): void
    {
        $service = (new ReflectionClass(OdooService::class))->newInstanceWithoutConstructor();
        $reflection = new ReflectionMethod(OdooService::class, 'readFromOdoo');
        $reflection->setAccessible(true);

        // مفيش اتصال — الاستثناء لازم يترد null مش يطلع بره
        $this->assertNull($reflection->invokeArgs($service, ['purchase.order', 'read', [[1]]]));
    }
}
