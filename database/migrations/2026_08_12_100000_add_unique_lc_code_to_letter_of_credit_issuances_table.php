<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lc_code هو رقم الاعتماد الصادر من البنك — مينفعش يتكرر مع نفس البنك
 * في نفس الشركة. الفاليديشن في StoreLetterOfCreditIssuanceRequest بيمسك
 * الحالة من الفورم ، والفهرس ده بيقفل الباب على أي POST مباشر.
 *
 * العمود بيقبل NULL و MySQL بتسمح بتكرار الـ NULL في الفهرس الـ unique ،
 * فالصفوف القديمة اللي مالهاش كود مش هتتأثر.
 */
return new class extends Migration
{
    private const TABLE = 'letter_of_credit_issuances';
    private const INDEX = 'lc_issuances_company_bank_code_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || $this->indexExists()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->unique(['company_id', 'financial_institution_id', 'lc_code'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! $this->indexExists()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }

    private function indexExists(): bool
    {
        return collect(Schema::getIndexes(self::TABLE))
            ->contains(fn ($index) => $index['name'] === self::INDEX);
    }
};
