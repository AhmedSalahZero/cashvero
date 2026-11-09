<?php

namespace Tests\Feature\Balances;

use App\Http\Controllers\CustomerInvoiceDashboardController;
use App\Models\Company;
use App\Models\User;
use Database\Factories\PartnerFactory;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesInvoices;
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
    use MakesInvoices;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
        $this->user = $this->makeUser([], $this->company);
    }

    /**
     * شريك بفاتورتين على الجهة المطلوبة
     *
     * @return array{0: int, 1: string, 2: string}  رقم الشريك ، و رقمي الفاتورتين
     */
    private function partnerWithTwoInvoices(string $modelType): array
    {
        $partner = PartnerFactory::new()->forCompany($this->company)->customer()->supplier()
            ->create(['name' => 'Search Test Partner']);

        foreach ([['SRCH-111', 100000, 14000], ['SRCH-222', 250000, 35000]] as [$number, $amount, $vat]) {
            $attributes = [
                'invoice_number' => $number,
                'invoice_date' => '2025-01-15',
                'invoice_due_date' => '2025-04-15',
                'invoice_amount' => $amount,
                'vat_amount' => $vat,
                'currency' => 'EGP',
                'exchange_rate' => 1,
            ];

            $modelType === 'CustomerInvoice'
                ? $this->invoice($partner, $attributes)
                : $this->supplierInvoice($partner, $attributes);
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
        $request->setUserResolver(fn () => $this->user);
        $this->app->instance('request', $request);

        $response = app(CustomerInvoiceDashboardController::class)
            ->showInvoiceReport($this->company, $request, $partnerId, 'EGP', $modelType);

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

    #[DataProvider('invoiceTypes')]
    public function test_it_finds_an_invoice_by_its_number(string $modelType): void
    {
        [$partnerId, $first] = $this->partnerWithTwoInvoices($modelType);

        $this->assertSame(2, $this->report($partnerId, $modelType, '')[0], 'الفاتورتين لازم يبانوا من غير بحث');

        [$total, $numbers] = $this->report($partnerId, $modelType, $first);

        $this->assertSame(1, $total, 'البحث برقم فاتورة كامل لازم يرجّع واحدة بس');
        $this->assertSame([$first], $numbers);
    }

    /** جزء من الرقم بيضيّق النتائج ، ما بيرجّعش صفر */
    #[DataProvider('invoiceTypes')]
    public function test_a_partial_number_narrows_instead_of_returning_nothing(string $modelType): void
    {
        [$partnerId] = $this->partnerWithTwoInvoices($modelType);

        $this->assertSame(2, $this->report($partnerId, $modelType, 'SRCH-')[0]);
        $this->assertSame(1, $this->report($partnerId, $modelType, '222')[0]);
    }

    /**
     * البحث بالمبلغ : المبلغ نفسه ، و المبلغ بعد الضريبة ، و بالفاصلة
     * الألفية اللي المستخدم بيشوفها على الشاشة
     */
    #[DataProvider('invoiceTypes')]
    public function test_it_finds_an_invoice_by_its_amount(string $modelType): void
    {
        [$partnerId, , $second] = $this->partnerWithTwoInvoices($modelType);

        foreach (['250000', '250,000', '285000', '285,000.00'] as $term) {
            [$total, $numbers] = $this->report($partnerId, $modelType, $term);

            $this->assertSame(1, $total, "البحث بـ \"{$term}\" لازم يوصل للفاتورة");
            $this->assertSame([$second], $numbers);
        }
    }

    /**
     * بحث ما لقاش حاجة لازم يفضل في الصفحة بنتيجة فاضية ، مش يرجّع
     * المستخدم لبرّه و يضيّع اللي كتبه
     */
    #[DataProvider('invoiceTypes')]
    public function test_a_search_that_matches_nothing_stays_on_the_page(string $modelType): void
    {
        [$partnerId] = $this->partnerWithTwoInvoices($modelType);

        [$total, $numbers] = $this->report($partnerId, $modelType, 'nothing-matches-this');

        $this->assertSame(0, $total, 'المفروض صفر نتيجة');
        $this->assertSame([], $numbers);
    }

    /** و شريك مالوش فواتير أصلا لسه بيترجّع لبرّه زي ما كان */
    public function test_a_partner_with_no_invoices_at_all_is_still_sent_back(): void
    {
        $partner = $this->customer($this->company, ['name' => 'Empty']);

        $this->assertSame(-1, $this->report($partner->id, 'CustomerInvoice', '')[0],
            'التقرير الفاضي من غير بحث لازم يفضل يرجّع المستخدم زي ما كان بيعمل');
    }

    /** التصدير بينزّل اللي على الشاشة ، مش التقرير كله */
    public function test_the_export_link_carries_the_search(): void
    {
        [$partnerId] = $this->partnerWithTwoInvoices('CustomerInvoice');

        $request = Request::create('/x', 'GET', ['search' => 'SRCH-222']);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $this->user);
        $this->app->instance('request', $request);

        $props = app(CustomerInvoiceDashboardController::class)
            ->showInvoiceReport($this->company, $request, $partnerId, 'EGP', 'CustomerInvoice')
            ->toResponse($request)->original->getData()['page']['props'];

        $this->assertStringContainsString('search=SRCH-222', urldecode($props['exportUrl']));
        $this->assertSame('SRCH-222', $props['filters']['search'], 'اللي المستخدم كتبه لازم يرجع للصندوق');
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
