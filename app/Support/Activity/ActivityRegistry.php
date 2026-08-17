<?php

namespace App\Support\Activity;

use App\Models;

/**
 * ActivityRegistry
 * ==================================================================
 * Declares WHICH models keep a per-record history, and how their
 * fields and values read to a person.
 *
 * The observer produces raw diffs (`type: cheque → cheque-under-collection`).
 * That is unreadable, and it is this file that turns it into
 * "moved the cheque from Cheques In Safe to Cheques Under Collection".
 *
 * ── Adding a model ────────────────────────────────────────────────
 * Add one entry to MODELS. Field labels fall back to a shared default
 * set, so most entries are two lines.
 *
 * ── What is deliberately NOT audited ──────────────────────────────
 * Derived tables — *Statement, *Limit, *BankStatement, *Withdrawal —
 * are excluded. They are recalculated wholesale on nearly every
 * transaction, so auditing them would bury the real events under
 * machine noise while adding nothing a person did.
 *
 * They are also partly written by DB triggers (see app/Triggers), which
 * bypass Eloquent entirely — an observer would never see those writes,
 * so the log would be noisy AND incomplete. Statement history belongs
 * to the transaction that caused it, which IS audited.
 */
class ActivityRegistry
{
    /**
     * Never recorded as a change, on any model: framework bookkeeping,
     * secrets, and derived columns that move on their own.
     */
    public const GLOBAL_IGNORED_FIELDS = [
        'created_at', 'updated_at', 'deleted_at',
        'password', 'remember_token', 'api_token',
        'created_by', 'updated_by',
        'odoo_error', 'odoo_reference', 'odoo_id', 'odoo_payment_id',
        'is_processing', 'job_id', 'cache_key',
    ];

    /**
     * Field-name → label, shared by every model. Per-model `fields`
     * entries override these.
     */
    public const DEFAULT_FIELD_LABELS = [
        'name' => 'Name',
        'type' => 'Type',
        'status' => 'Status',
        'date' => 'Date',
        'amount' => 'Amount',
        'currency' => 'Currency',
        'notes' => 'Notes',
        'note' => 'Note',
        'user_comment' => 'Comment',
        'description' => 'Description',
        'company_id' => 'Company',
        'partner_id' => 'Partner',
        'contract_id' => 'Contract',
        'branch_id' => 'Safe Account',
        'bank_id' => 'Bank',
        'financial_institution_id' => 'Financial Institution',
        'financial_institution_account_id' => 'Bank Account',
        'due_date' => 'Due Date',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'payment_date' => 'Payment Date',
        'receiving_date' => 'Receiving Date',
        'delivery_date' => 'Delivery Date',
        'cheque_number' => 'Cheque Number',
        'account_number' => 'Account Number',
        'received_amount' => 'Received Amount',
        'payment_amount' => 'Payment Amount',
        'is_reviewed' => 'Reviewed',
        'is_active' => 'Active',
        'email' => 'Email',
        'limit' => 'Limit',
        'interest_rate' => 'Interest Rate',
        'code' => 'Code',
    ];

    /**
     * Value maps for slug-style columns, so a diff reads as words.
     */
    private const MONEY_RECEIVED_TYPES = [
        'cash-in-safe' => 'Cash In Safe',
        'cash-in-bank' => 'Cash In Bank',
        'incoming-transfer' => 'Incoming Transfer',
        'cheque' => 'Cheques In Safe',
        'cheque-under-collection' => 'Cheque Under Collection',
        'cheque-rejected' => 'Rejected Cheque',
        'cheque-collected' => 'Collected Cheque',
        'cheque-collection-fees' => 'Cheque Collection Fees',
        'contracts-with-down-payments' => 'Contract With Down Payment',
        'unapplied-amounts' => 'Unapplied Amount',
        'down-payment' => 'Down Payment',
        'invoice-settlement-with-down-payment' => 'Invoice Settlement With Down Payment',
        'settlement-of-opening-balance' => 'Settlement Of Opening Balance',
    ];

