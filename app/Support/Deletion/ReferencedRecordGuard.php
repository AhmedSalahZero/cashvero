<?php

namespace App\Support\Deletion;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ReferencedRecordGuard
 * ==================================================================
 * "You cannot delete this while something is still attached to it."
 *
 * The codebase already applies that rule per facility, by hand —
 * CleanOverdraft, FullySecuredOverdraft, LetterOfGuaranteeFacility and
 * friends each carry their own hasAnyTransactions(). The three master
 * records everything else hangs off — Partner, Contract,
 * FinancialInstitution (and its accounts) — had no such rule at all,
 * which is how a deleted record could take live transactions down with
 * it.
 *
 * Two things made that dangerous rather than merely untidy:
 *
 *  1. Several children are wired with ON DELETE CASCADE, so MySQL
 *     removes them itself. Eloquent never sees the delete, no model
 *     event fires, and the cleanup code that would have unwound the
 *     child's own statements never runs — leaving orphan rows that
 *     still show up in dashboards. (That is exactly how an LG issuance
 *     vanished while its 20,000 credit-lg-amount row stayed behind.)
 *  2. The rest carry no foreign key at all, so the child simply keeps
 *     pointing at an id that no longer exists.
 *
 * Rather than 60 hand-written checks, the dependents are declared once
 * below and counted generically. DeletionDependentsTest reads the real
 * schema back and fails if a referencing column exists that this list
 * does not know about — so a new table cannot quietly reopen the hole.
 *
 * @see \App\Models\Partner::hasAnyTransactions()
 * @see \App\Models\Contract::hasAnyTransactions()
 * @see \App\Models\FinancialInstitution::hasAnyTransactions()
 * @see \App\Models\FinancialInstitutionAccount::hasAnyTransactions()
 */
class ReferencedRecordGuard
{
    /**
     * How many blocking groups to name before summarising the rest.
     */
    private const LABELS_IN_MESSAGE = 3;

