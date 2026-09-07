<?php

namespace Tests\Feature\Settlements;

use App\Models\CustomerInvoice;
use App\Models\PaymentSettlement;
use App\Models\Settlement;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * فاتورة نازل عليها تسويات ما ينفعش تتمسح
 *
 * * مكانش فيه اي حاجة تمنع ده — لا هوك على الموديل و لا foreign key على
 * * settlements.invoice_id — فمسح الفاتورة كان بيسيب تسوياتها يتيمة :
 * * مبالغ محسوبة على رصيد الشريك من غير ما نعرف مقابل ايه
 *
 * * كل الكتابة هنا جوه ترانزاكشن بترجع ، فمفيش صف بيتغيّر فعلا
 */
class InvoiceDeletionGuardTest extends TestCase
{
    private ?string $originalDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.connections.mysql.database');

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }
    }

    protected function tearDown(): void
    {
        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    /** @return array{0: object, 1: int} */
    private function settledInvoice(string $invoiceClass, string $settlementClass): array
    {
        $invoiceId = $settlementClass::whereNotNull('invoice_id')->value('invoice_id');
        $invoice = $invoiceId ? $invoiceClass::find($invoiceId) : null;

        if (! $invoice) {
            $this->markTestSkipped('No settled '.class_basename($invoiceClass).' on file.');
        }

        return [$invoice, (int) $invoiceId];
    }

    /* ───────────── المنع ───────────── */

    public function test_a_settled_customer_invoice_cannot_be_deleted(): void
    {
        [$invoice] = $this->settledInvoice(CustomerInvoice::class, Settlement::class);
        $before = CustomerInvoice::count();

        DB::beginTransaction();

        try {
            $invoice->delete();
            $this->fail('فاتورة عليها تسويات ما ينفعش تتمسح');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
            $this->assertStringContainsString('settlement', $e->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertSame($before, CustomerInvoice::count());
    }

    public function test_a_settled_supplier_invoice_cannot_be_deleted(): void
    {
        [$invoice] = $this->settledInvoice(SupplierInvoice::class, PaymentSettlement::class);
        $before = SupplierInvoice::count();

        DB::beginTransaction();

        try {
            $invoice->delete();
            $this->fail('فاتورة مورد عليها تسويات ما ينفعش تتمسح');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertSame($before, SupplierInvoice::count());
    }

    /**
     * * الرسالة لازم تقول انهي فاتورة و كام تسوية ، عشان المستخدم يعرف
     * * يروح فين — مش مجرد "مش ممكن الحذف"
     */
    public function test_the_message_names_the_invoice_and_the_count(): void
    {
        [$invoice, $invoiceId] = $this->settledInvoice(SupplierInvoice::class, PaymentSettlement::class);
        $count = PaymentSettlement::where('invoice_id', $invoiceId)->count();

        DB::beginTransaction();

        try {
            $invoice->delete();
            $this->fail('المفروض يرفض');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString((string) $invoice->getInvoiceNumber(), $e->getMessage());
            $this->assertStringContainsString((string) $count, $e->getMessage());
        } finally {
            DB::rollBack();
        }
    }

    /* ───────────── اللي المفروض يفضل شغال ───────────── */

    /**
     * * الفاتورة اللي ملهاش تسويات لازم تفضل تتمسح عادي — الحارس مش
     * * المفروض يقفل الحذف على طول
     */
    public function test_an_unsettled_invoice_can_still_be_deleted(): void
    {
        $invoice = SupplierInvoice::whereNotIn('id', function ($q) {
            $q->select('invoice_id')->from('payment_settlements')->whereNotNull('invoice_id');
        })->first();

        if (! $invoice) {
            $this->markTestSkipped('Every supplier invoice on file is settled.');
        }

        DB::beginTransaction();

        try {
            $invoice->delete();
            $this->assertFalse(SupplierInvoice::whereKey($invoice->getKey())->exists(),
                'الفاتورة من غير تسويات لازم تتمسح عادي');
        } finally {
            DB::rollBack();
        }

        $this->assertTrue(SupplierInvoice::whereKey($invoice->getKey())->exists(),
            'الرول باك لازم يرجّعها');
    }

    /* ───────────── المسارات اللي بتمسح ───────────── */

    /**
     * * مزامنة اودو بتمسح الفواتير اللي اتمسحت هناك — لازم تعدّي المحمية
     * * و تكمّل ، مش توقف الاستيراد كله ولا تمسح غصب
     */
    public function test_the_odoo_sync_skips_a_protected_invoice_instead_of_crashing(): void
    {
        $source = file_get_contents(app_path('Services/Api/OdooService.php'));
        $start = strpos($source, 'private function syncDeletedInvoices');
        $end = strpos($source, 'function chartOfAccount', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $body = substr($source, $start, $end - $start);

        $this->assertMatchesRegularExpression(
            '/try \{\s*\$invoice->delete\(\);\s*\} catch \(\\\\InvalidArgumentException/',
            $body,
            'الاستيراد لازم يمسك رفض الحذف بدل ما يقع'
        );
        $this->assertStringContainsString('continue;', $body, 'و يكمّل باقي الفواتير');
    }

    /**
     * * المسح الجماعي من الشاشة لازم يعدّي المحمي و يقول للمستخدم ، مش
     * * يقف في نص العملية بصفحة خطأ
     */
    public function test_bulk_delete_reports_blocked_rows_instead_of_failing(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/DeletingClass.php'));

        $this->assertStringContainsString('deleteWhatIsAllowed', $source);
        $this->assertStringNotContainsString('$all_model_data->each->delete();', $source,
            'المسح المباشر ما بيسمحش بالتعامل مع الصفوف المحمية');
        $this->assertStringContainsString('InvalidArgumentException', $source);
    }
}
