<?php

namespace Tests\Feature\NonBanking;

use Tests\TestCase;

/**
 * * الملف ده جاي منقول من system.veroanalysisb.com. موديول
 * * non-banking-service مش جزء من cashvero أصلا :
 * *
 * *   - الكلاس App\Models\NonBankingService\Study مش موجود
 * *   - و الراوت non_banking_services.balance_sheet.view مش مسجّل
 * *
 * * فالنسخة القديمة كانت بترمي "Class not found" في كل تشغيل ، يعني فشل
 * * دايم مالوش علاقة بأي كود هنا — و ده بالظبط اللي بيخلي السويت الحمرا
 * * تتجاهل. بنوضّح السبب بدل ما نسيبه أحمر.
 *
 * * لو الموديول مش هيتضاف لـ cashvero ، الملف ده يتحذف.
 */
class BalanceSheetTest extends TestCase
{
    public function test_the_non_banking_module_is_not_part_of_this_application(): void
    {
        $this->assertFalse(class_exists(\App\Models\NonBankingService\Study::class),
            'لو الموديول اتضاف ، الاختبار ده يترجع لنسخة system.veroanalysisb.com بدل ما يتخطّى');

        $this->markTestSkipped('non-banking-service is not part of cashvero — this file came over from the other system.');
    }
}