    /**
     * parent table => list of dependents that must block its deletion.
     *
     * Each dependent is [table, column, label] and may add:
     *   'through' => [table, column]  the dependent points at a bridge
     *                                 row, which points at the parent
     *   'movement' => true            count only rows where money
     *                                 actually moved, the same
     *                                 exclusion CleanOverdraft's own
     *                                 hasAnyTransactions() applies
     *
     * @return array<string, list<array{table:string,column:string,label:string,through?:array{0:string,1:string},movement?:bool}>>
     */
    private static function dependents(): array
    {
        return [
            'partners' => [
                ['table' => 'contracts', 'column' => 'partner_id', 'label' => 'Contracts'],
                ['table' => 'customer_invoices', 'column' => 'customer_id', 'label' => 'Customer Invoices'],
                ['table' => 'supplier_invoices', 'column' => 'supplier_id', 'label' => 'Supplier Invoices'],
                ['table' => 'money_received', 'column' => 'partner_id', 'label' => 'Money Received'],
                ['table' => 'money_payments', 'column' => 'partner_id', 'label' => 'Money Payments'],
                ['table' => 'settlements', 'column' => 'partner_id', 'label' => 'Settlements'],
                ['table' => 'payment_settlements', 'column' => 'partner_id', 'label' => 'Payment Settlements'],
                ['table' => 'settlement_allocations', 'column' => 'partner_id', 'label' => 'Settlement Allocations'],
                ['table' => 'down_payment_settlements', 'column' => 'customer_id', 'label' => 'Down Payment Settlements'],
                ['table' => 'down_payment_money_payment_settlements', 'column' => 'supplier_id', 'label' => 'Down Payment Settlements'],
                ['table' => 'po_allocations', 'column' => 'partner_id', 'label' => 'Purchase Order Allocations'],
                ['table' => 'employee_statements', 'column' => 'partner_id', 'label' => 'Employee Statements'],
                ['table' => 'other_partner_statements', 'column' => 'partner_id', 'label' => 'Other Partner Statements'],
                ['table' => 'shareholder_statements', 'column' => 'partner_id', 'label' => 'Shareholder Statements'],
                ['table' => 'subsidiary_company_statements', 'column' => 'partner_id', 'label' => 'Subsidiary Company Statements'],
                ['table' => 'tax_statements', 'column' => 'partner_id', 'label' => 'Tax Statements'],
                ['table' => 'other_dues', 'column' => 'partner_id', 'label' => 'Other Dues'],
                ['table' => 'letter_of_guarantee_issuances', 'column' => 'partner_id', 'label' => 'Letters of Guarantee'],
                ['table' => 'letter_of_credit_issuances', 'column' => 'partner_id', 'label' => 'Letters of Credit'],
                ['table' => 'factoring_transactions', 'column' => 'customer_id', 'label' => 'Factoring Transactions'],
                ['table' => 'lending_information_against_assignment_of_contracts', 'column' => 'customer_id', 'label' => 'Overdraft Lending Information'],
                // Shareholder accounts — see docs/shareholder-accounts.md
                ['table' => 'financial_institution_accounts', 'column' => 'shareholder_partner_id', 'label' => 'Bank Accounts'],
                ['table' => 'certificates_of_deposits', 'column' => 'shareholder_partner_id', 'label' => 'Certificates of Deposit'],
                ['table' => 'time_of_deposits', 'column' => 'shareholder_partner_id', 'label' => 'Time Deposits'],
                ['table' => 'medium_term_loans', 'column' => 'shareholder_partner_id', 'label' => 'Medium Term Loans'],
            ],

            'contracts' => [
                ['table' => 'letter_of_guarantee_issuances', 'column' => 'contract_id', 'label' => 'Letters of Guarantee'],
                ['table' => 'letter_of_credit_issuances', 'column' => 'contract_id', 'label' => 'Letters of Credit'],
                /**
                 * Its own purchase / sales orders do NOT block. They are
                 * sub-items of the contract form itself — there is no
                 * screen that deletes an order on its own — so they go
                 * with the contract, exactly like an empty bank account
                 * goes with its bank. What blocks is anything hanging
                 * off one of those orders, reached through the bridge
                 * below. (Blocking on the orders themselves would have
                 * made 94 of the 120 contracts on record permanently
                 * undeletable.)
                 */
                ['table' => 'down_payment_settlements', 'column' => 'sales_order_id', 'through' => ['sales_orders', 'contract_id'], 'label' => 'Down Payment Settlements'],
                ['table' => 'down_payment_money_payment_settlements', 'column' => 'purchase_order_id', 'through' => ['purchase_orders', 'contract_id'], 'label' => 'Down Payment Settlements'],
                ['table' => 'letter_of_guarantee_issuances', 'column' => 'purchase_order_id', 'through' => ['sales_orders', 'contract_id'], 'label' => 'Letters of Guarantee'],
                ['table' => 'letter_of_credit_issuances', 'column' => 'purchase_order_id', 'through' => ['sales_orders', 'contract_id'], 'label' => 'Letters of Credit'],
                ['table' => 'po_allocations', 'column' => 'purchase_order_id', 'through' => ['purchase_orders', 'contract_id'], 'label' => 'Purchase Order Allocations'],
                ['table' => 'money_received', 'column' => 'contract_id', 'label' => 'Money Received'],
                ['table' => 'money_payments', 'column' => 'contract_id', 'label' => 'Money Payments'],
                ['table' => 'cash_expense_contract', 'column' => 'contract_id', 'label' => 'Cash Expenses'],
                ['table' => 'down_payment_settlements', 'column' => 'contract_id', 'label' => 'Down Payment Settlements'],
                ['table' => 'down_payment_money_payment_settlements', 'column' => 'contract_id', 'label' => 'Down Payment Settlements'],
                ['table' => 'settlement_allocations', 'column' => 'contract_id', 'label' => 'Settlement Allocations'],
                ['table' => 'po_allocations', 'column' => 'contract_id', 'label' => 'Purchase Order Allocations'],
                ['table' => 'overdraft_against_assignment_of_contract_limits', 'column' => 'contract_id', 'label' => 'Overdraft Collateral'],
                ['table' => 'lending_information_against_assignment_of_contracts', 'column' => 'contract_id', 'label' => 'Overdraft Lending Information'],
            ],

            'financial_institutions' => [
                ['table' => 'letter_of_guarantee_issuances', 'column' => 'financial_institution_id', 'label' => 'Letters of Guarantee'],
                ['table' => 'letter_of_credit_issuances', 'column' => 'financial_institution_id', 'label' => 'Letters of Credit'],
                ['table' => 'letter_of_guarantee_facilities', 'column' => 'financial_institution_id', 'label' => 'Letter of Guarantee Facilities'],
                ['table' => 'letter_of_credit_facilities', 'column' => 'financial_institution_id', 'label' => 'Letter of Credit Facilities'],
                ['table' => 'letter_of_guarantee_statements', 'column' => 'financial_institution_id', 'label' => 'Letter of Guarantee Statements'],
                ['table' => 'letter_of_guarantee_cash_cover_statements', 'column' => 'financial_institution_id', 'label' => 'Letter of Guarantee Cash Cover'],
                ['table' => 'letter_of_credit_statements', 'column' => 'financial_institution_id', 'label' => 'Letter of Credit Statements'],
                ['table' => 'letter_of_credit_cash_cover_statements', 'column' => 'financial_institution_id', 'label' => 'Letter of Credit Cash Cover'],
                ['table' => 'clean_overdrafts', 'column' => 'financial_institution_id', 'label' => 'Clean Overdrafts'],
                ['table' => 'fully_secured_overdrafts', 'column' => 'financial_institution_id', 'label' => 'Fully Secured Overdrafts'],
                ['table' => 'overdraft_against_commercial_papers', 'column' => 'financial_institution_id', 'label' => 'Overdraft Against Commercial Paper'],
                ['table' => 'overdraft_against_assignment_of_contracts', 'column' => 'financial_institution_id', 'label' => 'Overdraft Against Assignment Of Contract'],
                ['table' => 'medium_term_loans', 'column' => 'financial_institution_id', 'label' => 'Medium Term Loans'],
                ['table' => 'certificates_of_deposits', 'column' => 'financial_institution_id', 'label' => 'Certificates of Deposit'],
                ['table' => 'time_of_deposits', 'column' => 'financial_institution_id', 'label' => 'Time Deposits'],
                ['table' => 'factoring_transactions', 'column' => 'financial_institution_id', 'label' => 'Factoring Transactions'],
                ['table' => 'interest_revenue_accounts', 'column' => 'financial_institution_id', 'label' => 'Interest Revenue Accounts'],
                ['table' => 'internal_money_transfers', 'column' => 'from_bank_id', 'label' => 'Internal Money Transfers'],
                ['table' => 'internal_money_transfers', 'column' => 'to_bank_id', 'label' => 'Internal Money Transfers'],
                ['table' => 'lc_settlement_internal_money_transfers', 'column' => 'from_bank_id', 'label' => 'Letter of Credit Settlement Transfers'],
                ['table' => 'buy_or_sell_currencies', 'column' => 'from_bank_id', 'label' => 'Buy / Sell Currency'],
                ['table' => 'buy_or_sell_currencies', 'column' => 'to_bank_id', 'label' => 'Buy / Sell Currency'],
                ['table' => 'cash_in_banks', 'column' => 'receiving_bank_id', 'label' => 'Cash In Bank'],
                ['table' => 'incoming_transfers', 'column' => 'receiving_bank_id', 'label' => 'Incoming Transfers'],
                ['table' => 'outgoing_transfers', 'column' => 'delivery_bank_id', 'label' => 'Outgoing Transfers'],
                ['table' => 'cheques', 'column' => 'drawl_bank_id', 'label' => 'Cheques'],
                ['table' => 'payable_cheques', 'column' => 'delivery_bank_id', 'label' => 'Payable Cheques'],
                /**
                 * Its accounts do NOT block on their own — destroy()
                 * deliberately deletes an empty bank's accounts with it.
                 * What blocks is money having moved on one of them.
                 */
                [
                    'table' => 'current_account_bank_statements',
                    'column' => 'financial_institution_account_id',
                    'through' => ['financial_institution_accounts', 'financial_institution_id'],
                    'movement' => true,
                    'label' => 'Bank Statement Transactions',
                ],
            ],

            'financial_institution_accounts' => [
                [
                    'table' => 'current_account_bank_statements',
                    'column' => 'financial_institution_account_id',
                    'movement' => true,
                    'label' => 'Bank Statement Transactions',
                ],
                ['table' => 'letter_of_guarantee_issuances', 'column' => 'cash_cover_deducted_from_account_id', 'label' => 'Letter of Guarantee Cash Cover'],
                ['table' => 'letter_of_guarantee_issuances', 'column' => 'lg_fees_and_commission_account_id', 'label' => 'Letter of Guarantee Fees & Commission'],
                ['table' => 'letter_of_credit_issuances', 'column' => 'cash_cover_deducted_from_account_id', 'label' => 'Letter of Credit Cash Cover'],
                ['table' => 'contract_loan_schedules', 'column' => 'financial_institution_account_id', 'label' => 'Contract Loan Schedules'],
                ['table' => 'loan_statements', 'column' => 'financial_institution_account_id', 'label' => 'Loan Statements'],
            ],
        ];
    }

