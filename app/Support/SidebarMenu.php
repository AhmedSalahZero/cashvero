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
                'home' => self::item(__('Home'), $user->isSuperAdmin(), route('home'), icon: '🏠'),
            ];
        }

        $companyId = $company->id;
        $hasOdoo = $company->hasOdooIntegrationCredentials();

        return [
            'home' => self::item(__('Home'), $user->isSuperAdmin(), route('home'), icon: '🏠'),

            // ✅ All 3 dashboard tabs migrated to Inertia/Vue (Dashboard/CashStatus,
            // Dashboard/LGLCStatus, Dashboard/Forecast) — flipped to inertia: true
            // per the "flip one flag, no sidebar restructuring" convention.
            'dashboard' => self::section(__('Dashboard'), '📊', [
                self::item(__('Cash Status'), $user->can('view cash status dashboard'), route('view.customer.invoice.dashboard.cash', ['company' => $companyId]), inertia: true, icon: '💵'),
                self::item(__('LG & LC Status'), $user->can('view lg & lc dashboard'), route('view.lglc.dashboard', ['company' => $companyId]), inertia: true, icon: '📜'),
                self::item(__('Cash Forecast'), $user->can('view cash Forecast dashboard'), route('view.customer.invoice.dashboard.forecast', ['company' => $companyId]), inertia: true, icon: '🔮'),
            ]),

            'statements' => self::section(__('Statements'), '📄', [
                self::item(__('Bank Statement'), $user->can('view bank statement report'), route('view.bank.statement', ['company' => $companyId]), inertia: true, icon: '🏦'),
                self::item(__('Safe Statement'), $user->can('view safe statement report'), route('view.safe.statement', ['company' => $companyId]), inertia: true, icon: '🗄️'),
                self::item(__('Factoring Statement'), $user->can('view bank statement report'), route('view.factoring.statement', ['company' => $companyId]), inertia: true, icon: '🧾'),
                self::item(__('LG By Beneficiary Name'), $user->can('view lg by beneficiary name report'), route('view.lg.by.beneficiary.name.report', ['company' => $companyId]), inertia: true, icon: '📜'),
                self::item(__('LG By Bank Name'), $user->can('view lg by bank name report'), route('view.lg.by.bank.name.report', ['company' => $companyId]), inertia: true, icon: '📜'),
                self::item(__('LG & LC Statement'), $user->can('view bank statement report'), route('view.lg.lc.bank.statement', ['company' => $companyId]), inertia: true, icon: '📜'),
                self::item(__('Cash Expense Statement'), $user->can('view cash expense report'), route('view.cash.expense.statement', ['company' => $companyId]), inertia: true, icon: '💳'),
                self::item(__('Partner Statement'), $user->can('view partners statement report'), route('view.partners.statement', ['company' => $companyId]), inertia: true, icon: '🤝'),
                self::item(__('Withdrawal Statement'), $user->can('view withdrawals settlement report'), route('view.withdrawals.settlement.report', ['company' => $companyId]), inertia: true, icon: '💸'),
            ]),

            'reports' => self::section(__('Reports'), '📈', [
                self::item(__('Company Cash Flow Report'), $user->can('view cash flow report'), route('view.cashflow.report', ['company' => $companyId]), icon: '📈'),
                self::item(__('Contract Cash Flow Report'), $user->can('view contract cash flow report'), route('view.contract.cashflow.report', ['company' => $companyId]), icon: '📈'),
                self::item(__('Consolidated Cash Flow Report'), $user->can('view cash flow report'), route('reports.consolidated-cash-flow.index', ['company' => $companyId]), icon: '📊'),
            ]),

            'financial-institutions' => self::section(__('Financial Institutions & Cash Account'), '🏦', [
                self::item(__('Financial Institutions'), $user->can('view financial institutions'), route('view.financial.institutions', ['company' => $companyId]), inertia: true, icon: '🏛️'),
                self::item(__('Safe Accounts'), $user->can('view branches'), route('branches.index', ['company' => $companyId]), icon: '🗄️'),
            ]),

            'customers' => self::section(__('Customers Section'), '👥', [
                self::item(__('Customers Balances'), $user->can('view customer balances'), route('view.balances', ['company' => $companyId, 'modelType' => 'CustomerInvoice']), inertia: true, icon: '💰'),
                self::item(__('Customers Contracts'), $user->can('view customers contracts'), route('contracts.index', ['company' => $companyId, 'type' => 'Customer']), icon: '📑'),
                self::item(__('Customer Aging'), $user->can('view customer aging'), route('view.aging.analysis', ['company' => $companyId, 'modelType' => 'CustomerInvoice']), inertia: true, icon: '⏳'),
                self::item(__('Collection Effectiveness Index'), $user->can('view collections effectiveness index'), route('view.collections.effectiveness.index', ['company' => $companyId]), inertia: true, icon: '🎯'),
                self::item(__('Upload New Customers Invoices Data'), $user->can(\uploadCustomerInvoiceData), route('view.uploading', ['company' => $companyId, 'model' => 'CustomerInvoice']), inertia: true, icon: '⬆️'),
            ]),

            'suppliers' => self::section(__('Suppliers Section'), '🚚', [
                self::item(__('Suppliers Balances'), $user->can('view supplier balances'), route('view.balances', ['company' => $companyId, 'modelType' => 'SupplierInvoice']), inertia: true, icon: '💰'),
                self::item(__('Suppliers Contracts'), $user->can('view suppliers contracts'), route('contracts.index', ['company' => $companyId, 'type' => 'Supplier']), icon: '📑'),
                self::item(__('Suppliers Aging'), $user->can('view supplier aging'), route('view.aging.analysis', ['company' => $companyId, 'modelType' => 'SupplierInvoice']), inertia: true, icon: '⏳'),
                self::item(__('Payment Effectiveness Index'), $user->can('view payments effectiveness index'), route('view.payments.effectiveness.index', ['company' => $companyId]), inertia: true, icon: '🎯'),
                self::item(__('Upload New Suppliers Invoices Data'), $user->can(\uploadSupplierInvoiceData), route('view.uploading', ['company' => $companyId, 'model' => 'SupplierInvoice']), inertia: true, icon: '⬆️'),
            ]),

            'treasury' => self::section(__('Treasury Transactions'), '💸', [
                self::item(__('Money Received'), $user->can('view money received'), route('view.money.receive', ['company' => $companyId]), inertia: true, icon: '📥'),
                self::item(__('Money Payment'), $user->can('view supplier payment'), route('view.money.payment', ['company' => $companyId]), inertia: true, icon: '📤'),
                self::item(__('Factoring With Recourse'), $user->can('view supplier payment'), route('factoring.with-recourse.index', ['company' => $companyId]), icon: '🔄'),
                self::item(__('Factoring Without Recourse'), $user->can('view supplier payment'), route('factoring.without-recourse.index', ['company' => $companyId]), icon: '🔄'),
                self::item(__('LC Settlement Internal Transfer'), $user->can('view lc settlement internal transfer'), route('lc-settlement-internal-money-transfers.index', ['company' => $companyId]), icon: '🔁'),
                self::item(__('Cash Expense'), $user->can('view cash expenses'), route('view.cash.expense', ['company' => $companyId]), icon: '💳'),
                self::item(__('Internal Money Transfer'), $user->can('view internal money transfer'), route('internal-money-transfers.index', ['company' => $companyId]), icon: '🔁'),
                self::item(__('Sell Or Buy Currency'), $user->can('view buy or sell currency'), route('buy-or-sell-currencies.index', ['company' => $companyId]), icon: '💱'),
                self::item(__('Foreign Exchange Rate'), $user->can('view foreign exchange rate'), route('view.foreign.exchange.rate', ['company' => $companyId]), icon: '💱'),
            ]),

            'lg-lc-issuance' => self::section(__('LGs And LC Issuance'), '📜', [
                self::item(__('Letter Of Guarantee (LG) Issuance'), $user->can('view letter of guarantee issuance'), route('view.letter.of.guarantee.issuance', ['company' => $companyId]), icon: '📜'),
                self::item(__('Letter Of Credit (LC) Issuance'), $user->can('view letter of credit issuance'), route('view.letter.of.credit.issuance', ['company' => $companyId]), icon: '📜'),
            ]),

            // Odoo Integration — entire section hidden unless the company
            // has Odoo integration configured. These 3 items are ACTIONS
            // (they open a confirm-and-sync modal), not navigable pages —
            // see the class docblock. `action` carries the route the modal
            // POSTs to.
            'odoo-integration' => self::section(__('Odoo Integration'), '🔗', [
                self::action(__('Read Partners'), true, route('read-odoo-partners', ['company' => $companyId]), icon: '🔄'),
                self::action(__('Read Invoices'), true, route('read-odoo-invoices', ['company' => $companyId]), icon: '🔄'),
                self::action(__('Read Contracts'), true, route('read-odoo-contracts', ['company' => $companyId]), icon: '🔄'),
            ], show: $hasOdoo),

            'opening-balances' => self::section(__('Opening Balances'), '💰', [
                self::item(__('Cash in Safe & Cheque Balance'), $user->can('update cash & cheques opening balances'), route('opening-balance.index', ['company' => $companyId]), inertia: true, icon: '🗄️'),
                self::item(__('Customers Opening Balances'), $user->can('update cash & cheques opening balances'), route('customers-opening-balance.index', ['company' => $companyId]), inertia: true, icon: '👥'),
                self::item(__('Suppliers Opening Balance'), $user->can('update cash & cheques opening balances'), route('suppliers-opening-balance.index', ['company' => $companyId]), inertia: true, icon: '🚚'),
            ]),

            'general-settings' => self::section(__('General Settings'), '⚙️', [
                self::item(__('Partners'), true, route('partners.index', ['company' => $companyId]), inertia: true, icon: '🤝'),
                self::item(__('Subsidiary Companies'), $user->can('view subsidiary companies'), route('partners.index', ['company' => $companyId, 'type' => 'subsidiary-companies']), inertia: true, icon: '🏢'),
                self::item(__('Cash Expenses'), $user->can('view cash expense categories'), route('cash.expense.category.index', ['company' => $companyId]), inertia: true, icon: '💳'),
                self::item(__('Deductions'), $user->can('view deductions'), route('deductions.index', ['company' => $companyId]), inertia: true, icon: '➖'),
                self::item(__('Business Sectors'), $user->can('view business sectors'), route('business.sectors.index', ['company' => $companyId]), inertia: true, icon: '🏭'),
                self::item(__('Business Units'), $user->can('view business units'), route('business.units.index', ['company' => $companyId]), inertia: true, icon: '🏭'),
                self::item(__('Sales Channels'), $user->can('view sales channels'), route('sales.channels.index', ['company' => $companyId]), inertia: true, icon: '📡'),
                self::item(__('Sales Persons'), $user->can('view sales persons'), route('sales.persons.index', ['company' => $companyId]), inertia: true, icon: '🧑‍💼'),
                self::item(__('Notification Settings'), $user->can('view notification settings'), route('notifications-settings.index', ['company' => $companyId]), inertia: true, icon: '🔔'),
                self::item(__('Other Odoo Integration Settings'), $hasOdoo, route('odoo-settings.index', ['company' => $companyId]), icon: '🔗'),
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

    protected static function item(string $title, bool $show, string $link, bool $inertia = false, string $icon = '▪️'): array
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

    protected static function action(string $title, bool $show, string $actionUrl, string $icon = '⟳'): array
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
