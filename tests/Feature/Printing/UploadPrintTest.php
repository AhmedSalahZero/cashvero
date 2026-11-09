<?php

namespace Tests\Feature\Printing;

use App\Http\Controllers\SalesGatheringController;
use App\Models\Company;
use App\Models\CustomizedFieldsExportation;
use App\Models\User;
use App\Traits\Reports\PrintsReport;
use Database\Factories\CustomerInvoiceFactory;
use Database\Factories\SupplierInvoiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * * الطباعة في صفحات الرفع (uploading) .
 *
 * * الفرق هنا عن باقي التقارير إن المستخدم بيختار الفترة الأول من مودال ،
 * * لأن الجداول دي فيها سنين من الصفوف و "اطبع كل حاجة" مش اللي حد
 * * بيقصده غالبا . و زي باقي التقارير ، اللي بيتطبع هو الفترة كلها مش
 * * الصفحة اللي على الشاشة .
 *
 * * الشركة و أعمدتها و الفواتير بيتعملوا في الاختبار نفسه .
 */
class UploadPrintTest extends TestCase
{
    use MakesInvoices;

    /** صفحة الجدول على الشاشة بتعرض ٥٠ صف */
    private const PAGE_LENGTH = 50;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
        $this->user = $this->makeUser([
            'view customer invoice analysis data',
            'export customer invoice analysis data',
            'view supplier invoice analysis data',
            'export supplier invoice analysis data',
        ], $this->company);

