<?php

use App\Models\CustomizedFieldsExportation;
use App\Models\TablesField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_invoices', 'excel_paid_amount')) {
                $table->decimal('excel_paid_amount', 14, 5)->nullable()->default(0)->after('odoo_paid_amount_in_main_currency');
            }
            if (! Schema::hasColumn('supplier_invoices', 'excel_paid_amount_in_main_currency')) {
                $table->decimal('excel_paid_amount_in_main_currency', 14, 5)->nullable()->default(0)->after('excel_paid_amount');
            }
        });

        // Make the field available in the supplier invoice template / export / import mapping.
        TablesField::firstOrCreate(
            ['model_name' => 'SupplierInvoice', 'field_name' => 'excel_paid_amount'],
            ['view_name' => 'Excel Paid Amount']
        );

        // Append the field to every company's selected SupplierInvoice fields so it shows in the template.
        CustomizedFieldsExportation::where('model_name', 'SupplierInvoice')->get()->each(function (CustomizedFieldsExportation $exportation) {
            $fields = $exportation->fields ?? [];
            if (! in_array('excel_paid_amount', $fields, true)) {
                $fields[] = 'excel_paid_amount';
                $exportation->fields = $fields;
                $exportation->save();
            }
        });

        $this->applyTriggers();
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_invoices', 'excel_paid_amount_in_main_currency')) {
                $table->dropColumn('excel_paid_amount_in_main_currency');
            }
            if (Schema::hasColumn('supplier_invoices', 'excel_paid_amount')) {
                $table->dropColumn('excel_paid_amount');
            }
        });

        TablesField::where('model_name', 'SupplierInvoice')->where('field_name', 'excel_paid_amount')->delete();

        CustomizedFieldsExportation::where('model_name', 'SupplierInvoice')->get()->each(function (CustomizedFieldsExportation $exportation) {
            $fields = $exportation->fields ?? [];
            if (in_array('excel_paid_amount', $fields, true)) {
                $exportation->fields = array_values(array_filter($fields, fn ($field) => $field !== 'excel_paid_amount'));
                $exportation->save();
            }
        });
    }

    private function applyTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS insert_net_invoice_amount_for_suppliers');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `insert_net_invoice_amount_for_suppliers` BEFORE INSERT
    ON `supplier_invoices` FOR EACH ROW
    begin

    set new.withhold_amount_in_main_currency = (new.withhold_amount * new.exchange_rate);
    set new.total_withhold_amount = new.withhold_amount + new.odoo_withhold_amount;
    set new.total_withhold_amount_in_main_currency = new.withhold_amount_in_main_currency + new.odoo_withhold_amount_in_main_currency;

        set @totalInvoiceAmount := ifnull(new.invoice_amount,0)  + ifnull(new.vat_amount,0) - ifnull(new.discount_amount,0) ;
    set new.net_invoice_amount =  @totalInvoiceAmount  ;
    set new.invoice_amount_in_main_currency = (new.invoice_amount * new.exchange_rate);
    set new.paid_amount_in_main_currency = new.paid_amount * new.exchange_rate;

    set new.excel_paid_amount = ifnull(new.excel_paid_amount,0);
    set new.excel_paid_amount_in_main_currency = new.excel_paid_amount * new.exchange_rate;

    set new.total_paid_amount = new.paid_amount + new.odoo_paid_amount + new.excel_paid_amount;
     set new.total_paid_amount_in_main_currency = new.paid_amount_in_main_currency + new.odoo_paid_amount_in_main_currency + new.excel_paid_amount_in_main_currency;

    set new.discount_amount_in_main_currency = (new.discount_amount * new.exchange_rate);
    set new.net_balance = round(@totalInvoiceAmount - ifnull(new.total_withhold_amount,0) - ifnull(new.total_paid_amount,0) - new.total_deductions,2);
    set new.net_balance_in_main_currency = round(new.net_balance * new.exchange_rate,2) ;
    IF(new.currency = 'EUR') then
        set new.currency = 'EURO';
    end if;

    set new.net_invoice_amount_in_main_currency = (new.net_invoice_amount * new.exchange_rate);
    set new.total_deductions_in_main_currency = new.total_deductions * new.exchange_rate;
    set new.vat_amount_in_main_currency = (new.vat_amount * new.exchange_rate);

    IF (NEW.net_balance = 0 ) THEN
            SET  NEW.invoice_status = 'paid';
        ELSEIF(ifnull(NEW.total_paid_amount,0) + ifnull(NEW.total_withhold_amount,0) > 0 and DATE(NEW.invoice_due_date) < DATE(NOW() )) THEN
        SET  NEW.invoice_status = 'partially_paid_and_past_due';
    ELSEIF( DATE(NEW.invoice_due_date) > DATE(NOW() )) THEN
        SET  NEW.invoice_status = 'not_due_yet';
    ELSEIF( DATE(NEW.invoice_due_date) = DATE(NOW() )) THEN
        SET  NEW.invoice_status = 'due_to_day';
    ELSEIF(ifnull(NEW.total_paid_amount,0) + ifnull(NEW.total_withhold_amount,0) = 0 and DATE(NEW.invoice_due_date) < DATE(NOW() )) THEN
        SET  NEW.invoice_status = 'past_due';
        END IF;

        set new.invoice_month = LPAD(MONTH(new.invoice_date), 2, 0);
        set new.invoice_year = YEAR(new.invoice_date);

