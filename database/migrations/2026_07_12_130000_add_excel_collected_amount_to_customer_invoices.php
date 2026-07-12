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
        Schema::table('customer_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_invoices', 'excel_collected_amount')) {
                $table->decimal('excel_collected_amount', 14, 5)->nullable()->default(0)->after('odoo_collected_amount_in_main_currency');
            }
            if (! Schema::hasColumn('customer_invoices', 'excel_collected_amount_in_main_currency')) {
                $table->decimal('excel_collected_amount_in_main_currency', 14, 5)->nullable()->default(0)->after('excel_collected_amount');
            }
        });

        // Make the field available in the customer invoice template / export / import mapping.
        TablesField::firstOrCreate(
            ['model_name' => 'CustomerInvoice', 'field_name' => 'excel_collected_amount'],
            ['view_name' => 'Excel Collected Amount']
        );

        // Append the field to every company's selected CustomerInvoice fields so it shows in the template.
        CustomizedFieldsExportation::where('model_name', 'CustomerInvoice')->get()->each(function (CustomizedFieldsExportation $exportation) {
            $fields = $exportation->fields ?? [];
            if (! in_array('excel_collected_amount', $fields, true)) {
                $fields[] = 'excel_collected_amount';
                $exportation->fields = $fields;
                $exportation->save();
            }
        });

        $this->applyTriggers();
    }

    public function down(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('customer_invoices', 'excel_collected_amount_in_main_currency')) {
                $table->dropColumn('excel_collected_amount_in_main_currency');
            }
            if (Schema::hasColumn('customer_invoices', 'excel_collected_amount')) {
                $table->dropColumn('excel_collected_amount');
            }
        });

        TablesField::where('model_name', 'CustomerInvoice')->where('field_name', 'excel_collected_amount')->delete();

        CustomizedFieldsExportation::where('model_name', 'CustomerInvoice')->get()->each(function (CustomizedFieldsExportation $exportation) {
            $fields = $exportation->fields ?? [];
            if (in_array('excel_collected_amount', $fields, true)) {
                $exportation->fields = array_values(array_filter($fields, fn ($field) => $field !== 'excel_collected_amount'));
                $exportation->save();
            }
        });
    }

    private function applyTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS insert_net_invoice_amount_for_customers');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `insert_net_invoice_amount_for_customers` BEFORE INSERT
    ON `customer_invoices` FOR EACH ROW
    begin

    set new.withhold_amount_in_main_currency =new.withhold_amount * new.exchange_rate;
        set new.total_withhold_amount = new.total_withhold_amount + new.odoo_withhold_amount;
    set new.total_withhold_amount_in_main_currency = new.withhold_amount_in_main_currency + new.odoo_withhold_amount_in_main_currency;

        set @totalInvoiceAmount := ifnull(new.invoice_amount,0)  + ifnull(new.vat_amount,0) - ifnull(new.discount_amount,0) ;
    set new.net_invoice_amount =  @totalInvoiceAmount ;
    set new.invoice_amount_in_main_currency = new.invoice_amount * new.exchange_rate;
    set new.discount_amount_in_main_currency = new.discount_amount * new.exchange_rate;
    set new.collected_amount_in_main_currency = new.collected_amount * new.exchange_rate;

    set new.excel_collected_amount = ifnull(new.excel_collected_amount,0);
    set new.excel_collected_amount_in_main_currency = new.excel_collected_amount * new.exchange_rate;

      set new.total_collected_amount = new.collected_amount + new.odoo_collected_amount + new.excel_collected_amount;
     set new.total_collected_amount_in_main_currency = new.collected_amount_in_main_currency + new.odoo_collected_amount_in_main_currency + new.excel_collected_amount_in_main_currency;

    set new.total_deductions_in_main_currency = new.total_deductions * new.exchange_rate;
    set new.net_invoice_amount_in_main_currency = new.net_invoice_amount * new.exchange_rate;
    set new.vat_amount_in_main_currency = new.vat_amount * new.exchange_rate;

    set new.net_balance = @totalInvoiceAmount - ifnull(new.total_withhold_amount,0) - ifnull(new.total_collected_amount,0) - new.total_deductions;
    set new.net_balance_in_main_currency = new.net_balance * new.exchange_rate;
    IF(new.currency = 'EUR') then
        set new.currency = 'EURO';
    end if;

    IF (NEW.net_balance = 0 ) THEN
            SET  NEW.invoice_status = 'collected';
        ELSEIF(ifnull(NEW.total_collected_amount,0) + ifnull(NEW.total_withhold_amount,0) > 0 and DATE(NEW.invoice_due_date) < DATE(NOW() )) THEN
        SET  NEW.invoice_status = 'partially_collected_and_past_due';
    ELSEIF( DATE(NEW.invoice_due_date) > DATE(NOW() )) THEN
        SET  NEW.invoice_status = 'not_due_yet';
    ELSEIF( DATE(NEW.invoice_due_date) = DATE(NOW() )) THEN
        SET  NEW.invoice_status = 'due_to_day';
    ELSEIF(ifnull(NEW.total_collected_amount,0) + ifnull(NEW.total_withhold_amount,0) = 0 and DATE(NEW.invoice_due_date) < DATE(NOW() )) THEN
        SET  NEW.invoice_status = 'past_due';
        END IF;

        set new.invoice_month = LPAD(MONTH(new.invoice_date), 2, 0);
        set new.invoice_year = YEAR(new.invoice_date);

