<?php

namespace Tests\Feature\MultipleCashExpense;

use App\Http\Controllers\MultipleCashExpenseController;
use App\Http\Requests\StoreMultipleCashExpenseRequest;
use App\Models\Branch;
use App\Models\CashInSafeStatement;
use App\Models\Company;
use App\Models\MultipleCashExpense;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Database\Factories\BranchFactory;
use Database\Factories\CashExpenseCategoryFactory;
use Database\Factories\CashExpenseCategoryNameFactory;
use Database\Factories\CashExpenseFactory;
use Database\Factories\CompanyFactory;
use Database\Factories\PartnerFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * * المصروفات النقدية المتعددة : حركة صرف واحدة فيها أكتر من بند
 *
 * * أهم ضمانتين هنا — و دول اللي بيلمسوا فلوس فعلا :
 * *   ١ - كل بند بينزل سطر مستقل في الكشف بمبلغه ، و مجموع الأسطر =
 * *       الإجمالي المصروف . لو ده اتكسر الأرصدة تبقى غلط
 * *   ٢ - التعديل و الحذف بيشيلوا أسطر الكشف القديمة ، فما يفضلش رصيد
 * *       متخصوم منه بند اتشال
 *
 * * الشركة و الفرع و التصنيفات و اليوزر معمولين في كل تست ، و كل الكتابة
 * * جوه الترانزاكشن اللي TestCase بيرجّعها في الآخر
 */
class MultipleCashExpenseTest extends TestCase
{
    private ?User $user = null;

    /** * شركة من غير أودو — الشاشة متاحة للنوع ده بس */
    private function company(): Company
    {
        $company = $this->makeCompany();
        $this->assertFalse($company->hasOdooCredentials());

        return $company;
    }

    /** * شركة متوصّلة بأودو (سيرفر وهمي .invalid — مفيش أي نداء بيطلع) */
    private function odooCompany(): Company
    {
        $company = CompanyFactory::new()->withCashVero()->withOdoo()->create();
        $this->assertTrue($company->hasOdooCredentials());

        return $company;
    }

    /**
     * * البيانات المرجعية اللي الشاشة محتاجاها : فرع ، و تصنيف تحته اسمين
     *
     * @return array{0: Branch, 1: \Illuminate\Support\Collection}
     */
    private function referenceData(Company $company): array
    {
        $branch = BranchFactory::new()->forCompany($company)->create(['name' => 'ZZ Test Branch']);
        $category = CashExpenseCategoryFactory::new()->forCompany($company)->create(['name' => 'ZZ Test Category']);

        $names = collect([1, 2])->map(fn (int $n) => CashExpenseCategoryNameFactory::new()->in($category)->create(['name' => 'ZZ Expense '.$n]));

        return [$branch, $names->values()];
    }

    private function user(Company $company): User
    {
        return $this->user ??= $this->makeUser([
            'multiple_cash_expense.view',
            'multiple_cash_expense.create',
            'multiple_cash_expense.update',
            'multiple_cash_expense.delete',
            'cash_expense.view',
        ], $company);
    }

    private function submit(Company $company, array $payload, string $method = 'POST'): StoreMultipleCashExpenseRequest
    {
        $user = $this->user($company);
        $request = StoreMultipleCashExpenseRequest::create('/x', $method, $payload);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        return $request;
    }

    private function payload(Branch $branch, $names, array $items): array
    {
        return [
            'type' => MultipleCashExpense::CASH_PAYMENT,
            'payment_date' => '2026-09-07',
            'currency' => $branch->currency ?: 'EGP',
            'exchange_rate' => 1,
            'delivery_branch_id' => $branch->id,
            'items' => $items,
        ];
    }

    /* ───────────── الحركة المالية ───────────── */

