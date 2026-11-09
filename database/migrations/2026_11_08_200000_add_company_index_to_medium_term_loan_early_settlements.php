<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every company-owned table is filtered by company_id on every screen; this was the only one of
 * them without an index on it (found by tests/Feature/Schema/DatabaseStructureTest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medium_term_loan_early_settlements', function (Blueprint $table) {
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('medium_term_loan_early_settlements', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });
    }
};