    private const CONTRACT_STATUSES = [
        'running' => 'Running',
        'running_and_against' => 'Running And Against',
        'finished' => 'Finished',
    ];

    /**
     * @var array<class-string, array{label:string, module:string, title?:string, fields?:array, ignore?:string[]}>
     *
     * `module`  → the permission module gating who may read this history.
     * `title`   → model method returning a human name for the record.
     * `fields`  → field label overrides and value maps.
     */
    private const MODELS = [

        /* ───────────────── Cash transactions ─────────────────────── */
        Models\MoneyReceived::class => [
            'label' => 'Money Received',
            'module' => 'money_received',
            'title' => 'getName',
            'fields' => [
                'type' => ['label' => 'Status', 'values' => self::MONEY_RECEIVED_TYPES],
                'received_amount' => ['label' => 'Received Amount'],
                'receiving_currency' => ['label' => 'Receiving Currency'],
            ],
        ],
        Models\MoneyPayment::class => [
            'label' => 'Money Payment',
            'module' => 'money_payment',
            'fields' => [
                'type' => ['label' => 'Status'],
                'payment_currency' => ['label' => 'Payment Currency'],
            ],
        ],
        Models\CashExpense::class => [
            'label' => 'Cash Expense',
            'module' => 'cash_expense',
            'fields' => ['type' => ['label' => 'Status']],
        ],
        Models\InternalMoneyTransfer::class => [
            'label' => 'Internal Money Transfer',
            'module' => 'internal_money_transfer',
        ],
        Models\LcSettlementInternalMoneyTransfer::class => [
            'label' => 'LC Settlement Transfer',
            'module' => 'lc_settlement_transfer',
        ],
        Models\BuyOrSellCurrency::class => [
            'label' => 'Buy / Sell Currency',
            'module' => 'buy_or_sell_currency',
        ],
        Models\ForeignExchangeRate::class => [
            'label' => 'Foreign Exchange Rate',
            'module' => 'foreign_exchange_rate',
        ],
        Models\Cheque::class => [
            'label' => 'Cheque',
            'module' => 'money_received',
            'fields' => ['type' => ['label' => 'Status', 'values' => self::MONEY_RECEIVED_TYPES]],
        ],
        Models\PayableCheque::class => [
            'label' => 'Payable Cheque',
            'module' => 'money_payment',
        ],
        Models\OpeningBalance::class => [
            'label' => 'Opening Balance',
            'module' => 'opening_balance',
        ],
        Models\CustomerOpeningBalance::class => [
            'label' => 'Customer Opening Balance',
            'module' => 'customer_opening_balance',
        ],
        Models\SupplierOpeningBalance::class => [
            'label' => 'Supplier Opening Balance',
            'module' => 'supplier_opening_balance',
        ],

        /* ───────────────────── Factoring ─────────────────────────── */
        Models\FactoringTransaction::class => [
            'label' => 'Factoring Transaction',
            'module' => 'factoring_with_recourse',
        ],
        Models\FactoringContract::class => [
            'label' => 'Factoring Contract',
            'module' => 'factoring_contract',
        ],
        Models\FactoringCompany::class => [
            'label' => 'Factoring Company',
            'module' => 'factoring_company',
        ],

        /* ───────────────────── Contracts ─────────────────────────── */
        Models\Contract::class => [
            'label' => 'Contract',
            'module' => 'customer_contract',
            'title' => 'getName',
            'fields' => ['status' => ['label' => 'Status', 'values' => self::CONTRACT_STATUSES]],
        ],
        Models\SalesOrder::class => ['label' => 'Sales Order', 'module' => 'customer_contract'],
        Models\PurchaseOrder::class => ['label' => 'Purchase Order', 'module' => 'supplier_contract'],
        Models\PoAllocation::class => ['label' => 'PO Allocation', 'module' => 'supplier_contract'],
        Models\ContractLoanSchedule::class => ['label' => 'Contract Loan Schedule', 'module' => 'contract_loan_schedule'],
        Models\ContractLoanScheduleSettlement::class => ['label' => 'Contract Loan Settlement', 'module' => 'contract_loan_schedule'],
        Models\DueDateHistory::class => ['label' => 'Adjusted Due Date', 'module' => 'adjusted_due_date'],

        /* ──────────── Financial institutions & facilities ────────── */
        Models\FinancialInstitution::class => [
            'label' => 'Financial Institution',
            'module' => 'financial_institution',
        ],
        Models\FinancialInstitutionAccount::class => [
            'label' => 'Bank Account',
            'module' => 'bank_account',
            'fields' => ['is_active' => ['label' => 'Active', 'values' => [1 => 'Unlocked', 0 => 'Locked']]],
        ],
        Models\LeasingCompany::class => ['label' => 'Leasing Company', 'module' => 'leasing_company'],
        Models\LeasingContract::class => ['label' => 'Leasing Contract', 'module' => 'leasing_contract'],

        /* ────────────────────── Deposits ─────────────────────────── */
        Models\CertificatesOfDeposit::class => ['label' => 'Certificate of Deposit', 'module' => 'certificate_of_deposit'],
        Models\TimeOfDeposit::class => ['label' => 'Time Deposit', 'module' => 'time_of_deposit'],
        Models\TdRenewalDateHistory::class => ['label' => 'Time Deposit Renewal', 'module' => 'time_of_deposit'],

        /* ──────────────── Overdrafts & loans ─────────────────────── */
        Models\MediumTermLoan::class => ['label' => 'Medium Term Loan', 'module' => 'medium_term_loan'],
        Models\LoanSchedule::class => ['label' => 'Loan Schedule', 'module' => 'medium_term_loan'],
        Models\LoanScheduleSettlement::class => ['label' => 'Loan Schedule Settlement', 'module' => 'medium_term_loan'],
        Models\FullySecuredOverdraft::class => ['label' => 'Fully Secured Overdraft', 'module' => 'fully_secured_overdraft'],
        Models\CleanOverdraft::class => ['label' => 'Clean Overdraft', 'module' => 'clean_overdraft'],
        Models\OverdraftAgainstCommercialPaper::class => ['label' => 'Overdraft Against Commercial Paper', 'module' => 'overdraft_commercial_paper'],
        Models\OverdraftAgainstAssignmentOfContract::class => ['label' => 'Overdraft Against Assignment of Contract', 'module' => 'overdraft_assignment_contract'],
        Models\LendingInformation::class => ['label' => 'Lending Information', 'module' => 'overdraft_assignment_contract'],

        /* ─────────────── Letters of guarantee & credit ───────────── */
        Models\LetterOfGuaranteeFacility::class => ['label' => 'LG Facility', 'module' => 'lg_facility'],
        Models\LetterOfGuaranteeIssuance::class => [
            'label' => 'LG Issuance',
            'module' => 'lg_issuance',
            'fields' => ['status' => ['label' => 'Status']],
        ],
        Models\LgRenewalDateHistory::class => ['label' => 'LG Renewal', 'module' => 'lg_issuance'],
        Models\LetterOfCreditFacility::class => ['label' => 'LC Facility', 'module' => 'lc_facility'],
        Models\LetterOfCreditIssuance::class => [
            'label' => 'LC Issuance',
            'module' => 'lc_issuance',
            'fields' => ['status' => ['label' => 'Status']],
        ],
        Models\LcIssuanceExpense::class => ['label' => 'LC Issuance Expense', 'module' => 'lc_issuance'],

        /* ─────────────────── Invoices & partners ─────────────────── */
        Models\CustomerInvoice::class => ['label' => 'Customer Invoice', 'module' => 'customer_balance'],
        Models\SupplierInvoice::class => ['label' => 'Supplier Invoice', 'module' => 'supplier_balance'],
        Models\InvoiceDeduction::class => ['label' => 'Invoice Deduction', 'module' => 'customer_balance'],
        Models\Partner::class => [
            'label' => 'Partner',
            'module' => 'customer',
            'title' => 'getName',
        ],

        /* ─────────────────── General settings ────────────────────── */
        Models\Branch::class => ['label' => 'Safe Account', 'module' => 'branch'],
        Models\CashExpenseCategory::class => ['label' => 'Cash Expense Category', 'module' => 'cash_expense_category'],
        Models\Deduction::class => ['label' => 'Deduction', 'module' => 'deduction'],
        Models\CashVeroBusinessSector::class => ['label' => 'Business Sector', 'module' => 'business_sector'],
        Models\CashVeroBusinessUnit::class => ['label' => 'Business Unit', 'module' => 'business_unit'],
        Models\CashVeroSalesChannel::class => ['label' => 'Sales Channel', 'module' => 'sales_channel'],
        Models\CashVeroSalesPerson::class => ['label' => 'Sales Person', 'module' => 'sales_person'],
        Models\CashflowReport::class => ['label' => 'Cash Flow Report', 'module' => 'cash_flow_report'],

        /* ──────────────────── Administration ─────────────────────── */
        Models\User::class => [
            'label' => 'User',
            'module' => 'user',
            'title' => 'getName',
            // Never record the hash, and `email` changes are worth seeing.
            'ignore' => ['password', 'remember_token', 'max_users'],
        ],
        Models\Company::class => ['label' => 'Company', 'module' => 'company'],
    ];

