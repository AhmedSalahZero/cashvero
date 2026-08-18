<?php

namespace App\Support\Permissions;

/**
 * PermissionRegistry
 * ==================================================================
 * THE single source of truth for every permission in CashVero.
 *
 * Everything downstream is derived from this file — never hardcoded
 * anywhere else:
 *
 *   • the `permissions` table            (PermissionSeeder)
 *   • the Gate abilities                 (AuthServiceProvider)
 *   • backend route enforcement          (RoutePermissionMap + EnforcePermission)
 *   • the permission list sent to Vue    (HandleInertiaRequests)
 *   • the sidebar                        (SidebarMenu)
 *   • the Role Management checkbox tree  (RoleController)
 *
 * ── Adding a new module ───────────────────────────────────────────
 * 1. Add one entry to MODULES below.
 * 2. Map its route names in RoutePermissionMap.
 * 3. Run `php artisan permissions:sync`.
 * That's the whole process — no controller, Gate or Vue change needed.
 *
 * ── Canonical keys vs legacy names ────────────────────────────────
 * Canonical keys are dotted: `money_received.delete`.
 *
 * But this application already has 182 natural-language permissions
 * ('delete cash expenses') that are LIVE — granted to real users in
 * production via `model_has_permissions`. Renaming them would
 * silently revoke access for every existing user on deploy.
 *
 * So each action carries a `legacy` list. A user is granted the
 * action if they hold the canonical key OR any of its legacy names.
 * That makes this whole system behaviour-preserving on deploy while
 * still exposing the clean dotted API everywhere in new code.
 *
 * Some legacy entries are deliberately "inherited" rather than exact —
 * e.g. the Factoring Statement page is gated today by
 * 'view bank statement report' (see the 2026-08 permissions audit).
 * Keeping that name in the list preserves today's access exactly,
 * while the new dedicated key lets an admin tighten it later without
 * a code change. Such cases are marked INHERITED.
 */
class PermissionRegistry
{
    /**
     * Action labels shown in the Role Management UI.
     */
    public const ACTION_LABELS = [
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'export' => 'Export',
        'import' => 'Import',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'cancel' => 'Cancel',
        'settle' => 'Settle',
        'change_cheque_status' => 'Change Cheque Status',
        'mark_as_paid' => 'Mark As Paid',
        'renew' => 'Renew',
        'restore' => 'Restore',
        'manage_rates' => 'Manage Rates',
        'manage_schedule' => 'Manage Schedule',
        'bulk_delete' => 'Bulk Delete',
        'lock' => 'Lock / Unlock',
        'assign_roles' => 'Assign Roles',
        'sync' => 'Sync',
    ];

    /**
     * Module groups — used purely to organise the Role Management UI.
     */
    public const GROUPS = [
        'dashboards' => 'Dashboards',
        'transactions' => 'Cash Transactions',
        'factoring' => 'Factoring',
        'customers' => 'Customers',
        'suppliers' => 'Suppliers',
        'contracts' => 'Contracts',
        'facilities' => 'Financial Institutions & Facilities',
        'deposits' => 'Deposits',
        'overdrafts' => 'Overdrafts & Loans',
        'lg_lc' => 'Letters of Guarantee & Credit',
        'reports' => 'Reports',
        'cash_flow' => 'Cash Flow',
        'opening_balances' => 'Opening Balances',
        'master_data' => 'General Settings',
        'data_upload' => 'Data Upload & Analysis',
        'administration' => 'Administration',
        'integrations' => 'Integrations',
    ];

