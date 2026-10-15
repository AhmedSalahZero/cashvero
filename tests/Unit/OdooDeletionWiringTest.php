<?php

namespace Tests\Unit;

use App\Exceptions\Handler;
use App\Exceptions\OdooOperationNotAllowedException;
use App\Services\Api\OdooPayment;
use App\Services\Api\MoneyPaymentOdooService;
use App\Services\Api\Traits\HasAtomicOdooDeletion;
use App\Traits\Models\HasNonCustomerOrSupplier;
use Illuminate\Http\Request;
use ReflectionClass;
use Tests\TestCase;

/**
 * * بتتأكد ان التوصيلات نفسها مظبوطة : الخدمات بتستخدم الترايت الذرية ،
 * * الحذف مابقاش deferred ، و الـ Handler بيعرض الرسالة المفهومة
 */
class OdooDeletionWiringTest extends TestCase
{
    public function test_odoo_services_actually_use_the_atomic_deletion_trait(): void
    {
        foreach ([OdooPayment::class, MoneyPaymentOdooService::class] as $service) {
            $traits = [];
            $class = new ReflectionClass($service);
            do {
                foreach ($class->getTraitNames() as $trait) {
                    $traits[] = $trait;
                    foreach ((new ReflectionClass($trait))->getTraitNames() as $nested) {
                        $traits[] = $nested;
                    }
                }
            } while ($class = $class->getParentClass());

            $this->assertContains(
                HasAtomicOdooDeletion::class,
                $traits,
                $service.' لازم يكون شايل الترايت الذرية'
            );
        }
    }

    public function test_the_delete_path_no_longer_defers_the_odoo_call_outside_the_transaction(): void
    {
        $file = (new ReflectionClass(HasNonCustomerOrSupplier::class))->getFileName();
        $method = $this->stripComments($this->methodBody($file, 'unlinkNonCustomerOrSupplierOdooExpense'));

        $this->assertStringNotContainsString(
            'OdooSync::defer',
            $method,
            'الحذف لازم يتنفذ جوه الترانزاكشن عشان الفشل يعمل rollback محلي'
        );
        $this->assertStringContainsString('->unlink($journalEntryId)', $method);
        $this->assertStringContainsString('->cancelDownPayment($odooId)', $method);
    }

    public function test_creation_path_still_uses_defer(): void
    {
        $file = (new ReflectionClass(HasNonCustomerOrSupplier::class))->getFileName();
        $source = file_get_contents($file);

        $this->assertStringContainsString(
            'OdooSync::defer',
            $source,
            'الإنشاء لازم يفضل deferred زي ما هو'
        );
    }

    public function test_handler_returns_a_friendly_flash_instead_of_a_500(): void
    {
        $handler = app(Handler::class);
        $exception = new OdooOperationNotAllowedException('raw odoo sequence chain message');

        $response = $handler->render(Request::create('/money-payment', 'DELETE'), $exception);

        $this->assertSame(302, $response->getStatusCode(), 'المفروض redirect مش صفحة خطأ');
        $this->assertSame(
            $exception->getUserMessage(),
            $response->getSession()->get('fail'),
            'الرسالة المفهومة لازم تتحط في الفلاش'
        );
        $this->assertNotSame('raw odoo sequence chain message', $response->getSession()->get('fail'));
    }

    public function test_handler_returns_json_for_ajax_requests(): void
    {
        $handler = app(Handler::class);
        $exception = new OdooOperationNotAllowedException('raw odoo sequence chain message');

        $request = Request::create('/money-payment', 'DELETE');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $handler->render($request, $exception);

        $this->assertSame(422, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertFalse($payload['status']);
        $this->assertSame($exception->getUserMessage(), $payload['msg']);
    }

    public function test_arabic_translation_exists_and_the_lang_file_was_not_mangled(): void
    {
        $path = resource_path('lang/ar.json');
        $raw = file_get_contents($path);

        $this->assertJson($raw, 'ar.json لازم يفضل JSON صالح');
        $this->assertStringContainsString('حسابك لا يملك الصلاحية لإجراء هذا التعديل', $raw);

        /**
         * * الملف فيه مفاتيح مكرّرة متقصودة ، فأي إعادة كتابة عن طريق
         * * json_decode/json_encode بتبلع المكرّر و بتغيّر ترجمات شغالة .
         * * الفحص ده بيتأكد ان الملف اتزاد فيه سطر بس مش اتعاد بناؤه
         */
        preg_match_all('/^\s*"((?:[^"\\\\]|\\\\.)*)"\s*:/m', $raw, $matches);
        $rawKeys = count($matches[1]);
        $decodedKeys = count(json_decode($raw, true));

        $this->assertGreaterThan(1000, $rawKeys, 'الملف اتقلّ فجأة — يبقى اتعاد بناؤه');
        $this->assertGreaterThanOrEqual($decodedKeys, $rawKeys);
    }

    private function methodBody(string $file, string $name): string
    {
        $source = file_get_contents($file);
        $start = strpos($source, 'function '.$name);
        $this->assertNotFalse($start, 'الميثود '.$name.' مش موجودة');

        return substr($source, $start, 1600);
    }

    /**
     * * بنشيل الكوميّنتات عشان الشرح اللي جواها ما يعديش على الفحص
     */
    private function stripComments(string $code): string
    {
        $code = preg_replace('#/\*.*?\*/#s', '', $code);

        return preg_replace('#//[^\n]*#', '', $code);
    }
}
