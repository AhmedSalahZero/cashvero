<?php

namespace Tests\Feature\Formatting;

use Tests\TestCase;

/**
 * * أعمدة الفلوس في صفحات الـ index لازم تعرض خانتين عشريتين .
 *
 * * number_format() من غير البارامتر التاني بيقرّب لأقرب واحد صحيح ، فمبلغ
 * * زي 4,349.75 كان بيبان 4,350 — القرشين بيتوهوا من غير ما حد ياخد باله .
 *
 * * الاختبار ده مستقل عن الداتا : بيبني الموديل في الميموري بقيمة فيها
 * * كسور و بيقرا الـ accessor على طول ، فبيفضل صحيح حتى لو الداتا اتغيّرت
 * * أو فضيت .
 */
class IndexMoneyDecimalsTest extends TestCase
{
    /**
     * كل صفحة من اللي العميل عدّدها : الموديل ، و عمود الفلوس فيه ،
     * و الميثود اللي بتعرضه
     *
     * @return array<string, array{0: class-string, 1: string, 2: string}>
     */
    public static function moneyColumns(): array
    {
        return [
            'money received · received amount' => [\App\Models\MoneyReceived::class, 'received_amount', 'getReceivedAmountFormatted'],
            'money payment · paid amount' => [\App\Models\MoneyPayment::class, 'paid_amount', 'getPaidAmountFormatted'],
            'cash expense · paid amount' => [\App\Models\CashExpense::class, 'paid_amount', 'getPaidAmountFormatted'],
            'internal transfer · amount' => [\App\Models\InternalMoneyTransfer::class, 'amount', 'getAmountFormatted'],
            'lc settlement transfer · amount' => [\App\Models\LcSettlementInternalMoneyTransfer::class, 'amount', 'getAmountFormatted'],
            'buy/sell · amount to sell' => [\App\Models\BuyOrSellCurrency::class, 'currency_to_sell_amount', 'getAmountToSellFormatted'],
            'buy/sell · amount to buy' => [\App\Models\BuyOrSellCurrency::class, 'currency_to_buy_amount', 'getAmountToBuyFormatted'],
            'lg · amount' => [\App\Models\LetterOfGuaranteeIssuance::class, 'lg_amount', 'getLgAmountFormatted'],
            'lg · cash cover' => [\App\Models\LetterOfGuaranteeIssuance::class, 'cash_cover_amount', 'getCashCoverAmountFormatted'],
            'lg · commission' => [\App\Models\LetterOfGuaranteeIssuance::class, 'lg_commission_amount', 'getLgCommissionAmountFormatted'],
            'lc · amount' => [\App\Models\LetterOfCreditIssuance::class, 'lc_amount', 'getLcAmountFormatted'],
            'lc · cash cover' => [\App\Models\LetterOfCreditIssuance::class, 'cash_cover_amount', 'getCashCoverAmountFormatted'],
            'lc · commission' => [\App\Models\LetterOfCreditIssuance::class, 'lc_commission_amount', 'getLcCommissionAmountFormatted'],
            'lc · interest' => [\App\Models\LetterOfCreditIssuance::class, 'interest_amount', 'getInterestAmountFormatted'],
        ];
    }

    /** @dataProvider moneyColumns */
    public function test_the_column_keeps_two_decimals(string $class, string $column, string $method): void
    {
        $model = new $class;
        $model->forceFill([$column => 4349.75]);

        $this->assertSame('4,349.75', $model->{$method}(),
            class_basename($class)."::{$method}() لازم يعرض كسرين");
    }

    /** * نص جنيه كان بيتقرّب و يختفي — ده الشكل اللي العميل شافه */
    /** @dataProvider moneyColumns */
    public function test_a_half_unit_is_not_rounded_away(string $class, string $column, string $method): void
    {
        $model = new $class;
        $model->forceFill([$column => 4349.5]);

        $this->assertNotSame('4,350', $model->{$method}());
        $this->assertSame('4,349.50', $model->{$method}());
    }

    /** * و الأرقام الصحيحة بتكتب أصفارها */
    /** @dataProvider moneyColumns */
    public function test_whole_numbers_still_show_both_decimals(string $class, string $column, string $method): void
    {
        $model = new $class;
        $model->forceFill([$column => 313680]);

        $this->assertSame('313,680.00', $model->{$method}());
    }

    /**
     * * أسعار الصرف مش فلوس — دي بتتعرض بدقة أعلى ، و التعديل ده
     * * مالوش يمسّها
     */
    public function test_exchange_rates_keep_their_own_precision(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ForeignExchangeRateController.php'));

        $this->assertStringContainsString('number_format($exchangeRate, 4)', $controller,
            'سعر الصرف لازم يفضل بأربع خانات');

        $buyOrSell = file_get_contents(app_path('Http/Controllers/BuyOrSellCurrenciesController.php'));

        $this->assertStringContainsString('number_format($model->getExchangeRate(), 4)', $buyOrSell);
    }
}
