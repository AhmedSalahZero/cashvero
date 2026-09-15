<?php

namespace Tests\Feature\Contracts;

use App\Http\Controllers\ContractsController;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CustomerInvoice;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * عمودَي "الفواتير المرتبطة" و "نسبة الإتمام" في صفحة العقود .
 *
 * * القاعدة اللي الاختبارات دي بتقفل عليها :
 * *   - القيمة المحسوبة هي صافي الفاتورة (بعد الضريبة)
 * *   - النسبة = الفواتير ÷ العقد
 * *   - النسبة بتتحسب بس لما كل الفواتير بعملة العقد نفسه ؛ غير كده
 * *     بنكتب العملات بدل رقم مالوش معنى
 *
 * * الحالات بتتبني هنا و بترجع (transaction) ، عشان الاختبار يثبت
 * * الحالات كلها مش اللي صادف وجوده في الداتا
 */
class LinkedInvoicesColumnTest extends TestCase
{
    private const COMPANY = 148;

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

        if (! Company::find(self::COMPANY)) {
            $this->markTestSkipped('Company '.self::COMPANY.' is not on file.');
        }
    }

    protected function tearDown(): void
    {
        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    /**
     * عقد بقيمة معلومة، و عليه الفواتير المطلوبة.
     *
     * @param  list<array{0: float, 1: float, 2: string}>  $invoices  [amount, vat, currency]
     */
    private function contractWith(float $contractAmount, string $contractCurrency, array $invoices): array
    {
        /**
         * * الكود لازم يكون فريد : الربط بين العقد و فواتيره بيتم بـ
         * * contract_code ، فكود متكرر يخلي عقد ياخد فواتير عقد تاني
         */
        $code = 'LI-TEST-'.uniqid();
        $partner = new Partner;
        $partner->forceFill([
            'company_id' => self::COMPANY,
            'name' => 'Linked Invoices Test',
            'is_customer' => 1,
        ])->save();

        $contract = new Contract;
        $contract->forceFill([
            'company_id' => self::COMPANY,
            'partner_id' => $partner->id,
            'model_type' => 'Customer',
            'status' => Contract::RUNNING,
            'name' => 'LI Test Contract',
            'code' => $code,
            'amount' => $contractAmount,
            'currency' => $contractCurrency,
            /**
             * * الصفحة بتترتب بـ start_date تنازلي و بتقسّم ٢٠ في الصفحة ،
             * * فتاريخ بعيد بيضمن إن العقد ده يبان في الصفحة الأولى بدل
             * * ما الاختبار يدوّر عليه في صفحة مش محمّلة
             */
            'start_date' => '2099-01-01',
        ])->save();

        foreach ($invoices as $i => [$amount, $vat, $currency]) {
            $invoice = new CustomerInvoice;
            $invoice->forceFill([
                'company_id' => self::COMPANY,
                'customer_id' => $partner->id,
                'invoice_number' => 'LI-'.$contract->id.'-'.$i,
                'invoice_date' => '2025-02-0'.($i + 1),
                'invoice_due_date' => '2025-05-0'.($i + 1),
                'invoice_amount' => $amount,
                'vat_amount' => $vat,
                'currency' => $currency,
                'exchange_rate' => 1,
                'contract_code' => $code,
                'contract_name' => 'LI Test Contract',
            ])->save();
        }

        return [$contract, $partner];
    }

    /** الصف اللي الكونترولر بيبعته للصفحة لعقد بعينه */
    private function rowFor(int $contractId): ?array
    {
        $request = Request::create('/x', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => User::first());
        $this->app->instance('request', $request);
        auth()->setUser(User::first());

        $props = app(ContractsController::class)
            ->index(Company::findOrFail(self::COMPANY), $request, 'Customer')
            ->toResponse($request)->original->getData()['page']['props'];

        foreach (($props['contracts'] ?? []) as $rows) {
            foreach ($rows as $row) {
                if ((int) $row['id'] === $contractId) {
                    return $row;
                }
            }
        }

        return null;
    }

    /** * عملة واحدة، نفس عملة العقد → مبلغ و نسبة */
    public function test_one_currency_shows_a_total_and_a_percentage(): void
    {
        DB::beginTransaction();

        try {
            // 2,000,000 + 1,000,000 قبل الضريبة ، و بعدها 3,420,000
            [$contract] = $this->contractWith(5_000_000, 'EGP', [
                [2_000_000, 280_000, 'EGP'],
                [1_000_000, 140_000, 'EGP'],
            ]);

            $row = $this->rowFor($contract->id);
            $this->assertNotNull($row, 'العقد المفروض يبان في الصفحة');

            $linked = $row['linked_invoices'];

            $this->assertTrue($linked['has_any']);
            $this->assertFalse($linked['is_mixed']);
            $this->assertSame('3,420,000.00 EGP', $linked['summary'],
                'المفروض صافي الفاتورة بعد الضريبة');
            $this->assertSame(68.4, $linked['completion_percentage'],
                '3,420,000 ÷ 5,000,000 = 68.4%');
            $this->assertSame('68.40%', $linked['completion_label']);
        } finally {
            DB::rollBack();
        }
    }

    /** * أكتر من عملة → مفيش إجمالي واحد ، و العملات بدل النسبة */
    public function test_mixed_currencies_show_a_breakdown_and_no_percentage(): void
    {
        DB::beginTransaction();

        try {
            [$contract] = $this->contractWith(5_000_000, 'EGP', [
                [2_000_000, 0, 'EGP'],
                [1_000_000, 0, 'USD'],
            ]);

            $linked = $this->rowFor($contract->id)['linked_invoices'];

            $this->assertTrue($linked['is_mixed']);
            $this->assertNull($linked['summary'], 'مجموع واحد لعملتين مالوش معنى');
            $this->assertNull($linked['completion_percentage']);
            $this->assertSame('EGP-USD', $linked['completion_label']);
            $this->assertCount(2, $linked['breakdown'], 'التفاصيل لازم تبقى سطر لكل عملة');

            $byCurrency = collect($linked['breakdown'])->keyBy('currency');
            $this->assertSame('2,000,000.00', $byCurrency['EGP']['total_formatted']);
            $this->assertSame('1,000,000.00', $byCurrency['USD']['total_formatted']);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * * عملة واحدة بس مش عملة العقد — نسبة من غير تحويل هتبقى رقم غلط ،
     * * فبتتعامل زي المختلطة
     */
    public function test_a_single_foreign_currency_is_treated_as_mixed(): void
    {
        DB::beginTransaction();

        try {
            [$contract] = $this->contractWith(5_000_000, 'EGP', [
                [3_000_000, 0, 'USD'],
            ]);

            $linked = $this->rowFor($contract->id)['linked_invoices'];

            $this->assertTrue($linked['is_mixed']);
            $this->assertNull($linked['completion_percentage'],
                'ما ينفعش نقسم دولار على جنيه');
            $this->assertSame('EGP-USD', $linked['completion_label']);
        } finally {
            DB::rollBack();
        }
    }

    /** * عقد من غير فواتير → مفيش مبلغ ، و النسبة صفر */
    public function test_a_contract_with_no_invoices_reads_zero(): void
    {
        DB::beginTransaction();

        try {
            [$contract] = $this->contractWith(5_000_000, 'EGP', []);

            $linked = $this->rowFor($contract->id)['linked_invoices'];

            $this->assertFalse($linked['has_any']);
            $this->assertNull($linked['summary']);
            $this->assertSame(0.0, $linked['completion_percentage']);
            $this->assertSame('0.00%', $linked['completion_label']);
        } finally {
            DB::rollBack();
        }
    }

    /** * عقد بقيمة صفر ما ينفعش يتقسم عليه */
    public function test_a_contract_with_no_value_has_no_percentage(): void
    {
        DB::beginTransaction();

        try {
            [$contract] = $this->contractWith(0, 'EGP', [
                [1_000_000, 0, 'EGP'],
            ]);

            $linked = $this->rowFor($contract->id)['linked_invoices'];

            $this->assertNull($linked['completion_percentage'], 'القسمة على صفر ممنوعة');
        } finally {
            DB::rollBack();
        }
    }

    /** * الفواتير الزيادة عن قيمة العقد بتتعرض زي ما هي ، فوق 100% */
    public function test_over_invoicing_goes_past_one_hundred_percent(): void
    {
        DB::beginTransaction();

        try {
            [$contract] = $this->contractWith(1_000_000, 'EGP', [
                [1_500_000, 0, 'EGP'],
            ]);

            $linked = $this->rowFor($contract->id)['linked_invoices'];

            $this->assertSame(150.0, $linked['completion_percentage']);
            $this->assertSame('150.00%', $linked['completion_label']);
        } finally {
            DB::rollBack();
        }
    }

    /** * و الصفحة معرّفة العمودين و بتعرضهم */
    public function test_the_page_renders_both_columns(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Contracts/Index.vue'));

        $this->assertStringContainsString("\$t('Linked Invoices')", $page);
        $this->assertStringContainsString("\$t('Completion')", $page);
        $this->assertStringContainsString('row.linked_invoices.summary', $page);
        $this->assertStringContainsString('row.linked_invoices.completion_label', $page);
        $this->assertStringContainsString('linkedBreakdown = row', $page,
            'لازم يكون فيه زرار بيفتح التفاصيل لما العملات تختلف');
    }

    /**
     * * الجدول زاد عمودين ، فالصفوف اللي بتمتد على عرضه لازم تكبر معاه —
     * * و إلا الأعمدة بتتزحلق
     */
    public function test_the_spanning_rows_match_the_new_column_count(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Contracts/Index.vue'));

        preg_match('/<thead class="cvr-table-head">(.*?)<\/thead>/s', $page, $head);
        $columns = preg_match_all('/<th /', $head[1] ?? '');

        $this->assertSame(9, $columns, 'المفروض ٩ أعمدة بعد الإضافة');
        $this->assertStringContainsString('colspan="9"', $page,
            'صف "مفيش عقود" لازم يمتد على الأعمدة كلها');
    }
}
