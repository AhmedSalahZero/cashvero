<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('factoring_transactions', 'uncollected_invoice_charges')) {
            Schema::table('factoring_transactions', function (Blueprint $table) {
                $table->decimal('uncollected_invoice_charges', 20, 2)->default(0)->after('rejection_account_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('factoring_transactions', 'uncollected_invoice_charges')) {
            Schema::table('factoring_transactions', function (Blueprint $table) {
                $table->dropColumn('uncollected_invoice_charges');
            });
        }
    }
};
