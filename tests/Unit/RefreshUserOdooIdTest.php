<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Services\Api\OdooService;
use ReflectionClass;
use Tests\TestCase;

/**
 * * refreshUserOdooId كانت بتحفظ odoo_id = null في الصف قبل ما تحاول
 * * تجيب الجديد ، فأي فشل في المصادقة كان بيسيب اليوزر من غير odoo_id
 * * للابد و يكسر تكامله بالكامل
 *
 * * التستات دي بتشتغل على اودو مش موجود (بورت مقفول) عشان المصادقة تفشل
 * * فعلا من غير ما نكلم اي سيرفر حقيقي — و من غير داتابيز كمان ، لأن
 * * المسار الصح مالهوش اي كتابة اصلا
 */
class RefreshUserOdooIdTest extends TestCase
{
    private function unreachableCompany(): Company
    {
        $company = new Company;
        $company->forceFill([
            'odoo_db_url' => 'http://127.0.0.1:9',
            'odoo_db_name' => 'no_such_db',
        ]);

        return $company;
    }

    private function userWithOdooId(?int $odooId): User
    {
        $user = new User;
        $user->forceFill([
            'odoo_username' => 'someone@example.com',
            'odoo_db_password' => 'secret',
            'odoo_id' => $odooId,
        ]);
        $user->syncOriginal();

        return $user;
    }

    public function test_a_failed_refresh_keeps_the_existing_odoo_id(): void
    {
        $user = $this->userWithOdooId(19);

        $result = OdooService::refreshUserOdooId($this->unreachableCompany(), $user);

        $this->assertNull($result, 'المصادقة فشلت فالمفروض ترجع null');
        $this->assertSame(19, $user->getOdooId(), 'الـ odoo_id القديم لازم يفضل زي ما هو');
        $this->assertFalse($user->isDirty('odoo_id'), 'مايكونش فيه اي تغيير مستني الحفظ');
    }

    public function test_a_failed_refresh_never_writes_to_the_row(): void
    {
        $user = new class extends User
        {
            public array $saves = [];

            public function save(array $options = [])
            {
                $this->saves[] = $this->getDirty();

                return true;
            }
        };
        $user->forceFill([
            'odoo_username' => 'someone@example.com',
            'odoo_db_password' => 'secret',
            'odoo_id' => 19,
        ]);
        $user->syncOriginal();
        $user->exists = true;

        OdooService::refreshUserOdooId($this->unreachableCompany(), $user);

        $this->assertSame([], $user->saves, 'مالهاش تكتب في الصف خالص لما المصادقة تفشل');
        $this->assertSame(19, $user->getOdooId());
    }

    public function test_a_user_without_credentials_is_left_alone(): void
    {
        $user = $this->userWithOdooId(19);
        $user->forceFill(['odoo_username' => null]);
        $user->syncOriginal();

        $this->assertNull(OdooService::refreshUserOdooId($this->unreachableCompany(), $user));
        $this->assertSame(19, $user->getOdooId());
    }

    public function test_the_destructive_null_write_is_gone_from_the_source(): void
    {
        $source = file_get_contents((new ReflectionClass(OdooService::class))->getFileName());
        $body = substr($source, strpos($source, 'function refreshUserOdooId'), 1800);

        $this->assertStringNotContainsString(
            "update(['odoo_id' => null])",
            $body,
            'ممنوع نحفظ null في الصف قبل ما نتأكد ان المصادقة نجحت'
        );
        $this->assertStringContainsString("setAttribute('odoo_id', null)", $body);
    }

    public function test_company_update_no_longer_blanks_every_user_odoo_id(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/CompanyController.php'));
        $source = preg_replace('#/\*.*?\*/#s', '', $source);

        $this->assertStringNotContainsString(
            "'odoo_id'=>null",
            $source,
            'حفظ الشركة مالوش يصفّر odoo_id لكل يوزراتها'
        );
        $this->assertStringContainsString('$odooConnectionChanged', $source);
    }
}
