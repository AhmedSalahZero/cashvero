<?php

use App\Support\Serials\SerialRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * * الرقم المسلسل للحركة (MR/CIS/2026/10/00001) وقت ما الحدث اتسجّل .
 *
 * * السجل اليومي بيعرض الحركة برقمها بدل #id . للحركة اللي لسه موجودة
 * * الرقم بيتقري من الصف نفسه ، لكن الحركة المحذوفة صفها راح — فالرقم
 * * لازم يتحفظ مع الحدث نفسه ، زي user_name بالظبط .
 *
 * * الأحداث القديمة بتاخد رقم الحركة لو لسه موجودة ؛ أحداث الحركات اللي
 * * اتحذفت قبل كده بتفضل #id .
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('record_activities', 'subject_serial')) {
            Schema::table('record_activities', fn (Blueprint $t) => $t->string('subject_serial', 32)->nullable());
        }

        foreach (array_keys(SerialRegistry::MODELS) as $class) {
            $table = (new $class)->getTable();

            DB::statement(
                "UPDATE record_activities ra
                 JOIN `{$table}` t ON t.id = ra.subject_id
                 SET ra.subject_serial = t.serial
                 WHERE ra.subject_type = ? AND ra.subject_serial IS NULL AND t.serial IS NOT NULL",
                [$class]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('record_activities', 'subject_serial')) {
            Schema::table('record_activities', fn (Blueprint $t) => $t->dropColumn('subject_serial'));
        }
    }
};
