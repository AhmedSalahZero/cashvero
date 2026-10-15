<?php

namespace Tests\Feature\Forms;

use App\Http\Controllers\SalesGatheringTestController;
use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * الحقول اللي وراها قوايم مُدارة لازم تبقى اختيار مش كتابة حرة .
 *
 * * مندوب المبيعات و قطاع العمل و وحدة العمل ليهم شاشات بتتدار منها ،
 * * و كل تقرير بيجمّع بالاسم المكتوب بالظبط — فـ "أحمد" و "احمد" و
 * * "Ahmed " بيبقوا تلات مناديب ، و تقرير المندوب بيتقسم من غير ما حد
 * * ياخد باله .
 */
class ManagedListFieldsTest extends TestCase
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

    /** @return array<int, array<string, mixed>> */
    private function fields(string $modelName, $modelId = null): array
    {
        $request = Request::create('/x', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => User::first());
        $this->app->instance('request', $request);
        auth()->setUser(User::first());

        $controller = app(SalesGatheringTestController::class);
        $company = Company::findOrFail(self::COMPANY);

        $response = $modelId
            ? $controller->editModel($company, $request, $modelName, $modelId)
            : $controller->createModel($company, $request, $modelName);

        return $response->toResponse($request)->original->getData()['page']['props']['fields'];
    }

    private function field(string $modelName, string $name, $modelId = null): ?array
    {
        foreach ($this->fields($modelName, $modelId) as $field) {
            if ($field['field'] === $name) {
                return $field;
            }
        }

        return null;
    }

    public static function managedFields(): array
    {
        return [
            'sales person' => ['sales_person'],
            'business sector' => ['business_sector'],
            'business unit' => ['business_unit'],
        ];
    }

    /**
     * * أي حقل من دول لو ظاهر في الفورمة لازم يكون select
     *
     * @dataProvider managedFields
     */
    public function test_the_field_is_a_select_not_free_text(string $name): void
    {
        $shown = false;

        foreach (['CustomerInvoice', 'SupplierInvoice'] as $modelName) {
            $field = $this->field($modelName, $name);

            if ($field === null) {
                continue;   // الشركة دي مش مختارة الحقل ده في الجدول
            }

            $shown = true;

            $this->assertSame('managed_list_select', $field['type'],
                "{$modelName}.{$name} لسه خانة كتابة حرة");
            $this->assertNotNull($field['manage_url'],
                "{$modelName}.{$name} لازم يقول للمستخدم يملا القايمة منين");
        }

        if (! $shown) {
            $this->markTestSkipped("{$name} is not among this company's selected fields.");
        }
    }

    /**
     * * ⚠️ الحقل ده كان نص حر ، فالبيانات القديمة ممكن تكون فيها قيم مش
     * * في القايمة . لو الـ select ما عرضهاش ، أول حفظ للصف القديم كان
     * * هيمسح القيمة من غير ما حد يقصد
     */
    public function test_a_value_outside_the_list_is_kept_as_an_option(): void
    {
        $invoice = CustomerInvoice::where('company_id', self::COMPANY)->first();

        if (! $invoice) {
            $this->markTestSkipped('No invoice on file.');
        }

        if ($this->field('CustomerInvoice', 'sales_person') === null) {
            $this->markTestSkipped('sales_person is not a selected field here.');
        }

        DB::beginTransaction();

        try {
            $legacy = 'Legacy Typed Name '.uniqid();
            $invoice->forceFill(['sales_person' => $legacy])->save();

            $field = $this->field('CustomerInvoice', 'sales_person', $invoice->id);

            $this->assertSame($legacy, $field['value']);
            $this->assertArrayHasKey($legacy, (array) $field['options'],
                'القيمة القديمة لازم تفضل في الاختيارات، و إلا بتتمسح مع أول حفظ');
        } finally {
            DB::rollBack();
        }
    }

    /** * القايمة بتيجي من جدولها ، مترتبة بالاسم */
    public function test_the_options_come_from_the_managed_list(): void
    {
        DB::beginTransaction();

        try {
            foreach (['Zain', 'Adam', 'Mona'] as $name) {
                DB::table('cash_vero_sales_persons')->insert([
                    'company_id' => self::COMPANY,
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $field = $this->field('CustomerInvoice', 'sales_person');

            if ($field === null) {
                $this->markTestSkipped('sales_person is not a selected field here.');
            }

            $options = array_keys((array) $field['options']);

            foreach (['Zain', 'Adam', 'Mona'] as $name) {
                $this->assertContains($name, $options);
            }

            $sorted = $options;
            sort($sorted);
            $this->assertSame($sorted, $options, 'الاختيارات لازم تبقى مترتبة بالاسم');
        } finally {
            DB::rollBack();
        }
    }

    /** * الواجهة بتعرض النوع ده و بتقول للمستخدم يملا القايمة منين */
    public function test_the_form_renders_the_select_and_the_empty_state(): void
    {
        $form = file_get_contents(resource_path('js/Pages/InvoiceUpload/InvoiceForm.vue'));

        $this->assertStringContainsString("f.type === 'managed_list_select'", $form);
        $this->assertStringContainsString('f.manage_url', $form);
        $this->assertStringContainsString("\$t('No options yet.')", $form);
        $this->assertStringNotContainsString("business_sector_select", $form,
            'النوع القديم المخصوص لقطاع العمل اتشال لصالح النوع العام');
    }
}
