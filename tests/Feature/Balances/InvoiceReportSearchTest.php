<?php

namespace Tests\Feature\Balances;

use App\Http\Controllers\CustomerInvoiceDashboardController;
use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\Partner;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * صندوق البحث في تقرير الفواتير — برقم الفاتورة أو بالمبلغ .
 *
 * * الصفحة متقسّمة صفحات على مستوى الـ SQL ، فالبحث لازم يتم في
 * * الاستعلام نفسه ؛ فلترة الصفوف اللي على الشاشة كانت هتبحث في صفحة
 * * واحدة مش في الفواتير كلها — و ده اللي الاختبارات دي بتثبّته .
 *
 * * نفس الراوت بيخدم فواتير العملاء و الموردين ، فكل اختبار هنا بيشتغل
 * * على النوعين .
 */
class InvoiceReportSearchTest extends TestCase
{
    private ?string $originalDatabase = null;

    private const COMPANY = 148;

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
     * شريك بفاتورتين على الجهة المطلوبة ، جوه transaction بتترجع بعدين .
     *
     * @return array{0: int, 1: string, 2: string}  رقم الشريك ، و رقمي الفاتورتين
     */
    private function partnerWithTwoInvoices(string $modelType): array
    {
        $partner = new Partner;
        $partner->forceFill([
            'company_id' => self::COMPANY,
            'name' => 'Search Test Partner',
            'is_customer' => 1,
            'is_supplier' => 1,
        ])->save();

        $class = 'App\\Models\\'.$modelType;
        $column = $class::CLIENT_ID_COLUMN_NAME;

        foreach ([['SRCH-111', 100000, 14000], ['SRCH-222', 250000, 35000]] as [$number, $amount, $vat]) {
            $invoice = new $class;
            $invoice->forceFill([
                'company_id' => self::COMPANY,
                $column => $partner->id,
                'invoice_number' => $number,
                'invoice_date' => '2025-01-15',
                'invoice_due_date' => '2025-04-15',
                'invoice_amount' => $amount,
                'vat_amount' => $vat,
                'currency' => 'EGP',
                'exchange_rate' => 1,
            ])->save();
        }

        return [$partner->id, 'SRCH-111', 'SRCH-222'];
    }

    /**
     * @return array{0: int, 1: list<string>}  عدد النتائج و أرقام الفواتير
     */
    private function report(int $partnerId, string $modelType, string $search): array
    {
        $request = Request::create('/x', 'GET', $search === '' ? [] : ['search' => $search]);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => User::first());
        $this->app->instance('request', $request);

        $response = app(CustomerInvoiceDashboardController::class)
            ->showInvoiceReport(Company::findOrFail(self::COMPANY), $request, $partnerId, 'EGP', $modelType);

        if (! $response instanceof \Inertia\Response) {
            return [-1, []];   // اترمى برّه الصفحة
        }

        $props = $response->toResponse($request)->original->getData()['page']['props'];

