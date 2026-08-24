<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * * عقد بتنفيذ شهري: قيمته بتتوزّع بالتساوي على شهور مدّته بدل ما
 * * تتعلّق بأوامر بيع/شراء وتنزل دفعة واحدة في تاريخ التحصيل.
 *
 * * الافتراضي 0 فكل العقود الحالية بتفضل بنفس سلوكها بالظبط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('is_monthly_executed')->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('is_monthly_executed');
        });
    }
};
