<?php

namespace Tests\Feature\Forms;

use App\Http\Controllers\SalesGatheringTestController;
use App\Models\Company;
use App\Models\CustomizedFieldsExportation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * * الحقول اللي وراها قوايم مُدارة لازم تبقى اختيار مش كتابة حرة .
 *
 * * مندوب المبيعات و قطاع العمل و وحدة العمل ليهم شاشات بتتدار منها ،
 * * و كل تقرير بيجمّع بالاسم المكتوب بالظبط — فـ "أحمد" و "احمد" و
 * * "Ahmed " بيبقوا تلات مناديب ، و تقرير المندوب بيتقسم من غير ما حد
 * * ياخد باله .
 *
 * * الشركة و أعمدتها المختارة و الفواتير و القوايم بيتعملوا في الاختبار نفسه .
 */
class ManagedListFieldsTest extends TestCase
{
    use MakesInvoices;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
        $this->user = $this->makeUser([], $this->company);

        /**
         * * الأعمدة اللي الشركة مختاراها لجدول الفواتير — الحقول التلاتة
         * * المُدارة منهم ، زي شركة شغّالة بيهم فعلا
         */
        foreach ([
            'CustomerInvoice' => ['customer_name', 'sales_person', 'business_sector', 'business_unit', 'invoice_number', 'invoice_date', 'invoice_due_date', 'currency', 'invoice_amount'],
            'SupplierInvoice' => ['supplier_name', 'invoice_number', 'invoice_date', 'invoice_due_date', 'currency', 'invoice_amount'],
        ] as $modelName => $fields) {
            CustomizedFieldsExportation::create([
                'company_id' => $this->company->id,
                'model_name' => $modelName,
                'fields' => $fields,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function fields(string $modelName, $modelId = null): array
    {
        $request = Request::create('/x', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $this->user);
        $this->app->instance('request', $request);
        auth()->setUser($this->user);

        $controller = app(SalesGatheringTestController::class);

        $response = $modelId
            ? $controller->editModel($this->company, $request, $modelName, $modelId)
            : $controller->createModel($this->company, $request, $modelName);

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
     */
    #[DataProvider('managedFields')]
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

        $this->assertTrue($shown, "{$name} is among the company's selected fields, so the form must show it.");
    }

    /**
     * * ⚠️ الحقل ده كان نص حر ، فالبيانات القديمة ممكن تكون فيها قيم مش
     * * في القايمة . لو الـ select ما عرضهاش ، أول حفظ للصف القديم كان
     * * هيمسح القيمة من غير ما حد يقصد
     */
    public function test_a_value_outside_the_list_is_kept_as_an_option(): void
    {
        $this->assertNotNull($this->field('CustomerInvoice', 'sales_person'), 'sales_person is a selected field here.');

        // القيمة اتكتبت بإيد قبل ما الحقل يبقى قايمة — مش موجودة في cash_vero_sales_persons
        $legacy = 'Legacy Typed Name '.uniqid();
        $invoice = $this->invoice($this->customer($this->company), ['sales_person' => $legacy]);

        $field = $this->field('CustomerInvoice', 'sales_person', $invoice->id);

        $this->assertSame($legacy, $field['value']);
        $this->assertArrayHasKey($legacy, (array) $field['options'],
            'القيمة القديمة لازم تفضل في الاختيارات، و إلا بتتمسح مع أول حفظ');
    }

    /** * القايمة بتيجي من جدولها ، مترتبة بالاسم */
    public function test_the_options_come_from_the_managed_list(): void
    {
        foreach (['Zain', 'Adam', 'Mona'] as $name) {
            DB::table('cash_vero_sales_persons')->insert([
                'company_id' => $this->company->id,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // مندوب شركة تانية ما يظهرش هنا
        DB::table('cash_vero_sales_persons')->insert([
            'company_id' => $this->makeCompany()->id,
            'name' => 'Bob Of Another Company',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $field = $this->field('CustomerInvoice', 'sales_person');

        $this->assertNotNull($field, 'sales_person is a selected field here.');

        $options = array_keys((array) $field['options']);

        $this->assertSame(['Adam', 'Mona', 'Zain'], $options, 'القايمة بتاعة الشركة دي بس، مترتبة بالاسم');

        foreach (['Zain', 'Adam', 'Mona'] as $name) {
            $this->assertContains($name, $options);
        }

        $sorted = $options;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);   // MySQL orders names ignoring capital letters
        $this->assertSame($sorted, $options, 'الاختيارات لازم تبقى مترتبة بالاسم');
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
