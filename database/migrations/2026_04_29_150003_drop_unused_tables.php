<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int,string>
     */
    private array $tables = [
        'allocation_settings',
        'business_sectors',
        'categories',
        'cities',
        'collection_settings',
        'countries',
        'direct_manpower_expense_quick_pricing_calculator',
        'direct_manpower_expenses',
        'existing_products_allocation_base',
        'expense_analysis',
        'expenses',
        'export_analysis',
        'financial_statement_able_item_main_item',
        'financial_statement_able_items',
        'financial_statement_able_main_item_calculations',
        'financial_statement_able_main_item_sub_items',
        'financial_statement_ables',
        'financial_statements',
        'freelancer_expense_quick_pricing_calculator',
        'freelancer_expenses',
        'general_expense_quick_pricing_calculator',
        'general_expenses',
        'inventory_statement_tests',
        'inventory_statements',
        'languages',
        'letter_of_credit_opening_balances',
        'letter_of_guarantee_opening_balances',
        'lg_opening_balances',
        'modified_seasonality',
        'modified_targe',
        'monthly_customer_invoices',
        'new_products_allocation_base',
        'other_direct_operation_expense_quick_pricing',
        'other_direct_operation_expenses',
        'other_variable_manpower_expenses',
        'others',
        'positions',
        'pricing_expenses',
        'pricing_plans',
        'products',
        'products_seasonalities',
        'profitabilities',
        'quantity_allocation_settings',
        'quantity_categories',
        'quantity_collection_settings',
        'quantity_existing_products_allocation_base',
        'quantity_modified_seasonality',
        'quantity_modified_targe',
        'quantity_new_products_allocation_base',
        'quantity_products',
        'quantity_products_seasonalities',
        'quantity_sales_forecast',
        'quantity_second_allocation_settings',
        'quantity_second_existing_products_allocation_base',
        'quantity_second_new_products_allocation_base',
        'quick_pricing_calculators',
        'quotation_pricing_calculators',
        'receivables_payments',
        'revenue_business_lines',
        'sales_and_marketing_expenses',
        'sales_and_marketing_quick_pricing_calculator',
        'sales_forecast',
        'second_allocation_settings',
        'second_existing_products_allocation_base',
        'second_new_products_allocation_base',
        'service_categories',
        'service_items',
        'service_natures',
        'serviceables',
        'sharing_links',
        'states',
		'labeling_items',
        'test_db_name',
    ];

    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            foreach ($this->tables as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    public function down(): void
    {
        // Irreversible: dropped tables cannot be restored without their original schemas.
    }
};