    /**
     * * الضمانة الأساسية : ٣ بنود = ٣ أسطر مستقلة ، مش سطر واحد بالمجموع
     */
    public function test_each_line_lands_on_the_statement_on_its_own(): void
    {
        $company = $this->company();

        [$branch, $names] = $this->referenceData($company);

        $request = $this->submit($company, $this->payload($branch, $names, [
            ['cash_expense_category_name_id' => $names[0]->id, 'paid_amount' => 3000],
            ['cash_expense_category_name_id' => $names[1]->id, 'paid_amount' => 5000],
            ['cash_expense_category_name_id' => $names[0]->id, 'paid_amount' => 2000],
        ]));

        (new MultipleCashExpenseController)->store($company, $request);

        $expense = MultipleCashExpense::where('company_id', $company->id)->latest('id')->first();
        $lines = CashInSafeStatement::whereIn('multiple_cash_expense_item_id', $expense->items->pluck('id'))->get();

        $this->assertSame(3, $expense->items->count(), 'التلات بنود لازم يتحفظوا');
        $this->assertSame(3, $lines->count(), 'لازم ٣ أسطر مستقلة في الكشف مش سطر واحد');

        $this->assertEqualsCanonicalizing(
            ['3000.00', '5000.00', '2000.00'],
            $lines->pluck('credit')->map(fn ($c) => (string) $c)->all(),
            'كل سطر بمبلغ بنده'
        );

        $this->assertEqualsWithDelta(10000, (float) $lines->sum('credit'), 0.01,
            'مجموع الأسطر لازم يساوي المصروف كله');
        $this->assertEqualsWithDelta((float) $expense->paid_amount, (float) $lines->sum('credit'), 0.01,
            'الإجمالي المخزّن لازم يساوي اللي نزل الكشف فعلا');
    }

    /** * الإجمالي بيتحسب من البنود مش من إدخال منفصل */
    public function test_the_total_is_derived_from_the_lines(): void
    {
        $company = $this->company();

        [$branch, $names] = $this->referenceData($company);

        $payload = $this->payload($branch, $names, [
            ['cash_expense_category_name_id' => $names[0]->id, 'paid_amount' => 1500.25],
            ['cash_expense_category_name_id' => $names[1]->id, 'paid_amount' => 2499.75],
        ]);
        /* حتى لو حد بعت إجمالي غلط ، البنود هي المرجع */
        $payload['paid_amount'] = 999999;

        (new MultipleCashExpenseController)->store($company, $this->submit($company, $payload));

        $expense = MultipleCashExpense::where('company_id', $company->id)->latest('id')->first();

        $this->assertEqualsWithDelta(4000.00, (float) $expense->paid_amount, 0.01,
            'الإجمالي من البنود مش من اللي اتبعت');
    }

    /**
     * * التعديل لازم يشيل أسطر الكشف القديمة — و إلا الرصيد يفضل متخصوم
     * * منه مبلغ اتغيّر
     */
    public function test_editing_leaves_no_stale_statement_lines(): void
    {
        $company = $this->company();

        [$branch, $names] = $this->referenceData($company);

        (new MultipleCashExpenseController)->store($company, $this->submit($company,
            $this->payload($branch, $names, [
                ['cash_expense_category_name_id' => $names[0]->id, 'paid_amount' => 3000],
                ['cash_expense_category_name_id' => $names[1]->id, 'paid_amount' => 5000],
            ])));

        $expense = MultipleCashExpense::where('company_id', $company->id)->latest('id')->first();
        $oldItemIds = $expense->items->pluck('id');

        $editRequest = $this->submit($company, $this->payload($branch, $names, [
            ['cash_expense_category_name_id' => $names[0]->id, 'paid_amount' => 1000],
        ]), 'PUT');

        (new MultipleCashExpenseController)->update($company, $editRequest, $expense);

        $expense->refresh()->load('items');

        $this->assertSame(1, $expense->items->count());
        $this->assertEqualsWithDelta(1000.00, (float) $expense->paid_amount, 0.01);

        $this->assertSame(0,
            CashInSafeStatement::whereIn('multiple_cash_expense_item_id', $oldItemIds)->count(),
            'أسطر الكشف القديمة لازم تختفي، و إلا الرصيد غلط');

        $this->assertSame(1,
            CashInSafeStatement::whereIn('multiple_cash_expense_item_id', $expense->items->pluck('id'))->count());
    }

