<?php

namespace Tests\Feature\Settlements;

use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
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

    private function company(): Company
    {
        $company = Company::first();

        if (! $company) {
            $this->markTestSkipped('No company on file.');
        }

        return $company;
    }

    /**
     * * بيستخدم حركة موجودة فعلا من غير حفظ أي حاجة : الاستثناء بيتظبط قبل
     * * أي كتابة ، فالداتا ما بتتلمسش
     */
    private function existingMoneyReceived(): MoneyReceived
    {
        $money = MoneyReceived::first();

        if (! $money) {
            $this->markTestSkipped('No money received on file.');
        }

        return $money;
    }

    /* ───────────── الرفض ───────────── */

    public function test_it_refuses_a_settlement_with_no_invoice_id(): void
    {
        $money = $this->existingMoneyReceived();
        $before = DB::table('settlements')->count();

        /**
         * * الترانزاكشن هنا مش تجميل : لو الحارس اتشال (وقت اختبار الطفرات)
         * * الاستدعاء ده بيكتب صف فعلا في قاعدة التطوير — الرول باك بيضمن
         * * ان الاختبار ما يسيبش أثر مهما كان الكود تحته
         */
        DB::beginTransaction();

        try {
            $money->storeNewSettlement(
                [['settlement_amount' => 26150, 'withhold_amount' => 0]],
                (int) $money->partner_id,
                $this->company()
            );
            $this->fail('كان لازم يرفض التسوية من غير فاتورة');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertSame($before, DB::table('settlements')->count(),
            'ما ينفعش يكون كتب صف قبل ما يرمي الاستثناء');
    }

    public function test_it_refuses_a_settlement_whose_invoice_was_deleted(): void
    {
        $money = $this->existingMoneyReceived();
        $missingId = ((int) CustomerInvoice::max('id')) + 10_000;
        $before = DB::table('settlements')->count();

        DB::beginTransaction();

        try {
            $money->storeNewSettlement(
                [['invoice_id' => $missingId, 'settlement_amount' => 500]],
                (int) $money->partner_id,
                $this->company()
            );
            $this->fail('كان لازم يرفض فاتورة مش موجودة');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertSame($before, DB::table('settlements')->count());
    }

    /**
     * * الصفوف اللي مبلغها صفر بتتخطى من قبل الحارس أصلا ، فما ينفعش الحارس
     * * يبوّظ الحالة دي و يرمي استثناء عليها
     */
    public function test_a_zero_amount_row_is_skipped_not_rejected(): void
    {
        $money = $this->existingMoneyReceived();
        $before = DB::table('settlements')->count();

        DB::beginTransaction();

        try {
            $result = $money->storeNewSettlement(
                [['settlement_amount' => 0, 'withhold_amount' => 0]],
                (int) $money->partner_id,
                $this->company()
            );

            $this->assertSame([], $result['settlements']);
        } finally {
            DB::rollBack();
        }

        $this->assertSame($before, DB::table('settlements')->count());
    }

    public function test_an_empty_list_is_accepted(): void
    {
        $money = $this->existingMoneyReceived();

        $result = $money->storeNewSettlement([], (int) $money->partner_id, $this->company());

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
        $supplierInvoiceId = SupplierInvoice::max('id');
        $customerInvoiceId = CustomerInvoice::max('id');

        if (! $supplierInvoiceId || ! $customerInvoiceId) {
            $this->markTestSkipped('Both invoice tables need at least one row.');
        }

        $moneyReceived = $this->existingMoneyReceived();

        $onlySupplierSide = ! CustomerInvoice::whereKey($supplierInvoiceId)->exists();

        if (! $onlySupplierSide) {
            $this->markTestSkipped('That supplier invoice id also exists on the customer side.');
        }

        $before = DB::table('settlements')->count();

        DB::beginTransaction();

        try {
            $moneyReceived->storeNewSettlement(
                [['invoice_id' => $supplierInvoiceId, 'settlement_amount' => 100]],
                (int) $moneyReceived->partner_id,
                $this->company()
            );
            $this->fail('فاتورة مورد ما ينفعش تتقبل على ماني ريسيد');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertSame($before, DB::table('settlements')->count());
    }

    /**
     * * و الاتجاه التاني : فاتورة المورد لازم **تعدّي** على الماني بايمنت —
     * * من غير الاختبار ده لو الكود بص على جدول العملاء دايمًا الاختبار
     * * اللي فوق هيفضل ناجح (لأن الرقم مش موجود في الجدولين)
     *
     * * كل ده جوه ترانزاكشن بترجع ، فمفيش صف بيتكتب فعلا
     */
    public function test_a_supplier_invoice_is_accepted_on_a_money_payment(): void
    {
        $supplierInvoiceId = SupplierInvoice::max('id');
        $money = MoneyPayment::first();

        if (! $supplierInvoiceId || ! $money) {
            $this->markTestSkipped('Needs a supplier invoice and a money payment on file.');
        }

        $this->assertFalse(
            CustomerInvoice::whereKey($supplierInvoiceId)->exists(),
            'الاختبار ده معناه يضيع لو نفس الرقم موجود في جدول فواتير العملاء'
        );

        DB::beginTransaction();

        try {
            $result = $money->storeNewSettlement(
                [['invoice_id' => $supplierInvoiceId, 'settlement_amount' => 1]],
                (int) $money->partner_id,
                $this->company(),
                false,
                false
            );

            $this->assertCount(1, $result['settlements'],
                'فاتورة المورد لازم تتقبل على الماني بايمنت');
        } finally {
            DB::rollBack();
        }
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

    /**
     * * الصفوف القديمة اللي فاتورتها ناقصة لسه موجودة ، فالبوب اب لازم يقول
     * * السبب بدل ما يعرض فاتورة بصفر
     */
    public function test_the_popup_names_the_reason_instead_of_showing_a_zero_invoice(): void
    {
        $orphanSettlement = DB::table('settlements')
            ->whereRaw('coalesce(settlement_amount, 0) <> 0')
            ->whereNull('invoice_id')
            ->first();

        $table = 'settlements';
        $fk = 'money_received_id';
        $model = MoneyReceived::class;

        if (! $orphanSettlement) {
            $orphanSettlement = DB::table('payment_settlements')
                ->whereRaw('coalesce(settlement_amount, 0) <> 0')
                ->whereNull('invoice_id')
                ->first();
            $table = 'payment_settlements';
            $fk = 'money_payment_id';
            $model = MoneyPayment::class;
        }

        if (! $orphanSettlement) {
            $this->markTestSkipped('No invoice-less settlement on file to render.');
        }

        $money = $model::find($orphanSettlement->{$fk});

        if (! $money) {
            $this->markTestSkipped('The owning money row is gone.');
        }

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
