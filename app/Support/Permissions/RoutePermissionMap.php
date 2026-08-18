<?php

namespace App\Support\Permissions;

/**
 * RoutePermissionMap
 * ==================================================================
 * Maps every named route to the permission key(s) required to reach it.
 *
 * This is what makes backend enforcement possible without editing 94
 * controllers: EnforcePermission reads this map on every request and
 * aborts 403 before the controller action ever runs. One place to look,
 * one place to change.
 *
 * ── Rules ─────────────────────────────────────────────────────────
 * • A value may be a single key or an array. An ARRAY MEANS "ANY OF" —
 *   the user needs at least one of them (used where one screen serves
 *   two modules, e.g. the shared Aging page).
 * • Routes in PUBLIC_ROUTES need authentication only, no permission.
 * • Unlisted routes are allowed through and logged as `unmapped` (the
 *   fail-open policy chosen for this rollout). RouteCoverageTest fails
 *   the build if anything is unmapped, so this can't drift silently.
 *
 * ── Adding a route ────────────────────────────────────────────────
 * Add `'route.name' => 'module.action'` below. Nothing else.
 */
class RoutePermissionMap
{
    /**
     * Routes that require authentication but no specific permission:
     * auth flows, the user's own profile, framework/dev tooling, and
     * generic lookups that expose nothing on their own.
     */
    private const PUBLIC_ROUTES = [
        // ── Authentication ──
        'login', 'logout', 'register',
        'password.request', 'password.email', 'password.reset',
        'password.update', 'password.confirm',

        // ── The user's own profile & session ──
        'profile.edit', 'profile.update', 'theme.toggle',

        /**
         * ── Landing / routing helpers ──
         *
         * `home` is a dispatcher, not a data page: 0 companies → logout,
         * 1 → redirect to that company's dashboard, 2+ → a picker listing
         * only companies the user is already attached to. It exposes
         * nothing on its own, and everything it forwards to is gated
         * individually.
         *
         * It must stay reachable. Requiring `home.view` turned it into a
         * dead end for any account whose permissions have not been
         * configured yet — which, with user-based permissions, is every
         * newly created user until an admin sets them up. They landed on
         * a bare 403 at the front door with nowhere to go and no
         * explanation. HomeController now renders a "no access
         * configured" state for exactly that case.
         */
        'home',
        'home.redirect',

        // ── Notifications (already filtered per-permission inside
        //    App\Notification::formatForMenuItem) ──
        'view.notifications', 'notifications.detail', 'mark.notifications.as.read',

        // ── Framework, debug & dev tooling ──
        'debugbar.assets.css', 'debugbar.assets.js', 'debugbar.openhandler',
        'debugbar.clockwork', 'debugbar.cache.delete', 'debugbar.queries.explain',
        'ignition.healthCheck', 'ignition.executeSolution', 'ignition.updateConfig',
        'livewire.preview-file', 'livewire.upload-file', 'default-livewire.update',

        /**
         * Per-record history. Not mappable here: the permission depends
         * on the {subject} model in the URL, so RecordActivityController
         * resolves it from ActivityRegistry and enforces
         * "<module>.view" itself.
         */
        'record.activity',

        // ── Misc UI helpers with no data exposure ──
        'remove.company.image',
    ];

