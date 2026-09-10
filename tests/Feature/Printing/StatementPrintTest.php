<?php

namespace Tests\Feature\Printing;

use App\Traits\Reports\PrintsReport;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * * الطباعة في تقارير الـ statements .
 *
 * * الشرط الأساسي اللي العميل طلبه هو إن الطباعة تطلع التقرير كله مش
 * * الصفحة اللي قدامي . الطريقة اللي بتضمن ده مش إننا نفتكر نعمله في كل
 * * تقرير ، لكن إن الطباعة و التصدير بيقروا من نفس الميثود
 * * (reportPayload) — و التصدير أصلا بيشتغل على المدى كله من غير تقسيم
 * * صفحات . الاختبارات دي بتقفل على الترتيبة دي .
 */
class StatementPrintTest extends TestCase
{
    private const COMPANY = 148;

    private ?string $originalDatabase = null;

    /**
     * * الاختبار اللي بينادي التقارير فعلا محتاج قاعدة فيها بيانات —
     * * قاعدة الاختبارات فاضية ، فبنشاور على قاعدة التطوير و بنقرا منها
     * * بس (مفيش أي كتابة هنا)
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');
    }

    protected function tearDown(): void
    {
        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    /**
     * كل تقرير : الكونترولر ، و اسم راوت الطباعة
     *
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function reports(): array
    {
        return [
            'safe' => [\App\Http\Controllers\SafeStatementController::class, 'print.safe.statement'],
            'bank' => [\App\Http\Controllers\BankStatementController::class, 'print.bank.statement'],
            'cash expense' => [\App\Http\Controllers\CashExpenseStatementController::class, 'print.cash.expense.statement'],
            'factoring' => [\App\Http\Controllers\FactoringStatementController::class, 'print.factoring.statement'],
            'factoring charges' => [\App\Http\Controllers\FactoringChargesStatementController::class, 'print.factoring.charges.statement'],
            'partners' => [\App\Http\Controllers\PartnersStatementController::class, 'print.partners.statement'],
            'withdrawals' => [\App\Http\Controllers\WithdrawalsSettlementReportController::class, 'print.withdrawals.settlement.report'],
            'lg / lc' => [\App\Http\Controllers\LGLCSBanktatementController::class, 'print.lg.lc.bank.statement'],
            'lg by bank' => [\App\Http\Controllers\LgByBankNameReportController::class, 'print.lg.by.bank.name.report'],
            'lg by beneficiary' => [\App\Http\Controllers\LgByBeneficiaryNameReportController::class, 'print.lg.by.beneficiary.name.report'],
            'cash cover' => [\App\Http\Controllers\CashCoverStatementController::class, 'print.cash.cover.statement'],
        ];
    }

    /** @dataProvider reports */
    public function test_the_report_offers_a_print_action(string $controller, string $routeName): void
    {
        $this->assertTrue(method_exists($controller, 'print'),
            class_basename($controller).' مالوش ميثود print');

        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "الراوت {$routeName} مش متسجّل");
        $this->assertStringContainsString('@print', $route->getActionName());
    }

    /**
     * * الطباعة و التصدير لازم يقروا من نفس المصدر — لو اتفصلوا ، واحد
     * * فيهم هيفضل شغّال و التاني هيقع بهدوء من غير ما حد ياخد باله
     *
     * @dataProvider reports
     */
    public function test_print_and_export_read_the_same_payload(string $controller): void
    {
        $source = file_get_contents((new \ReflectionClass($controller))->getFileName());

        /* التقرير اللي مالوش تصدير إكسل مفيش payload مشترك يتقارن بيه —
           الضمان بتاعه إن print() نفسها ما بتقسّمش، و ده متغطّى تحت */
        if (! method_exists($controller, 'reportPayload')) {
            $this->assertStringNotContainsString('->paginate(', $this->methodBody($controller, 'print'),
                class_basename($controller).' بيقسّم صفحات في الطباعة');

            return;
        }

        $this->assertMatchesRegularExpression('/private function reportPayload\(/', $source,
            class_basename($controller).' لازم يكون فيه مصدر واحد للأعمدة و الصفوف');

        foreach (['print', 'exportExcel'] as $method) {
            $body = $this->methodBody($controller, $method);

            $this->assertStringContainsString('$this->reportPayload(', $body,
                class_basename($controller)."::{$method}() مش بيقرا من reportPayload");
        }
    }

    /**
     * * و الصفوف بتتجاب من غير paginate — دي هي "كل الصفحات" نفسها
     *
     * @dataProvider reports
     */
    public function test_the_payload_is_not_paginated(string $controller): void
    {
        /* التقرير اللي مالوش تصدير إكسل مالوش payload مشترك ، فالفحص
           بيتم على print() نفسها */
        $body = $this->methodBody($controller, $this->payloadMethod($controller));

        /* الكلام المكتوب في التعليقات فيه كلمة "unpaginated" ، فبندوّر
           على النداء نفسه مش على الكلمة */
        $this->assertStringNotContainsString('->paginate(', $body,
            class_basename($controller).' بيقسّم صفحات في الطباعة — المفروض المدى كله');
        $this->assertStringNotContainsString('->limit(', $body,
            class_basename($controller).' بيحدّ الصفوف في الطباعة');
    }

    /**
     * * و الطباعة نفسها ما تقصّش الصفوف بعد ما تجيبها .
     *
     * * الاختبار اللي فوق بيحمي المصدر ، لكن `print()` نفسها ممكن تاخد
     * * أول ٢٥ صف و التقرير يفضل "غير مقسّم" على الورق و ناقص فعليا
     *
     * @dataProvider reports
     */
    public function test_print_does_not_trim_the_rows(string $controller): void
    {
        $body = $this->methodBody($controller, 'print');

        foreach (['->take(', '->slice(', '->limit(', '->forPage(', 'array_slice('] as $trim) {
            $this->assertStringNotContainsString($trim, $body,
                class_basename($controller)."::print() بيقصّ الصفوف بـ {$trim} — المفروض التقرير كله");
        }
    }

    /** * و الصفحة بتبعت رابط الطباعة للواجهة */
    /** @dataProvider reports */
    public function test_the_result_page_is_given_a_print_url(string $controller): void
    {
        $source = file_get_contents((new \ReflectionClass($controller))->getFileName());

        $this->assertStringContainsString("'printUrl'", $source,
            class_basename($controller).' مش بيبعت printUrl للواجهة');
    }

    /** * الكونترولر بيستعمل التريت المشترك ، فالشكل واحد في كل التقارير */
    /** @dataProvider reports */
    public function test_the_controller_uses_the_shared_print_sheet(string $controller): void
    {
        $this->assertContains(PrintsReport::class, class_uses_recursive($controller),
            class_basename($controller).' بيطبع بطريقته الخاصة بدل الشكل المشترك');
    }


    /**
     * * زرار الطباعة لازم يكون في الصفحة نفسها — الكونترولر ممكن يبعت
     * * الرابط و الواجهة ما تعرضهوش ، و ساعتها الميزة مش موجودة فعليا
     */
    public function test_every_result_page_offers_the_print_button(): void
    {
        $missing = [];

        foreach (glob(resource_path('js/Pages/Statements/*/Result.vue')) as $page) {
            $source = file_get_contents($page);

            if (! str_contains($source, 'urls.printUrl')) {
                $missing[] = basename(dirname($page));
            }
        }

        $this->assertSame([], $missing, 'الصفحات دي مالهاش زرار طباعة: '.implode(', ', $missing));
    }

    /**
     * * زرار التصدير و زرار الطباعة لازم يفضلوا مجموعة واحدة .
     *
     * * صف العنوان معمول بـ justify-between ، اللي بيوزّع المسافة بين
     * * عناصره . بعنصرين (عنوان + تصدير) ده بيبقى تمام ؛ أول ما ضفنا
     * * الطباعة كعنصر تالت ، الثلاثة اتوزّعوا و وقعت مسافة بين الزرارين .
     * * الحل إنهم يبقوا جوه حاوية واحدة ، فبيعدّوا كعنصر واحد .
     */
    public function test_the_export_and_print_buttons_stay_together(): void
    {
        $offenders = [];

        foreach (glob(resource_path('js/Pages/Statements/*/Result.vue')) as $page) {
            $source = file_get_contents($page);

            if (! str_contains($source, 'urls.printUrl') || ! str_contains($source, 'urls.exportUrl')) {
                continue;
            }

            /**
             * * بناخد الجزء اللي بين الزرارين : لو الاتنين في نفس المجموعة
             * * مفيش أي </div> بينهم — و لو فيه ، يبقى كل واحد في حتة
             */
            $between = substr(
                $source,
                strpos($source, 'urls.exportUrl'),
                strpos($source, 'urls.printUrl') - strpos($source, 'urls.exportUrl')
            );

            if (str_contains($between, '</div>')) {
                $offenders[] = basename(dirname($page));
            }
        }

        $this->assertSame([], $offenders,
            'الزرارين متفرّقين في الصفحات دي: '.implode(', ', $offenders));
    }

    /* ───────────── تقرير الفواتير ───────────── */

    /** * تقرير الفواتير بيتطبع للعملاء و للموردين من نفس الراوت */
    public function test_the_invoice_report_can_be_printed(): void
    {
        $route = Route::getRoutes()->getByName('print.invoice.report');

        $this->assertNotNull($route, 'راوت طباعة تقرير الفواتير مش متسجّل');
        $this->assertStringContainsString('{modelType}', $route->uri(),
            'نفس الراوت لازم يخدم العملاء و الموردين');
        $this->assertStringContainsString('@printInvoiceReport', $route->getActionName());
    }

    /** * و بيقرا من نفس المصدر بتاع الإكسل ، فمستحيل يختلفوا */
    public function test_the_invoice_report_print_shares_the_export_payload(): void
    {
        $controller = \App\Http\Controllers\CustomerInvoiceDashboardController::class;

        foreach (['printInvoiceReport', 'exportInvoiceReport'] as $method) {
            $this->assertStringContainsString('$this->invoiceReportPayload(', $this->methodBody($controller, $method),
                "{$method}() مش بيقرا من invoiceReportPayload");
        }

        $this->assertStringNotContainsString('->paginate(', $this->methodBody($controller, 'invoiceReportPayload'),
            'طباعة تقرير الفواتير المفروض تطلع كل الفواتير مش صفحة');

        foreach (['->take(', '->slice(', '->limit(', '->forPage(', 'array_slice('] as $trim) {
            $this->assertStringNotContainsString($trim, $this->methodBody($controller, 'printInvoiceReport'),
                "طباعة تقرير الفواتير بتقصّ الصفوف بـ {$trim}");
        }
    }

    /** * و الصفحة بتعرض الزرار */
    public function test_the_invoice_report_page_offers_the_print_button(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Balances/InvoiceReport.vue'));

        $this->assertStringContainsString('printUrl', $page);
        $this->assertMatchesRegularExpression('/:href="printUrl"/', $page);
    }

    /** * كل نصوص ورقة الطباعة مترجمة */
    public function test_the_print_sheet_is_translated(): void
    {
        $arabic = json_decode(file_get_contents(resource_path('lang/ar.json')), true);
        $vue = file_get_contents(resource_path('js/Pages/Statements/Print.vue'));

        preg_match_all("/\\\$t\('([^']+)'\)/", $vue, $matches);

        $missing = array_values(array_filter(
            array_unique($matches[1]),
            fn (string $key) => ! isset($arabic[$key])
        ));

        $this->assertSame([], $missing, 'نصوص من غير ترجمة: '.implode(', ', $missing));
    }


    /**
     * * كل متغيّر بيتقرا في print() و exportExcel() لازم يكون متعرّف جواها .
     *
     * * لما نقلت بناء الأعمدة و الصفوف لميثود لوحدها ، فضلت خمس تقارير
     * * بتستعمل في التصدير متغيرات بقت جوه الميثود الجديدة
     * * ($filters ، $endingBalanceTotal ...) . الكود كان بيعدّي الـ lint ،
     * * و كل الاختبارات الساكنة عدّت خضرا ، و التصدير كان بيقع وقت
     * * التشغيل بس — و ده اللي حصل فعلا .
     *
     * @dataProvider reports
     */
    public function test_no_method_reads_a_variable_it_never_defines(string $controller): void
    {
        foreach (['print', 'exportExcel', 'reportPayload'] as $method) {
            if (! method_exists($controller, $method)) {
                continue;
            }

            $undefined = $this->undefinedVariablesIn($controller, $method);

            $this->assertSame([], $undefined,
                class_basename($controller)."::{$method}() بتقرا متغيرات مش معرّفة: ".implode(', ', $undefined));
        }
    }

    /**
     * المتغيرات اللي بتتقرا في الميثود من غير ما تتعرّف فيها .
     *
     * @return list<string>
     */
    private function undefinedVariablesIn(string $class, string $method): array
    {
        $reflection = new \ReflectionMethod($class, $method);
        $body = $this->methodBody($class, $method);

        $defined = ['this'];

        foreach ($reflection->getParameters() as $parameter) {
            $defined[] = $parameter->getName();
        }

        // $x = ... ، و [$a, $b] = ...
        preg_match_all('/\$(\w+)\s*=(?!=)/', $body, $m);
        $defined = array_merge($defined, $m[1]);

        preg_match_all('/\[([^\]]*)\]\s*=/', $body, $lists);
        foreach ($lists[1] as $list) {
            preg_match_all('/\$(\w+)/', $list, $inner);
            $defined = array_merge($defined, $inner[1]);
        }

        // بارامترات الـ closures و الـ use ، و foreach ، و catch
        preg_match_all('/function\s*\(([^)]*)\)(?:\s*use\s*\(([^)]*)\))?/', $body, $fns, PREG_SET_ORDER);
        foreach ($fns as $fn) {
            preg_match_all('/\$(\w+)/', ($fn[1] ?? '').','.($fn[2] ?? ''), $inner);
            $defined = array_merge($defined, $inner[1]);
        }

        preg_match_all('/fn\s*\(([^)]*)\)/', $body, $arrows);
        foreach ($arrows[1] as $params) {
            preg_match_all('/\$(\w+)/', $params, $inner);
            $defined = array_merge($defined, $inner[1]);
        }

        /* الـ foreach ممكن يكون جواه نداء بأقواس
           (foreach ($x->values() as $i => $row)) ، فبندوّر على "as"
           نفسها مش على شكل السطر كله */
        preg_match_all('/\bas\s+(&?\$\w+(?:\s*=>\s*&?\$\w+)?)/', $body, $loops);
        foreach ($loops[1] as $vars) {
            preg_match_all('/\$(\w+)/', $vars, $inner);
            $defined = array_merge($defined, $inner[1]);
        }

        preg_match_all('/catch\s*\([^)]*\$(\w+)\s*\)/', $body, $catches);
        $defined = array_merge($defined, $catches[1]);

        preg_match_all('/\$(\w+)/', $body, $used);

        return array_values(array_unique(array_diff($used[1], $defined)));
    }

    /** اسم الميثود اللي بتبني الأعمدة و الصفوف في التقرير ده */
    private function payloadMethod(string $controller): string
    {
        return method_exists($controller, 'reportPayload') ? 'reportPayload' : 'print';
    }

    private function methodBody(string $class, string $method): string
    {
        $reflection = new \ReflectionMethod($class, $method);
        $lines = file($reflection->getFileName());

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }
}