    /** * الحذف بياخد أسطر الكشف معاه */
    public function test_deleting_removes_its_statement_lines(): void
    {
        $company = $this->company();

        [$branch, $names] = $this->referenceData($company);

        (new MultipleCashExpenseController)->store($company, $this->submit($company,
            $this->payload($branch, $names, [
                ['cash_expense_category_name_id' => $names[0]->id, 'paid_amount' => 700],
                ['cash_expense_category_name_id' => $names[1]->id, 'paid_amount' => 300],
            ])));

        $expense = MultipleCashExpense::where('company_id', $company->id)->latest('id')->first();
        $itemIds = $expense->items->pluck('id');

        $this->assertSame(2, CashInSafeStatement::whereIn('multiple_cash_expense_item_id', $itemIds)->count());

        (new MultipleCashExpenseController)->destroy($company, $expense);

        $this->assertSame(0, CashInSafeStatement::whereIn('multiple_cash_expense_item_id', $itemIds)->count(),
            'ما ينفعش يفضل رصيد متخصوم منه حركة اتمسحت');
        $this->assertSame(0, MultipleCashExpense::whereKey($expense->id)->count());
    }

    /* ───────────── التحقق من المدخلات ───────────── */

    public function test_a_payment_with_no_lines_is_refused(): void
    {
        $company = $this->company();

        [$branch, $names] = $this->referenceData($company);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->submit($company, $this->payload($branch, $names, []));
    }

    public function test_a_line_with_a_zero_amount_is_refused(): void
    {
        $company = $this->company();

        [$branch, $names] = $this->referenceData($company);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->submit($company, $this->payload($branch, $names, [
            ['cash_expense_category_name_id' => $names[0]->id, 'paid_amount' => 0],
        ]));
    }

    /** * التوزيع لازم يشاور على بند موجود فعلا */
    public function test_an_allocation_pointing_at_a_missing_line_is_refused(): void
    {
        $company = $this->company();

        [$branch, $names] = $this->referenceData($company);

        $payload = $this->payload($branch, $names, [
            ['cash_expense_category_name_id' => $names[0]->id, 'paid_amount' => 100],
        ]);
        // * عقد حقيقي للشركة : كده الرفض جاي من البند المفقود بس ، مش من العقد
        $contractId = $this->contract($company);

        $payload['allocations'] = [
            ['item_index' => 5, 'contract_id' => $contractId, 'amount' => 50],
        ];

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->submit($company, $payload);
    }

    /* ───────────── حالة أودو ───────────── */

    /** * الشاشة مالهاش تكامل مع أودو ، فبتتقفل للشركة اللي عليه */
    public function test_the_screen_is_closed_for_a_company_on_odoo(): void
    {
        $odooCompany = $this->odooCompany();

        $this->expectException(NotFoundHttpException::class);

        (new MultipleCashExpenseController)->index($odooCompany, Request::create('/x'));
    }

    /** * و التاب نفسها بتختفي من القائمة الجانبية */
    public function test_the_sidebar_hides_it_for_a_company_on_odoo(): void
    {
        $sidebar = file_get_contents(app_path('Support/SidebarMenu.php'));

        $this->assertStringContainsString("multiple_cash_expense.view", $sidebar);
        $this->assertStringContainsString('! $company->hasOdooCredentials()', $sidebar,
            'التاب لازم تختفي لو الشركة على أودو');
    }

    /* ───────────── الصلاحيات و التوصيلات ───────────── */