    /**
     * @var array<string, array{label:string, group:string, actions:array<string, string[]>}>
     */
    private const MODULES = [

        /* ───────────────────────── Dashboards ───────────────────── */
        'home' => [
            'label' => 'Home',
            'group' => 'dashboards',
            'actions' => ['view' => ['view home']],
        ],
        'dashboard_cash' => [
            'label' => 'Cash Status Dashboard',
            'group' => 'dashboards',
            'actions' => ['view' => ['view cash status dashboard']],
        ],
        'dashboard_forecast' => [
            'label' => 'Cash Forecast Dashboard',
            'group' => 'dashboards',
            'actions' => ['view' => ['view cash Forecast dashboard']],
        ],
        'dashboard_lg_lc' => [
            'label' => 'LG & LC Dashboard',
            'group' => 'dashboards',
            'actions' => ['view' => ['view lg & lc dashboard']],
        ],
        'invoice_report' => [
            'label' => 'Invoice Report',
            'group' => 'dashboards',
            // INHERITED: reached from the cash dashboard, gated with it today.
            'actions' => [
                'view' => ['view cash status dashboard'],
                'export' => ['view cash status dashboard'],
            ],
        ],

        /* ─────────────────────── Cash Transactions ──────────────── */
        'money_received' => [
            'label' => 'Money Received',
            'group' => 'transactions',
            'actions' => [
                'view' => ['view money received'],
                'create' => ['create money received'],
                'update' => ['update money received'],
                'delete' => ['delete money received'],
                /**
                 * Moving a cheque between its states: send under
                 * collection, apply collection, return to safe, mark
                 * rejected, back to under collection. Distinct from
                 * `update`, which edits the cheque's own figures — this
                 * is who may advance the cash through its lifecycle.
                 *
                 * `money_received.settle` is kept as an alias: the
                 * user-based migration already wrote that exact key onto
                 * users, so dropping it would revoke the action from
                 * everyone who has been migrated.
                 */
                'change_cheque_status' => ['money_received.settle', 'update money received'],
                'export' => ['view money received'],
            ],
        ],
        'money_payment' => [
            'label' => 'Money Payment',
            'group' => 'transactions',
            'actions' => [
                'view' => ['view supplier payment'],
                'create' => ['create supplier payment'],
                'update' => ['update supplier payment'],
                'delete' => ['delete supplier payment'],
                // Marking a payable cheque or outgoing transfer as
                // actually paid. Alias kept — see money_received above.
                'mark_as_paid' => ['money_payment.settle', 'update supplier payment'],
                'export' => ['view supplier payment'],
            ],
        ],
        'cash_expense' => [
            'label' => 'Cash Expense',
            'group' => 'transactions',
            'actions' => [
                'view' => ['view cash expenses'],
                'create' => ['create cash expenses'],
                'update' => ['update cash expenses'],
                'delete' => ['delete cash expenses'],
                // Same meaning as money_payment. Alias kept.
                'mark_as_paid' => ['cash_expense.settle', 'update cash expenses'],
                'export' => ['view cash expenses'],
            ],
        ],
        'internal_money_transfer' => [
            'label' => 'Internal Money Transfer',
            'group' => 'transactions',
            'actions' => [
                'view' => ['view internal money transfer'],
                'create' => ['create internal money transfer'],
                'update' => ['update internal money transfer'],
                'delete' => ['delete internal money transfer'],
            ],
        ],
        'lc_settlement_transfer' => [
            'label' => 'LC Settlement Internal Transfer',
            'group' => 'transactions',
            'actions' => [
                'view' => ['view lc settlement internal transfer'],
                'create' => ['create lc settlement internal transfer'],
                'update' => ['update lc settlement internal transfer'],
                'delete' => ['delete lc settlement internal transfer'],
            ],
        ],
        'buy_or_sell_currency' => [
            'label' => 'Buy / Sell Currency',
            'group' => 'transactions',
            'actions' => [
                'view' => ['view buy or sell currency'],
                'create' => ['create buy or sell currency'],
                'update' => ['update buy or sell currency'],
                'delete' => ['delete buy or sell currency'],
            ],
        ],
        'foreign_exchange_rate' => [
            'label' => 'Foreign Exchange Rate',
            'group' => 'transactions',
            'actions' => [
                'view' => ['view foreign exchange rate'],
                'create' => ['create foreign exchange rate'],
                'update' => ['update foreign exchange rate'],
                'delete' => ['delete foreign exchange rate'],
            ],
        ],

        /* ───────────────────────── Factoring ────────────────────── */
        'factoring_with_recourse' => [
            'label' => 'Factoring With Recourse',
            'group' => 'factoring',
            // INHERITED: gated by supplier-payment permissions today.
            'actions' => [
                'view' => ['view supplier payment'],
                'create' => ['create supplier payment'],
                'update' => ['update supplier payment'],
                'delete' => ['delete supplier payment'],
                'approve' => ['update supplier payment'],
                'reject' => ['update supplier payment'],
            ],
        ],
        'factoring_without_recourse' => [
            'label' => 'Factoring Without Recourse',
            'group' => 'factoring',
            'actions' => [
                'view' => ['view supplier payment'],
                'create' => ['create supplier payment'],
                'update' => ['update supplier payment'],
                'delete' => ['delete supplier payment'],
                'settle' => ['update supplier payment'],
            ],
        ],
        'factoring_contract' => [
            'label' => 'Factoring Contract',
            'group' => 'factoring',
            // INHERITED: FactoringContractController reuses clean-overdraft perms.
            'actions' => [
                'view' => ['view clean overdraft'],
                'create' => ['create clean overdraft'],
                'update' => ['update clean overdraft'],
                'delete' => ['delete clean overdraft'],
                'renew' => ['update clean overdraft'],
            ],
        ],
        'factoring_company' => [
            'label' => 'Factoring Company',
            'group' => 'factoring',
            'actions' => [
                'view' => ['view financial institutions'],
                'create' => ['create financial institutions'],
                'update' => ['update financial institutions'],
                'delete' => ['delete financial institutions'],
            ],
        ],

        /* ───────────────────────── Customers ────────────────────── */
        'customer' => [
            'label' => 'Customers',
            'group' => 'customers',
            'actions' => [
                'view' => ['view customers'],
                'create' => ['create customers', 'update customers'],
                'update' => ['update customers'],
                'delete' => ['update customers'],
            ],
        ],
        'customer_balance' => [
            'label' => 'Customer Balances',
            'group' => 'customers',
            'actions' => [
                'view' => ['view customer balances'],
                'update' => ['view customer balances'],
                'export' => ['view customer balances'],
            ],
        ],
        'customer_aging' => [
            'label' => 'Customer Aging',
            'group' => 'customers',
            'actions' => [
                'view' => ['view customer aging'],
                'export' => ['view customer aging'],
            ],
        ],
        'collection_effectiveness' => [
            'label' => 'Collection Effectiveness Index',
            'group' => 'customers',
            'actions' => ['view' => ['view collections effectiveness index']],
        ],

        /* ───────────────────────── Suppliers ────────────────────── */
        'supplier' => [
            'label' => 'Suppliers',
            'group' => 'suppliers',
            'actions' => [
                'view' => ['view suppliers'],
                'create' => ['create suppliers', 'update customers'],
                'update' => ['update suppliers', 'update customers'],
                'delete' => ['update customers'],
            ],
        ],
        'supplier_balance' => [
            'label' => 'Supplier Balances',
            'group' => 'suppliers',
            'actions' => [
                'view' => ['view supplier balances'],
                'update' => ['view supplier balances'],
                'export' => ['view supplier balances'],
            ],
        ],
        'supplier_aging' => [
            'label' => 'Supplier Aging',
            'group' => 'suppliers',
            'actions' => [
                'view' => ['view supplier aging'],
                'export' => ['view supplier aging'],
            ],
        ],
        'payment_effectiveness' => [
            'label' => 'Payment Effectiveness Index',
            'group' => 'suppliers',
            'actions' => ['view' => ['view payments effectiveness index']],
        ],

        /* ───────────────────────── Contracts ────────────────────── */
        'customer_contract' => [
            'label' => 'Customer Contracts',
            'group' => 'contracts',
            'actions' => [
                'view' => ['view customers contracts'],
                'create' => ['create customers contracts', 'view customers contracts'],
                'update' => ['update customers contracts'],
                'delete' => ['delete customers contracts', 'update customers contracts'],
                'approve' => ['update customers contracts'],
            ],
        ],
        'supplier_contract' => [
            'label' => 'Supplier Contracts',
            'group' => 'contracts',
            'actions' => [
                'view' => ['view suppliers contracts'],
                'create' => ['create suppliers contracts', 'view suppliers contracts'],
                'update' => ['update suppliers contracts'],
                'delete' => ['delete suppliers contracts', 'update suppliers contracts'],
                'approve' => ['update suppliers contracts'],
            ],
        ],
        'down_payment_contract' => [
            'label' => 'Down Payment Contracts',
            'group' => 'contracts',
            'actions' => [
                'view' => ['view customers contracts'],
                'settle' => ['update customers contracts'],
            ],
        ],
        'contract_loan_schedule' => [
            'label' => 'Contract Loan Schedule',
            'group' => 'contracts',
            'actions' => [
                'view' => ['view customers contracts'],
                'create' => ['update customers contracts'],
                'update' => ['update customers contracts'],
                'delete' => ['update customers contracts'],
            ],
        ],
        'adjusted_due_date' => [
            'label' => 'Adjusted Due Dates',
            'group' => 'contracts',
            'actions' => [
                'view' => ['view customer balances'],
                'create' => ['view customer balances'],
                'update' => ['view customer balances'],
                'delete' => ['view customer balances'],
            ],
        ],

        /* ──────────────── Financial Institutions & Facilities ───── */
        'financial_institution' => [
            'label' => 'Financial Institutions',
            'group' => 'facilities',
            'actions' => [
                'view' => ['view financial institutions'],
                'create' => ['create financial institutions'],
                'update' => ['update financial institutions'],
                'delete' => ['delete financial institutions'],
            ],
        ],
        'bank_account' => [
            'label' => 'Bank Accounts',
            'group' => 'facilities',
            'actions' => [
                'view' => ['view financial institutions'],
                'create' => ['create financial institutions'],
                'update' => ['update financial institutions'],
                'delete' => ['delete financial institutions'],
                'lock' => ['update financial institutions'],
            ],
        ],
        'facility_overview' => [
            'label' => 'Facilities Overview Pages',
            'group' => 'facilities',
            'actions' => ['view' => ['view financial institutions']],
        ],
        'leasing_company' => [
            'label' => 'Leasing Companies',
            'group' => 'facilities',
            'actions' => [
                'view' => ['view financial institutions'],
                'create' => ['create financial institutions'],
                'update' => ['update financial institutions'],
                'delete' => ['delete financial institutions'],
            ],
        ],
        'leasing_contract' => [
            'label' => 'Leasing Contracts',
            'group' => 'facilities',
            // INHERITED: LeasingContractController reuses medium-term-loan perms.
            'actions' => [
                'view' => ['view medium term loan'],
                'create' => ['create medium term loan'],
                'update' => ['update medium term loan'],
                'delete' => ['delete medium term loan'],
                'manage_schedule' => ['update medium term loan'],
            ],
        ],

        /* ───────────────────────── Deposits ─────────────────────── */
        'certificate_of_deposit' => [
            'label' => 'Certificates of Deposit',
            'group' => 'deposits',
            'actions' => [
                'view' => ['view certificate of deposit'],
                'create' => ['create certificate of deposit'],
                'update' => ['update certificate of deposit', 'create certificate of deposit'],
                'delete' => ['delete certificate of deposit', 'create certificate of deposit'],
                'settle' => ['create certificate of deposit'],
            ],
        ],
        'time_of_deposit' => [
            'label' => 'Time Deposits',
            'group' => 'deposits',
            'actions' => [
                'view' => ['view time of deposit'],
                'create' => ['create time of deposit'],
                'update' => ['update time of deposit', 'create time of deposit'],
                'delete' => ['delete time of deposit', 'create time of deposit'],
                'settle' => ['create time of deposit'],
                'renew' => ['create time of deposit'],
            ],
        ],

        /* ────────────────── Overdrafts & Loans ──────────────────── */
        'medium_term_loan' => [
            'label' => 'Medium Term Loans',
            'group' => 'overdrafts',
            'actions' => [
                'view' => ['view medium term loan'],
                'create' => ['create medium term loan'],
                'update' => ['update medium term loan'],
                'delete' => ['delete medium term loan'],
                'manage_schedule' => ['update medium term loan'],
                'export' => ['view medium term loan'],
            ],
        ],
        'fully_secured_overdraft' => [
            'label' => 'Fully Secured Overdraft',
            'group' => 'overdrafts',
            'actions' => [
                'view' => ['view fully secured overdraft'],
                'create' => ['create fully secured overdraft'],
                'update' => ['update fully secured overdraft'],
                'delete' => ['delete fully secured overdraft'],
                'renew' => ['update fully secured overdraft'],
                'manage_rates' => ['update fully secured overdraft'],
            ],
        ],
        'clean_overdraft' => [
            'label' => 'Clean Overdraft',
            'group' => 'overdrafts',
            'actions' => [
                'view' => ['view clean overdraft'],
                'create' => ['create clean overdraft'],
                'update' => ['update clean overdraft'],
                'delete' => ['delete clean overdraft'],
                'renew' => ['update clean overdraft'],
                'manage_rates' => ['update clean overdraft'],
            ],
        ],
        'overdraft_commercial_paper' => [
            'label' => 'Overdraft Against Commercial Paper',
            'group' => 'overdrafts',
            'actions' => [
                'view' => ['view overdraft against commercial paper'],
                // No legacy 'create ...' permission ever existed for this
                // module; inherit update so today's access is preserved.
                'create' => ['update overdraft against commercial paper'],
                'update' => ['update overdraft against commercial paper'],
                'delete' => ['delete overdraft against commercial paper'],
                'renew' => ['update overdraft against commercial paper'],
                'manage_rates' => ['update overdraft against commercial paper'],
            ],
        ],
        'overdraft_assignment_contract' => [
            'label' => 'Overdraft Against Assignment of Contract',
            'group' => 'overdrafts',
            'actions' => [
                'view' => ['view overdraft against assignment of contract'],
                'create' => ['create overdraft against assignment of contract'],
                'update' => ['update overdraft against assignment of contract'],
                'delete' => ['delete overdraft against assignment of contract'],
                'renew' => ['update overdraft against assignment of contract'],
                'manage_rates' => ['update overdraft against assignment of contract'],
            ],
        ],

        /* ──────────────── Letters of Guarantee & Credit ─────────── */
        'lg_facility' => [
            'label' => 'Letter of Guarantee Facility',
            'group' => 'lg_lc',
            'actions' => [
                'view' => ['view letter of guarantee facility'],
                'create' => ['create letter of guarantee facility'],
                'update' => ['update letter of guarantee facility', 'create letter of guarantee facility'],
                'delete' => ['delete letter of guarantee facility', 'create letter of guarantee facility'],
                'renew' => ['update letter of guarantee facility', 'create letter of guarantee facility'],
            ],
        ],
        'lg_issuance' => [
            'label' => 'Letter of Guarantee Issuance',
            'group' => 'lg_lc',
            'actions' => [
                'view' => ['view letter of guarantee issuance'],
                'create' => ['create letter of guarantee issuance', 'view letter of guarantee issuance'],
                'update' => ['update letter of guarantee issuance', 'view letter of guarantee issuance'],
                'delete' => ['delete letter of guarantee issuance', 'view letter of guarantee issuance'],
                'cancel' => ['update letter of guarantee issuance', 'view letter of guarantee issuance'],
                'renew' => ['update letter of guarantee issuance', 'view letter of guarantee issuance'],
                'import' => ['create letter of guarantee issuance', 'view letter of guarantee issuance'],
            ],
        ],
        'lc_facility' => [
            'label' => 'Letter of Credit Facility',
            'group' => 'lg_lc',
            'actions' => [
                'view' => ['view letter of credit facility'],
                'create' => ['create letter of credit facility'],
                'update' => ['update letter of credit facility', 'create letter of credit facility'],
                'delete' => ['delete letter of credit facility', 'create letter of credit facility'],
                'renew' => ['update letter of credit facility', 'create letter of credit facility'],
            ],
        ],
        'lc_issuance' => [
            'label' => 'Letter of Credit Issuance',
            'group' => 'lg_lc',
            'actions' => [
                'view' => ['view letter of credit issuance'],
                'create' => ['create letter of credit issuance', 'view letter of credit issuance'],
                'update' => ['update letter of credit issuance', 'view letter of credit issuance'],
                'delete' => ['delete letter of credit issuance', 'view letter of credit issuance'],
                'settle' => ['update letter of credit issuance', 'view letter of credit issuance'],
            ],
        ],

        /* ───────────────────────── Reports ──────────────────────── */
        'report_bank_statement' => [
            'label' => 'Bank Statement',
            'group' => 'reports',
            'actions' => [
                'view' => ['view bank statement report'],
                'update' => ['view bank statement report'],
                'export' => ['view bank statement report'],
            ],
        ],
        'report_safe_statement' => [
            'label' => 'Safe Statement',
            'group' => 'reports',
            'actions' => [
                'view' => ['view safe statement report'],
                'export' => ['view safe statement report'],
            ],
        ],
        'report_factoring_statement' => [
            'label' => 'Factoring Statement',
            'group' => 'reports',
            // INHERITED: gated by the bank-statement permission today.
            'actions' => [
                'view' => ['view bank statement report'],
                'export' => ['view bank statement report'],
            ],
        ],
        'report_factoring_charges' => [
            'label' => 'Factoring Charges Statement',
            'group' => 'reports',
            'actions' => [
                'view' => ['view bank statement report'],
                'export' => ['view bank statement report'],
            ],
        ],
        'report_lg_lc_statement' => [
            'label' => 'LG & LC Statement',
            'group' => 'reports',
            'actions' => [
                'view' => ['view lc & lg statement report', 'view bank statement report'],
                'export' => ['view lc & lg statement report', 'view bank statement report'],
            ],
        ],
        'report_lg_by_beneficiary' => [
            'label' => 'LG by Beneficiary Name',
            'group' => 'reports',
            'actions' => [
                'view' => ['view lg by beneficiary name report'],
                'export' => ['view lg by beneficiary name report'],
            ],
        ],
        'report_lg_by_bank' => [
            'label' => 'LG by Bank Name',
            'group' => 'reports',
            'actions' => [
                'view' => ['view lg by bank name report'],
                'export' => ['view lg by bank name report'],
            ],
        ],
        'report_cash_expense_statement' => [
            'label' => 'Cash Expense Statement',
            'group' => 'reports',
            'actions' => [
                'view' => ['view cash expense report'],
                'export' => ['view cash expense report'],
            ],
        ],
        'report_partners_statement' => [
            'label' => 'Partner Statement',
            'group' => 'reports',
            'actions' => [
                'view' => ['view partners statement report'],
                'export' => ['view partners statement report'],
            ],
        ],
        'report_taxes_insurance' => [
            'label' => 'Taxes & Insurance Statement',
            'group' => 'reports',
            'actions' => [
                'view' => ['view partners statement report'],
                'export' => ['view partners statement report'],
            ],
        ],
        'report_withdrawals_settlement' => [
            'label' => 'Withdrawal Settlement Report',
            'group' => 'reports',
            'actions' => [
                'view' => ['view withdrawals settlement report'],
                'export' => ['view withdrawals settlement report'],
            ],
        ],

        /* ──────────────────────── Cash Flow ─────────────────────── */
        'cash_flow_report' => [
            'label' => 'Company Cash Flow Report',
            'group' => 'cash_flow',
            'actions' => [
                'view' => ['view cash flow report'],
                'create' => ['view cash flow report'],
                'update' => ['view cash flow report'],
                'delete' => ['view cash flow report'],
                'export' => ['view cash flow report'],
            ],
        ],
        'contract_cash_flow_report' => [
            'label' => 'Contract Cash Flow Report',
            'group' => 'cash_flow',
            'actions' => [
                'view' => ['view contract cash flow report'],
                'export' => ['view contract cash flow report'],
            ],
        ],
        'consolidated_cash_flow_report' => [
            'label' => 'Consolidated Cash Flow Report',
            'group' => 'cash_flow',
            'actions' => [
                'view' => ['view cash flow report'],
                'export' => ['view cash flow report'],
            ],
        ],

        /* ─────────────────── Opening Balances ───────────────────── */
        'opening_balance' => [
            'label' => 'Cash & Cheque Opening Balances',
            'group' => 'opening_balances',
            'actions' => [
                'view' => ['update cash & cheques opening balances'],
                'create' => ['update cash & cheques opening balances'],
                'update' => ['update cash & cheques opening balances'],
                'delete' => ['update cash & cheques opening balances'],
            ],
        ],
        'customer_opening_balance' => [
            'label' => 'Customer Opening Balances',
            'group' => 'opening_balances',
            'actions' => [
                'view' => ['update cash & cheques opening balances'],
                'create' => ['update cash & cheques opening balances'],
                'update' => ['update cash & cheques opening balances'],
                'delete' => ['update cash & cheques opening balances'],
            ],
        ],
        'supplier_opening_balance' => [
            'label' => 'Supplier Opening Balances',
            'group' => 'opening_balances',
            'actions' => [
                'view' => ['update cash & cheques opening balances'],
                'create' => ['update cash & cheques opening balances'],
                'update' => ['update cash & cheques opening balances'],
                'delete' => ['update cash & cheques opening balances'],
            ],
        ],

        /* ─────────────────── General Settings ───────────────────── */
        'branch' => [
            'label' => 'Safe Accounts / Branches',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view branches'],
                'create' => ['create branches'],
                'update' => ['update branches'],
                'delete' => ['delete branches'],
            ],
        ],
        'employee' => [
            'label' => 'Employees',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view employees', 'view customers'],
                'create' => ['create employees', 'update customers'],
                'update' => ['update employees', 'update customers'],
                'delete' => ['update customers'],
            ],
        ],
        'shareholder' => [
            'label' => 'Shareholders',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view shareholders', 'view customers'],
                'create' => ['create shareholders', 'update customers'],
                'update' => ['update shareholders', 'update customers'],
                'delete' => ['update customers'],
            ],
        ],
        'other_partner' => [
            'label' => 'Other Partners',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view other partners', 'view customers'],
                'create' => ['create other partners', 'update customers'],
                'update' => ['update other partners', 'update customers'],
                'delete' => ['update customers'],
            ],
        ],
        'subsidiary_company' => [
            'label' => 'Subsidiary Companies',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view subsidiary companies'],
                'create' => ['create subsidiary companies', 'update customers'],
                'update' => ['update subsidiary companies', 'update customers'],
                'delete' => ['update customers'],
            ],
        ],
        'cash_expense_category' => [
            'label' => 'Cash Expense Categories',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view cash expense categories'],
                'create' => ['view cash expense categories'],
                'update' => ['view cash expense categories'],
                'delete' => ['view cash expense categories'],
            ],
        ],
        'deduction' => [
            'label' => 'Deductions',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view deductions'],
                'create' => ['create deductions', 'view deductions'],
                'update' => ['update deductions', 'view deductions'],
                'delete' => ['view deductions'],
            ],
        ],
        'business_sector' => [
            'label' => 'Business Sectors',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view business sectors'],
                'create' => ['create business sectors', 'view business sectors'],
                'update' => ['update business sectors', 'view business sectors'],
                'delete' => ['delete business sectors', 'view business sectors'],
            ],
        ],
        'business_unit' => [
            'label' => 'Business Units',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view business units'],
                'create' => ['create business units', 'view business units'],
                'update' => ['update business units', 'view business units'],
                'delete' => ['delete business units', 'view business units'],
            ],
        ],
        'sales_channel' => [
            'label' => 'Sales Channels',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view sales channels'],
                'create' => ['create sales channels', 'view sales channels'],
                'update' => ['update sales channels', 'view sales channels'],
                'delete' => ['delete sales channels', 'view sales channels'],
            ],
        ],
        'sales_person' => [
            'label' => 'Sales Persons',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view sales persons'],
                'create' => ['create sales persons', 'view sales persons'],
                'update' => ['update sales persons', 'view sales persons'],
                'delete' => ['delete sales persons', 'view sales persons'],
            ],
        ],
        'notification_setting' => [
            'label' => 'Notification Settings',
            'group' => 'master_data',
            'actions' => [
                'view' => ['view notification settings'],
                'create' => ['view notification settings'],
                'update' => ['view notification settings'],
                'delete' => ['view notification settings'],
            ],
        ],

        /* ────────────────── Data Upload & Analysis ──────────────── */
        'customer_invoice_data' => [
            'label' => 'Customer Invoice Data',
            'group' => 'data_upload',
            'actions' => [
                'view' => ['view customer invoice analysis data'],
                'import' => ['upload customer invoice analysis data'],
                'export' => ['export customer invoice analysis data'],
                'delete' => ['delete customer invoice analysis data'],
                'bulk_delete' => ['delete customer invoice analysis data'],
            ],
        ],
        'supplier_invoice_data' => [
            'label' => 'Supplier Invoice Data',
            'group' => 'data_upload',
            'actions' => [
                'view' => ['view supplier invoice analysis data'],
                'import' => ['upload supplier invoice analysis data'],
                'export' => ['export supplier invoice analysis data'],
                'delete' => ['delete supplier invoice analysis data'],
                'bulk_delete' => ['delete supplier invoice analysis data'],
            ],
        ],
        'loan_schedule_data' => [
            'label' => 'Loan Schedule Data',
            'group' => 'data_upload',
            'actions' => [
                'view' => ['view loan schedule analysis data'],
                'import' => ['upload loan schedule analysis data'],
                'export' => ['export loan schedule analysis data'],
                'delete' => ['delete loan schedule analysis data'],
                'bulk_delete' => ['delete loan schedule analysis data'],
            ],
        ],

        /* ──────────────────── Administration ────────────────────── */
        'user' => [
            'label' => 'Users',
            'group' => 'administration',
            'hint' => 'The Users list: who exists, and creating or removing accounts. "Assign Roles" also unlocks the per-user permissions screen (the eye icon) where access is actually configured.',
            'actions' => [
                'view' => ['view users'],
                'create' => ['create user'],
                'update' => ['create user'],
                'delete' => ['create user'],
                'assign_roles' => ['update permissions'],
            ],
        ],
        'role' => [
            'label' => 'Roles & Permissions',
            'group' => 'administration',
            'hint' => 'The role TEMPLATES screen. Templates are copied onto a user when their account is created — editing one never changes an existing user.',
            'actions' => [
                'view' => ['update permissions'],
                'create' => ['update permissions'],
                'update' => ['update permissions'],
                'delete' => ['update permissions'],
            ],
        ],
        'company' => [
            'label' => 'Companies',
            'group' => 'administration',
            'hint' => 'The Companies list (the building icon in the top bar). Creating and removing companies themselves — not the data inside them.',
            'actions' => [
                'view' => ['view company admin'],
                'create' => ['create company admin'],
                'update' => ['create company admin'],
                'delete' => ['create company admin'],
            ],
        ],
        'company_admin' => [
            'label' => 'Company Admin Accounts',
            'group' => 'administration',
            'hint' => 'Reach over OTHER PEOPLE\'s Company Admin accounts — whether they show up in your Users list and whether you may hand out that role. It grants you none of a Company Admin\'s own abilities.',
            'action_labels' => [
                'view' => 'See Them In The Users List',
                'create' => 'Create & Assign This Role',
            ],
            'actions' => [
                'view' => ['view company admin'],
                'create' => ['create company admin'],
            ],
        ],
        'manager' => [
            'label' => 'Manager Accounts',
            'group' => 'administration',
            'hint' => 'Same idea as Company Admin Accounts: whether other people\'s Manager accounts appear in your Users list, and whether you may hand out that role. Not a Manager\'s own abilities.',
            'action_labels' => [
                'view' => 'See Them In The Users List',
                'create' => 'Create & Assign This Role',
            ],
            'actions' => [
                'view' => ['view managers'],
                'create' => ['create manager'],
            ],
        ],
        'super_admin' => [
            'label' => 'Super Admin Accounts',
            'group' => 'administration',
            'hint' => 'Whether other people\'s Super Admin accounts appear in your Users list. Nothing more: the Super Admin role can only ever be handed out by another Super Admin, and it bypasses every permission in this screen.',
            'action_labels' => [
                'view' => 'See Them In The Users List',
            ],
            'actions' => ['view' => ['view super admin']],
        ],

        /* ────────────────────── Integrations ────────────────────── */
        'odoo_integration' => [
            'label' => 'Odoo Integration',
            'group' => 'integrations',
            'actions' => [
                'view' => ['view financial institutions'],
                'update' => ['update financial institutions'],
                'delete' => ['delete financial institutions'],
                'sync' => ['update financial institutions'],
            ],
        ],
    ];

    /**
     * Modules that only an administrator should reach.
     */
    public const ADMIN_MODULES = [
        'super_admin', 'company', 'company_admin', 'manager', 'user', 'role',
    ];

    /**
     * Default permission TEMPLATE per role.
     * ------------------------------------------------------------------
     * ⚠️ Roles do not grant access in this application — permissions are
     * held per user. These sets are what a newly created user of each
     * role is seeded with, and what "Start from a template" applies on
     * the per-user permission screen. Nothing here reaches an existing
     * user.
     *
     * ⚠️ These are deliberately NOT derived from HAuth's `default-roles`
     * declarations. Those grant 293–314 of the 314 keys to EVERY role
     * (they were the defaults for per-user grants, never a role model),
     * so seeding them would make all four roles functionally identical
     * and hand a plain `user` near-total access.
     *
     * The matrix below is the differentiated model this system is for —
     * a Manager can create and edit but not delete, exactly as specified:
     *
     *     Manager  ✓ money_received.create  ✓ money_received.update
     *              ✗ money_received.delete  ✓ cash_flow_report.view
     *
     * Nothing here is locked down: this is only the starting point a
     * fresh install gets. Every template is fully editable afterwards
     * in the Role Management UI, and `permissions:sync` never
     * overwrites an administrator's changes unless run with
     * --reset-roles.
     *
     * @var array<string, array{modules:string, actions:string|string[], except_modules?:string[], except_actions?:string[]}>
     */
    private const ROLE_DEFAULTS = [
        // Handled by the Gate::before bypass; granted explicitly too so
        // the Role Management screen shows what the role means.
        'super-admin' => [
            'modules' => 'all',
            'actions' => 'all',
        ],
        // Runs one company end to end, including its users and roles.
        // Cannot manage other companies or super admins.
        'company-admin' => [
            'modules' => 'all',
            'actions' => 'all',
            'except_modules' => ['super_admin', 'company'],
        ],
        // Full day-to-day operation: can create, edit, approve, review,
        // settle and export everywhere — but cannot delete, cannot bulk
        // delete, and cannot touch users, roles or companies.
        'manager' => [
            'modules' => 'all',
            'actions' => [
                'view', 'create', 'update', 'export', 'import', 'review',
                'approve', 'reject', 'cancel', 'settle', 'renew',
                'manage_rates', 'manage_schedule', 'lock', 'sync',
            ],
            'except_modules' => self::ADMIN_MODULES,
        ],
        // Read-only across the business, plus the ability to export what
        // they can already see. Everything else is granted deliberately
        // by an administrator.
        'user' => [
            'modules' => 'all',
            'actions' => ['view', 'export'],
            'except_modules' => self::ADMIN_MODULES,
        ],
    ];

    /** @var array<string, array>|null */
    private static ?array $flat = null;

    /** @var array<string, string[]>|null */
    private static ?array $grantIndex = null;

    /** @var array<string, true>|null */
    private static ?array $legacyIndex = null;

    /** @var array<string, string[]>|null */
    private static ?array $legacyToKeys = null;

    /**
     * Raw module definitions.
     */
    public static function modules(): array
    {
        return self::MODULES;
    }

    /**
     * Every permission, flattened and keyed by its canonical dotted key.
     *
     * @return array<string, array{key:string, module:string, module_label:string,
     *                             group:string, group_label:string, action:string,
     *                             action_label:string, legacy:string[]}>
     */
    public static function all(): array
    {
        if (self::$flat !== null) {
            return self::$flat;
        }

        $flat = [];
        foreach (self::MODULES as $module => $def) {
            foreach ($def['actions'] as $action => $legacy) {
                $key = "{$module}.{$action}";
                $flat[$key] = [
                    'key' => $key,
                    'module' => $module,
                    'module_label' => $def['label'],
                    'group' => $def['group'],
                    'group_label' => self::GROUPS[$def['group']] ?? $def['group'],
                    'action' => $action,
                    /**
                     * Per-module override first. A few modules use an
                     * action whose generic label is actively misleading
                     * for them — "View" on Manager Accounts does not mean
                     * viewing a manager, it means whether those accounts
                     * appear in the Users list at all.
                     */
                    'action_label' => $def['action_labels'][$action]
                        ?? self::ACTION_LABELS[$action]
                        ?? ucfirst(str_replace('_', ' ', $action)),
                    'hint' => $def['hint'] ?? null,
                    'legacy' => array_values(array_unique($legacy)),
                ];
            }
        }

        return self::$flat = $flat;
    }

    /**
     * All canonical keys.
     *
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * Is this one of the legacy natural-language permission names this
     * registry still recognises ('delete cash expenses')?
     *
     * Needed because a legacy name is not a registry KEY, so a call like
     * `$user->can('view cash expenses')` would otherwise fall past
     * Gate::before to Spatie's own gate — which unions role and direct
     * grants and would therefore reintroduce role inheritance through
     * the back door. AuthServiceProvider uses this to route legacy names
     * through the same user-only resolver.
     */
    public static function isLegacyName(string $name): bool
    {
        if (self::$legacyIndex === null) {
            $index = [];
            foreach (self::all() as $permission) {
                foreach ($permission['legacy'] as $legacy) {
                    $index[$legacy] = true;
                }
            }
            self::$legacyIndex = $index;
        }

        return isset(self::$legacyIndex[$name]);
    }

    /**
     * Every permission NAME that grants this key — the canonical key
     * itself plus its legacy aliases. Holding any one of them is enough.
     *
     * @return string[]
     */
    public static function grantNames(string $key): array
    {
        if (self::$grantIndex === null) {
            $index = [];
            foreach (self::all() as $k => $p) {
                $index[$k] = array_values(array_unique(array_merge([$k], $p['legacy'])));
            }
            self::$grantIndex = $index;
        }

        return self::$grantIndex[$key] ?? [$key];
    }

    /**
     * Canonical keys that a legacy permission name stands for.
     *
     * The reverse of `grantNames()`. Needed because after
     * `permissions:migrate-to-user` a user holds ONLY canonical keys, so
     * a surviving call site written the old way — `can('view users')`,
     * and App\Notification has 13 of them — would resolve against a name
     * nobody holds any more and silently return false.
     *
     * @return string[]
     */
    public static function keysForLegacy(string $name): array
    {
        if (self::$legacyToKeys === null) {
            $index = [];
            foreach (self::all() as $key => $permission) {
                foreach ($permission['legacy'] as $legacy) {
                    $index[$legacy][] = $key;
                }
            }
            self::$legacyToKeys = $index;
        }

        return self::$legacyToKeys[$name] ?? [];
    }

    /**
     * Permission names that must physically exist in the `permissions`
     * table: every canonical key plus every legacy name still in use.
     *
     * @return string[]
     */
    public static function seedableNames(): array
    {
        $names = self::keys();
        foreach (self::all() as $p) {
            foreach ($p['legacy'] as $legacy) {
                $names[] = $legacy;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The canonical keys a role's TEMPLATE carries on a fresh install.
     *
     * @return string[]
     */
    public static function defaultKeysForRole(string $roleName): array
    {
        $policy = self::ROLE_DEFAULTS[$roleName] ?? null;

        if ($policy === null) {
            return [];
        }

        $exceptModules = array_fill_keys($policy['except_modules'] ?? [], true);
        $exceptActions = array_fill_keys($policy['except_actions'] ?? [], true);
        $allowedActions = $policy['actions'] === 'all'
            ? null
            : array_fill_keys((array) $policy['actions'], true);

        $keys = [];

        foreach (self::all() as $key => $permission) {
            if (isset($exceptModules[$permission['module']])) {
                continue;
            }

            if (isset($exceptActions[$permission['action']])) {
                continue;
            }

            if ($allowedActions !== null && ! isset($allowedActions[$permission['action']])) {
                continue;
            }

            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * @return string[] role names this registry declares defaults for
     */
    public static function rolesWithDefaults(): array
    {
        return array_keys(self::ROLE_DEFAULTS);
    }

    /**
     * The registry shaped for the Role Management UI:
     * groups → modules → actions.
     */
    public static function tree(): array
    {
        $tree = [];
        foreach (self::all() as $p) {
            $tree[$p['group']]['key'] = $p['group'];
            $tree[$p['group']]['label'] = $p['group_label'];
            $tree[$p['group']]['modules'][$p['module']]['key'] = $p['module'];
            $tree[$p['group']]['modules'][$p['module']]['label'] = $p['module_label'];
            // One-line explanation shown under the module name. Only the
            // modules whose meaning is not obvious carry one — the
            // Administration group especially, where "Managers" governs
            // visibility of a role rather than that role's abilities.
            $tree[$p['group']]['modules'][$p['module']]['hint'] = $p['hint'];
            $tree[$p['group']]['modules'][$p['module']]['permissions'][] = [
                'key' => $p['key'],
                'action' => $p['action'],
                'label' => $p['action_label'],
            ];
        }

        // Re-index to plain lists so Inertia serialises arrays, not objects.
        return array_values(array_map(function ($group) {
            $group['modules'] = array_values($group['modules']);

            return $group;
        }, $tree));
    }
}