    /**
     * @return list<array{table:string,column:string,label:string,through?:array{0:string,1:string},movement?:bool}>
     */
    public static function dependentsOf(string $parentTable): array
    {
        return self::dependents()[$parentTable] ?? [];
    }

    /** @return list<string> */
    public static function guardedTables(): array
    {
        return array_keys(self::dependents());
    }

    /**
     * What is still attached, as label => row count.
     *
     * Labels are merged, so "Internal Money Transfers" reached through
     * from_bank_id and to_bank_id reads as one number rather than two
     * near-identical lines.
     *
     * @return array<string, int>
     */
    public static function blockers(string $parentTable, int $parentId): array
    {
        $blockers = [];

        foreach (self::dependentsOf($parentTable) as $dependent) {
            $count = self::countFor($dependent, $parentId);

            if ($count > 0) {
                $label = $dependent['label'];
                $blockers[$label] = ($blockers[$label] ?? 0) + $count;
            }
        }

        arsort($blockers);

        return $blockers;
    }

    public static function blocks(string $parentTable, int $parentId): bool
    {
        foreach (self::dependentsOf($parentTable) as $dependent) {
            if (self::countFor($dependent, $parentId) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * The message to flash back, or null when the delete is safe.
     *
     * Naming what is attached is the point — it tells the user where to
     * go, instead of refusing and leaving them to guess.
     */
    public static function blockMessage(string $parentTable, int $parentId, ?string $name = null): ?string
    {
        $blockers = self::blockers($parentTable, $parentId);

        if ($blockers === []) {
            return null;
        }

        $named = array_slice($blockers, 0, self::LABELS_IN_MESSAGE, true);
        $remaining = count($blockers) - count($named);

        $details = [];
        foreach ($named as $label => $count) {
            $details[] = $count.' '.__($label);
        }

        if ($remaining > 0) {
            $details[] = __(':count more', ['count' => $remaining]);
        }

        return __('Cannot delete ":name" because it is still linked to :details. Please delete those first.', [
            'name' => $name ?: __('this item'),
            'details' => implode(', ', $details),
        ]);
    }

    /**
     * @param  array{table:string,column:string,label:string,through?:array{0:string,1:string},movement?:bool}  $dependent
     */
    private static function countFor(array $dependent, int $parentId): int
    {
        $table = $dependent['table'];
        $column = $dependent['column'];

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        $query = DB::table($table);

        if (isset($dependent['through'])) {
            [$bridgeTable, $bridgeColumn] = $dependent['through'];

            if (! Schema::hasTable($bridgeTable) || ! Schema::hasColumn($bridgeTable, $bridgeColumn)) {
                return 0;
            }

            $query->whereIn($table.'.'.$column, function ($sub) use ($bridgeTable, $bridgeColumn, $parentId) {
                $sub->select('id')->from($bridgeTable)->where($bridgeColumn, $parentId);
            });
        } else {
            $query->where($table.'.'.$column, $parentId);
        }

        /**
         * Same exclusion CleanOverdraft::hasAnyTransactions() makes: the
         * zero-amount rows the system writes for itself are not
         * transactions, and neither is an opening balance — a bank
         * account that only carries the balance it was created with has
         * had no money move on it.
         */
        if ($dependent['movement'] ?? false) {
            if (Schema::hasColumn($table, 'is_beginning_balance')) {
                $query->where($table.'.is_beginning_balance', 0);
            }
            $query->where(function ($q) use ($table) {
                $q->where($table.'.debit', '>', 0)->orWhere($table.'.credit', '>', 0);
            });
        }

        return $query->count();
    }
}
