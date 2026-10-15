<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Internal settlements, allocated across real invoices.
 *
 * v1 stored one flat amount and subtracted it from the balance at the
 * page level. That never touched the invoices, so nothing downstream
 * of an invoice — its net_balance, its status, the aging buckets —
 * knew the money had moved.
 *
 * An internal settlement now writes the SAME rows a collection and a
 * payment write:
 *
 *   settlements          → trigger → customer_invoices.collected_amount → net_balance
 *   payment_settlements  → trigger → supplier_invoices.paid_amount      → net_balance
 *
 * so the balances move through the app's own machinery rather than
 * being adjusted on the way to the screen. Reversing one is a DELETE:
 * the delete triggers recompute each invoice from whatever rows remain.
 *
 * The link column is what makes that reversal exact — it is how an
 * edit or a delete finds precisely the rows this settlement created,
 * and nothing else's.
 *
 * @see \App\Models\InternalSettlement::applyAllocations()
 */
return new class extends Migration
{
    /** table => the column its own settlement rows hang off */
    private const TABLES = ['settlements', 'payment_settlements'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'internal_settlement_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unsignedBigInteger('internal_settlement_id')
                    ->nullable()
                    ->after('company_id')
                    ->comment('Set only on rows created by an internal settlement — see App\Models\InternalSettlement');
                $blueprint->index('internal_settlement_id', $table.'_internal_settlement_id_index');
            });
        }

        /**
         * The v1 rows carry no allocations, so they would be the only
         * settlements in the system that move a balance without moving
         * an invoice — two behaviours living side by side. Confirmed
         * with the project owner that these are test entries and are to
         * be removed rather than migrated (one of them is against a
         * partner with no open supplier invoices at all, so there is
         * nothing it could have been allocated to).
         */
        if (Schema::hasTable('internal_settlements')) {
            $removed = DB::table('internal_settlements')->delete();
            if ($removed) {
                echo "  removed {$removed} pre-allocation internal settlement(s)\n";
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'internal_settlement_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($table.'_internal_settlement_id_index');
                $blueprint->dropColumn('internal_settlement_id');
            });
        }
    }
};
