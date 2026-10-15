<?php

namespace Tests\Feature\Printing;

use App\Http\Controllers\SalesGatheringController;
use App\Models\Company;
use App\Models\User;
use App\Traits\Reports\PrintsReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * * الطباعة في صفحات الرفع (uploading) .
 *
 * * الفرق هنا عن باقي التقارير إن المستخدم بيختار الفترة الأول من مودال ،
 * * لأن الجداول دي فيها سنين من الصفوف و "اطبع كل حاجة" مش اللي حد
 * * بيقصده غالبا . و زي باقي التقارير ، اللي بيتطبع هو الفترة كلها مش
 * * الصفحة اللي على الشاشة .
 */
class UploadPrintTest extends TestCase
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

    private function props(string $method, string $model, array $query = []): ?array
    {
        $company = Company::findOrFail(self::COMPANY);

        /**
         * * سكوب الشركة بيتقرا من الريكوست ، فلازم يتبعت — من غيره
         * * الاستعلام بيرجع فاضي و الاختبار يعدّي على حاجة مش صح
         */
        $request = Request::create('/x', 'GET', $query + ['company_id' => $company->id]);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => User::first());
        $this->app->instance('request', $request);
        auth()->setUser(User::first());

        $response = app(SalesGatheringController::class)->{$method}($company, $request, $model);

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

    /** @dataProvider models */
    public function test_the_page_is_given_a_print_url(string $model): void
    {
        $props = $this->props('index', $model);

        $this->assertNotNull($props);
        $this->assertArrayHasKey('printUrl', $props);
        $this->assertStringContainsString('/print', $props['printUrl']);
    }

    /**
     * * اللي بيتطبع هو كل صفوف الفترة ، مش الصفحة اللي على الشاشة
     *
     * @dataProvider models
     */
    public function test_print_covers_every_row_not_just_the_page(string $model): void
    {
        $index = $this->props('index', $model);
        $print = $this->props('print', $model);

        $total = (int) $index['pagination']['total'];

        if ($total === 0) {
            $this->markTestSkipped("No {$model} rows on file.");
        }

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
    /** @dataProvider models */
    public function test_the_chosen_period_narrows_the_printout(string $model): void
    {
        $all = $this->props('print', $model);

        if (count($all['rows']) === 0) {
            $this->markTestSkipped("No {$model} rows on file.");
        }

        $window = $this->props('print', $model, ['from' => '2022-01-01', 'to' => '2022-12-31']);

        $this->assertLessThanOrEqual(count($all['rows']), count($window['rows']));
        $this->assertSame('2022-01-01 → 2022-12-31', $window['meta'][0]['value'],
            'الورقة لازم تقول الفترة اللي اتطبعت');
    }

    /**
     * * فترة مالهاش صفوف لازم تطلع ورقة فاضية .
     *
     * * الاختبار ده هو اللي بيثبت إن الفلتر شغال أصلا : "أقل من أو
     * * يساوي" في الاختبار اللي فوق بيعدّي حتى لو الفلتر اتشال خالص ،
     * * لأن العدد بيفضل زي ما هو .
     *
     * @dataProvider models
     */
    public function test_a_period_with_no_rows_prints_nothing(string $model): void
    {
        $all = $this->props('print', $model);

        if (count($all['rows']) === 0) {
            $this->markTestSkipped("No {$model} rows on file.");
        }

        $empty = $this->props('print', $model, ['from' => '2099-01-01', 'to' => '2099-12-31']);

        $this->assertSame([], $empty['rows'],
            'الفترة اللي مالهاش صفوف لازم تطبع فاضي — لو رجّعت صفوف يبقى الفلتر مش بيتطبّق');
    }

    /** * فترة مقلوبة بتتظبط بدل ما ترجّع فاضي */
    public function test_a_backwards_period_is_swapped(): void
    {
        $forward = $this->props('print', 'CustomerInvoice', ['from' => '2022-01-01', 'to' => '2022-12-31']);
        $backward = $this->props('print', 'CustomerInvoice', ['from' => '2022-12-31', 'to' => '2022-01-01']);

        $this->assertSame(count($forward['rows']), count($backward['rows']));
    }

    /** * الأعمدة هي نفس اللي المستخدم مختارها للجدول و التصدير */
    /** @dataProvider models */
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
