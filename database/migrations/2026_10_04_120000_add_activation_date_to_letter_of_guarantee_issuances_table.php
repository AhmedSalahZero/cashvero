<?php

use App\Enums\LgTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * * خطاب ضمان الدفعة المقدمة مشروط بوصول شيك الدفعة من العميل — البنك
 * * مش بيفعّله غير لما الدفعة توصل . activation_date هو اليوم ده ، و لحد
 * * ما يتكتب مفيش اي حركة بتنزل للخطاب (لا كاش كفر و لا مصاريف اصدار و
 * * لا عمولة و لا رصيد) لا عندنا و لا في Odoo
 *
 * * is_activated نفس المعلومة بس جاهزة للاستعلامات . الـ default = 1 يعني
 * * اي نوع تاني و اي صف قديم مفعّل من غير ما حد يلمسه ، و الموديل بيحدّثه
 * * مع كل حفظ
 *
 * * خطابات الدفعة المقدمة الموجودة حركاتها نازلة بتاريخ الاصدار ، يعني
 * * اتفعّلت يومها — فـ activation_date بياخد نفس التاريخ . التحديث بـ SQL
 * * مباشر عشان ما يعديش على الموديل : مفيش قيود Odoo هتتعمل تاني و قفل
 * * المراجعة مايوقفش الهجرة
 *
 * @see \App\Models\LetterOfGuaranteeIssuance::isActivated()
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * * cancel() و backToRunningStatus() بيكتبوا في cancellation_date
         * * من زمان ، بس العمود ده عمره ما اتعمله migration — و الغاء خطاب
         * * لسه مش مفعّل مالوش حركة الغاء نقرا منها التاريخ . الشرط عشان
         * * السيرفرات اللي العمود اتضاف فيها بإيد
         */
        $hasCancellationDate = Schema::hasColumn('letter_of_guarantee_issuances', 'cancellation_date');

        Schema::table('letter_of_guarantee_issuances', function (Blueprint $table) use ($hasCancellationDate) {
            $table->date('activation_date')->nullable()->after('purchase_order_date');
            $table->boolean('is_activated')->default(true)->after('activation_date');

            if (! $hasCancellationDate) {
                $table->date('cancellation_date')->nullable()->after('renewal_date');
            }
        });

        DB::table('letter_of_guarantee_issuances')
            ->where('lg_type', LgTypes::ADVANCED_PAYMENT_LGS)
            ->update(['activation_date' => DB::raw('issuance_date')]);

        DB::table('letter_of_guarantee_issuances')
            ->where('lg_type', LgTypes::ADVANCED_PAYMENT_LGS)
            ->whereNull('activation_date')
            ->update(['is_activated' => false]);
    }

    public function down(): void
    {
        /**
         * * cancellation_date مش بيتمسح : ممكن يكون موجود قبل الهجرة دي
         */
        Schema::table('letter_of_guarantee_issuances', function (Blueprint $table) {
            $table->dropColumn(['activation_date', 'is_activated']);
        });
    }
};
