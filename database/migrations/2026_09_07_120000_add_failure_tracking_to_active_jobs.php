<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * * صف active_jobs هو اللي بيقول "في رفع شغال دلوقتي" ، و صفحة الرفع
 * * بتخفي فورمة الرفع طول ما هو موجود
 *
 * * لو الـ job مات من غير ما يرمي ImportFailed (الـ worker اتقفل ، الذاكرة
 * * خلصت ، timeout) الصف كان بيفضل موجود للأبد ، فالصفحة تفضل بتقول
 * * "جاري المعالجة" و المستخدم عمره ما يقدر يرفع تاني و لا يعرف السبب
 *
 * * العمودين دول بيخلونا نسجّل ان الرفع فشل و ليه ، بدل ما نمسح الصف و
 * * السبب يضيع
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('active_jobs', function (Blueprint $table) {
            $table->timestamp('failed_at')->nullable()->after('model_name');
            $table->text('failure_reason')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('active_jobs', function (Blueprint $table) {
            $table->dropColumn(['failed_at', 'failure_reason']);
        });
    }
};
