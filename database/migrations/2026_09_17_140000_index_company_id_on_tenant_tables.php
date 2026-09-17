<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * * فهرس على company_id في كل جدول مالوش .
 *
 * * company_id هو فلتر المستأجر : بيظهر في كل استعلام تقريبا في
 * * التطبيق . من غير فهرس ، كل واحد منهم بيقرا الجدول كله و يرمي
 * * الصفوف اللي مش بتاعة الشركة — و ده بيكبر مع كل شركة جديدة .
 *
 * * الهجرة دي بتعدّي على الجداول الموجودة فعلا و بتضيف الفهرس الناقص
 * * بس ، فتشغيلها مرتين مالوش أثر .
 *
 * * ⚠️ على قاعدة إنتاج كبيرة إضافة الفهرس بتقفل الجدول لحظيا ؛ يفضّل
 * * تتشغّل في وقت هادي .
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tablesMissingTheIndex() as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->index('company_id', $this->indexName($table));
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablesWithOurIndex() as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($this->indexName($table));
            });
        }
    }

    /**
     * * الاسم مختصر بالـ md5 : MySQL بتقف عند ٦٤ حرف و في جداول اسمها
     * * لوحده بيقرّب من الحد
     */
    private function indexName(string $table): string
    {
        return 'cid_'.substr(md5($table), 0, 16).'_idx';
    }

    /** @return list<string> */
    private function tablesMissingTheIndex(): array
    {
        $out = [];

        foreach ($this->allTables() as $table) {
            if (! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            // عمود أول في أي فهرس = مفهرس فعلا
            $indexed = collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->where('Seq_in_index', 1)
                ->pluck('Column_name')
                ->contains('company_id');

            if (! $indexed) {
                $out[] = $table;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function tablesWithOurIndex(): array
    {
        $out = [];

        foreach ($this->allTables() as $table) {
            $has = collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->contains(fn ($row) => $row->Key_name === $this->indexName($table));

            if ($has) {
                $out[] = $table;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function allTables(): array
    {
        return array_map(
            fn ($row) => array_values((array) $row)[0],
            DB::select('SHOW TABLES')
        );
    }
};