    /**
     * @var array<string, string|string[]>
     */
    private const MAP = [

        /* ───────────────────────── Dashboards ───────────────────── */
        'view.customer.invoice.dashboard.cash' => 'dashboard_cash.view',
        'view.customer.invoice.dashboard.forecast' => 'dashboard_forecast.view',
        'view.lglc.dashboard' => 'dashboard_lg_lc.view',
        'refresh.chart.limits.data' => 'dashboard_cash.view',
        'view.invoice.report' => 'invoice_report.view',
        'view.invoice.statement.report' => 'invoice_report.view',
        'export.invoice.report' => 'invoice_report.export',
        'export.invoice.statement.report' => 'invoice_report.export',

        /* ────────────────────── Money Received ──────────────────── */
        'view.money.receive' => 'money_received.view',
        'view.money.receive.json' => 'money_received.view',
        'create.money.receive' => 'money_received.create',
        'store.money.receive' => 'money_received.create',
        'edit.money.receive' => 'money_received.update',
        'update.money.receive' => 'money_received.update',
        'delete.money.receive' => 'money_received.delete',
        'cheque.apply.collection' => 'money_received.change_cheque_status',
        'cheque.send.to.collection' => 'money_received.change_cheque_status',
        'cheque.send.to.safe' => 'money_received.change_cheque_status',
        'cheque.send.to.rejected.safe' => 'money_received.change_cheque_status',
        'cheque.send.to.under.collection' => 'money_received.change_cheque_status',
        'resend.with.odoo' => 'odoo_integration.sync',
        'get.contracts.for.customer' => 'money_received.view',
        'get.contracts.for.customer.with.start.and.end.date' => 'money_received.view',
        'get.customers.of.opening-balance' => 'money_received.view',
        'get.account.amount.based.on.account.id' => 'money_received.view',
        'update.balance.and.net.balance.based.on.account.id.ajax' => 'money_received.view',
        'update.balance.and.net.balance.based.on.account.number' => 'money_received.view',

        /* ─────────────────────── Money Payment ──────────────────── */
        'view.money.payment' => 'money_payment.view',
        'create.money.payment' => 'money_payment.create',
        'store.money.payment' => 'money_payment.create',
        'edit.money.payment' => 'money_payment.update',
        'update.money.payment' => 'money_payment.update',
        'delete.money.payment' => 'money_payment.delete',
        'payable.cheque.mark.as.paid' => 'money_payment.mark_as_paid',
        'outgoing.transfer.mark.as.paid' => 'money_payment.mark_as_paid',
        'update.opening.payable.cheque' => 'money_payment.update',
        'get.contracts.for.supplier' => 'money_payment.view',
        'get.suppliers.of.opening-balance' => 'money_payment.view',
        'get.current.end.balance.of.cash.in.safe.statement' => 'money_payment.view',

        /* ──────────────────────── Cash Expense ──────────────────── */
        'view.cash.expense' => 'cash_expense.view',
        'create.cash.expense' => 'cash_expense.create',
        'store.cash.expense' => 'cash_expense.create',
        'edit.cash.expense' => 'cash_expense.update',
        'update.cash.expense' => 'cash_expense.update',
        'delete.cash.expense' => 'cash_expense.delete',
        'cash.expense.payable.cheque.mark.as.paid' => 'cash_expense.mark_as_paid',
        'cash.expense.outgoing.transfer.mark.as.paid' => 'cash_expense.mark_as_paid',

        /* ───────────────── Internal / LC settlement transfers ───── */
        'internal-money-transfers.index' => 'internal_money_transfer.view',
        'internal-money-transfers.create' => 'internal_money_transfer.create',
        'internal-money-transfers.store' => 'internal_money_transfer.create',
        'internal-money-transfers.edit' => 'internal_money_transfer.update',
        'internal-money-transfers.update' => 'internal_money_transfer.update',
        'internal-money-transfers.destroy' => 'internal_money_transfer.delete',
        'lc-settlement-internal-money-transfers.index' => 'lc_settlement_transfer.view',
        'lc-settlement-internal-money-transfers.create' => 'lc_settlement_transfer.create',
        'lc-settlement-internal-money-transfers.store' => 'lc_settlement_transfer.create',
        'lc-settlement-internal-money-transfers.edit' => 'lc_settlement_transfer.update',
        'lc-settlement-internal-money-transfers.update' => 'lc_settlement_transfer.update',
        'lc-settlement-internal-money-transfers.destroy' => 'lc_settlement_transfer.delete',
        'lc-settlement-internal-money-transfers.settle-data' => 'lc_settlement_transfer.view',
        'lc-settlement-internal-money-transfers.settle' => 'lc_settlement_transfer.create',
        'lc-settlement-internal-money-transfers.reset' => 'lc_settlement_transfer.delete',

        /* ──────────────── Currency & exchange rates ─────────────── */
        'buy-or-sell-currencies.index' => 'buy_or_sell_currency.view',
        'buy-or-sell-currencies.create' => 'buy_or_sell_currency.create',
        'buy-or-sell-currencies.store' => 'buy_or_sell_currency.create',
        'buy-or-sell-currencies.edit' => 'buy_or_sell_currency.update',
        'buy-or-sell-currencies.update' => 'buy_or_sell_currency.update',
        'buy-or-sell-currencies.destroy' => 'buy_or_sell_currency.delete',
        'view.foreign.exchange.rate' => 'foreign_exchange_rate.view',
        'store.foreign.exchange.rate' => 'foreign_exchange_rate.create',
        'edit.foreign.exchange.rate' => 'foreign_exchange_rate.update',
        'update.foreign.exchange.rate' => 'foreign_exchange_rate.update',
        'delete.foreign.exchange.rate' => 'foreign_exchange_rate.delete',
        'get.exchange.rate.for.date.and.currencies' => 'foreign_exchange_rate.view',

        /* ───────────────────────── Factoring ────────────────────── */
        'factoring.with-recourse.index' => 'factoring_with_recourse.view',
        'factoring.with-recourse.create' => 'factoring_with_recourse.create',
        'factoring.with-recourse.store' => 'factoring_with_recourse.create',
        'factoring.with-recourse.edit' => 'factoring_with_recourse.update',
        'factoring.with-recourse.update' => 'factoring_with_recourse.update',
        'factoring.with-recourse.destroy' => 'factoring_with_recourse.delete',
        'factoring.with-recourse.calculate' => 'factoring_with_recourse.view',
        'factoring.with-recourse.mark-collected' => 'factoring_with_recourse.approve',
        'factoring.with-recourse.revert-collected' => 'factoring_with_recourse.approve',
        'factoring.with-recourse.mark-rejected' => 'factoring_with_recourse.reject',
        'factoring.with-recourse.revert-rejected' => 'factoring_with_recourse.reject',
        'factoring.without-recourse.index' => 'factoring_without_recourse.view',
        'factoring.without-recourse.create' => 'factoring_without_recourse.create',
        'factoring.without-recourse.store' => 'factoring_without_recourse.create',
        'factoring.without-recourse.edit' => 'factoring_without_recourse.update',
        'factoring.without-recourse.update' => 'factoring_without_recourse.update',
        'factoring.without-recourse.destroy' => 'factoring_without_recourse.delete',
        'factoring.without-recourse.calculate' => 'factoring_without_recourse.view',
        'factoring.without-recourse.mark-as-settled' => 'factoring_without_recourse.settle',
        'factoring.without-recourse.revert-settlement' => 'factoring_without_recourse.settle',
        'factoring.without-recourse.mark-difference-received' => 'factoring_without_recourse.settle',
        'factoring.without-recourse.revert-difference-received' => 'factoring_without_recourse.settle',
        'factoring.contracts.index' => 'factoring_contract.view',
        'factoring.contracts.create' => 'factoring_contract.create',
        'factoring.contracts.store' => 'factoring_contract.create',
        'factoring.contracts.edit' => 'factoring_contract.update',
        'factoring.contracts.update' => 'factoring_contract.update',
        'factoring.contracts.destroy' => 'factoring_contract.delete',
        'factoring.contracts.renew' => 'factoring_contract.renew',
        'factoring.contracts.delete-renewal' => 'factoring_contract.renew',
        'factoring.companies.store' => 'factoring_company.create',
        'factoring.companies.update' => 'factoring_company.update',
        'factoring.companies.destroy' => 'factoring_company.delete',

        /* ───────────── Balances / aging / effectiveness ─────────── */
        'view.balances' => ['customer_balance.view', 'supplier_balance.view'],
        'show.total.net.balance.in' => ['customer_balance.view', 'supplier_balance.view'],
        'view.aging.analysis' => ['customer_aging.view', 'supplier_aging.view'],
        'result.aging.analysis' => ['customer_aging.view', 'supplier_aging.view'],
        'get.customers.or.suppliers.from.business.units.currencies' => ['customer_aging.view', 'supplier_aging.view'],
        'view.collections.effectiveness.index' => 'collection_effectiveness.view',
        'result.collections.effectiveness.index' => 'collection_effectiveness.view',
        'view.payments.effectiveness.index' => 'payment_effectiveness.view',
        'result.payments.effectiveness.index' => 'payment_effectiveness.view',
        'adjust.due.dates' => 'adjusted_due_date.view',
        'store.adjust.due.dates' => 'adjusted_due_date.create',
        'edit.adjust.due.dates' => 'adjusted_due_date.update',
        'update.adjust.due.dates' => 'adjusted_due_date.update',
        'delete.adjust.due.dates' => 'adjusted_due_date.delete',
        'update.invoice.deductions' => ['customer_balance.update', 'supplier_balance.update'],
        'get.supplier.invoices' => 'supplier_balance.view',

        /* ───────────────────────── Contracts ────────────────────── */
        'contracts.index' => ['customer_contract.view', 'supplier_contract.view'],
        'contracts.create' => ['customer_contract.create', 'supplier_contract.create'],
        'contracts.store' => ['customer_contract.create', 'supplier_contract.create'],
        'contracts.edit' => ['customer_contract.update', 'supplier_contract.update'],
        'contracts.update' => ['customer_contract.update', 'supplier_contract.update'],
        'contracts.destroy' => ['customer_contract.delete', 'supplier_contract.delete'],
        'contract.mark.as.finished' => ['customer_contract.approve', 'supplier_contract.approve'],
        'contract.mark.as.running.and.against' => ['customer_contract.approve', 'supplier_contract.approve'],
        'store.po.allocations' => ['customer_contract.update', 'supplier_contract.update'],
        'generate.unique.rondom.contract.code' => ['customer_contract.create', 'supplier_contract.create'],
        'get.contracts.for.customer.or.supplier' => ['customer_contract.view', 'supplier_contract.view'],
        'update.contracts.based.on.customer' => ['customer_contract.view', 'supplier_contract.view'],
        'update.purchase.orders.based.on.contract' => ['customer_contract.view', 'supplier_contract.view'],
        'update.sales.orders.based.on.contract' => ['customer_contract.view', 'supplier_contract.view'],
        'get.po.or.so.from.contract' => ['customer_contract.view', 'supplier_contract.view'],
        'get.projects.for.customer.or.supplier' => ['customer_contract.view', 'supplier_contract.view'],
        'view.contracts.down.payments' => 'down_payment_contract.view',
        'view.down.payment.settlement' => 'down_payment_contract.view',
        'store.down.payment.settlement' => 'down_payment_contract.settle',
        'view.contract.loan.schedule.settlements' => 'contract_loan_schedule.view',
        'store.contract.loan.schedule.settlements' => 'contract_loan_schedule.create',
        'edit.contract.loan.schedule.settlements' => 'contract_loan_schedule.update',
        'update.contract.loan.schedule.settlements' => 'contract_loan_schedule.update',
        'delete.contract.loan.schedule.settlements' => 'contract_loan_schedule.delete',
        'contract.loan.schedule.account.numbers' => 'contract_loan_schedule.view',

        /* ─────────────── Financial institutions & accounts ──────── */
        'view.financial.institutions' => 'financial_institution.view',
        'create.financial.institutions' => 'financial_institution.create',
        'store.financial.institutions' => 'financial_institution.create',
        'edit.financial.institutions' => 'financial_institution.update',
        'update.financial.institutions' => 'financial_institution.update',
        'delete.financial.institutions' => 'financial_institution.delete',
        'get.interest.rate.for.financial.institution.id' => 'financial_institution.view',
        'update.lc.issuance.based.on.financial.institution' => 'financial_institution.view',
        'view.all.bank.accounts' => 'bank_account.view',
        'financial.institution.add.account' => 'bank_account.create',
        'financial.institution.store.account' => 'bank_account.create',
        'edit.financial.institutions.account' => 'bank_account.update',
        'update.financial.institutions.account' => 'bank_account.update',
        'delete.financial.institutions.account' => 'bank_account.delete',
        'lock.or.unlock.financial.institutions.account' => 'bank_account.lock',
        'lock.or.unlock.bank.account' => 'bank_account.lock',
        'update.current.account.based.on.currency' => 'bank_account.view',
        'financial-institution-facilities.bank-accounts' => 'facility_overview.view',
        'financial-institution-facilities.oda-mtl' => 'facility_overview.view',
        'financial-institution-facilities.lg-lc' => 'facility_overview.view',
        'financial-institution-facilities.leasing' => 'facility_overview.view',
        'financial-institution-facilities.factoring' => 'facility_overview.view',
        'leasing.companies.store' => 'leasing_company.create',
        'leasing.companies.update' => 'leasing_company.update',
        'leasing.companies.destroy' => 'leasing_company.delete',
        'leasing.contracts.index' => 'leasing_contract.view',
        'leasing.contracts.create' => 'leasing_contract.create',
        'leasing.contracts.store' => 'leasing_contract.create',
        'leasing.contracts.edit' => 'leasing_contract.update',
        'leasing.contracts.update' => 'leasing_contract.update',
        'leasing.contracts.destroy' => 'leasing_contract.delete',
        'leasing.contracts.schedule.destroy' => 'leasing_contract.manage_schedule',

        /* ───────────────────────── Deposits ─────────────────────── */
        'view.certificates.of.deposit' => 'certificate_of_deposit.view',
        'create.certificates.of.deposit' => 'certificate_of_deposit.create',
        'store.certificates.of.deposit' => 'certificate_of_deposit.create',
        'edit.certificates.of.deposit' => 'certificate_of_deposit.update',
        'update.certificates.of.deposit' => 'certificate_of_deposit.update',
        'delete.certificates.of.deposit' => 'certificate_of_deposit.delete',
        'apply.deposit.to.certificate.of.deposit' => 'certificate_of_deposit.settle',
        'reverse.deposit.to.certificate.of.deposit' => 'certificate_of_deposit.settle',
        'apply.break.to.certificate.of.deposit' => 'certificate_of_deposit.settle',
        'reverse.broken.to.certificate.of.deposit' => 'certificate_of_deposit.settle',
        'apply.period.interest.to.certificates.of.deposit' => 'certificate_of_deposit.settle',
        'delete.period.interest.to.certificates.of.deposit' => 'certificate_of_deposit.settle',
        'view.period.interest.to.certificates.of.deposit' => 'certificate_of_deposit.view',
        'view.time.of.deposit' => 'time_of_deposit.view',
        'create.time.of.deposit' => 'time_of_deposit.create',
        'store.time.of.deposit' => 'time_of_deposit.create',
        'edit.time.of.deposit' => 'time_of_deposit.update',
        'update.time.of.deposit' => 'time_of_deposit.update',
        'delete.time.of.deposit' => 'time_of_deposit.delete',
        'apply.deposit.to.time.of.deposit' => 'time_of_deposit.settle',
        'reverse.deposit.to.time.of.deposit' => 'time_of_deposit.settle',
        'apply.break.to.time.of.deposit' => 'time_of_deposit.settle',
        'reverse.broken.to.time.of.deposit' => 'time_of_deposit.settle',
        'apply.period.interest.to.time.of.deposit' => 'time_of_deposit.settle',
        'delete.period.interest.to.time.of.deposit' => 'time_of_deposit.settle',
        'view.period.interest.to.time.of.deposit' => 'time_of_deposit.view',
        'time.of.deposit.renewal.date' => 'time_of_deposit.renew',
        'store.time.of.deposit.renewal.date' => 'time_of_deposit.renew',
        'edit.time.of.deposit.renewal.date' => 'time_of_deposit.renew',
        'update.time.of.deposit.renewal.date' => 'time_of_deposit.renew',
        'delete.time.of.deposit.renewal.date' => 'time_of_deposit.renew',

        /* ─────────────────── Loans & overdrafts ─────────────────── */
        'loans.index' => 'medium_term_loan.view',
        'loans.create' => 'medium_term_loan.create',
        'loans.store' => 'medium_term_loan.create',
        'loans.edit' => 'medium_term_loan.update',
        'loans.update' => 'medium_term_loan.update',
        'loans.destroy' => 'medium_term_loan.delete',
        'loans.statement' => 'medium_term_loan.view',
        'loans.schedule.destroy' => 'medium_term_loan.manage_schedule',
        'refresh.medium.term.loan.report' => 'medium_term_loan.view',
        'get.medium.term.loan.for.financial.institution' => 'medium_term_loan.view',
        'view.loan.schedule.settlements' => 'medium_term_loan.view',
        'store.loan.schedule.settlements' => 'medium_term_loan.manage_schedule',
        'edit.loan.schedule.settlements' => 'medium_term_loan.manage_schedule',
        'update.loan.schedule.settlements' => 'medium_term_loan.manage_schedule',
        'delete.loan.schedule.settlements' => 'medium_term_loan.manage_schedule',

        'view.fully.secured.overdraft' => 'fully_secured_overdraft.view',
        'create.fully.secured.overdraft' => 'fully_secured_overdraft.create',
        'store.fully.secured.overdraft' => 'fully_secured_overdraft.create',
        'edit.fully.secured.overdraft' => 'fully_secured_overdraft.update',
        'update.fully.secured.overdraft' => 'fully_secured_overdraft.update',
        'delete.fully.secured.overdraft' => 'fully_secured_overdraft.delete',
        'fully-secured-overdraft.renew' => 'fully_secured_overdraft.renew',
        'fully-secured-overdraft.delete-renewal' => 'fully_secured_overdraft.renew',
        'fully-secured-overdraft-apply.rates' => 'fully_secured_overdraft.manage_rates',
        'fully-secured-overdraft-edit-rates' => 'fully_secured_overdraft.manage_rates',
        'fully-secured-overdraft-delete-rate' => 'fully_secured_overdraft.manage_rates',

        'view.clean.overdraft' => 'clean_overdraft.view',
        'create.clean.overdraft' => 'clean_overdraft.create',
        'store.clean.overdraft' => 'clean_overdraft.create',
        'edit.clean.overdraft' => 'clean_overdraft.update',
        'update.clean.overdraft' => 'clean_overdraft.update',
        'delete.clean.overdraft' => 'clean_overdraft.delete',
        'clean-overdraft.renew' => 'clean_overdraft.renew',
        'clean-overdraft.delete-renewal' => 'clean_overdraft.renew',
        'clean-overdraft-apply.rates' => 'clean_overdraft.manage_rates',
        'clean-overdraft-edit-rates' => 'clean_overdraft.manage_rates',
        'clean-overdraft-delete-rate' => 'clean_overdraft.manage_rates',

        'view.overdraft.against.commercial.paper' => 'overdraft_commercial_paper.view',
        'create.overdraft.against.commercial.paper' => 'overdraft_commercial_paper.create',
        'store.overdraft.against.commercial.paper' => 'overdraft_commercial_paper.create',
        'edit.overdraft.against.commercial.paper' => 'overdraft_commercial_paper.update',
        'update.overdraft.against.commercial.paper' => 'overdraft_commercial_paper.update',
        'delete.overdraft.against.commercial.paper' => 'overdraft_commercial_paper.delete',
        'overdraft-against-commercial-paper.renew' => 'overdraft_commercial_paper.renew',
        'overdraft-against-commercial-paper.delete-renewal' => 'overdraft_commercial_paper.renew',
        'overdraft-against-commercial-paper-apply.rates' => 'overdraft_commercial_paper.manage_rates',
        'overdraft-against-commercial-paper-edit-rates' => 'overdraft_commercial_paper.manage_rates',
        'overdraft-against-commercial-paper-delete-rate' => 'overdraft_commercial_paper.manage_rates',

        'view.overdraft.against.assignment.of.contract' => 'overdraft_assignment_contract.view',
        'create.overdraft.against.assignment.of.contract' => 'overdraft_assignment_contract.create',
        'store.overdraft.against.assignment.of.contract' => 'overdraft_assignment_contract.create',
        'edit.overdraft.against.assignment.of.contract' => 'overdraft_assignment_contract.update',
        'update.overdraft.against.assignment.of.contract' => 'overdraft_assignment_contract.update',
        'delete.overdraft.against.assignment.of.contract' => 'overdraft_assignment_contract.delete',
        'overdraft-against-assignment-of-contract.renew' => 'overdraft_assignment_contract.renew',
        'overdraft-against-assignment-of-contract.delete-renewal' => 'overdraft_assignment_contract.renew',
        'overdraft-against-assignment-of-contract-apply.rates' => 'overdraft_assignment_contract.manage_rates',
        'overdraft-against-assignment-of-contract-edit-rates' => 'overdraft_assignment_contract.manage_rates',
        'overdraft-against-assignment-of-contract-delete-rate' => 'overdraft_assignment_contract.manage_rates',
        'apply.against.lending' => 'overdraft_assignment_contract.view',
        'lending.information.apply.for.against.assignment.of.contract' => 'overdraft_assignment_contract.update',
        'lending.information.edit.for.against.assignment.of.contract' => 'overdraft_assignment_contract.update',
        'lending.information.delete.for.against.assignment.of.contract' => 'overdraft_assignment_contract.update',

        /* ────────────────── Letters of Guarantee ────────────────── */
        'view.letter.of.guarantee.facility' => 'lg_facility.view',
        'create.letter.of.guarantee.facility' => 'lg_facility.create',
        'store.letter.of.guarantee.facility' => 'lg_facility.create',
        'edit.letter.of.guarantee.facility' => 'lg_facility.update',
        'update.letter.of.guarantee.facility' => 'lg_facility.update',
        'delete.letter.of.guarantee.facility' => 'lg_facility.delete',
        'letter-of-guarantee-facility.renew' => 'lg_facility.renew',
        'letter-of-guarantee-facility.delete-renewal' => 'lg_facility.renew',
        'get.lg.facility.based.on.financial.institution' => 'lg_facility.view',
        'update.letter.of.guarantee.outstanding.balance.and.limit' => 'lg_facility.view',

        'view.letter.of.guarantee.issuance' => 'lg_issuance.view',
        'create.letter.of.guarantee.issuance' => 'lg_issuance.create',
        'store.letter.of.guarantee.issuance' => 'lg_issuance.create',
        'edit.letter.of.guarantee.issuance' => 'lg_issuance.update',
        'update.letter.of.guarantee.issuance' => 'lg_issuance.update',
        'delete.letter.of.guarantee.issuance' => 'lg_issuance.delete',
        'cancel.letter.of.guarantee.issuance' => 'lg_issuance.cancel',
        'back.to.running.letter.of.guarantee.issuance' => 'lg_issuance.cancel',
        'letter.of.guarantee.issuance.tab.data' => 'lg_issuance.view',
        'get.bank.name.by.currency' => 'lg_issuance.view',
        'get.beneficiary.name.by.currency' => 'lg_issuance.view',
        'advanced.lg.payment.apply.amount.to.be.decreased' => 'lg_issuance.update',
        'advanced.lg.payment.edit.amount.to.be.decreased' => 'lg_issuance.update',
        'delete.lg.advanced.payment' => 'lg_issuance.update',
        'letter.of.issuance.renewal.date' => 'lg_issuance.renew',
        'store.letter.of.issuance.renewal.date' => 'lg_issuance.renew',
        'edit.letter.of.issuance.renewal.date' => 'lg_issuance.renew',
        'update.letter.of.issuance.renewal.date' => 'lg_issuance.renew',
        'delete.letter.of.issuance.renewal.date' => 'lg_issuance.renew',
        'import.letter.of.guarantee.issuance' => 'lg_issuance.import',
        'download.letter.of.guarantee.issuance.template' => 'lg_issuance.import',
        'status.letter.of.guarantee.issuance.import' => 'lg_issuance.import',
        'errors.letter.of.guarantee.issuance.import' => 'lg_issuance.import',

        /* ──────────────────── Letters of Credit ─────────────────── */
        'view.letter.of.credit.facility' => 'lc_facility.view',
        'create.letter.of.credit.facility' => 'lc_facility.create',
        'store.letter.of.credit.facility' => 'lc_facility.create',
        'edit.letter.of.credit.facility' => 'lc_facility.update',
        'update.letter.of.credit.facility' => 'lc_facility.update',
        'delete.letter.of.credit.facility' => 'lc_facility.delete',
        'letter-of-credit-facility.renew' => 'lc_facility.renew',
        'letter-of-credit-facility.delete-renewal' => 'lc_facility.renew',
        'get.lc.facility.based.on.financial.institution' => 'lc_facility.view',
        'update.letter.of.credit.outstanding.balance.and.limit' => 'lc_facility.view',

        'view.letter.of.credit.issuance' => 'lc_issuance.view',
        'create.letter.of.credit.issuance' => 'lc_issuance.create',
        'store.letter.of.credit.issuance' => 'lc_issuance.create',
        'edit.letter.of.credit.issuance' => 'lc_issuance.update',
        'update.letter.of.credit.issuance' => 'lc_issuance.update',
        'delete.letter.of.credit.issuance' => 'lc_issuance.delete',
        'make.letter.of.credit.issuance.as.paid' => 'lc_issuance.settle',
        'back.to.running.letter.of.credit.issuance' => 'lc_issuance.settle',
        'apply.lc.issuance.expense' => 'lc_issuance.update',
        'update.lc.issuance.expense' => 'lc_issuance.update',
        'delete.lc.issuance.expense' => 'lc_issuance.update',
        'get.remaining.balance.lc.issuance' => 'lc_issuance.view',
        'lc.get.account.numbers.for.account.type' => 'lc_issuance.view',

        /* ───────────────────────── Reports ──────────────────────── */
        'view.bank.statement' => 'report_bank_statement.view',
        'result.bank.statement' => 'report_bank_statement.view',
        'export.bank.statement' => 'report_bank_statement.export',
        'bank.statement.account.numbers' => 'report_bank_statement.view',
        'update.bank.statement.debit.or.credit' => 'report_bank_statement.update',
        'update.commission.fees' => 'report_bank_statement.update',
        'view.safe.statement' => 'report_safe_statement.view',
        'result.safe.statement' => 'report_safe_statement.view',
        'export.safe.statement' => 'report_safe_statement.export',
        'view.factoring.statement' => 'report_factoring_statement.view',
        'result.factoring.statement' => 'report_factoring_statement.view',
        'export.factoring.statement' => 'report_factoring_statement.export',
        'factoring.statement.contracts' => 'report_factoring_statement.view',
        'factoring.statement.currencies' => 'report_factoring_statement.view',
        'view.factoring.charges.statement' => 'report_factoring_charges.view',
        'result.factoring.charges.statement' => 'report_factoring_charges.view',
        'export.factoring.charges.statement' => 'report_factoring_charges.export',
        'factoring.charges.statement.contracts' => 'report_factoring_charges.view',
        'factoring.charges.statement.currencies' => 'report_factoring_charges.view',
        'view.lg.lc.bank.statement' => 'report_lg_lc_statement.view',
        'result.lg.lc.bank.statement' => 'report_lg_lc_statement.view',
        'export.lg.lc.bank.statement' => 'report_lg_lc_statement.export',
        'get.lc.or.lg.types' => 'report_lg_lc_statement.view',
        'view.lg.by.beneficiary.name.report' => 'report_lg_by_beneficiary.view',
        'result.lg.by.beneficiary.name.report' => 'report_lg_by_beneficiary.view',
        'export.lg.by.beneficiary.name.report' => 'report_lg_by_beneficiary.export',
        'view.lg.by.bank.name.report' => 'report_lg_by_bank.view',
        'result.lg.by.bank.name.report' => 'report_lg_by_bank.view',
        'export.lg.by.bank.name.report' => 'report_lg_by_bank.export',
        'view.cash.expense.statement' => 'report_cash_expense_statement.view',
        'result.cash.expense.statement' => 'report_cash_expense_statement.view',
        'export.cash.expense.statement' => 'report_cash_expense_statement.export',
        'view.partners.statement' => 'report_partners_statement.view',
        'result.partners.statement' => 'report_partners_statement.view',
        'export.partners.statement' => 'report_partners_statement.export',
        'partners.statement.partners.by.type' => 'report_partners_statement.view',
        'view.taxes.insurance.statement' => 'report_taxes_insurance.view',
        'result.taxes.insurance.statement' => 'report_taxes_insurance.view',
        'view.withdrawals.settlement.report' => 'report_withdrawals_settlement.view',
        'result.withdrawals.settlement.report' => 'report_withdrawals_settlement.view',
        'export.withdrawals.settlement.report' => 'report_withdrawals_settlement.export',
        'refresh.withdrawal.report' => 'report_withdrawals_settlement.view',
        'withdrawals.settlement.banks' => 'report_withdrawals_settlement.view',

        /* ──────────────────────── Cash flow ─────────────────────── */
        'view.cashflow.report' => 'cash_flow_report.view',
        'result.cashflow.report' => 'cash_flow_report.view',
        'export.cashflow.report' => 'cash_flow_report.export',
        'delete.cashflow.report' => 'cash_flow_report.delete',
        'save.projection' => 'cash_flow_report.create',
        'adjust.customer.dues.invoices' => 'cash_flow_report.update',
        'adjust.loan.past.dues.installments' => 'cash_flow_report.update',
        'view.contract.cashflow.report' => 'contract_cash_flow_report.view',
        'result.contract.cashflow.report' => 'contract_cash_flow_report.view',
        'reports.consolidated-cash-flow.index' => 'consolidated_cash_flow_report.view',
        'reports.consolidated-cash-flow.result' => 'consolidated_cash_flow_report.view',
        'reports.consolidated-cash-flow.export' => 'consolidated_cash_flow_report.export',

        /* ─────────────────── Opening balances ───────────────────── */
        'opening-balance.index' => 'opening_balance.view',
        'opening-balance.show' => 'opening_balance.view',
        'opening-balance.manage' => 'opening_balance.view',
        'opening-balance.create' => 'opening_balance.create',
        'opening-balance.store' => 'opening_balance.create',
        'opening-balance.edit' => 'opening_balance.update',
        'opening-balance.update' => 'opening_balance.update',
        'opening-balance.destroy' => 'opening_balance.delete',
        'customers-opening-balance.index' => 'customer_opening_balance.view',
        'customers-opening-balance.show' => 'customer_opening_balance.view',
        'customers-opening-balance.manage' => 'customer_opening_balance.view',
        'customers-opening-balance.create' => 'customer_opening_balance.create',
        'customers-opening-balance.store' => 'customer_opening_balance.create',
        'customers-opening-balance.edit' => 'customer_opening_balance.update',
        'customers-opening-balance.update' => 'customer_opening_balance.update',
        'customers-opening-balance.destroy' => 'customer_opening_balance.delete',
        'suppliers-opening-balance.index' => 'supplier_opening_balance.view',
        'suppliers-opening-balance.show' => 'supplier_opening_balance.view',
        'suppliers-opening-balance.manage' => 'supplier_opening_balance.view',
        'suppliers-opening-balance.create' => 'supplier_opening_balance.create',
        'suppliers-opening-balance.store' => 'supplier_opening_balance.create',
        'suppliers-opening-balance.edit' => 'supplier_opening_balance.update',
        'suppliers-opening-balance.update' => 'supplier_opening_balance.update',
        'suppliers-opening-balance.destroy' => 'supplier_opening_balance.delete',

        /* ───────────────── Partners & general settings ──────────── */
        // Partners/Index serves customers, suppliers, employees,
        // shareholders, subsidiaries and other partners on one route —
        // any of those view rights admits you; the controller narrows
        // the per-type rights it hands to the page.
        'partners.index' => [
            'customer.view', 'supplier.view', 'employee.view',
            'shareholder.view', 'other_partner.view', 'subsidiary_company.view',
        ],
        'partners.create' => [
            'customer.create', 'supplier.create', 'employee.create',
            'shareholder.create', 'other_partner.create', 'subsidiary_company.create',
        ],
        'partners.store' => [
            'customer.create', 'supplier.create', 'employee.create',
            'shareholder.create', 'other_partner.create', 'subsidiary_company.create',
        ],
        'partners.edit' => [
            'customer.update', 'supplier.update', 'employee.update',
            'shareholder.update', 'other_partner.update', 'subsidiary_company.update',
        ],
        'partners.update' => [
            'customer.update', 'supplier.update', 'employee.update',
            'shareholder.update', 'other_partner.update', 'subsidiary_company.update',
        ],
        'partners.destroy' => [
            'customer.delete', 'supplier.delete', 'employee.delete',
            'shareholder.delete', 'other_partner.delete', 'subsidiary_company.delete',
        ],
        'add.new.partner' => ['customer.create', 'supplier.create'],
        'add.new.partner.type' => ['customer.create', 'supplier.create'],

        'branches.index' => 'branch.view',
        'branches.create' => 'branch.create',
        'branches.store' => 'branch.create',
        'branches.edit' => 'branch.update',
        'branches.update' => 'branch.update',
        'branches.destroy' => 'branch.delete',
        'get.branch.based.on.currency' => 'branch.view',

        'cash.expense.category.index' => 'cash_expense_category.view',
        'cash.expense.category.create' => 'cash_expense_category.create',
        'cash.expense.category.store' => 'cash_expense_category.create',
        'cash.expense.category.edit' => 'cash_expense_category.update',
        'cash.expense.category.update' => 'cash_expense_category.update',
        'cash.expense.category.destroy' => 'cash_expense_category.delete',
        'update.expense.category.name.based.on.category' => 'cash_expense_category.view',

        'deductions.index' => 'deduction.view',
        'deductions.store' => 'deduction.create',
        'deductions.update' => 'deduction.update',
        'deductions.destroy' => 'deduction.delete',

        'business.sectors.index' => 'business_sector.view',
        'business.sectors.store' => 'business_sector.create',
        'business.sectors.update' => 'business_sector.update',
        'business.sectors.destroy' => 'business_sector.delete',
        'business.units.index' => 'business_unit.view',
        'business.units.store' => 'business_unit.create',
        'business.units.update' => 'business_unit.update',
        'business.units.destroy' => 'business_unit.delete',
        'sales.channels.index' => 'sales_channel.view',
        'sales.channels.store' => 'sales_channel.create',
        'sales.channels.update' => 'sales_channel.update',
        'sales.channels.destroy' => 'sales_channel.delete',
        'sales.persons.index' => 'sales_person.view',
        'sales.persons.store' => 'sales_person.create',
        'sales.persons.update' => 'sales_person.update',
        'sales.persons.destroy' => 'sales_person.delete',

        'notifications-settings.index' => 'notification_setting.view',
        'notifications-settings.show' => 'notification_setting.view',
        'notifications-settings.create' => 'notification_setting.create',
        'notifications-settings.store' => 'notification_setting.create',
        'notifications-settings.edit' => 'notification_setting.update',
        'notifications-settings.update' => 'notification_setting.update',
        'notifications-settings.destroy' => 'notification_setting.delete',

        /* ─────────────── Data upload / sales gathering ──────────── */
        // These screens are driven by a {model} segment; the controller
        // resolves the exact per-model permission via
        // PermissionRegistry + getUploadPermissionName(). The map here
        // admits anyone holding an upload right for ANY dataset, then
        // SalesGatheringController narrows it per model.
        'view.uploading' => ['customer_invoice_data.view', 'supplier_invoice_data.view', 'loan_schedule_data.view'],
        'salesGathering.index' => ['customer_invoice_data.view', 'supplier_invoice_data.view', 'loan_schedule_data.view'],
        'salesGathering.show' => ['customer_invoice_data.view', 'supplier_invoice_data.view', 'loan_schedule_data.view'],
        'salesGathering.create' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'salesGathering.store' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'salesGathering.edit' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'salesGathering.update' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'salesGathering.destroy' => ['customer_invoice_data.delete', 'supplier_invoice_data.delete', 'loan_schedule_data.delete'],
        'salesGathering.export' => ['customer_invoice_data.export', 'supplier_invoice_data.export', 'loan_schedule_data.export'],
        'salesGatheringImport' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'salesGatheringTest.edit' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'salesGatheringTest.update' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'salesGatheringTest.destroy' => ['customer_invoice_data.delete', 'supplier_invoice_data.delete', 'loan_schedule_data.delete'],
        'salesGatheringTest.editCachedRow' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'salesGatheringTest.updateCachedRow' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'salesGatheringTest.insertToMainTable' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'create.sales.form' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'edit.sales.form' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'admin.store.analysis' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'admin.update.analysis' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'table.fields.selection.view' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'table.fields.selection.save' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'deleteAllCaches' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'deleteMultiRowsFromCaching' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        // Upload progress/state. Both expose the contents of a pending
        // import, so they belong behind the import right rather than in
        // PUBLIC_ROUTES — and `last.upload.failed` is a Route::any(),
        // so leaving it public would have left a write verb open.
        'active.job' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],
        'last.upload.failed' => ['customer_invoice_data.import', 'supplier_invoice_data.import', 'loan_schedule_data.import'],

