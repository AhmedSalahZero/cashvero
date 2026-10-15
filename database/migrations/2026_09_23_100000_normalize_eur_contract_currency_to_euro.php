<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * * العملة القياسية في النظام كله هي 'EURO' — دي القيمة الوحيدة اللي
 * * getCurrencies() بترجّعها ، و اللي منتقي العملات في التقارير بيبعتها ،
 * * و اللي أسعار الصرف متخزّنة بيها في foreign_exchange_rates .
 *
 * * التطبيع ده مطبّق فعلا في كذا مكان : تريجرات customer_invoices و
 * * supplier_invoices بتعمل
 * *     IF (new.currency = 'EUR') then set new.currency = 'EURO'; end if;
 * * و ImportData بتعمله كمان — لكن جدول contracts اتنسي .
 *
 * * النتيجة : عقود عملتها 'EUR' كانت بتقع بين كرسيين
 * *   ١. تقرير الـ Consolidated بيفلتر بـ whereIn(currency, [... 'EURO' ...])
 * *      فما كانش بيلقطها اصلا .
 * *   ٢. تقرير الشركة بيلقطها ، بس getExchangeRateAtOrOne('EUR','EGP')
 * *      بترجّع 1 لان مفيش سعر متخزّن بالاسم ده — فمبلغ اليورو كان بيتعرض
 * *      على انه جنيه ، اقل من قيمته الحقيقية بحوالي 56 مرة .
 *
 * * وقت كتابة الميجريشن كان فيه 3 عقود موردين بس بالقيمة دي ، مالهاش
 * * فواتير ولا دفعات مقدمة ولا po_allocations ولا عقود تابعة — فالتحويل
 * * مابيأثرش على اي ترابط ، بس بيخلي مبالغها تتحوّل بسعر الصرف الصح .
 */
return new class extends Migration
{
    public function up(): void
    {
        // ١. تصحيح الصفوف الموجودة
        DB::table('contracts')->where('currency', 'EUR')->update([
            'currency' => 'EURO',
        ]);

        // ٢. منع الرجوع : تريجر تطبيع زي اللي على جدولي الفواتير بالظبط .
        //    بيتحط هنا كمان مش في app/Triggers/Cashvero/contracts_triggers.sql
        //    بس ، عشان الـ deploy يمشي حتى لو حد نسي `php artisan run:sql` .
        //    الملف و الميجريشن بيعرّفوا نفس التريجر بنفس الاسم ، و
        //    `drop trigger if exists` بيخلي اعادة التشغيل امنة .
        foreach ($this->triggers() as $name => $timing) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$name}`");
            DB::unprepared(
                "CREATE TRIGGER `{$name}` BEFORE {$timing} ON `contracts` FOR EACH ROW ".
                "BEGIN IF (NEW.currency = 'EUR') THEN SET NEW.currency = 'EURO'; END IF; END"
            );
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->triggers()) as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$name}`");
        }

        /*
         * * البيانات مابترجعش : 'EUR' كانت قيمة غلط من الاساس ، و اي
         * * UPDATE عكسي هيحوّل معاها العقود اللي عملتها 'EURO' صح من
         * * الاول — و مافيش طريقة نفرّق بينهم بعد التحويل .
         */
    }

    /** @return array<string, string> اسم التريجر => توقيته */
    private function triggers(): array
    {
        return [
            'normalize_contract_currency_before_insert' => 'INSERT',
            'normalize_contract_currency_before_update' => 'UPDATE',
        ];
    }
};