end
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS update_net_invoice_amount');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `update_net_invoice_amount` BEFORE
UPDATE
    ON `customer_invoices` FOR EACH ROW
    begin

    set new.withhold_amount_in_main_currency = new.withhold_amount * new.exchange_rate;
    set new.total_withhold_amount = new.withhold_amount + new.odoo_withhold_amount;
    set new.total_withhold_amount_in_main_currency = new.withhold_amount_in_main_currency + new.odoo_withhold_amount_in_main_currency;

    set @totalInvoiceAmount := ifnull(new.invoice_amount,0)  + ifnull(new.vat_amount,0) - ifnull(new.discount_amount,0) ;
    set @totalInvoiceAmountInMainCurrency := ifnull(new.invoice_amount_in_main_currency,0)  + ifnull(new.vat_amount_in_main_currency,0) - ifnull(new.discount_amount_in_main_currency,0) ;
    set new.net_invoice_amount =  @totalInvoiceAmount ;
    set new.net_invoice_amount_in_main_currency = (new.net_invoice_amount * new.exchange_rate);
    set new.invoice_amount_in_main_currency = (new.invoice_amount * new.exchange_rate);
    set new.invoice_amount_in_main_currency = new.invoice_amount * new.exchange_rate;
    set new.discount_amount_in_main_currency = new.discount_amount * new.exchange_rate;
    set new.collected_amount_in_main_currency = new.collected_amount * new.exchange_rate;

    set new.excel_collected_amount = ifnull(new.excel_collected_amount,0);
    set new.excel_collected_amount_in_main_currency = new.excel_collected_amount * new.exchange_rate;

     set new.total_collected_amount = new.collected_amount + new.odoo_collected_amount + new.excel_collected_amount;
     set new.total_collected_amount_in_main_currency = new.collected_amount_in_main_currency + new.odoo_collected_amount_in_main_currency + new.excel_collected_amount_in_main_currency;

    set new.total_deductions_in_main_currency = new.total_deductions * new.exchange_rate;
    set new.net_invoice_amount_in_main_currency = new.net_invoice_amount * new.exchange_rate;
    set new.vat_amount_in_main_currency = new.vat_amount * new.exchange_rate;

    set new.discount_amount_in_main_currency = new.discount_amount * new.exchange_rate;
    set new.net_balance = @totalInvoiceAmount - ifnull(new.total_withhold_amount,0) - ifnull(new.total_collected_amount,0) - new.total_deductions;
    set new.net_balance_in_main_currency = new.net_balance * new.exchange_rate;
    IF(new.currency = 'EUR') then
        set new.currency = 'EURO';
    end if;
     IF (new.net_balance = 0 ) THEN
        SET  new.invoice_status = 'collected';
     ELSEIF(ifnull(new.total_collected_amount,0) + ifnull(new.total_withhold_amount,0) > 0 and DATE(new.invoice_due_date) < DATE(NOW() )) THEN
     SET  new.invoice_status = 'partially_collected_and_past_due';
    ELSEIF( DATE(new.invoice_due_date) > DATE(NOW() )) THEN
     SET  new.invoice_status = 'not_due_yet';
    ELSEIF( DATE(new.invoice_due_date) = DATE(NOW() )) THEN
     SET  new.invoice_status = 'due_to_day';
     ELSEIF(ifnull(new.total_collected_amount,0) + ifnull(new.total_withhold_amount,0) = 0 and DATE(new.invoice_due_date) < DATE(NOW() )) THEN
     SET  new.invoice_status = 'past_due';
    END IF ;
    set new.invoice_month = LPAD(MONTH(new.invoice_date), 2, 0);
    set new.invoice_year = YEAR(new.invoice_date);

END
SQL);
    }
};