end
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS update_net_invoice_amount_for_suppliers');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `update_net_invoice_amount_for_suppliers` BEFORE
UPDATE
    ON `supplier_invoices` FOR EACH ROW
    begin

    set new.withhold_amount_in_main_currency = (new.withhold_amount * new.exchange_rate);
    set new.total_withhold_amount = new.withhold_amount + new.odoo_withhold_amount;
    set new.total_withhold_amount_in_main_currency = new.withhold_amount_in_main_currency + new.odoo_withhold_amount_in_main_currency;

    set @totalInvoiceAmount := ifnull(new.invoice_amount,0)  + ifnull(new.vat_amount,0) - ifnull(new.discount_amount,0) ;
    set @totalInvoiceAmountInMainCurrency := ifnull(new.invoice_amount_in_main_currency,0)  + ifnull(new.vat_amount_in_main_currency,0) - ifnull(new.discount_amount_in_main_currency,0) ;
    set new.net_invoice_amount = ( @totalInvoiceAmount );
    set new.net_invoice_amount_in_main_currency = (new.net_invoice_amount * new.exchange_rate);
    set new.invoice_amount_in_main_currency = (new.invoice_amount * new.exchange_rate);
    set new.paid_amount_in_main_currency = new.paid_amount * new.exchange_rate;

    set new.excel_paid_amount = ifnull(new.excel_paid_amount,0);
    set new.excel_paid_amount_in_main_currency = new.excel_paid_amount * new.exchange_rate;

     set new.total_paid_amount = new.paid_amount + new.odoo_paid_amount + new.excel_paid_amount;
     set new.total_paid_amount_in_main_currency = new.paid_amount_in_main_currency + new.odoo_paid_amount_in_main_currency + new.excel_paid_amount_in_main_currency;

    set new.total_deductions_in_main_currency = new.total_deductions * new.exchange_rate;
    set new.discount_amount_in_main_currency = (new.discount_amount * new.exchange_rate);
    set new.vat_amount_in_main_currency = (new.vat_amount * new.exchange_rate);

    set new.net_balance = round(@totalInvoiceAmount - ifnull(new.total_withhold_amount,0) - ifnull(new.total_paid_amount,2) - new.total_deductions,2);
    set new.net_balance_in_main_currency = round(new.net_balance * new.exchange_rate,2);
    IF(new.currency = 'EUR') then
        set new.currency = 'EURO';
    end if;

     IF (new.net_balance = 0 ) THEN
        SET  new.invoice_status = 'paid';
     ELSEIF(ifnull(new.total_paid_amount,0) + ifnull(new.total_withhold_amount,0) > 0 and DATE(new.invoice_due_date) < DATE(NOW() )) THEN
     SET  new.invoice_status = 'partially_paid_and_past_due';
    ELSEIF( DATE(new.invoice_due_date) > DATE(NOW() )) THEN
     SET  new.invoice_status = 'not_due_yet';
    ELSEIF( DATE(new.invoice_due_date) = DATE(NOW() )) THEN
     SET  new.invoice_status = 'due_to_day';
     ELSEIF(ifnull(new.total_paid_amount,0) + ifnull(new.total_withhold_amount,0) = 0 and DATE(new.invoice_due_date) < DATE(NOW() )) THEN
     SET  new.invoice_status = 'past_due';
    END IF ;
    set new.invoice_month = LPAD(MONTH(new.invoice_date), 2, 0);
    set new.invoice_year = YEAR(new.invoice_date);

END
SQL);
    }
};