    /** @var array<class-string, array>|null */
    private static ?array $resolved = null;

    /**
     * Every audited model class → its config.
     */
    public static function all(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $resolved = [];
        foreach (self::MODELS as $class => $config) {
            // Skip anything whose class was removed — the registry must
            // never be the reason the app fails to boot.
            if (! class_exists($class)) {
                continue;
            }
            $resolved[$class] = $config;
        }

        return self::$resolved = $resolved;
    }

    /**
     * @return class-string[]
     */
    public static function models(): array
    {
        return array_keys(self::all());
    }

    public static function tracks(string|object $model): bool
    {
        return isset(self::all()[is_object($model) ? $model::class : $model]);
    }

    public static function configFor(string|object $model): ?array
    {
        return self::all()[is_object($model) ? $model::class : $model] ?? null;
    }

    /**
     * Human label for a model class ("Money Received").
     */
    public static function labelFor(string $class): string
    {
        return self::all()[$class]['label'] ?? class_basename($class);
    }

    /**
     * The permission module that gates reading this model's history —
     * you may read the log of a record you are allowed to view.
     */
    public static function moduleFor(string $class): ?string
    {
        return self::all()[$class]['module'] ?? null;
    }

    /**
     * Fields that should never appear in a diff for this model.
     *
     * @return array<string, true>
     */
    public static function ignoredFields(string $class): array
    {
        $config = self::all()[$class] ?? [];

        return array_fill_keys(
            array_merge(self::GLOBAL_IGNORED_FIELDS, $config['ignore'] ?? []),
            true
        );
    }

    /**
     * Label for one field, resolved at READ time so it follows the
     * viewer's language rather than the actor's.
     */
    public static function fieldLabel(string $class, string $field): string
    {
        $config = self::all()[$class] ?? [];

        $label = $config['fields'][$field]['label']
            ?? self::DEFAULT_FIELD_LABELS[$field]
            ?? null;

        if ($label !== null) {
            return __($label);
        }

        // Fall back to a readable form of the column name:
        // `financial_institution_id` → "Financial Institution".
        $readable = preg_replace('/_id$/', '', $field);

        return ucwords(str_replace('_', ' ', $readable));
    }

    /**
     * Human form of a stored value — turns `cheque-under-collection`
     * into "Cheque Under Collection", booleans into Yes/No, and null
     * into an em dash.
     */
    public static function valueLabel(string $class, string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $map = self::all()[$class]['fields'][$field]['values'] ?? null;

        if ($map !== null && array_key_exists($value, $map)) {
            return __($map[$value]);
        }

        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        // Common tinyint flags: only translate for fields that read as flags.
        if (str_starts_with($field, 'is_') || str_starts_with($field, 'has_')) {
            return ((int) $value) === 1 ? __('Yes') : __('No');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
