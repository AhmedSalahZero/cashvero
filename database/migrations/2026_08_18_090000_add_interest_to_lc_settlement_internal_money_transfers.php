<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-requested (2026-08-18): interest for a bank-financed Letter of
 * Credit is no longer entered at "Mark as Paid" time (it can't be known
 * yet — it depends on the real settlement date). Instead each LC
 * Settlement Internal Transfer row — one per partial or full settlement
 * of a bank-financed LC — now carries its own interest amount and where
 * that interest was posted, since a single LC can be settled in several
 * partial payments over time, each potentially attracting its own
 * interest for the period since the previous settlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lc_settlement_internal_money_transfers', function (Blueprint $table) {
            $table->decimal('interest_amount', 14)->default(0)->after('amount')->comment('الفايدة المحسوبة/المعدلة لهذه التسوية بس — مش الفايدة التراكمية لكل القرض');
            $table->string('interest_destination')->nullable()->after('interest_amount')->comment('lc_overdraft = بتتقيد جوه كشف حساب الاعتماد نفسه (مدين ودائن) | current_account = بتتخصم كخروج فعلي من نفس الحساب اللي بيتسدد منه الأصل');
        });
    }

    public function down(): void
    {
        Schema::table('lc_settlement_internal_money_transfers', function (Blueprint $table) {
            $table->dropColumn(['interest_amount', 'interest_destination']);
        });
    }
};
