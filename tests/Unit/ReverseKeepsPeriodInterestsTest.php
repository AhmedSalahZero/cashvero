<?php

namespace Tests\Unit;

use App\Models\CertificatesOfDeposit;
use App\Models\CurrentAccountBankStatement;
use App\Models\TimeOfDeposit;
use Tests\TestCase;

/**
 * * التراجع عن الاستحقاق أو الكسر كان بيحذف **كل** صفوف الوديعة ، يعني
 * * الفوايد الدورية اللي المستخدم نزّلها بإيده على مدار شهور كانت
 * * بتروح معاها — و ده اللي حصل فعلاً للوديعة 39 و ضيّع 20 فايدة
 *
 * * و قيود الفوايد بتفضل في اودو ، فالداتا المحلية و اودو يفضلوا
 * * مختلفين — نفس الفجوة اللي اتصلحت بالإيد
 */
class ReverseKeepsPeriodInterestsTest extends TestCase
{
    private function statement(array $attributes): CurrentAccountBankStatement
    {
        $row = new CurrentAccountBankStatement;
        $row->forceFill(array_merge([
            'type' => null,
            'is_period_cd_or_td_interest' => 0,
            'is_break_interest' => 0,
            'is_td_renewal' => 0,
        ], $attributes));

        return $row;
    }

    /**
     * * وديعة فيها كل الأنواع اللي بتظهر في الداتا الحقيقية
     */
    private function depositWithEveryRowKind(string $class = TimeOfDeposit::class): object
    {
        $deposit = new $class;
        $deposit->setRelation('currentAccountBankStatements', new \Illuminate\Database\Eloquent\Collection([
            $this->statement(['id' => 1, 'is_period_cd_or_td_interest' => 1]),                                  // فايدة دورية
            $this->statement(['id' => 2, 'is_period_cd_or_td_interest' => 1, 'is_break_interest' => null]),      // فايدة دورية قديمة
            $this->statement(['id' => 3, 'is_td_renewal' => 1, 'is_break_interest' => null]),                    // تجديد
            $this->statement(['id' => 4, 'type' => CurrentAccountBankStatement::DEDUCTED_FOR_CURRENT_ACCOUNT]),  // الخصم الأصلي
            $this->statement(['id' => 5]),                                                                      // أصل الوديعة
            $this->statement(['id' => 6, 'is_break_interest' => 1]),                                             // فايدة الاستحقاق
            $this->statement(['id' => 7, 'is_break_interest' => null]),                                          // أصل وديعة قديم
        ]));

        return $deposit;
    }

    private function idsToDelete(string $class = TimeOfDeposit::class): array
    {
        return $this->depositWithEveryRowKind($class)
            ->statementRowsFromMaturityOrBreak()
            ->pluck('id')
            ->all();
    }

    /* ───────────── اللي بيفضل ───────────── */

    public function test_period_interests_survive(): void
    {
        $this->assertNotContains(1, $this->idsToDelete(), 'الفايدة الدورية لازم تفضل');
    }

    /**
     * * فيه 11 صف فايدة دورية عندهم is_break_interest = NULL — لازم
     * * يتعاملوا زي الباقي
     */
    public function test_an_older_period_interest_row_survives_too(): void
    {
        $this->assertNotContains(2, $this->idsToDelete(), 'الفايدة القديمة (brk=NULL) لازم تفضل');
    }

    public function test_a_renewal_row_survives(): void
    {
        $this->assertNotContains(3, $this->idsToDelete(), 'التجديد حدث مستقل');
    }

    /**
     * * الفلوس لسه في الوديعة لأنها رجعت running ، فالخصم لازم يفضل و
     * * إلا الحساب يزيد بمبلغ الوديعة بالغلط
     */
    public function test_the_original_deduction_survives(): void
    {
        $this->assertNotContains(4, $this->idsToDelete(), 'الخصم الأصلي لازم يفضل');
    }

    /* ───────────── اللي بيتحذف ───────────── */

    public function test_the_principal_row_is_deleted(): void
    {
        $this->assertContains(5, $this->idsToDelete(), 'أصل الوديعة من عملية الاستحقاق');
    }

    public function test_the_maturity_interest_row_is_deleted(): void
    {
        $this->assertContains(6, $this->idsToDelete(), 'فايدة الاستحقاق من العملية نفسها');
    }

    public function test_an_older_principal_row_is_deleted_too(): void
    {
        $this->assertContains(7, $this->idsToDelete(), 'أصل وديعة قديم (brk=NULL) برضه يتحذف');
    }

    public function test_exactly_three_kinds_are_deleted(): void
    {
        $this->assertSame([5, 6, 7], $this->idsToDelete());
    }