        // ⚠️ Generic destructive endpoints. These were completely
        // ungated before this system existed (see the 2026-08 audit:
        // `truncate` was a GET that mass-deleted any model named in the
        // URL). They now demand a bulk-delete right, and the
        // controllers additionally whitelist the {model} segment.
        'truncate' => ['customer_invoice_data.bulk_delete', 'supplier_invoice_data.bulk_delete', 'loan_schedule_data.bulk_delete'],
        'multipleRowsDelete' => ['customer_invoice_data.bulk_delete', 'supplier_invoice_data.bulk_delete', 'loan_schedule_data.bulk_delete'],
        'delete.model' => ['customer_invoice_data.delete', 'supplier_invoice_data.delete', 'loan_schedule_data.delete'],

        /* ──────────────────── Administration ────────────────────── */
        'user.index' => 'user.view',
        'user.create' => 'user.create',
        'user.store' => 'user.create',
        'user.edit' => 'user.update',
        'user.update' => 'user.update',
        'user.destroy' => 'user.delete',
        'remove.user' => 'user.delete',
        'user.permissions.edit' => 'user.assign_roles',
        'user.permissions.update' => 'user.assign_roles',
        'roles.index' => 'role.view',
        'roles.create' => 'role.create',
        'roles.store' => 'role.create',
        'roles.edit' => 'role.update',
        'roles.update' => 'role.update',
        'roles.destroy' => 'role.delete',
        'companySection.index' => 'company.view',
        'companySection.show' => 'company.view',
        'companySection.create' => 'company.create',
        'companySection.store' => 'company.create',
        'companySection.edit' => 'company.update',
        'companySection.update' => 'company.update',
        'companySection.destroy' => 'company.delete',
        'remove.company' => 'company.delete',

