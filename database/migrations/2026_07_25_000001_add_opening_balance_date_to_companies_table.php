<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a company-wide "Opening Balance Date" — the single reference
 * date all opening-balance forms (Safe, Customers, Suppliers) now
 * read from, and the floor date used to validate every Financial
 * Institution account's balance_date (new or edited).
 *
 * Default '2025-01-01' is applied at the DB level so existing
 * companies (created before this field existed) are backfilled
 * automatically rather than ending up with a NULL required date —
 * confirmed with the project owner as the correct default for both
 * new companies and this backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->date('opening_balance_date')->default('2025-01-01')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('opening_balance_date');
        });
    }
};
