<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * البنك بيغير الـ cash cover والعمولة عند التجديد .. فا كل صف تجديد
 * بقى بيحتفظ بالشروط الجديدة اللي بدأت من عنده وكمان بالشروط القديمة
 * اللي كانت قبله (previous_*) علشان لو الصف اتعدل او اتحذف نرجع الـ LG
 * لحالته الاولى بالظبط من غير ما نضطر نعيد حساب اى حاجة من الاول
 *
 * كلهم nullable عن قصد: NULL معناها "التجديد ده ما غيرش الشرط ده"
 * وده اللى بيخلى كل صفوف التجديد القديمة تفضل شغالة زى ما هى
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lg_renewal_date_histories', function (Blueprint $table) {
            $table->decimal('cash_cover_amount', 14)->nullable()->after('fees_amount');
            $table->decimal('cash_cover_rate', 5)->nullable()->after('cash_cover_amount');
            $table->decimal('lg_commission_amount', 14)->nullable()->after('cash_cover_rate');
            $table->decimal('min_lg_commission_fees', 14)->nullable()->after('lg_commission_amount');

            $table->decimal('previous_cash_cover_amount', 14)->nullable()->after('min_lg_commission_fees');
            $table->decimal('previous_cash_cover_rate', 5)->nullable()->after('previous_cash_cover_amount');
            $table->decimal('previous_lg_commission_amount', 14)->nullable()->after('previous_cash_cover_rate');
            $table->decimal('previous_min_lg_commission_fees', 14)->nullable()->after('previous_lg_commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('lg_renewal_date_histories', function (Blueprint $table) {
            $table->dropColumn([
                'cash_cover_amount',
                'cash_cover_rate',
                'lg_commission_amount',
                'min_lg_commission_fees',
                'previous_cash_cover_amount',
                'previous_cash_cover_rate',
                'previous_lg_commission_amount',
                'previous_min_lg_commission_fees',
            ]);
        });
    }
};
