<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

/**
 * SidebarMenu
 * ------------------------------------------------------------------
 * Builds the CashVero sidebar structure, shared globally to every
 * Inertia page via HandleInertiaRequests — NOT passed per-controller.
 * This means every migrated page automatically gets the full sidebar
 * without needing its own navUrls prop.
 *
 * Every route name and permission string here was verified against
 * the original Blade sidebar builder that lived in helpers.php
 * (getHeaderMenu — since removed) and App\Notification::formatForMenuItem() —
 * not guessed. Where the original groups things differently (e.g.
 * "Statements" and "Reports" were one combined section, "Safe
 * Accounts" is literally the Branches feature), this restructures
 * into the 12-section layout the project owner requested, while
 * keeping every underlying route/permission identical to what
 * already works today.
 *
 * ⚠️ Only `view.financial.institutions` points to a page actually
 * migrated to Vue/Inertia so far — everything else is a plain link to
 * its still-Blade page. As each page gets migrated, flip its `inertia`
 * flag to true here — that's the only change needed, no sidebar
 * restructuring.
 *
 * ⚠️ "Read Partners / Invoices / Contracts" (Odoo Integration) are NOT
 * navigable pages in the original app — they're modal triggers
 * (confirmed via the real `data-show-notification-modal` attributes
 * in the Blade version). These are marked `action` here instead of
 * `link`, so the Vue sidebar renders them as buttons that open a
 * confirm-and-sync modal rather than as navigation links.
 *
 * Icons are Lucide kebab-case names rendered by NavIcon.vue
 * (resources/js/Components/NavIcon.vue) — not emoji.
 */
