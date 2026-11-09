<?php

namespace Tests\Feature\Settlements;

use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * * التسوية لازم تكون مقابل فاتورة موجودة
 *
 * * قبل كده storeNewSettlement() كانت بتقبل أي صف مبلغه أكبر من صفر حتى لو
 * * مفيهوش invoice_id ، فاتخزنت صفوف بمبالغ حقيقية و invoice_id = NULL ،
 * * و بوب اب تفاصيل التسوية كان بيعرضها "N/A ... 0.00" جنب مبلغ تسوية حقيقي
 *
 * * راجعنا كل المسارات اللي بتنادي الدالة : مفيش مسار شرعي بيبعت تسوية من
 * * غير فاتورة — لا فورم الحركة ، لا فورم تسوية الدفعة المقدمة ، و لا مسار
 * * النقل عند التعديل
 */
class SettlementRequiresInvoiceTest extends TestCase
{
    use MakesInvoices;

    private Company $company;

    private Partner $customer;

    /** قبض من عميل ، معمول للاختبار — الحارس بيرفض قبل أي كتابة */
    private MoneyReceived $money;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
        $this->customer = $this->customer($this->company);
        $this->money = $this->received($this->customer, ['received_amount' => 26150]);
    }

    /* ───────────── الرفض ───────────── */

    public function test_it_refuses_a_settlement_with_no_invoice_id(): void
    {
        $before = DB::table('settlements')->count();

        try {
            $this->money->storeNewSettlement(
                [['settlement_amount' => 26150, 'withhold_amount' => 0]],
                (int) $this->money->partner_id,
                $this->company
            );
            $this->fail('كان لازم يرفض التسوية من غير فاتورة');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }

        $this->assertSame($before, DB::table('settlements')->count(),
            'ما ينفعش يكون كتب صف قبل ما يرمي الاستثناء');
    }

    public function test_it_refuses_a_settlement_whose_invoice_was_deleted(): void
    {
        // فاتورة اتعملت و اتمسحت : رقمها اتعرف إنه كان موجود و مبقاش
        $deleted = $this->invoice($this->customer);
        $missingId = $deleted->id;
        $deleted->delete();
        $this->assertFalse(CustomerInvoice::whereKey($missingId)->exists());

        $before = DB::table('settlements')->count();

        try {
            $this->money->storeNewSettlement(
                [['invoice_id' => $missingId, 'settlement_amount' => 500]],
                (int) $this->money->partner_id,
                $this->company
            );
            $this->fail('كان لازم يرفض فاتورة مش موجودة');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }

        $this->assertSame($before, DB::table('settlements')->count());
    }

    /**
     * * الصفوف اللي مبلغها صفر بتتخطى من قبل الحارس أصلا ، فما ينفعش الحارس
     * * يبوّظ الحالة دي و يرمي استثناء عليها
     */
    public function test_a_zero_amount_row_is_skipped_not_rejected(): void
    {
        $before = DB::table('settlements')->count();

        $result = $this->money->storeNewSettlement(
            [['settlement_amount' => 0, 'withhold_amount' => 0]],
            (int) $this->money->partner_id,
            $this->company
        );

        $this->assertSame([], $result['settlements']);
        $this->assertSame($before, DB::table('settlements')->count());
    }

    public function test_an_empty_list_is_accepted(): void
    {
        $result = $this->money->storeNewSettlement([], (int) $this->money->partner_id, $this->company);

        $this->assertSame([], $result['settlements']);
        $this->assertSame(0, $result['total_withhold_amount']);
    }

    /* ───────────── الجهة الصح للفواتير ───────────── */

    /**
     * * الماني ريسيد بيتسوّى بفواتير عملاء و الماني بايمنت بفواتير موردين —
     * * فما ينفعش فاتورة مورد تعدّي على قبض لمجرد ان الرقم موجود في الجدول
     * * التاني
     */
    public function test_it_checks_the_invoice_on_the_right_side(): void
    {
        $supplierInvoiceId = $this->supplierInvoice($this->supplier($this->company))->id;

        $this->assertFalse(CustomerInvoice::whereKey($supplierInvoiceId)->exists(),
            'الاختبار ده معناه يضيع لو نفس الرقم موجود في جدول فواتير العملاء');

        $before = DB::table('settlements')->count();

        try {
            $this->money->storeNewSettlement(
                [['invoice_id' => $supplierInvoiceId, 'settlement_amount' => 100]],
                (int) $this->money->partner_id,
                $this->company
            );
            $this->fail('فاتورة مورد ما ينفعش تتقبل على ماني ريسيد');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }

        $this->assertSame($before, DB::table('settlements')->count());
    }

    /**
     * * و الاتجاه التاني : فاتورة المورد لازم **تعدّي** على الماني بايمنت —
     * * من غير الاختبار ده لو الكود بص على جدول العملاء دايمًا الاختبار
     * * اللي فوق هيفضل ناجح (لأن الرقم مش موجود في الجدولين)
     */
    public function test_a_supplier_invoice_is_accepted_on_a_money_payment(): void
    {
        $supplier = $this->supplier($this->company);
        $supplierInvoiceId = $this->supplierInvoice($supplier)->id;
        $money = $this->payment($supplier);

        $this->assertFalse(
            CustomerInvoice::whereKey($supplierInvoiceId)->exists(),
            'الاختبار ده معناه يضيع لو نفس الرقم موجود في جدول فواتير العملاء'
        );

        $result = $money->storeNewSettlement(
            [['invoice_id' => $supplierInvoiceId, 'settlement_amount' => 1]],
            (int) $money->partner_id,
            $this->company,
            false,
            false
        );

        $this->assertCount(1, $result['settlements'],
            'فاتورة المورد لازم تتقبل على الماني بايمنت');
    }

    /* ───────────── كل المسارات بتبعت invoice_id ───────────── */

    /**
     * * الحارس ده هيضرب لو أي مسار بيبعت تسوية من غير فاتورة ، فبنتأكد ان
     * * كل المسارات فعلا بتبعتها
     */
    public function test_every_caller_supplies_an_invoice_id(): void
    {
        $callers = [
            app_path('Http/Controllers/MoneyReceivedController.php'),
            app_path('Http/Controllers/MoneyPaymentController.php'),
            app_path('Http/Controllers/DownPaymentContractsController.php'),
        ];

        foreach ($callers as $path) {
            $source = file_get_contents($path);

            if (! str_contains($source, 'storeNewSettlement(')) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                "/storeNewSettlement\(\s*(\\\$request->get\('settlements', \[\]\)|\\\$oldSettlements)/",
                $source,
                basename($path).' must pass either the request settlements or the carried-forward rows.'
            );
        }

        // الفورمات نفسها لازم تبعت invoice_id مع كل صف
        foreach ([
            resource_path('js/Pages/MoneyPayment/Form.vue'),
            resource_path('js/Pages/MoneyReceived/Form.vue'),
            resource_path('js/Pages/DownPaymentSettlement/Form.vue'),
        ] as $form) {
            if (! is_file($form)) {
                continue;
            }

            $this->assertStringContainsString('invoice_id', file_get_contents($form),
                basename(dirname($form)).'/'.basename($form).' must send invoice_id with each settlement.');
        }
    }

    /* ───────────── العرض ───────────── */

    public static function sides(): array
    {
        return [
            'money received' => ['settlements', 'money_received_id', MoneyReceived::class],
            'money payment' => ['payment_settlements', 'money_payment_id', MoneyPayment::class],
        ];
    }

    /**
     * * الصفوف القديمة اللي فاتورتها ناقصة لسه موجودة ، فالبوب اب لازم يقول
     * * السبب بدل ما يعرض فاتورة بصفر
     *
     * * الصف القديم ده ما بقاش ينفع يتعمل من الكود (الحارس فوق بيرفضه) ، فبيتكتب
     * * هنا مباشرة في الجدول زي ما كان اتخزن زمان
     */
    #[DataProvider('sides')]
    public function test_the_popup_names_the_reason_instead_of_showing_a_zero_invoice(string $table, string $fk, string $model): void
    {
        $money = $model === MoneyReceived::class
            ? $this->money
            : $this->payment($this->supplier($this->company), ['paid_amount' => 26150]);

        $row = [
            $fk => $money->id,
            'company_id' => $this->company->id,
            'partner_id' => $money->partner_id,
            'invoice_id' => null,
            'settlement_amount' => '26150',
            'withhold_amount' => '0',
        ];
        if ($table === 'payment_settlements') {
            $row['letter_of_credit_issuance_id'] = 0;
        }
        DB::table($table)->insert($row);

        $orphanSettlement = DB::table($table)
            ->where($fk, $money->id)
            ->whereRaw('coalesce(settlement_amount, 0) <> 0')
            ->whereNull('invoice_id')
            ->first();
        $this->assertNotNull($orphanSettlement);

        $money = $model::findOrFail($orphanSettlement->{$fk});

        $row = collect($money->getSettlementsInfo()['rows'])
            ->firstWhere('settlement_amount', number_format((float) $orphanSettlement->settlement_amount, 2));

        $this->assertNotNull($row, 'الصف لازم يظهر في البوب اب');
        $this->assertFalse($row['has_invoice'], 'لازم يتعلّم انه من غير فاتورة');
        $this->assertSame(__('No Invoice Linked'), $row['invoice_number']);
        $this->assertSame('—', $row['invoice_amount'],
            'ما ينفعش يعرض 0.00 و كأن الفاتورة قيمتها صفر');
        $this->assertNotSame('0.00', $row['settlement_amount'],
            'مبلغ التسوية نفسه حقيقي و لازم يفضل باين');
    }
}
