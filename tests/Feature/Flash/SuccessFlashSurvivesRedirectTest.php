<?php

namespace Tests\Feature\Flash;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * * رسالة النجاح بعد الـ redirect
 *
 * * php-flasher بيحقن Flasher\Laravel\Middleware\SessionMiddleware في
 * * مجموعة web أوتوماتيك (مش مكتوبة في app/Http/Kernel.php). الخريطة
 * * الافتراضية بتاعته بتخطف session('success') — و كمان error/warning/
 * * info — من كل ريسبونس ، بتحوّلها لـ flasher::envelopes و بتعمل
 * * forget للمفتاح الأصلي.
 *
 * * النتيجة كانت إن redirect()->with('success', ...) يوصل الليـاوت و
 * * المفتاح مش موجود ، فـ SweetAlert ما بتشتغلش ، و flasher كمان ما
 * * بيعرضش حاجة لأن الـ views ما بتناديش flasher_render. الرسالة كانت
 * * بتضيع بالكامل.
 *
 * * و ده يفسّر ليه مسار الخطأ متكتب session()->put('fail', ...) : كلمة
 * * fail مش في خريطة flasher فكانت بتنجو لوحدها.
 *
 * * الحل: config/flasher.php بـ flash_bag => false
 */
class SuccessFlashSurvivesRedirectTest extends TestCase
{
    /**
     * * مجموعة web الكاملة هنا محتاجة يوزر و شركة (Inertia + صلاحيات) ،
     * * فبنكتفي بميدلوير الجلسة. الحارس الحقيقي على سلوك flasher هو
     * * test_the_flasher_flash_bag_bridge_is_disabled تحت — و مع
     * * flash_bag => false الباكدج مش بيسجّل الـ RequestExtension أصلا ،
     * * فميدلوير flasher ما بتتبنيش و ما بتلمسش الجلسة
     */
    private const FLASHER_STACK = [
        \Illuminate\Session\Middleware\StartSession::class,
    ];

    public function test_the_flasher_flash_bag_bridge_is_disabled(): void
    {
        $this->assertFalse(
            config('flasher.flash_bag'),
            'من غير flash_bag => false بيتخطف session(\'success\') قبل ما أي صفحة تشوفه'
        );
    }

    public function test_a_success_flash_survives_a_redirect_through_the_web_group(): void
    {
        Route::middleware(self::FLASHER_STACK)->get('/__flash_set', fn () => redirect()->to('/__flash_read')->with('success', 'Invoices Reading Has Been Completed'));
        Route::middleware(self::FLASHER_STACK)->get('/__flash_read', fn () => (string) session('success', 'MISSING'));

        $this->get('/__flash_set')->assertRedirect('/__flash_read');

        $this->get('/__flash_read')->assertSee('Invoices Reading Has Been Completed');
    }

    /**
     * * الحل القديم لمسار الخطأ لازم يفضل شغال زي ما هو
     */
    public function test_a_fail_flash_still_survives(): void
    {
        Route::middleware(self::FLASHER_STACK)->get('/__fail_set', function () {
            session()->put('fail', 'Could not access Odoo');

            return redirect()->to('/__fail_read');
        });
        Route::middleware(self::FLASHER_STACK)->get('/__fail_read', fn () => (string) session('fail', 'MISSING'));

        $this->get('/__fail_set');

        $this->get('/__fail_read')->assertSee('Could not access Odoo');
    }

    /**
     * * كل مفاتيح الخريطة الافتراضية لازم تعدّي ، مش success بس
     */
    public function test_the_other_flash_keys_survive_too(): void
    {
        foreach (['error', 'warning', 'info', 'danger', 'notice'] as $key) {
            Route::middleware(self::FLASHER_STACK)->get("/__k_set_{$key}", fn () => redirect()->to('/x')->with($key, "value-of-{$key}"));
            Route::middleware(self::FLASHER_STACK)->get("/__k_read_{$key}", fn () => (string) session($key, 'MISSING'));

            $this->get("/__k_set_{$key}");

            $this->get("/__k_read_{$key}")->assertSee("value-of-{$key}");
        }
    }

    /**
     * * الباكدج بيحقن ميدلويره في مجموعة web من غير ما تكون مكتوبة في
     * * app/Http/Kernel.php. مع flash_bag => false بيبطّل يحقنها خالص —
     * * فوجودها في المجموعة معناه إن الجسر رجع اشتغل و بيخطف المفاتيح
     */
    public function test_the_flasher_session_bridge_is_not_wired_into_the_web_group(): void
    {
        $group = app('router')->getMiddlewareGroups()['web'] ?? [];

        $this->assertNotContains(
            \Flasher\Laravel\Middleware\SessionMiddleware::class,
            $group,
            'الميدلوير دي بتعمل forget لـ session(\'success\') قبل ما أي صفحة تشوفه'
        );
    }

    /**
     * * cashvero بيعرض الرسايل بـ Inertia (ToastStack) ، و
     * * HandleInertiaRequests هي اللي بتمرّر المفاتيح للواجهة
     */
    public function test_the_inertia_bridge_still_shares_the_success_key(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

        $this->assertStringContainsString("'success'", $middleware);
        $this->assertStringContainsString('flash', $middleware);
    }
}
