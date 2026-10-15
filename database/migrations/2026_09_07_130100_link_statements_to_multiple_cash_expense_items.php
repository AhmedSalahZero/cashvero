<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * * كل بند في المصروف المتعدد بينزل سطر مستقل في كشف الحساب — و أسطر
 * * الكشوف بترتبط بمصدرها بعمود مفتاح مخصّص لكل نوع مصدر
 * * (cash_expense_id ، money_payment_id ... إلخ)
 *
 * * فالبند محتاج عموده الخاص بيه في كل كشف ممكن يتصرف منه ، بالظبط زي
 * * ما المصروف العادي عنده cash_expense_id
 */
return new class extends Migration
{
    /**
     * * الكشوف اللي المصروف ممكن يتصرف منها : الخزنة ، الحساب الجاري ،
     * * و أنواع الجاري مدين الأربعة
     */
    private const STATEMENT_TABLES = [
        'cash_in_safe_statements',
        'current_account_bank_statements',
        'clean_overdraft_bank_statements',
        'fully_secured_overdraft_bank_statements',
        'overdraft_against_commercial_paper_bank_statements',
        'overdraft_against_assignment_of_contract_bank_statements',
    ];

    public function up(): void
    {
        foreach (self::STATEMENT_TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'multiple_cash_expense_item_id')) {
                continue;
            }

            /**
             * * الاسم التلقائي للفهرس على بعض الجداول بيعدّي حد الـ 64 حرف
             * * بتاع MySQL ، فبنسميه بإيدنا باسم قصير مشتق من الجدول
             */
            $indexName = 'mce_item_'.substr(md5($table), 0, 12).'_idx';

            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->unsignedBigInteger('multiple_cash_expense_item_id')->nullable();
                $blueprint->index('multiple_cash_expense_item_id', $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach (self::STATEMENT_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'multiple_cash_expense_item_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('multiple_cash_expense_item_id');
            });
        }
    }
};