        /* ────────────────────── Integrations ────────────────────── */
        'odoo-settings.index' => 'odoo_integration.view',
        'odoo-settings.show' => 'odoo_integration.view',
        'odoo-settings.create' => 'odoo_integration.update',
        'odoo-settings.store' => 'odoo_integration.update',
        'odoo-settings.edit' => 'odoo_integration.update',
        'odoo-settings.update' => 'odoo_integration.update',
        'odoo-settings.destroy' => 'odoo_integration.delete',
        'read-odoo-contracts' => 'odoo_integration.sync',
        'read-odoo-invoices' => 'odoo_integration.sync',
        'read-odoo-partners' => 'odoo_integration.sync',
        'send-odoo-collection-or-payments' => 'odoo_integration.sync',
    ];

    /** @var array<string, true>|null */
    private static ?array $publicIndex = null;

    public static function map(): array
    {
        return self::MAP;
    }

    public static function isPublic(string $routeName): bool
    {
        if (self::$publicIndex === null) {
            self::$publicIndex = array_fill_keys(self::PUBLIC_ROUTES, true);
        }

        return isset(self::$publicIndex[$routeName]);
    }

    /**
     * Permission key(s) required for a route, or null when unmapped.
     *
     * @return string[]|null
     */
    public static function for(string $routeName): ?array
    {
        $required = self::MAP[$routeName] ?? null;

        if ($required === null) {
            return null;
        }

        return (array) $required;
    }

    /**
     * @return string[]
     */
    public static function publicRoutes(): array
    {
        return self::PUBLIC_ROUTES;
    }
}