    /* ───────────── شهادة الإيداع ───────────── */

    public function test_certificates_of_deposit_behave_the_same(): void
    {
        $this->assertSame([5, 6, 7], $this->idsToDelete(CertificatesOfDeposit::class));
    }

    /* ───────────── تفاصيل تقنية بتفرق ───────────── */

    /**
     * * where() على الكوليكشن بتقارن بـ == السايبة ، و null == 0 بترجع
     * * true في PHP — فصف فايدة قيمتها null كان ممكن يتحذف
     */
    public function test_a_null_period_interest_flag_is_treated_as_not_an_interest(): void
    {
        $deposit = new TimeOfDeposit;
        $deposit->setRelation('currentAccountBankStatements', new \Illuminate\Database\Eloquent\Collection([
            $this->statement(['id' => 10, 'is_period_cd_or_td_interest' => null]),
        ]));

        $this->assertSame([10], $deposit->statementRowsFromMaturityOrBreak()->pluck('id')->all());
    }

    /**
     * * deleteButTriggerChangeOnLastElement بتطلب Eloquent Collection
     * * مش Support Collection
     */
    public function test_it_returns_an_eloquent_collection(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Collection::class,
            $this->depositWithEveryRowKind()->statementRowsFromMaturityOrBreak()
        );
    }

    public function test_a_deposit_with_nothing_to_delete_returns_an_empty_collection(): void
    {
        $deposit = new TimeOfDeposit;
        $deposit->setRelation('currentAccountBankStatements', new \Illuminate\Database\Eloquent\Collection([
            $this->statement(['id' => 20, 'is_period_cd_or_td_interest' => 1]),
        ]));

        $this->assertTrue($deposit->statementRowsFromMaturityOrBreak()->isEmpty());
    }

    /* ───────────── التوصيلات في الأربع أماكن ───────────── */

    public function test_every_reverse_action_uses_the_shared_filter(): void
    {
        foreach (['TimeOfDepositsController', 'CertificatesOfDepositsController'] as $controller) {
            $source = file_get_contents(app_path('Http/Controllers/'.$controller.'.php'));

            $this->assertSame(
                2,
                substr_count($source, 'statementRowsFromMaturityOrBreak()'),
                $controller.' لازم يستخدمها في reverseDeposit و reverseBroken'
            );

            $this->assertStringNotContainsString(
                "currentAccountBankStatements->where('type','!=',CurrentAccountBankStatement::DEDUCTED_FOR_CURRENT_ACCOUNT)",
                $source,
                $controller.' مافيهوش فلتر قديم فاضل'
            );
        }
    }

    /**
     * * الحذف التاني غير المفلتر كان بيلغي حماية السطر اللي فوقه ، لأن
     * * العلاقة متخزنة في الذاكرة فبيشتغل على نسخة لسه فيها الخصم الأصلي
     */
    public function test_the_second_unfiltered_delete_is_gone_from_the_reverse_actions(): void
    {
        foreach (['TimeOfDepositsController', 'CertificatesOfDepositsController'] as $controller) {
            $source = file_get_contents(app_path('Http/Controllers/'.$controller.'.php'));

            foreach (['reverseDeposit', 'reverseBroken'] as $method) {
                preg_match('/public function '.$method.'\(.*?\n\t\}/s', $source, $matches);
                $this->assertNotEmpty($matches, $controller.'::'.$method.' مش موجودة');

                /**
                 * * بنشيل الكوميّنتات : الشرح جواها بيذكر اسم الميثود
                 * * فبيتعد نداء و هو مش نداء
                 */
                $body = preg_replace('#/\*.*?\*/#s', '', $matches[0]);

                $this->assertSame(
                    1,
                    substr_count($body, 'deleteButTriggerChangeOnLastElement'),
                    $controller.'::'.$method.' لازم تحذف مرة واحدة بس'
                );
            }
        }
    }

    /**
     * * حذف الوديعة نفسها حاجة تانية خالص — المفروض يشيل كل حاجة
     */
    public function test_deleting_the_deposit_itself_still_removes_everything(): void
    {
        foreach (['TimeOfDepositsController', 'CertificatesOfDepositsController'] as $controller) {
            $source = file_get_contents(app_path('Http/Controllers/'.$controller.'.php'));

            preg_match('/public function destroy\(.*?\n\t\}/s', $source, $matches);
            $this->assertNotEmpty($matches);

            $this->assertStringContainsString(
                'currentAccountBankStatements)',
                $matches[0],
                'destroy لازم تفضل بتحذف كل حاجة'
            );
            $this->assertStringNotContainsString('statementRowsFromMaturityOrBreak', $matches[0]);
        }
    }
}
