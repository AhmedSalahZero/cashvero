<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Column order on the Customer/Supplier Invoice tables (and the
 * "Select Template Fields" checkbox screen) was never an explicit,
 * intentional setting — ExportTable::columnsFiltration() reads
 * 'tables_fields' with no ORDER BY at all, so display order has
 * always just been whatever order the rows happen to sit in by
 * primary key (id).
 *
 * That's why 'contract_code' and 'contract_name' show up at the very
 * end of the Supplier Invoice table: they were registered later than
 * every other SupplierInvoice field (contract_code via a later data
 * fix, contract_name via 2026_08_09_090000_update_invoice_export_fields_labels),
 * so they picked up much higher ids than fields like supplier_name,
 * invoice_date, etc., which were all seeded together back in 2022.
 *
 * This migration:
 *  1. Adds a real, explicit 'sort_order' column so column order is no
 *     longer an accident of insertion order.
 *  2. Backfills sort_order = id for every existing row, which
 *     reproduces today's on-screen order exactly for every model —
 *     nothing else moves.
 *  3. Re-numbers just 'contract_name' and 'contract_code' on
 *     SupplierInvoice so they sit immediately after 'supplier_name'
 *     (per request: Supplier Name → Contract Name → Contract Code →
 *     everything else, unchanged).
 *
 * ExportTable::columnsFiltration() is updated separately (same
 * commit) to actually ORDER BY sort_order (falling back to id for
 * any future row that doesn't get one set), so this data change takes
 * effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tables_fields', 'sort_order')) {
            Schema::table('tables_fields', function (Blueprint $table) {
                $table->integer('sort_order')->nullable()->after('is_sales_trend');
            });
        }

        // 1. Preserve current order everywhere: sort_order = id.
        DB::statement('UPDATE tables_fields SET sort_order = id WHERE sort_order IS NULL');

        // 2. Move Contract Name / Contract Code to sit right after
        // Supplier Name for SupplierInvoice specifically. Using
        // fractional-looking spacing (supplier_name's order + 1, +2)
        // so nothing else needs renumbering.
        $supplierNameOrder = DB::table('tables_fields')
            ->where('model_name', 'SupplierInvoice')
            ->where('field_name', 'supplier_name')
            ->value('sort_order');

        if ($supplierNameOrder !== null) {
            DB::table('tables_fields')
                ->where('model_name', 'SupplierInvoice')
                ->where('field_name', 'contract_name')
                ->update(['sort_order' => $supplierNameOrder + 1, 'updated_at' => now()]);

            DB::table('tables_fields')
                ->where('model_name', 'SupplierInvoice')
                ->where('field_name', 'contract_code')
                ->update(['sort_order' => $supplierNameOrder + 2, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Restore contract_name/contract_code's sort_order back to
        // their own id (their original effective position), rather
        // than dropping the column, since other rows now rely on
        // sort_order too.
        DB::statement("
            UPDATE tables_fields
            SET sort_order = id
            WHERE model_name = 'SupplierInvoice' AND field_name IN ('contract_name', 'contract_code')
        ");
    }
};
