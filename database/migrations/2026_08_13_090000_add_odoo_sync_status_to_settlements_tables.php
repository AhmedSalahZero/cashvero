<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the same failure-tracking columns every other Odoo-touching
 * table already has (synced_with_odoo, odoo_error_message) to
 * `settlements` and `payment_settlements`. Both tables already had
 * odoo_reference / odoo_reference_name for the SUCCESS case — this
 * migration only adds what was missing for the FAILURE case, so a
 * down payment settlement that fails against one specific invoice can
 * be shown on Balances/DownPaymentContracts.vue instead of only
 * flashing once and disappearing.
 *
 * Defaults match the pattern used elsewhere (e.g. cash_expenses):
 * synced_with_odoo defaults to 1/true, since a settlement that never
 * even attempts an Odoo sync (company has no Odoo credentials) should
 * not be treated as a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            if (! Schema::hasColumn('settlements', 'synced_with_odoo')) {
                $table->boolean('synced_with_odoo')->default(1)->after('odoo_reference_name');
            }
            if (! Schema::hasColumn('settlements', 'odoo_error_message')) {
                $table->text('odoo_error_message')->nullable()->after('synced_with_odoo');
            }
        });

        Schema::table('payment_settlements', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_settlements', 'synced_with_odoo')) {
                $table->boolean('synced_with_odoo')->default(1)->after('odoo_reference_name');
            }
            if (! Schema::hasColumn('payment_settlements', 'odoo_error_message')) {
                $table->text('odoo_error_message')->nullable()->after('synced_with_odoo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn(['synced_with_odoo', 'odoo_error_message']);
        });

        Schema::table('payment_settlements', function (Blueprint $table) {
            $table->dropColumn(['synced_with_odoo', 'odoo_error_message']);
        });
    }
};
