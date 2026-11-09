<?php

namespace Tests\Feature\Copy;

use App\Models\AccountType;
use App\Models\BuyOrSellCurrency;
use App\Models\Company;
use App\Models\InternalMoneyTransfer;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\Concerns\MakesBanks;
use Tests\TestCase;

/**
 * * زرار "نسخ" — نفس فكرته في المصروفات النقدية : بيفتح فورمة الإنشاء
 * * مليانة من صف موجود ، و الحفظ بيعمل صف جديد
 *
 * * الحاجة المهمة هنا : النسخة ما تحملش هوية الصف الأصلي و لا الأرقام
 * * اللي المفروض تتكتب من جديد — و إلا الحفظ هيبقى تعديل للأصل بدل
 * * إنشاء ، أو هيقع على رقم شيك مكرر
 *
 * * التحويلة و عملية الشراء/البيع معمولين في كل تست لشركة معمولة في التست ،
 * * بكل الحقول اللي النسخ المفروض يفضّيها مليانة
 */
class CopyActionsTest extends TestCase
{
    use MakesBanks;

    private ?Company $company = null;

    private function company(): Company
    {
        return $this->company ??= $this->makeCompany();
    }

    /** * تحويلة بنك لبنك ، برقم شيك و تاريخ ، بين حسابين جاريين للشركة */
    private function internalTransfer(): InternalMoneyTransfer
    {
        $company = $this->company();
        $from = $this->currentAccount($this->bank($company), 'EGP');
        $to = $this->currentAccount($this->bank($company), 'EGP');
        $currentAccount = $this->accountTypeId(AccountType::CURRENT_ACCOUNT);

        return InternalMoneyTransfer::create([
            'company_id' => $company->id,
            'type' => InternalMoneyTransfer::BANK_TO_BANK,
            'transfer_date' => '2026-03-15',
            'transfer_days' => 1,
            'amount' => 1500.50,
            'currency' => 'EGP',
            'cheque_number' => 'COPY-CHQ-'.uniqid(),
            'from_bank_id' => $from->financial_institution_id,
            'from_account_type_id' => $currentAccount,
            'from_account_number' => $from->account_number,
            'to_bank_id' => $to->financial_institution_id,
            'to_account_type_id' => $currentAccount,
            'to_account_number' => $to->account_number,
        ])->fresh();
    }

    /** * بيع دولار و شراء جنيه بين حسابين للشركة ، بتاريخ و سعر صرف */
    private function currencyExchange(): BuyOrSellCurrency
    {
        $company = $this->company();
        $from = $this->currentAccount($this->bank($company), 'USD');
        $to = $this->currentAccount($this->bank($company), 'EGP');
        $currentAccount = $this->accountTypeId(AccountType::CURRENT_ACCOUNT);

        return BuyOrSellCurrency::create([
            'company_id' => $company->id,
            'type' => BuyOrSellCurrency::BANK_TO_BANK,
            'transaction_date' => '2026-03-16',
            'currency_to_sell' => 'USD',
            'currency_to_buy' => 'EGP',
            'currency_to_sell_amount' => 100,
            'exchange_rate' => 48.25,
            'currency_to_buy_amount' => 4825,
            'from_bank_id' => $from->financial_institution_id,
            'from_account_type_id' => $currentAccount,
            'from_account_number' => $from->account_number,
            'to_bank_id' => $to->financial_institution_id,
            'to_account_type_id' => $currentAccount,
            'to_account_number' => $to->account_number,
        ])->fresh();
    }

    private function props(string $controllerClass, array $args): array
    {
        $controller = app($controllerClass);
        $method = new ReflectionMethod($controllerClass, 'buildFormProps');
        $method->setAccessible(true);

        return $method->invokeArgs($controller, $args);
    }

    /* ───────────── التحويلات الداخلية ───────────── */

    public function test_copying_an_internal_transfer_opens_a_create_form(): void
    {
        $transfer = $this->internalTransfer();
        $company = $this->company();
        $class = \App\Http\Controllers\InternalMoneyTransferController::class;

        $edit = $this->props($class, [$company, $transfer->getType(), $transfer, false]);
        $copy = $this->props($class, [$company, $transfer->getType(), $transfer, true]);

        $this->assertSame('edit', $edit['mode']);
        $this->assertSame('create', $copy['mode'], 'النسخ فورمة إنشاء مش تعديل');
        $this->assertNotSame($edit['submitUrl'], $copy['submitUrl'], 'لازم يتبعت على راوت الإنشاء');
        $this->assertStringContainsString('store', $copy['submitUrl']);
    }

    public function test_a_copied_transfer_drops_its_identity_and_cheque_number(): void
    {
        $transfer = $this->internalTransfer();
        $company = $this->company();
        $copy = $this->props(\App\Http\Controllers\InternalMoneyTransferController::class,
            [$company, $transfer->getType(), $transfer, true]);

        foreach (['id', 'cheque_number', 'transfer_date'] as $field) {
            $this->assertNotNull($transfer->{$field}, 'الأصل لازم يكون فيه '.$field.' عشان الفحص يبقى له معنى');
            $this->assertNull($copy['model'][$field], $field.' ما ينفعش يتنسخ');
        }

        // و اللي المفروض يتنسخ فعلا لازم يفضل
        $this->assertSame($transfer->getAmount(), $copy['model']['amount'], 'المبلغ هو نص فايدة النسخ');
        $this->assertSame($transfer->getCurrency(), $copy['model']['currency']);
    }

