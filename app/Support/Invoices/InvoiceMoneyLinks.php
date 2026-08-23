<?php

namespace App\Support\Invoices;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which uploaded invoices already have money recorded against them.
 *
 * An invoice with a collection, a payment, an allocation or a deduction
 * behind it is not a spreadsheet row any more — deleting it would leave
 * the settlement pointing at nothing and the partner's balance wrong.
 * Bulk delete therefore removes what it can and reports what it could
 * not, instead of refusing everything or quietly taking the lot.
 *
 * ⚠️ The child tables share the column name `invoice_id` between
 * customer and supplier invoices with no discriminator on it, so the
 * SIDE is decided by the table, not by a flag:
 *
 *   settlements            → money_received_id      → customer
 *   payment_settlements    → money_payment_id …     → supplier
 *   settlement_allocations → money_payment_id       → supplier
 *
 * Reading `settlements.invoice_id` for a supplier invoice would match
 * an unrelated customer invoice that happens to share the id, so each
 * model only ever looks at its own tables. invoice_deductions and
 * weekly_cashflow_custom_due_invoices DO carry `invoice_type`, and are
 * filtered on it.
 */
class InvoiceMoneyLinks
{
    /**
     * model => list of [table, column, label, invoice_type|null]
     */
    private const LINKS = [
        'CustomerInvoice' => [
            ['settlements', 'invoice_id', 'Collections', null],
            ['factoring_transactions', 'customer_invoice_id', 'Factoring Transactions', null],
            ['invoice_deductions', 'invoice_id', 'Deductions', 'CustomerInvoice'],
        ],
        'SupplierInvoice' => [
            ['payment_settlements', 'invoice_id', 'Payments', null],
            ['settlement_allocations', 'invoice_id', 'Payment Allocations', null],
            ['letter_of_credit_issuances', 'supplier_invoice_id', 'Letters of Credit', null],
            ['invoice_deductions', 'invoice_id', 'Deductions', 'SupplierInvoice'],
        ],
    ];

    public static function isGuarded(string $model): bool
    {
        return isset(self::LINKS[$model]);
    }

    /**
     * The ids, among $invoiceIds, that already carry money.
     *
     * Chunked because a bulk delete can hand over thousands of ids and
     * MySQL will not take them all in one IN () list.
     *
     * @param  list<int>  $invoiceIds
     * @return list<int>
     */
    public static function idsWithMoney(string $model, array $invoiceIds): array
    {
        if ($invoiceIds === [] || ! self::isGuarded($model)) {
            return [];
        }

        $blocked = [];

        foreach (self::LINKS[$model] as [$table, $column, $label, $invoiceType]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach (array_chunk($invoiceIds, 1000) as $chunk) {
                $query = DB::table($table)->whereIn($column, $chunk);

                if ($invoiceType !== null && Schema::hasColumn($table, 'invoice_type')) {
                    $query->where('invoice_type', $invoiceType);
                }

                foreach ($query->pluck($column) as $id) {
                    $blocked[(int) $id] = true;
                }
            }
        }

        return array_map('intval', array_keys($blocked));
    }

    /**
     * What is holding each blocked invoice, as label => count, for the
     * message shown to the user.
     *
     * @param  list<int>  $invoiceIds
     * @return array<string, int>
     */
    public static function reasons(string $model, array $invoiceIds): array
    {
        if ($invoiceIds === [] || ! self::isGuarded($model)) {
            return [];
        }

        $reasons = [];

        foreach (self::LINKS[$model] as [$table, $column, $label, $invoiceType]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $count = 0;

            foreach (array_chunk($invoiceIds, 1000) as $chunk) {
                $query = DB::table($table)->whereIn($column, $chunk);

                if ($invoiceType !== null && Schema::hasColumn($table, 'invoice_type')) {
                    $query->where('invoice_type', $invoiceType);
                }

                $count += $query->distinct()->count($column);
            }

            if ($count > 0) {
                $reasons[$label] = ($reasons[$label] ?? 0) + $count;
            }
        }

        arsort($reasons);

        return $reasons;
    }
}
