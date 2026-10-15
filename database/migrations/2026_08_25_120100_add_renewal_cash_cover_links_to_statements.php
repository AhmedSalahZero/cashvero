<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فرق الـ cash cover اللى بيتخصم عند التجديد بيتسجل فى مكانين — زى ما
 * بيحصل بالظبط وقت الاصدار: صف فى letter_of_guarantee_cash_cover_statements
 * وصف فى current_account_bank_statements .. الاتنين محتاجين يعرفوا
 * انهم بتوع أنهى تجديد علشان لو التجديد اتعدل او اتحذف يتشالوا معاه
 *
 * is_renewal_cash_cover عمود منفصل عن is_renewal_fees عن قصد: الـ cash
 * cover مش مصاريف .. لو اتحسب كمصاريف هيدخل غلط فى تقارير الـ
 * Commission & Fees (شوف LetterOfGuaranteeIssuance::getCommissionAndFeesAtDates)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_of_guarantee_cash_cover_statements', function (Blueprint $table) {
            $table->unsignedBigInteger('lg_renewal_date_history_id')->nullable()->after('lg_advanced_payment_history_id');
            $table->index('lg_renewal_date_history_id', 'lg_cash_cover_renewal_history_index');
        });

        Schema::table('current_account_bank_statements', function (Blueprint $table) {
            $table->boolean('is_renewal_cash_cover')->default(false)->after('is_renewal_fees');
        });
    }

    public function down(): void
    {
        Schema::table('letter_of_guarantee_cash_cover_statements', function (Blueprint $table) {
            $table->dropIndex('lg_cash_cover_renewal_history_index');
            $table->dropColumn('lg_renewal_date_history_id');
        });

        Schema::table('current_account_bank_statements', function (Blueprint $table) {
            $table->dropColumn('is_renewal_cash_cover');
        });
    }
};
