<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Three changes to the 'tables_fields' config table that drives the
 * "Export Fields" / upload-template screen for Customer & Supplier
 * Invoice:
 *
 *  1. Adds a missing 'Contract Name' checkbox for SupplierInvoice.
 *     The 'contract_name' column already exists and is populated on
 *     supplier_invoices — it was just never registered here, so it
 *     never appeared as a selectable field. CustomerInvoice already
 *     has this row; SupplierInvoice did not.
 *
 *  2. Renames the SupplierInvoice 'excel_paid_amount' field's label
 *     from "Excel Paid Amount" to "Previous Payments".
 *
 *  3. Renames the CustomerInvoice 'excel_collected_amount' field's
 *     label from "Excel Collected Amount" to "Previous Collection".
 *
 * Only the human-readable label (view_name) changes for #2 and #3 —
 * the underlying field_name ('excel_paid_amount' / 'excel_collected_amount')
 * is untouched, so nothing about how uploaded values get saved
 * changes. Old templates/files using the previous header text still
 * import correctly: SupplierInvoice::getImportHeaderAliases() and
 * CustomerInvoice::getImportHeaderAliases() register the old label as
 * a still-accepted alternate header name.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the missing Contract Name field for SupplierInvoice,
        // only if it isn't already there for any given company_id row.
        $alreadyExists = DB::table('tables_fields')
            ->where('model_name', 'SupplierInvoice')
            ->where('field_name', 'contract_name')
            ->exists();

        if (! $alreadyExists) {
            DB::table('tables_fields')->insert([
                'model_name' => 'SupplierInvoice',
                'field_name' => 'contract_name',
                'view_name' => 'Contract Name',
                'is_sales_trend' => false,
                'company_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Rename SupplierInvoice's excel_paid_amount label.
        DB::table('tables_fields')
            ->where('model_name', 'SupplierInvoice')
            ->where('field_name', 'excel_paid_amount')
            ->update(['view_name' => 'Previous Payments', 'updated_at' => now()]);

        // 3. Rename CustomerInvoice's excel_collected_amount label.
        DB::table('tables_fields')
            ->where('model_name', 'CustomerInvoice')
            ->where('field_name', 'excel_collected_amount')
            ->update(['view_name' => 'Previous Collection', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('tables_fields')
            ->where('model_name', 'SupplierInvoice')
            ->where('field_name', 'contract_name')
            ->delete();

        DB::table('tables_fields')
            ->where('model_name', 'SupplierInvoice')
            ->where('field_name', 'excel_paid_amount')
            ->update(['view_name' => 'Excel Paid Amount', 'updated_at' => now()]);

        DB::table('tables_fields')
            ->where('model_name', 'CustomerInvoice')
            ->where('field_name', 'excel_collected_amount')
            ->update(['view_name' => 'Excel Collected Amount', 'updated_at' => now()]);
    }
};
