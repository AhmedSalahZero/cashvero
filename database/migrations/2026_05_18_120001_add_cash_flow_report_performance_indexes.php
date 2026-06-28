<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->indexColumns('money_received', ['company_id', 'type', 'receiving_date'], 'mr_co_type_receiving_date_idx');
        $this->indexColumns('money_received', ['company_id', 'partner_id'], 'mr_co_partner_idx');
        $this->indexColumns('money_received', ['company_id', 'contract_id'], 'mr_co_contract_idx');

        $this->indexColumns('money_payments', ['company_id', 'type', 'delivery_date'], 'mp_co_type_delivery_idx');
        $this->indexColumns('money_payments', ['company_id', 'partner_id'], 'mp_co_partner_idx');

        $this->indexColumns('cheques', ['money_received_id', 'status'], 'cheques_mr_status_idx');
        $this->indexColumns('cheques', ['status', 'expected_collection_date'], 'cheques_status_expected_coll_idx');
        $this->indexColumns('cheques', ['status', 'actual_collection_date'], 'cheques_status_actual_coll_idx');

        $this->indexColumns('payable_cheques', ['money_payment_id', 'status'], 'payable_chq_mp_status_idx');
        $this->indexColumns('payable_cheques', ['status', 'actual_payment_date'], 'payable_chq_status_actual_pay_idx');
        $this->indexColumns('payable_cheques', ['status', 'due_date'], 'payable_chq_status_due_idx');

        $this->indexColumns('settlements', ['money_received_id', 'invoice_id'], 'settlements_mr_invoice_idx');

        $this->indexColumns('settlement_allocations', ['contract_id', 'money_payment_id'], 'set_alloc_contract_mp_idx');
        $this->indexColumns('settlement_allocations', ['money_payment_id'], 'set_alloc_mp_idx');

        $this->indexColumns('customer_invoices', ['company_id', 'contract_code'], 'ci_co_contract_code_idx');
        $this->indexColumns('customer_invoices', ['contract_code', 'invoice_due_date'], 'ci_contract_due_idx');

        $this->indexColumns('supplier_invoices', ['company_id', 'contract_code'], 'si_co_contract_code_idx');
        $this->indexColumns('supplier_invoices', ['contract_code'], 'si_contract_code_idx');

        $this->indexColumns('cash_expenses', ['company_id', 'type', 'payment_date'], 'ce_co_type_payment_idx');

        $this->indexColumns('cash_expense_contract', ['contract_id', 'cash_expense_id'], 'cec_contract_expense_idx');

        $this->indexColumns('po_allocations', ['contract_id'], 'po_alloc_contract_idx');

        $this->indexColumns('foreign_exchange_rates', ['company_id', 'from_currency', 'to_currency', 'date'], 'fx_co_currencies_date_idx');

        $this->indexColumns(
            'weekly_cashflow_custom_due_invoices',
            ['company_id', 'cashflow_report_id', 'is_contract', 'invoice_type'],
            'wccfi_co_report_contract_type_idx',
        );

        $this->indexColumns('weekly_cashflow_custom_past_due_schedules', ['company_id', 'is_contract'], 'wccpds_co_contract_idx');

        $this->indexColumns('contracts', ['company_id', 'status'], 'contracts_co_status_idx');
    }

    public function down(): void
    {
        $this->dropIndex('money_received', 'mr_co_type_receiving_date_idx');
        $this->dropIndex('money_received', 'mr_co_partner_idx');
        $this->dropIndex('money_received', 'mr_co_contract_idx');

        $this->dropIndex('money_payments', 'mp_co_type_delivery_idx');
        $this->dropIndex('money_payments', 'mp_co_partner_idx');

        $this->dropIndex('cheques', 'cheques_mr_status_idx');
        $this->dropIndex('cheques', 'cheques_status_expected_coll_idx');
        $this->dropIndex('cheques', 'cheques_status_actual_coll_idx');

        $this->dropIndex('payable_cheques', 'payable_chq_mp_status_idx');
        $this->dropIndex('payable_chq_status_actual_pay_idx');
        $this->dropIndex('payable_cheques', 'payable_chq_status_due_idx');

        $this->dropIndex('settlements', 'settlements_mr_invoice_idx');

        $this->dropIndex('settlement_allocations', 'set_alloc_contract_mp_idx');
        $this->dropIndex('settlement_allocations', 'set_alloc_mp_idx');

        $this->dropIndex('customer_invoices', 'ci_co_contract_code_idx');
        $this->dropIndex('customer_invoices', 'ci_contract_due_idx');

        $this->dropIndex('supplier_invoices', 'si_co_contract_code_idx');
        $this->dropIndex('supplier_invoices', 'si_contract_code_idx');

        $this->dropIndex('cash_expenses', 'ce_co_type_payment_idx');

        $this->dropIndex('cash_expense_contract', 'cec_contract_expense_idx');

        $this->dropIndex('po_allocations', 'po_alloc_contract_idx');

        $this->dropIndex('foreign_exchange_rates', 'fx_co_currencies_date_idx');

        $this->dropIndex('weekly_cashflow_custom_due_invoices', 'wccfi_co_report_contract_type_idx');

        $this->dropIndex('weekly_cashflow_custom_past_due_schedules', 'wccpds_co_contract_idx');

        $this->dropIndex('contracts', 'contracts_co_status_idx');
    }

    /**
     * @param  list<string>  $columns
     */
    private function indexColumns(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->index($columns, $indexName);
            });
        } catch (\Throwable) {
            // Skip if index already exists from a previous partial migrate run.
        }
    }

    private function dropIndex(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        } catch (\Throwable) {
            // Index may not exist if a prior partial migration run failed mid-way.
        }
    }
};
