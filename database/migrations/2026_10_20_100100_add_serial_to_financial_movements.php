<?php

use App\Support\Serials\SerialBackfiller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * * الرقم المسلسل على الحركات المالية — MR/CIS/2026/10/00001 .
 * * شوف App\Support\Serials\SerialRegistry .
 *
 * * الـ unique على (company_id, serial) بيتعمل قبل الترقيم عشان يحمي
 * * الترقيم نفسه ، و الترقيم بيحصل هنا (مش أمر بعد الديبلوي) عشان
 * * الموقع بيبقى down وقت الهجرة : لو حركة جديدة اتعملت قبل الترقيم كانت
 * * هتاخد 00001 و الحركات القديمة تاخد أرقام بعدها .
 */
return new class extends Migration
{
    private const TABLES = [
        'money_received',
        'money_payments',
        'cash_expenses',
        'multiple_cash_expenses',
        'internal_money_transfers',
        'buy_or_sell_currencies',
        'letter_of_guarantee_issuances',
        'letter_of_credit_issuances',
        'factoring_transactions',
        'lc_settlement_internal_money_transfers',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'serial')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('serial', 32)->nullable());
            }

            /**
             * * الاسم مختصر بالـ md5 : MySQL بتقف عند ٦٤ حرف و أسماء زي
             * * lc_settlement_internal_money_transfers بتعدّيها
             */
            $index = $this->indexName($table);

            if (! $this->indexExists($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->unique(['company_id', 'serial'], $index));
            }
        }

        app(SerialBackfiller::class)->run();
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $index = $this->indexName($table);

            if ($this->indexExists($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($index));
            }

            if (Schema::hasColumn($table, 'serial')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('serial'));
            }
        }

        if (Schema::hasTable('serial_sequences')) {
            DB::table('serial_sequences')->truncate();
        }
    }

    private function indexName(string $table): string
    {
        return 'ser_'.substr(md5($table), 0, 16).'_uq';
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => $row->Key_name === $index);
    }
};
