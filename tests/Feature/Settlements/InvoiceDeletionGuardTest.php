<?php

namespace Tests\Feature\Settlements;

use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\PaymentSettlement;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * * فاتورة نازل عليها تسويات ما ينفعش تتمسح
 *
 * * مكانش فيه اي حاجة تمنع ده — لا هوك على الموديل و لا foreign key على
 * * settlements.invoice_id — فمسح الفاتورة كان بيسيب تسوياتها يتيمة :
 * * مبالغ محسوبة على رصيد الشريك من غير ما نعرف مقابل ايه
 *
 * * كل الفواتير و التسويات هنا بيعملها الاختبار نفسه لشركة جديدة ، و كله
 * * بيترجع بعد الاختبار
 */
class InvoiceDeletionGuardTest extends TestCase
{
    use MakesInvoices;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
    }

    /** فاتورة عميل عليها تسويتين من قبض */
    private function settledCustomerInvoice(): CustomerInvoice
    {
        $customer = $this->customer($this->company);
        $invoice = $this->invoice($customer, ['invoice_number' => 'GUARD-C-1', 'invoice_amount' => 1000]);
        $money = $this->received($customer, ['received_amount' => 500]);
        $this->settle($money, $invoice, 300);
        $this->settle($money, $invoice, 200);

        return $invoice;
    }

    /** فاتورة مورد عليها تلات تسويات من صرف */
    private function settledSupplierInvoice(): SupplierInvoice
    {
        $supplier = $this->supplier($this->company);
        $invoice = $this->supplierInvoice($supplier, ['invoice_number' => 'GUARD-S-1', 'invoice_amount' => 1000]);
        $money = $this->payment($supplier, ['paid_amount' => 600]);
        $this->settle($money, $invoice, 100);
        $this->settle($money, $invoice, 200);
        $this->settle($money, $invoice, 300);

        return $invoice;
    }

    /* ───────────── المنع ───────────── */

    public function test_a_settled_customer_invoice_cannot_be_deleted(): void
    {
        $invoice = $this->settledCustomerInvoice();
        $before = CustomerInvoice::count();

        try {
            $invoice->delete();
            $this->fail('فاتورة عليها تسويات ما ينفعش تتمسح');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
            $this->assertStringContainsString('settlement', $e->getMessage());
        }

        $this->assertSame($before, CustomerInvoice::count());
    }

    public function test_a_settled_supplier_invoice_cannot_be_deleted(): void
    {
        $invoice = $this->settledSupplierInvoice();
        $before = SupplierInvoice::count();

        try {
            $invoice->delete();
            $this->fail('فاتورة مورد عليها تسويات ما ينفعش تتمسح');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }

        $this->assertSame($before, SupplierInvoice::count());
    }

    /**
     * * الرسالة لازم تقول انهي فاتورة و كام تسوية ، عشان المستخدم يعرف
     * * يروح فين — مش مجرد "مش ممكن الحذف"
     */
    public function test_the_message_names_the_invoice_and_the_count(): void
    {
        $invoice = $this->settledSupplierInvoice();
        $count = PaymentSettlement::where('invoice_id', $invoice->id)->count();
        $this->assertSame(3, $count);

        try {
            $invoice->delete();
            $this->fail('المفروض يرفض');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString((string) $invoice->getInvoiceNumber(), $e->getMessage());
            $this->assertStringContainsString((string) $count, $e->getMessage());
        }
    }

    /* ───────────── اللي المفروض يفضل شغال ───────────── */

    /**
     * * الفاتورة اللي ملهاش تسويات لازم تفضل تتمسح عادي — الحارس مش
     * * المفروض يقفل الحذف على طول
     */
    public function test_an_unsettled_invoice_can_still_be_deleted(): void
    {
        $this->settledSupplierInvoice();   // جارتها المسوّاة ما تأثرش عليها
        $invoice = $this->supplierInvoice($this->supplier($this->company), ['invoice_number' => 'GUARD-S-FREE']);

        $this->assertSame(0, PaymentSettlement::where('invoice_id', $invoice->id)->count());

        // جوه ترانزاكشن داخلية : الحذف لازم يكون قابل للرجوع زي أي كتابة عادية
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