        // الأعمدة اللي الشركة مختاراها للجدول و التصدير
        foreach ([
            'CustomerInvoice' => ['customer_name', 'invoice_number', 'invoice_date', 'invoice_due_date', 'currency', 'invoice_amount', 'vat_amount'],
            'SupplierInvoice' => ['supplier_name', 'invoice_number', 'invoice_date', 'invoice_due_date', 'currency', 'invoice_amount', 'vat_amount'],
        ] as $model => $fields) {
            CustomizedFieldsExportation::create(['company_id' => $this->company->id, 'model_name' => $model, 'fields' => $fields]);
        }
    }

    /**
     * * صفوف أكتر من صفحة واحدة : ٥٠ في ٢٠٢٣ و ٥ في ٢٠٢٢ — عشان الطباعة
     * * يبان إنها بتطلع الجدول كله ، و الفترة يبان إنها بتضيّق فعلا
     *
     * @return int عدد الصفوف
     */
    private function rowsOnFile(string $model): int
    {
        $sequence = fn ($s) => ['invoice_date' => $s->index < 5
            ? sprintf('2022-%02d-15', $s->index + 1)
            : sprintf('2023-%02d-%02d', ($s->index % 12) + 1, ($s->index % 28) + 1)];

        $model === 'CustomerInvoice'
            ? CustomerInvoiceFactory::new()->forCustomer($this->customer($this->company))->count(self::PAGE_LENGTH + 5)->sequence($sequence)->create()
            : SupplierInvoiceFactory::new()->forSupplier($this->supplier($this->company))->count(self::PAGE_LENGTH + 5)->sequence($sequence)->create();

        // و صفوف شركة تانية ما تدخلش في الطباعة
        CustomerInvoiceFactory::new()->forCustomer($this->customer($this->makeCompany()))->create(['invoice_date' => '2022-06-15']);
        SupplierInvoiceFactory::new()->forSupplier($this->supplier($this->makeCompany()))->create(['invoice_date' => '2022-06-15']);

        return self::PAGE_LENGTH + 5;
    }

    private function props(string $method, string $model, array $query = []): ?array
    {
        /**
         * * سكوب الشركة بيتقرا من الريكوست ، فلازم يتبعت — من غيره
         * * الاستعلام بيرجع فاضي و الاختبار يعدّي على حاجة مش صح
         */
        $request = Request::create('/x', 'GET', $query + ['company_id' => $this->company->id]);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $this->user);
        $this->app->instance('request', $request);
        auth()->setUser($this->user);

        $response = app(SalesGatheringController::class)->{$method}($this->company, $request, $model);

        if (! $response instanceof \Inertia\Response) {
            return null;
        }

        return $response->toResponse($request)->original->getData()['page']['props'];
    }

    public static function models(): array
    {
        return [
            'customer invoice' => ['CustomerInvoice'],
            'supplier invoice' => ['SupplierInvoice'],
        ];
    }

    public function test_the_print_route_exists(): void
    {
        $route = Route::getRoutes()->getByName('print.uploading');

        $this->assertNotNull($route);
        $this->assertStringContainsString('@print', $route->getActionName());
    }

    /**
     * * ⚠️ الراوت لازم يفضل فوق uploading/{model}/{loanId?} : البارامتر
     * * الاختياري ده بيبلع أي سيجمنت ، فـ /print كان هيتقرا كـ loanId و
     * * الصفحة تفتح بدل الطباعة
     */
    public function test_the_print_route_is_matched_before_the_optional_parameter(): void
    {
        $matched = null;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (preg_match('#uploading/#', $route->uri())) {
                $matched = $route->getName();
                break;
            }
        }

        $this->assertSame('print.uploading', $matched,
            'راوت الطباعة لازم يتسجّل قبل الراوت اللي فيه {loanId?}');
    }

    #[DataProvider('models')]
    public function test_the_page_is_given_a_print_url(string $model): void
    {
        $props = $this->props('index', $model);

        $this->assertNotNull($props);
        $this->assertArrayHasKey('printUrl', $props);
        $this->assertStringContainsString('/print', $props['printUrl']);
    }

    /**
     * * اللي بيتطبع هو كل صفوف الفترة ، مش الصفحة اللي على الشاشة
     */
    #[DataProvider('models')]
    public function test_print_covers_every_row_not_just_the_page(string $model): void
    {
        $made = $this->rowsOnFile($model);

        $index = $this->props('index', $model);
        $print = $this->props('print', $model);

        $total = (int) $index['pagination']['total'];

        $this->assertSame($made, $total, 'الجدول على الشاشة بيعد صفوف الشركة دي بس');

        $this->assertLessThan($total + 1, count($index['rows']));
        $this->assertSame($total, count($print['rows']),
            'الطباعة لازم تطلع الجدول كله');

        // و لو الصفحة أقل من الإجمالي ، يبقى فعلا فيه أكتر من صفحة
        if (count($index['rows']) < $total) {
            $this->assertGreaterThan(count($index['rows']), count($print['rows']),
                'الطباعة رجّعت صفحة واحدة بس');
        }
    }

    /** * الفترة بتضيّق فعلا ، و بتتكتب في ورقة الطباعة */
    #[DataProvider('models')]
    public function test_the_chosen_period_narrows_the_printout(string $model): void
    {
        $this->rowsOnFile($model);

        $all = $this->props('print', $model);

        $this->assertNotSame([], $all['rows']);

        $window = $this->props('print', $model, ['from' => '2022-01-01', 'to' => '2022-12-31']);

        $this->assertLessThanOrEqual(count($all['rows']), count($window['rows']));
        $this->assertCount(5, $window['rows'], 'الصفوف الخمسة بتاعة ٢٠٢٢ بس');
        $this->assertSame('2022-01-01 → 2022-12-31', $window['meta'][0]['value'],
            'الورقة لازم تقول الفترة اللي اتطبعت');
    }

    /**
     * * فترة مالهاش صفوف لازم تطلع ورقة فاضية .
     *
     * * الاختبار ده هو اللي بيثبت إن الفلتر شغال أصلا : "أقل من أو
     * * يساوي" في الاختبار اللي فوق بيعدّي حتى لو الفلتر اتشال خالص ،
     * * لأن العدد بيفضل زي ما هو .
     */
    #[DataProvider('models')]
    public function test_a_period_with_no_rows_prints_nothing(string $model): void
    {
        $this->rowsOnFile($model);

        $all = $this->props('print', $model);

        $this->assertNotSame([], $all['rows']);

        $empty = $this->props('print', $model, ['from' => '2099-01-01', 'to' => '2099-12-31']);

        $this->assertSame([], $empty['rows'],
            'الفترة اللي مالهاش صفوف لازم تطبع فاضي — لو رجّعت صفوف يبقى الفلتر مش بيتطبّق');
    }

    /** * فترة مقلوبة بتتظبط بدل ما ترجّع فاضي */
    public function test_a_backwards_period_is_swapped(): void
    {
        $this->rowsOnFile('CustomerInvoice');

        $forward = $this->props('print', 'CustomerInvoice', ['from' => '2022-01-01', 'to' => '2022-12-31']);
        $backward = $this->props('print', 'CustomerInvoice', ['from' => '2022-12-31', 'to' => '2022-01-01']);

        $this->assertNotSame([], $forward['rows'], 'الفترة فيها صفوف، فلو المقلوبة رجّعت فاضي يبان');
        $this->assertSame(count($forward['rows']), count($backward['rows']));
    }

    /** * الأعمدة هي نفس اللي المستخدم مختارها للجدول و التصدير */
    #[DataProvider('models')]
    public function test_the_columns_match_the_screen(string $model): void
    {
        $index = $this->props('index', $model);
        $print = $this->props('print', $model);

        $screen = array_column($index['columns'], 'label');

        // ورقة الطباعة بتزوّد عمود الترقيم في الأول
        $this->assertSame(array_merge(['#'], $screen), $print['headings']);
    }

    /** * بيطبع من خلال نفس ورقة الطباعة المشتركة */
    public function test_it_uses_the_shared_print_sheet(): void
    {
        $this->assertContains(PrintsReport::class, class_uses_recursive(SalesGatheringController::class));

        $body = (new \ReflectionMethod(SalesGatheringController::class, 'print'));
        $lines = file($body->getFileName());
        $source = implode('', array_slice($lines, $body->getStartLine() - 1, $body->getEndLine() - $body->getStartLine() + 1));

        $this->assertStringContainsString('renderReportPrint(', $source);
        $this->assertStringNotContainsString('->paginate(', $source);
    }

    /** * و الصفحة فيها الزرار و المودال */
    public function test_the_page_offers_the_dialog(): void
    {
        $page = file_get_contents(resource_path('js/Pages/InvoiceUpload/Index.vue'));

        $this->assertStringContainsString('openPrintDialog', $page);
        $this->assertStringContainsString('printDialogOpen', $page);
        $this->assertStringContainsString('submitPrint', $page);
        $this->assertStringContainsString("\$t('Choose the period to print.')", $page);
    }
}