class SidebarMenu
{
    public static function build(?Company $company, ?User $user): array
    {
        if (! $user) {
            return [];
        }

        if (! $company) {
            return [
                'home' => self::item(__('Home'), $user->isSuperAdmin(), route('home'), icon: 'home'),
            ];
        }

        $companyId = $company->id;
        $hasOdoo = $company->hasOdooIntegrationCredentials();

        return [
            'home' => self::item(__('Home'), $user->isSuperAdmin(), route('home'), icon: 'home'),

            // ✅ All 3 dashboard tabs migrated to Inertia/Vue (Dashboard/CashStatus,
            // Dashboard/LGLCStatus, Dashboard/Forecast) — flipped to inertia: true
            // per the "flip one flag, no sidebar restructuring" convention.
            'dashboard' => self::section(__('Dashboard'), 'layout-dashboard', [
                self::item(__('Cash Status'), $user->can('view cash status dashboard'), route('view.customer.invoice.dashboard.cash', ['company' => $companyId]), inertia: true, icon: 'banknote'),
                self::item(__('LG & LC Status'), $user->can('view lg & lc dashboard'), route('view.lglc.dashboard', ['company' => $companyId]), inertia: true, icon: 'scroll-text'),
                self::item(__('Cash Forecast'), $user->can('view cash Forecast dashboard'), route('view.customer.invoice.dashboard.forecast', ['company' => $companyId]), inertia: true, icon: 'sparkles'),
            ]),

            'statements' => self::section(__('Statements'), 'file-text', [
                self::item(__('Bank Statement'), $user->can('view bank statement report'), route('view.bank.statement', ['company' => $companyId]), inertia: true, icon: 'landmark'),
                self::item(__('Safe Statement'), $user->can('view safe statement report'), route('view.safe.statement', ['company' => $companyId]), inertia: true, icon: 'archive'),
                self::item(__('Factoring Statement'), $user->can('view bank statement report'), route('view.factoring.statement', ['company' => $companyId]), inertia: true, icon: 'receipt'),
                self::item(__('LG By Beneficiary Name'), $user->can('view lg by beneficiary name report'), route('view.lg.by.beneficiary.name.report', ['company' => $companyId]), inertia: true, icon: 'file-badge'),
                self::item(__('LG By Bank Name'), $user->can('view lg by bank name report'), route('view.lg.by.bank.name.report', ['company' => $companyId]), inertia: true, icon: 'building'),
                self::item(__('LG & LC Statement'), $user->can('view bank statement report'), route('view.lg.lc.bank.statement', ['company' => $companyId]), inertia: true, icon: 'files'),
                self::item(__('Cash Expense Statement'), $user->can('view cash expense report'), route('view.cash.expense.statement', ['company' => $companyId]), inertia: true, icon: 'credit-card'),
                self::item(__('Partner Statement'), $user->can('view partners statement report'), route('view.partners.statement', ['company' => $companyId]), inertia: true, icon: 'handshake'),
                self::item(__('Withdrawal Statement'), $user->can('view withdrawals settlement report'), route('view.withdrawals.settlement.report', ['company' => $companyId]), inertia: true, icon: 'arrow-down-circle'),
            ]),

            'reports' => self::section(__('Reports'), 'chart-line', [
                self::item(__('Company Cash Flow Report'), $user->can('view cash flow report'), route('view.cashflow.report', ['company' => $companyId]), icon: 'chart-line'),
                self::item(__('Contract Cash Flow Report'), $user->can('view contract cash flow report'), route('view.contract.cashflow.report', ['company' => $companyId]), icon: 'chart-column'),
                self::item(__('Consolidated Cash Flow Report'), $user->can('view cash flow report'), route('reports.consolidated-cash-flow.index', ['company' => $companyId]), icon: 'chart-pie'),
            ]),

            'financial-institutions' => self::section(__('Financial Institutions & Cash Account'), 'landmark', [
                self::item(__('Financial Institutions'), $user->can('view financial institutions'), route('view.financial.institutions', ['company' => $companyId]), inertia: true, icon: 'building-2'),
                // NEW (2026-07-25, project-owner requested): 5 read-only
                // roll-up pages — see FinancialInstitutionFacilitiesController's
                // docblock. Reuse the same 'view financial institutions'
                // permission since these are just alternate read-only
                // views over the same underlying data, not a new feature
                // area needing its own permission to seed.
                self::item(__('Bank Accounts'), $user->can('view financial institutions'), route('financial-institution-facilities.bank-accounts', ['company' => $companyId]), inertia: true, icon: 'landmark'),
                self::item(__('ODA & MTL Facilities'), $user->can('view financial institutions'), route('financial-institution-facilities.oda-mtl', ['company' => $companyId]), inertia: true, icon: 'trending-down'),
                self::item(__('LG & LC Facilities'), $user->can('view financial institutions'), route('financial-institution-facilities.lg-lc', ['company' => $companyId]), inertia: true, icon: 'scroll-text'),
                self::item(__('Leasing Facilities'), $user->can('view financial institutions'), route('financial-institution-facilities.leasing', ['company' => $companyId]), inertia: true, icon: 'truck'),
                self::item(__('Factoring Facilities'), $user->can('view financial institutions'), route('financial-institution-facilities.factoring', ['company' => $companyId]), inertia: true, icon: 'receipt'),
            ]),

            'customers' => self::section(__('Customers Section'), 'users', [
                self::item(__('Customers Balances'), $user->can('view customer balances'), route('view.balances', ['company' => $companyId, 'modelType' => 'CustomerInvoice']), inertia: true, icon: 'circle-dollar-sign'),
                self::item(__('Customers Contracts'), $user->can('view customers contracts'), route('contracts.index', ['company' => $companyId, 'type' => 'Customer']), icon: 'file-stack'),
                self::item(__('Customer Aging'), $user->can('view customer aging'), route('view.aging.analysis', ['company' => $companyId, 'modelType' => 'CustomerInvoice']), inertia: true, icon: 'hourglass'),
                self::item(__('Collection Effectiveness Index'), $user->can('view collections effectiveness index'), route('view.collections.effectiveness.index', ['company' => $companyId]), inertia: true, icon: 'target'),
                self::item(__('Upload New Customers Invoices Data'), $user->can(\uploadCustomerInvoiceData), route('view.uploading', ['company' => $companyId, 'model' => 'CustomerInvoice']), inertia: true, icon: 'upload'),
            ]),

            'suppliers' => self::section(__('Suppliers Section'), 'truck', [
                self::item(__('Suppliers Balances'), $user->can('view supplier balances'), route('view.balances', ['company' => $companyId, 'modelType' => 'SupplierInvoice']), inertia: true, icon: 'circle-dollar-sign'),
                self::item(__('Suppliers Contracts'), $user->can('view suppliers contracts'), route('contracts.index', ['company' => $companyId, 'type' => 'Supplier']), icon: 'file-stack'),
                self::item(__('Suppliers Aging'), $user->can('view supplier aging'), route('view.aging.analysis', ['company' => $companyId, 'modelType' => 'SupplierInvoice']), inertia: true, icon: 'hourglass'),
                self::item(__('Payment Effectiveness Index'), $user->can('view payments effectiveness index'), route('view.payments.effectiveness.index', ['company' => $companyId]), inertia: true, icon: 'target'),
                self::item(__('Upload New Suppliers Invoices Data'), $user->can(\uploadSupplierInvoiceData), route('view.uploading', ['company' => $companyId, 'model' => 'SupplierInvoice']), inertia: true, icon: 'upload'),
            ]),

            'treasury' => self::section(__('Treasury Transactions'), 'wallet', [
                self::item(__('Money Received'), $user->can('view money received'), route('view.money.receive', ['company' => $companyId]), inertia: true, icon: 'download'),
                self::item(__('Money Payment'), $user->can('view supplier payment'), route('view.money.payment', ['company' => $companyId]), inertia: true, icon: 'upload'),
                self::item(__('Factoring With Recourse'), $user->can('view supplier payment'), route('factoring.with-recourse.index', ['company' => $companyId]), icon: 'refresh-cw'),
                self::item(__('Factoring Without Recourse'), $user->can('view supplier payment'), route('factoring.without-recourse.index', ['company' => $companyId]), icon: 'refresh-cw'),
                self::item(__('LC Settlement Internal Transfer'), $user->can('view lc settlement internal transfer'), route('lc-settlement-internal-money-transfers.index', ['company' => $companyId]), icon: 'arrow-left-right'),
                self::item(__('Cash Expense'), $user->can('view cash expenses'), route('view.cash.expense', ['company' => $companyId]), icon: 'credit-card'),
                self::item(__('Internal Money Transfer'), $user->can('view internal money transfer'), route('internal-money-transfers.index', ['company' => $companyId]), icon: 'arrow-left-right'),
                self::item(__('Sell Or Buy Currency'), $user->can('view buy or sell currency'), route('buy-or-sell-currencies.index', ['company' => $companyId]), icon: 'coins'),
                self::item(__('Foreign Exchange Rate'), $user->can('view foreign exchange rate'), route('view.foreign.exchange.rate', ['company' => $companyId]), icon: 'currency'),
            ]),

            'lg-lc-issuance' => self::section(__('LGs And LC Issuance'), 'scroll-text', [
                self::item(__('Letter Of Guarantee (LG) Issuance'), $user->can('view letter of guarantee issuance'), route('view.letter.of.guarantee.issuance', ['company' => $companyId]), icon: 'scroll-text'),
                self::item(__('Letter Of Credit (LC) Issuance'), $user->can('view letter of credit issuance'), route('view.letter.of.credit.issuance', ['company' => $companyId]), icon: 'scroll-text'),
            ]),

            // Odoo Integration — entire section hidden unless the company
            // has Odoo integration configured. These 3 items are ACTIONS
            // (they open a confirm-and-sync modal), not navigable pages —
            // see the class docblock. `action` carries the route the modal
            // POSTs to.
            'odoo-integration' => self::section(__('Odoo Integration'), 'link', [
                self::action(__('Read Partners'), true, route('read-odoo-partners', ['company' => $companyId]), icon: 'refresh-cw'),
                self::action(__('Read Invoices'), true, route('read-odoo-invoices', ['company' => $companyId]), icon: 'refresh-cw'),
                self::action(__('Read Contracts'), true, route('read-odoo-contracts', ['company' => $companyId]), icon: 'refresh-cw'),
            ], show: $hasOdoo),

            'opening-balances' => self::section(__('Opening Balances'), 'vault', [
                self::item(__('Cash in Safe & Cheque Balance'), $user->can('update cash & cheques opening balances'), route('opening-balance.index', ['company' => $companyId]), inertia: true, icon: 'archive'),
                self::item(__('Customers Opening Balances'), $user->can('update cash & cheques opening balances'), route('customers-opening-balance.index', ['company' => $companyId]), inertia: true, icon: 'users'),
                self::item(__('Suppliers Opening Balance'), $user->can('update cash & cheques opening balances'), route('suppliers-opening-balance.index', ['company' => $companyId]), inertia: true, icon: 'truck'),
            ]),

            'general-settings' => self::section(__('General Settings'), 'settings', [
                // Moved here from "Financial Institutions & Cash Account"
                // (confirmed with project owner, 2026-07-25): a Safe Account
                // only has a name and a currency — no financial-institution
                // logic (no balance, no interest, no Odoo sync) — so it's a
                // pure general-setting master list, same as Business Sectors
                // or Sales Channels. Route/permission unchanged (still
                // BranchesController / 'view branches').
                self::item(__('Safe Accounts'), $user->can('view branches'), route('branches.index', ['company' => $companyId]), icon: 'archive'),
                self::item(__('Partners'), true, route('partners.index', ['company' => $companyId]), inertia: true, icon: 'handshake'),
                self::item(__('Subsidiary Companies'), $user->can('view subsidiary companies'), route('partners.index', ['company' => $companyId, 'type' => 'subsidiary-companies']), inertia: true, icon: 'building-2'),
                self::item(__('Cash Expenses'), $user->can('view cash expense categories'), route('cash.expense.category.index', ['company' => $companyId]), inertia: true, icon: 'credit-card'),
                self::item(__('Deductions'), $user->can('view deductions'), route('deductions.index', ['company' => $companyId]), inertia: true, icon: 'minus'),
                self::item(__('Business Sectors'), $user->can('view business sectors'), route('business.sectors.index', ['company' => $companyId]), inertia: true, icon: 'factory'),
                self::item(__('Business Units'), $user->can('view business units'), route('business.units.index', ['company' => $companyId]), inertia: true, icon: 'factory'),
                self::item(__('Sales Channels'), $user->can('view sales channels'), route('sales.channels.index', ['company' => $companyId]), inertia: true, icon: 'radio'),
                self::item(__('Sales Persons'), $user->can('view sales persons'), route('sales.persons.index', ['company' => $companyId]), inertia: true, icon: 'user-round'),
                self::item(__('Notification Settings'), $user->can('view notification settings'), route('notifications-settings.index', ['company' => $companyId]), inertia: true, icon: 'bell'),
                self::item(__('Other Odoo Integration Settings'), $hasOdoo, route('odoo-settings.index', ['company' => $companyId]), icon: 'link'),
            ]),
        ];
    }

    protected static function section(string $title, string $icon, array $items, ?bool $show = null): array
    {
        $anyVisible = collect($items)->contains(fn ($i) => $i['show']);

        return [
            'title' => $title,
            'icon' => $icon,
            'show' => $show ?? $anyVisible,
            'items' => $items,
        ];
    }

    protected static function item(string $title, bool $show, string $link, bool $inertia = false, string $icon = 'circle'): array
    {
        return [
            'type' => 'link',
            'title' => $title,
            'show' => $show,
            'link' => $link,
            'inertia' => $inertia,
            'icon' => $icon,
        ];
    }

    protected static function action(string $title, bool $show, string $actionUrl, string $icon = 'refresh-cw'): array
    {
        return [
            'type' => 'action',
            'title' => $title,
            'show' => $show,
            'action_url' => $actionUrl,
            'icon' => $icon,
        ];
    }
}