    public function test_it_has_its_own_permission_module(): void
    {
        $all = PermissionRegistry::all();

        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $this->assertArrayHasKey("multiple_cash_expense.{$action}", $all,
                'الموديل مستقل، فصلاحياته مستقلة');
        }
    }

    public function test_all_routes_are_registered_and_mapped(): void
    {
        $map = file_get_contents(app_path('Support/Permissions/RoutePermissionMap.php'));

        foreach ([
            'multiple-cash-expenses.index' => 'multiple_cash_expense.view',
            'multiple-cash-expenses.create' => 'multiple_cash_expense.create',
            'multiple-cash-expenses.store' => 'multiple_cash_expense.create',
            'multiple-cash-expenses.edit' => 'multiple_cash_expense.update',
            'multiple-cash-expenses.update' => 'multiple_cash_expense.update',
            'multiple-cash-expenses.destroy' => 'multiple_cash_expense.delete',
        ] as $name => $permission) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), 'الراوت مش متسجل: '.$name);
            $this->assertStringContainsString("'{$name}' => '{$permission}'", $map, $name.' مش متربوط بصلاحية');
        }
    }

    /* ───────────── كشف المصروفات ───────────── */

    /**
     * * كشف المصروفات النقدية كان بيقرا من جدول المصروفات العادية بس ،
     * * فالحركات المتعددة كانت بتختفي منه خالص و التقرير يقول "لا توجد
     * * بيانات" رغم إن الفلوس اتصرفت فعلا
     *
     * * و بيبان **بند بند** — نفس المبدأ اللي بيحكم نزوله في كشف الخزنة
     */
    public function test_each_line_appears_in_the_cash_expense_statement(): void
    {
        $company = $this->company();

        [$branch, $names] = $this->referenceData($company);

        (new MultipleCashExpenseController)->store($company, $this->submit($company,
            $this->payload($branch, $names, [
                ['cash_expense_category_name_id' => $names[0]->id, 'paid_amount' => 22],
                ['cash_expense_category_name_id' => $names[1]->id, 'paid_amount' => 33],
            ])));

        $expense = MultipleCashExpense::where('company_id', $company->id)->latest('id')->first();

        $request = Request::create('/result', 'GET', [
            'start_date' => '2000-01-01',
            'end_date' => '2030-12-31',
            'currency' => $expense->currency,
            'cash_expense_category_name_id' => [$names[0]->id, $names[1]->id],
        ]);
        $user = $this->user($company);
        $request->setUserResolver(fn () => $user);

        $response = (new \App\Http\Controllers\CashExpenseStatementController)->result($company, $request);

        $this->assertNotInstanceOf(\Illuminate\Http\RedirectResponse::class, $response,
            'التقرير لسه بيقول مفيش بيانات رغم وجود حركات');

        $props = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];
        $amounts = collect($props['paginator']['data'])->pluck('paidAmount')->all();

        $this->assertContains(22.0, $amounts, 'البند الأول لازم يبان بمبلغه');
        $this->assertContains(33.0, $amounts, 'البند التاني لازم يبان بمبلغه');
        $this->assertNotContains(55.0, $amounts, 'ما ينفعش يبان صف واحد بالإجمالي');
    }

    /**
     * * و الأهم : التقرير للمصروفات العادية لازم يفضل بالظبط زي ما كان —
     * * الاتحاد ما ينفعش يغيّر عدد ولا مجموع أي حاجة قايمة
     */
    public function test_the_statement_for_ordinary_expenses_is_unchanged(): void
    {
        $company = $this->company();
        [$branch, $names] = $this->referenceData($company);
        $currency = $company->getMainFunctionalCurrency();
        $categoryIds = $names->pluck('id')->all();

        /*
         * * مصروفات عادية : اتنين جوه الفلتر ، و واحد بعملة تانية ، و واحد
         * * برا المدة ، و واحد بتصنيف مش في الفلتر — التلاتة دول ما يدخلوش
         */
        $ordinary = fn (array $attributes) => CashExpenseFactory::new()->forExpenseName($names[0])->create($attributes + ['currency' => $currency]);
        $ordinary(['payment_date' => '2026-01-10', 'paid_amount' => 120]);
        CashExpenseFactory::new()->forExpenseName($names[1])->create(['payment_date' => '2026-02-10', 'paid_amount' => 80, 'currency' => $currency]);
        $ordinary(['payment_date' => '2026-01-11', 'paid_amount' => 999, 'currency' => 'USD']);
        $ordinary(['payment_date' => '1999-12-31', 'paid_amount' => 999]);
        $otherName = CashExpenseCategoryNameFactory::new()->in($names[0]->cashExpenseCategory)->create();
        CashExpenseFactory::new()->forExpenseName($otherName)->create(['payment_date' => '2026-01-12', 'paid_amount' => 999, 'currency' => $currency]);

        // * و حركة متعددة جوه نفس الفلتر ، ببندين
        (new MultipleCashExpenseController)->store($company, $this->submit($company,
            $this->payload($branch, $names, [
                ['cash_expense_category_name_id' => $names[0]->id, 'paid_amount' => 7],
                ['cash_expense_category_name_id' => $names[1]->id, 'paid_amount' => 3],
            ])));

        // what the report must still report, computed straight from the table
        $base = \DB::table('cash_expenses')
            ->where('company_id', $company->id)
            ->where('currency', $currency)
            ->whereBetween('payment_date', ['2000-01-01', '2030-12-31'])
            ->whereIn('cash_expense_category_name_id', $categoryIds);

        $expectedCount = (clone $base)->count();
        $expectedSum = (float) (clone $base)->sum('paid_amount');

        $this->assertSame(2, $expectedCount, 'المصروفين العاديين اللي جوه الفلتر بس');
        $this->assertEqualsWithDelta(200.0, $expectedSum, 0.01);

        // plus whatever multiple-expense lines exist for the same filter
        $extra = \DB::table('multiple_cash_expense_items')
            ->join('multiple_cash_expenses', 'multiple_cash_expenses.id', '=', 'multiple_cash_expense_items.multiple_cash_expense_id')
            ->where('multiple_cash_expenses.company_id', $company->id)
            ->where('multiple_cash_expenses.currency', $currency)
            ->whereBetween('multiple_cash_expenses.payment_date', ['2000-01-01', '2030-12-31'])
            ->whereIn('multiple_cash_expense_items.cash_expense_category_name_id', $categoryIds);

        $expectedCount += (clone $extra)->count();
        $expectedSum += (float) (clone $extra)->sum('multiple_cash_expense_items.paid_amount');

        $this->assertSame(4, $expectedCount);
        $this->assertEqualsWithDelta(210.0, $expectedSum, 0.01);

        $request = Request::create('/result', 'GET', [
            'start_date' => '2000-01-01',
            'end_date' => '2030-12-31',
            'currency' => $currency,
            'cash_expense_category_name_id' => $categoryIds,
        ]);
        $user = $this->user($company);
        $request->setUserResolver(fn () => $user);

        $response = (new \App\Http\Controllers\CashExpenseStatementController)->result($company, $request);

        $this->assertNotInstanceOf(\Illuminate\Http\RedirectResponse::class, $response,
            'التقرير قال مفيش بيانات و فيه بيانات');

        $props = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];

        $this->assertSame($expectedCount, $props['kpis']['transactionCount'],
            'عدد الحركات في التقرير اتغيّر');
        $this->assertEqualsWithDelta($expectedSum, $props['kpis']['totalPaidAmount'], 0.01,
            'إجمالي التقرير اتغيّر');
    }

    /** * عقد للشركة نوزّع عليه (جدول contracts — مالوش factory هنا) */
    private function contract(Company $company): int
    {
        $customer = PartnerFactory::new()->forCompany($company)->customer()->create();

        return (int) DB::table('contracts')->insertGetId([
            'company_id' => $company->id,
            'partner_id' => $customer->id,
            'status' => 'running',
            'model_type' => 'Customer',
            'name' => 'Allocation Contract',
            'code' => 'c-mce-'.uniqid(),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'amount' => 100000,
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ]);
    }

    /* ───────────── اللي ما اتلمسش ───────────── */

    /**
     * * الموديل ده مستقل تمامًا : ما ينفعش يكون لمس المصروفات العادية
     */
    public function test_the_existing_cash_expense_screen_was_not_touched(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CashExpenseController.php'));

        $this->assertStringNotContainsString('MultipleCashExpense', $controller,
            'المصروفات العادية ما تعرفش حاجة عن الشاشة الجديدة');

        $model = file_get_contents(app_path('Models/CashExpense.php'));
        $this->assertStringNotContainsString('MultipleCashExpense', $model);
    }

    /** * و مفيش تكامل أودو اتكتب للشاشة دي */
    public function test_it_has_no_odoo_integration(): void
    {
        foreach ([
            app_path('Http/Controllers/MultipleCashExpenseController.php'),
            app_path('Models/MultipleCashExpense.php'),
            app_path('Models/MultipleCashExpenseItem.php'),
        ] as $path) {
            $source = file_get_contents($path);

            $this->assertStringNotContainsString('OdooSync', $source, basename($path));
            $this->assertStringNotContainsString('OdooPayment', $source, basename($path));
            $this->assertStringNotContainsString('createDownPayment', $source, basename($path));
        }
    }
}
