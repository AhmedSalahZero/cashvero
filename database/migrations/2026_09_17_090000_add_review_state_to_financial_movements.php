<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * * حالة المراجعة على الحركات المالية .
 *
 * * أربع جداول كان عندها is_reviewed / reviewed_by من قبل بس من غير أي
 * * شاشة تستعملهم ؛ الباقي مالوش . الهجرة دي بتوحّد الأعمدة الأربعة على
 * * كل الحركات المالية عشان الميزة تشتغل بنفس الشكل في كل صفحة .
 *
 * * reviewed_at بيتخزّن عشان نعرف *امتى* اتراجعت — السجل بيقول مين و إيه
 * * لكن الوقت لازم يبقى على الصف نفسه عشان الفلترة و التقارير .
 */
return new class extends Migration
{
    /** الجداول اللي عليها حركات مالية تتراجع */
    private const TABLES = [
        'money_received',
        'money_payments',
        'factoring_transactions',
        'lc_settlement_internal_money_transfers',
        'cash_expenses',
        'multiple_cash_expenses',
        'internal_money_transfers',
        'buy_or_sell_currencies',
        'foreign_exchange_rates',
        'letter_of_guarantee_issuances',
        'letter_of_credit_issuances',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'is_reviewed')) {
                    $t->boolean('is_reviewed')->default(false);
                }

                if (! Schema::hasColumn($table, 'reviewed_by')) {
                    $t->unsignedBigInteger('reviewed_by')->nullable();
                }

                if (! Schema::hasColumn($table, 'reviewed_at')) {
                    $t->timestamp('reviewed_at')->nullable();
                }

                if (! Schema::hasColumn($table, 'review_comment')) {
                    $t->text('review_comment')->nullable();
                }
            });

            /**
             * * الفهرس عشان فلتر "المراجَع / غير المراجَع" في صفحة الـ index
             * * ما يعملش full scan ، و الجداول دي بتكبر مع الوقت .
             *
             * * الاسم مختصر بالـ md5 : MySQL بتقف عند ٦٤ حرف و أسماء زي
             * * lc_settlement_internal_money_transfers بتعدّيها .
             */
            $index = 'rev_'.substr(md5($table), 0, 16).'_idx';

            if (! $this->indexExists($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->index(['company_id', 'is_reviewed'], $index));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $index = 'rev_'.substr(md5($table), 0, 16).'_idx';

            if ($this->indexExists($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
            }

            /**
             * * الأعمدة اللي كانت موجودة قبل الهجرة دي مش بتتشال — مش
             * * بتاعتنا عشان نحذفها
             */
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['reviewed_at', 'review_comment'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => $row->Key_name === $index);
    }
};
