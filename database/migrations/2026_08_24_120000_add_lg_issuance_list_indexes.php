<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the LG Issuance list screen.
 *
 * `letter_of_guarantee_issuances` had indexes on its foreign keys and
 * nothing else — in particular NOTHING on `company_id`. Every visit to
 * /letter-of-guarantee-issuance runs two full table scans (the
 * paginator's COUNT(*) and the page query itself) for the active tab,
 * and another two each time a tab is opened or a search is run:
 *
 *   WHERE company_id = ? AND lg_type = ? AND renewal_date BETWEEN ? AND ?   -- default list
 *   WHERE company_id = ? AND lg_type = ? AND transaction_name LIKE ?        -- search
 *   WHERE company_id = ? AND lg_type = ? AND lg_code LIKE ?                 -- search
 *   WHERE company_id = ? AND lg_type = ? AND partner_id IN (…)              -- search by customer
 *
 * Each index is ordered the way the query filters: the two equality
 * columns first, the ranged/scanned column last.
 *
 * The two LIKE searches are leading-wildcard ('%term%'), so the index
 * cannot seek on the name itself — but it still turns the scan into a
 * covering index scan over just this company's rows of just this type,
 * instead of a scan of every LG row of every company.
 *
 * @see \App\Http\Controllers\LetterOfGuaranteeIssuanceController::queryTab()
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_of_guarantee_issuances', function (Blueprint $table) {
            $table->index(['company_id', 'lg_type', 'renewal_date'], 'lg_issuances_company_type_renewal_index');
            $table->index(['company_id', 'lg_type', 'transaction_name'], 'lg_issuances_company_type_txn_name_index');
            $table->index(['company_id', 'lg_type', 'lg_code'], 'lg_issuances_company_type_lg_code_index');
            $table->index(['company_id', 'lg_type', 'partner_id'], 'lg_issuances_company_type_partner_index');
        });

        /**
         * The customer-name search resolves through `partners`, which
         * has no index at all beyond its primary key — so matching a
         * name scans every partner row of every company.
         */
        Schema::table('partners', function (Blueprint $table) {
            $table->index(['company_id', 'name'], 'partners_company_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('letter_of_guarantee_issuances', function (Blueprint $table) {
            $table->dropIndex('lg_issuances_company_type_renewal_index');
            $table->dropIndex('lg_issuances_company_type_txn_name_index');
            $table->dropIndex('lg_issuances_company_type_lg_code_index');
            $table->dropIndex('lg_issuances_company_type_partner_index');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropIndex('partners_company_name_index');
        });
    }
};