    /* ───────────── شراء / بيع العملة ───────────── */

    public function test_copying_a_currency_exchange_opens_a_create_form(): void
    {
        $row = $this->currencyExchange();
        $company = $this->company();
        $class = \App\Http\Controllers\BuyOrSellCurrenciesController::class;

        $edit = $this->props($class, [$company, $row, false]);
        $copy = $this->props($class, [$company, $row, true]);

        $this->assertSame('edit', $edit['mode']);
        $this->assertSame('create', $copy['mode']);
        $this->assertStringContainsString('store', $copy['submitUrl']);
    }

    public function test_a_copied_currency_exchange_drops_its_identity_and_date(): void
    {
        $row = $this->currencyExchange();
        $company = $this->company();
        $copy = $this->props(\App\Http\Controllers\BuyOrSellCurrenciesController::class, [$company, $row, true]);

        $this->assertNull($copy['model']['id']);
        $this->assertNull($copy['model']['transaction_date']);
        $this->assertSame($row->getExchangeRate(), $copy['model']['exchange_rate'], 'سعر الصرف لازم يتنسخ');
    }

    /* ───────────── اللي ما يتغيّرش ───────────── */

    /** * فتح فورمة تعديل عادية ما تتأثرش بإضافة النسخ */
    public function test_editing_still_carries_everything(): void
    {
        $transfer = $this->internalTransfer();
        $company = $this->company();
        $edit = $this->props(\App\Http\Controllers\InternalMoneyTransferController::class,
            [$company, $transfer->getType(), $transfer, false]);

        $this->assertSame($transfer->id, $edit['model']['id'], 'التعديل لازم يعرف هوية الصف');
        $this->assertSame($transfer->getTransferDate(), $edit['model']['transfer_date']);
        $this->assertStringContainsString('update', $edit['submitUrl']);
    }

    /**
     * * و نفس الضمانة للجهة التانية — من غيرها كان ممكن التنظيف يتطبق على
     * * التعديل كمان و يفضي هوية الصف و تاريخه من غير ما حد ياخد باله
     */
    public function test_editing_a_currency_exchange_still_carries_everything(): void
    {
        $row = $this->currencyExchange();
        $company = $this->company();
        $edit = $this->props(\App\Http\Controllers\BuyOrSellCurrenciesController::class, [$company, $row, false]);

        $this->assertSame($row->id, $edit['model']['id'], 'التعديل لازم يعرف هوية الصف');
        $this->assertSame($row->getTransactionDate(), $edit['model']['transaction_date'],
            'تاريخ العملية ما ينفعش يتفضّي في التعديل');
        $this->assertStringContainsString('update', $edit['submitUrl']);
    }

    /* ───────────── التوصيلات ───────────── */

    public function test_both_copy_routes_are_registered_as_get(): void
    {
        foreach (['internal-money-transfers.copy', 'buy-or-sell-currencies.copy'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, 'الراوت مش متسجل: '.$name);
            $this->assertContains('GET', $route->methods(), $name.' لازم يكون GET');
        }
    }

    /** * النسخ بيفتح فورمة إنشاء ، فلازم يتقيّد بصلاحية الإنشاء */
    public function test_both_copy_routes_require_the_create_permission(): void
    {
        $map = file_get_contents(app_path('Support/Permissions/RoutePermissionMap.php'));

        $this->assertStringContainsString("'internal-money-transfers.copy' => 'internal_money_transfer.create'", $map);
        $this->assertStringContainsString("'buy-or-sell-currencies.copy' => 'buy_or_sell_currency.create'", $map);
    }

    /** * الزرار لازم يبان في الشاشتين و يكون متقيّد بـ canCreate */
    public function test_both_index_pages_render_a_copy_button(): void
    {
        foreach ([
            'js/Pages/InternalMoneyTransfer/Index.vue',
            'js/Pages/BuyOrSellCurrencies/Index.vue',
        ] as $page) {
            $source = file_get_contents(resource_path($page));

            $this->assertStringContainsString('row.copy_url', $source, $page);
            $this->assertStringContainsString('canCreate && row.copy_url', $source,
                $page.': الزرار بيودّي لفورمة إنشاء فلازم يتقيّد بصلاحية الإنشاء');
            $this->assertStringContainsString('canUpdate || canDelete || canCreate', $source,
                $page.': خلية الأكشن لازم تظهر لليوزر اللي عنده إنشاء بس، و إلا الزرار مش هيتشاف');
        }
    }

    /** * الكونترولرز لازم يبعتوا الـ url مع كل صف */
    public function test_both_controllers_send_the_copy_url(): void
    {
        foreach ([
            ['InternalMoneyTransferController.php', 'internal-money-transfers.copy'],
            ['BuyOrSellCurrenciesController.php', 'buy-or-sell-currencies.copy'],
        ] as [$file, $routeName]) {
            $source = file_get_contents(app_path('Http/Controllers/'.$file));

            $this->assertStringContainsString("'copy_url' => route('".$routeName."'", $source, $file);
            $this->assertStringContainsString('public function copy(', $source, $file);
        }
    }
}