        return [
            (int) $props['invoices']['total'],
            array_column($props['invoices']['data'], 'invoice_number'),
        ];
    }

    public static function invoiceTypes(): array
    {
        return ['customer' => ['CustomerInvoice'], 'supplier' => ['SupplierInvoice']];
    }

    /** @dataProvider invoiceTypes */
    public function test_it_finds_an_invoice_by_its_number(string $modelType): void
    {
        DB::beginTransaction();

        try {
            [$partnerId, $first] = $this->partnerWithTwoInvoices($modelType);

            $this->assertSame(2, $this->report($partnerId, $modelType, '')[0], 'الفاتورتين لازم يبانوا من غير بحث');

            [$total, $numbers] = $this->report($partnerId, $modelType, $first);

            $this->assertSame(1, $total, 'البحث برقم فاتورة كامل لازم يرجّع واحدة بس');
            $this->assertSame([$first], $numbers);
        } finally {
            DB::rollBack();
        }
    }

    /** جزء من الرقم بيضيّق النتائج ، ما بيرجّعش صفر */
    /** @dataProvider invoiceTypes */
    public function test_a_partial_number_narrows_instead_of_returning_nothing(string $modelType): void
    {
        DB::beginTransaction();

        try {
            [$partnerId] = $this->partnerWithTwoInvoices($modelType);

            $this->assertSame(2, $this->report($partnerId, $modelType, 'SRCH-')[0]);
            $this->assertSame(1, $this->report($partnerId, $modelType, '222')[0]);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * البحث بالمبلغ : المبلغ نفسه ، و المبلغ بعد الضريبة ، و بالفاصلة
     * الألفية اللي المستخدم بيشوفها على الشاشة
     *
     * @dataProvider invoiceTypes
     */
    public function test_it_finds_an_invoice_by_its_amount(string $modelType): void
    {
        DB::beginTransaction();

        try {
            [$partnerId, , $second] = $this->partnerWithTwoInvoices($modelType);

            foreach (['250000', '250,000', '285000', '285,000.00'] as $term) {
                [$total, $numbers] = $this->report($partnerId, $modelType, $term);

                $this->assertSame(1, $total, "البحث بـ \"{$term}\" لازم يوصل للفاتورة");
                $this->assertSame([$second], $numbers);
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * بحث ما لقاش حاجة لازم يفضل في الصفحة بنتيجة فاضية ، مش يرجّع
     * المستخدم لبرّه و يضيّع اللي كتبه
     *
     * @dataProvider invoiceTypes
     */
    public function test_a_search_that_matches_nothing_stays_on_the_page(string $modelType): void
    {
        DB::beginTransaction();

        try {
            [$partnerId] = $this->partnerWithTwoInvoices($modelType);

            [$total, $numbers] = $this->report($partnerId, $modelType, 'nothing-matches-this');

            $this->assertSame(0, $total, 'المفروض صفر نتيجة');
            $this->assertSame([], $numbers);
        } finally {
            DB::rollBack();
        }
    }

    /** و شريك مالوش فواتير أصلا لسه بيترجّع لبرّه زي ما كان */
    public function test_a_partner_with_no_invoices_at_all_is_still_sent_back(): void
    {
        DB::beginTransaction();

        try {
            $partner = new Partner;
            $partner->forceFill(['company_id' => self::COMPANY, 'name' => 'Empty', 'is_customer' => 1])->save();

            $this->assertSame(-1, $this->report($partner->id, 'CustomerInvoice', '')[0],
                'التقرير الفاضي من غير بحث لازم يفضل يرجّع المستخدم زي ما كان بيعمل');
        } finally {
            DB::rollBack();
        }
    }

    /** التصدير بينزّل اللي على الشاشة ، مش التقرير كله */
    public function test_the_export_link_carries_the_search(): void
    {
        DB::beginTransaction();

        try {
            [$partnerId] = $this->partnerWithTwoInvoices('CustomerInvoice');

            $request = Request::create('/x', 'GET', ['search' => 'SRCH-222']);
            $request->setLaravelSession(app('session.store'));
            $request->setUserResolver(fn () => User::first());
            $this->app->instance('request', $request);

            $props = app(CustomerInvoiceDashboardController::class)
                ->showInvoiceReport(Company::findOrFail(self::COMPANY), $request, $partnerId, 'EGP', 'CustomerInvoice')
                ->toResponse($request)->original->getData()['page']['props'];

            $this->assertStringContainsString('search=SRCH-222', urldecode($props['exportUrl']));
            $this->assertSame('SRCH-222', $props['filters']['search'], 'اللي المستخدم كتبه لازم يرجع للصندوق');
        } finally {
            DB::rollBack();
        }
    }

    /** الصفحة بتستقبل البروبس الجديدة فعلا */
    public function test_the_page_declares_the_search_props(): void
    {
        $component = file_get_contents(resource_path('js/Pages/Balances/InvoiceReport.vue'));
        preg_match('/defineProps\(\s*\{(.*?)\n\}\)/s', $component, $matches);

        foreach (['filters', 'indexUrl'] as $prop) {
            $this->assertMatchesRegularExpression('/^\s*'.$prop.'\s*:/m', $matches[1] ?? '',
                "InvoiceReport.vue بياخد `{$prop}` لكن مش معرّفه");
        }

        $this->assertStringContainsString("router.get(props.indexUrl", $component,
            'صندوق البحث لازم يرجع للسيرفر ، مش يفلتر الصفحة اللي قدامه');
    }
}
