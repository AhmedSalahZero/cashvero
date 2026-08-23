<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';

const props = defineProps({
    company: Object,
    activeLcType: String,
    filterDates: Object,
    lcTypes: Object,     // { 'sight-lc': 'Sight LC', ... }
    createUrls: Object,  // { 'lc-facility': url, 'hundred-percentage-cash-cover': url }
    // { canCreate, canUpdate, canDelete, canSettle }
    // This page previously had no permission gating at all.
    permissions: { type: Object, default: () => ({}) },
    sightLcTab: Object,        // { rows: paginator }
    deferredTab: Object,
    cashAgainstDocumentTab: Object,
    customersWithContracts: Array, // [{id, name, contracts: [{id, name, code, amount}]}]
    navUrls: Object,
});

/**
 * Each tab is now its own separate Inertia prop server-side, and rows
 * are now a real paginator instead of a plain array (see
 * LetterOfCreditIssuanceController@index for why — this page used to
 * have no pagination at all, loading a company's entire LC history on
 * every visit). This puts the three props back into the
 * { [type]: {...} } shape the rest of this file already expects.
 */
const tabs = computed(() => ({
    'sight-lc': props.sightLcTab,
    'deferred': props.deferredTab,
    'cash-against-document': props.cashAgainstDocumentTab,
}));
const TAB_PROP_NAMES = {
    'sight-lc': 'sightLcTab',
    'deferred': 'deferredTab',
    'cash-against-document': 'cashAgainstDocumentTab',
};

const activeTab = ref(props.activeLcType);
const currentTab = computed(() => tabs.value[activeTab.value] || { rows: { data: [], links: [], last_page: 1 } });

const searchField = ref('transaction_name');
const searchValue = ref('');
function applySearch() {
    router.get(route_view_url(), { active: activeTab.value, field: searchField.value, value: searchValue.value }, { preserveState: true, only: [TAB_PROP_NAMES[activeTab.value]] });
}
function route_view_url() {
    return window.location.pathname;
}
function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true, only: [TAB_PROP_NAMES[activeTab.value]] });
}

/* ── Mark As Paid modal ──────────────────────────────────────────
   Client-confirmed real gap (2026-08-11, with the original Blade
   files provided directly): the Cash Cover section, per-line
   Exchange Rate / Net Balance displays, and the "Allocate Payment To
   Customer Contract" repeater all existed in the original and were
   dropped entirely during the earlier Vue migration — even the save
   action's own backend calls (storeNewSettlementAfterDeleteOldOne /
   storeNewAllocationAfterDeleteOldOne) were still intact and correct,
   they just had no UI feeding them real allocation data. Rebuilt here
   faithfully from the original cancel-issuance-modal.blade.php. ──── */
const payTarget = ref(null);
const payForm = ref({
    supplier_invoice_id: '',
    payment_date: '',
    payment_currency: '',
    payment_account_type_id: '',
    payment_account_number_id: '',
    lc_remaining_amount: 0,
    lc_type: '',
});
let nextAllocationRowId = 1;
function blankAllocationRow() {
    return { _rowId: nextAllocationRowId++, partner_id: '', contract_id: '', allocation_amount: 0 };
}
const allocationRows = ref([blankAllocationRow()]);
function addAllocationRow() { allocationRows.value.push(blankAllocationRow()); }
function removeAllocationRow(rowId) {
    if (allocationRows.value.length <= 1) return;
    allocationRows.value = allocationRows.value.filter(r => r._rowId !== rowId);
}
function contractsForCustomer(partnerId) {
    return props.customersWithContracts.find(c => c.id === partnerId)?.contracts ?? [];
}
function contractDetails(row) {
    return contractsForCustomer(row.partner_id).find(c => c.id === row.contract_id) ?? null;
}
// Selected invoice's own currency/net balance/exchange rate — the
// original shows these live as soon as an invoice is picked.
const selectedInvoice = computed(() => {
    if (!payTarget.value) return null;
    return payTarget.value.supplier_invoices.find(inv => inv.id === payForm.value.supplier_invoice_id) ?? null;
});
function openPay(row) {
    payTarget.value = row;
    payForm.value = {
        supplier_invoice_id: row.supplier_invoice_id ?? '',
        payment_date: row.due_date ?? '',
        payment_currency: row.payment_currency ?? '',
        payment_account_type_id: row.payment_account_type_id ?? (row.current_account_types[0]?.id ?? ''),
        payment_account_number_id: row.payment_account_number_id ?? '',
        lc_remaining_amount: row.lc_amount ?? 0,
        lc_type: row.lc_type,
    };
    allocationRows.value = [blankAllocationRow()];
    filteredPaymentAccounts.value = [];
    /**
     * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): the backend
     * ALWAYS posts the "LC Payment" statement line using whatever
     * lc_remaining_amount gets submitted — regardless of whether the
     * LC is financed by bank or by self. My reactive recalculation
     * only ever ran inside the "financed by self" section (the only
     * place with a Payment Currency picker), so a bank-financed LC's
     * field silently stayed at its raw, unconverted default (the LC
     * amount in its OWN currency) the entire time. For a bank-financed
     * LC there's no explicit payment-currency choice to make — the
     * statement is always posted in the Cash Cover currency — so this
     * computes it immediately using that, matching exactly how the
     * Cash Cover section's own conversion already works.
     */
    if (!row.is_financed_by_self) {
        const cashCoverRate = Number(row.cash_cover_rate || 0) / 100;
        payForm.value.lc_remaining_amount = Math.round(Number(row.lc_amount_in_main_currency) * (1 - cashCoverRate) * 100) / 100;
    }
}
/**
 * Client-directed (2026-08-11), confirmed against the original's own
 * behavior: LC Remaining Amount = (LC Amount, converted into whichever
 * currency is picked as Payment Currency) × (1 − Cash Cover Rate%).
 * Recalculates live the moment Payment Currency changes; the field
 * stays editable afterward so the person can still override it.
 */
function recalculateLcRemainingAmount() {
    if (!payTarget.value || !payForm.value.payment_currency) return;
    const isLcCurrency = payForm.value.payment_currency === payTarget.value.lc_currency;
    const amountInPaymentCurrency = isLcCurrency
        ? Number(payTarget.value.lc_amount)
        : Number(payTarget.value.lc_amount) * Number(payTarget.value.lc_exchange_rate || 1);
    const cashCoverRate = Number(payTarget.value.cash_cover_rate || 0) / 100;
    payForm.value.lc_remaining_amount = Math.round(amountInPaymentCurrency * (1 - cashCoverRate) * 100) / 100;
}
watch(() => payForm.value.payment_currency, recalculateLcRemainingAmount);

/**
 * Client-flagged (2026-08-11): Account Number must only show accounts
 * in the selected Payment Currency, further narrowed by Account Type —
 * previously showed every account for the bank regardless of currency.
 * Reuses the same live lookup already used by Money Payment / Money
 * Received / Cash Expense for this exact purpose.
 */
const filteredPaymentAccounts = ref([]);
async function fetchAccountsForPaymentCurrencyAndType() {
    if (!payTarget.value || !payForm.value.payment_currency || !payForm.value.payment_account_type_id) {
        filteredPaymentAccounts.value = [];
        return;
    }
    const url = payTarget.value.account_number_lookup_url
        .replace('__TYPE__', payForm.value.payment_account_type_id)
        .replace('__CURRENCY__', payForm.value.payment_currency);
    try {
        const response = await fetch(url);
        const data = await response.json();
        filteredPaymentAccounts.value = Object.entries(data?.data ?? {}).map(([id, accountNumber]) => ({ id, account_number: accountNumber }));
    } catch (e) {
        filteredPaymentAccounts.value = [];
    }
}
watch(() => [payForm.value.payment_currency, payForm.value.payment_account_type_id], fetchAccountsForPaymentCurrencyAndType);
function submitPay() {
    const payload = {
        ...payForm.value,
        allocations: allocationRows.value
            .filter(r => r.partner_id && r.contract_id && r.allocation_amount > 0)
            .map(({ _rowId, ...rest }) => rest),
    };
    router.post(payTarget.value.mark_as_paid_url, payload, { onFinish: () => { payTarget.value = null; } });
}

/* ── Back To Running modal ───────────────────────────────────── */
const backToRunningTarget = ref(null);
function openBackToRunning(row) { backToRunningTarget.value = row; }
function submitBackToRunning() {
    router.post(backToRunningTarget.value.back_to_running_url, { lc_type: backToRunningTarget.value.lc_type }, { onFinish: () => { backToRunningTarget.value = null; } });
}

/* ── Expenses modal ───────────────────────────────────────────── */
const expensesTarget = ref(null);
const newExpenseForm = ref({ expense_name: '', date: '', amount: 0, currency: 'usd', exchange_rate: 1 });
function openExpenses(row) {
    expensesTarget.value = row;
    newExpenseForm.value = { expense_name: '', date: new Date().toISOString().split('T')[0], amount: 0, currency: 'usd', exchange_rate: 1 };
}
function submitNewExpense() {
    const payload = {
        expense_name: { create: newExpenseForm.value.expense_name },
        date: { create: newExpenseForm.value.date },
        amount: { create: newExpenseForm.value.amount },
        currency: { create: newExpenseForm.value.currency },
        exchange_rate: { create: newExpenseForm.value.exchange_rate },
        amount_in_main_currency: { create: newExpenseForm.value.amount * newExpenseForm.value.exchange_rate },
    };
    router.post(expensesTarget.value.apply_expense_url, payload, { onFinish: () => { expensesTarget.value = null; } });
}
const editExpenseTarget = ref(null);
const editExpenseForm = ref({ expense_name: '', date: '', amount: 0, currency: 'usd', exchange_rate: 1 });
function openEditExpense(expense) {
    editExpenseTarget.value = expense;
    editExpenseForm.value = { expense_name: expense.name, date: expense.date, amount: expense.amount, currency: expense.currency, exchange_rate: expense.exchange_rate };
}
function submitEditExpense() {
    const payload = {
        expense_name: { update: editExpenseForm.value.expense_name },
        date: { update: editExpenseForm.value.date },
        amount: { update: editExpenseForm.value.amount },
        currency: { update: editExpenseForm.value.currency },
        exchange_rate: { update: editExpenseForm.value.exchange_rate },
        amount_in_main_currency: { update: editExpenseForm.value.amount * editExpenseForm.value.exchange_rate },
    };
    router.post(editExpenseTarget.value.update_url, payload, { onFinish: () => { editExpenseTarget.value = null; } });
}
const deleteExpenseTarget = ref(null);
function confirmDeleteExpense(expense) { deleteExpenseTarget.value = expense; }
function destroyExpense() {
    router.delete(deleteExpenseTarget.value.delete_url, { onFinish: () => { deleteExpenseTarget.value = null; } });
}

/* ── Delete confirmation ──────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

const commentTarget = ref(null);
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">LC Issuance</h1>
            <p class="text-sm cvr-text-blue mb-6">Letters Of Credit issued across all financial institutions</p>

            <!-- Tabs -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="flex items-center gap-1 flex-wrap">
                    <button
                        v-for="(label, type) in lcTypes"
                        :key="type"
                        @click="activeTab = type"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': activeTab === type }"
                    >
                        {{ label }}
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <Link v-if="permissions.canCreate" :href="createUrls['lc-facility']" class="cvr-btn-copper px-3 py-1.5 rounded text-sm">+ Via LC Facility</Link>
                    <Link v-if="permissions.canCreate" :href="createUrls['hundred-percentage-cash-cover']" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">+ 100% Cash Cover</Link>
                </div>
            </div>

            <!-- Search -->
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="cvr-form-label">Search By</label>
                    <select v-model="searchField" class="cvr-input px-3 py-2 rounded">
                        <option value="transaction_name">Transaction Name</option>
                        <option value="lc_code">LC Code</option>
                    </select>
                </div>
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="searchValue" @keyup.enter="applySearch" type="text" placeholder="Search..." class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
                <button @click="applySearch" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">Search</button>
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-left">#</th>
                            <th class="px-3 py-3 text-left">Transaction Name</th>
                            <th class="px-3 py-3 text-left">Beneficiary</th>
                            <th class="px-3 py-3 text-left">Source</th>
                            <th class="px-3 py-3 text-left">Status</th>
                            <th class="px-3 py-3 text-left">Bank Name</th>
                            <th class="px-3 py-3 text-left">LC Code</th>
                            <th class="px-3 py-3 text-left">LC Amount</th>
                            <th class="px-3 py-3 text-left">Issuance Date</th>
                            <th class="px-3 py-3 text-left">Due Date</th>
                            <th class="px-3 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in currentTab.rows.data" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-3 py-3 cvr-text-primary max-w-[12rem] break-words">{{ row.transaction_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary max-w-[12rem] break-words">{{ row.beneficiary_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.source_formatted }}</td>
                            <td class="px-3 py-3">
                                <span class="cvr-badge" :class="row.is_paid ? 'cvr-badge-current' : 'cvr-badge-active'">{{ row.status_formatted }}</span>
                            </td>
                            <td class="px-3 py-3 cvr-text-secondary max-w-[10rem] break-words">{{ row.bank_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.lc_code }}</td>
                            <td class="px-3 py-3 cvr-num">{{ row.lc_amount_formatted }} <span class="cvr-text-muted">{{ row.lc_currency?.toUpperCase() }}</span></td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.issuance_date_formatted }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.due_date_formatted }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <RecordLogButton subject="LetterOfCreditIssuance" :id="row.id" :company-id="company.id" />
                                    <button v-if="row.has_comment" @click="commentTarget = row" class="cvr-action-btn" title="User Comment">💬</button>
                                    <button v-if="permissions.canUpdate && row.is_running" @click="openExpenses(row)" class="cvr-action-btn" title="Expenses">💵</button>
                                    <button v-if="permissions.canSettle" @click="openPay(row)" class="cvr-action-btn" title="Apply Payment">💰</button>
                                    <button v-if="permissions.canSettle && row.is_paid" @click="openBackToRunning(row)" class="cvr-action-btn" title="Back To Running">↩️</button>

                                    <!-- Client-requested (2026-08-11): once
                                         an LC is paid, Edit and Delete no
                                         longer make sense — "Back To
                                         Running" (above) is the correct way
                                         to undo a payment before editing or
                                         removing it. -->
                                    <Link v-if="permissions.canUpdate && !row.is_paid" :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">Edit</Link>
                                    <button v-if="permissions.canDelete && !row.is_paid" @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="currentTab.rows.data.length === 0">
                            <td colspan="11" class="px-4 py-8 text-center cvr-text-muted">
                                No LC Issuance records found.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="currentTab.rows.last_page > 1" class="flex items-center justify-between mt-4 flex-wrap gap-3">
                    <p class="text-xs cvr-text-muted">
                        Showing {{ currentTab.rows.from }}–{{ currentTab.rows.to }} of {{ currentTab.rows.total }}
                    </p>
                    <div class="flex items-center gap-1 flex-wrap">
                        <button
                            v-for="(link, i) in currentTab.rows.links"
                            :key="i"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            class="cvr-filter-pill"
                            :class="{ 'cvr-filter-pill-active': link.active, 'opacity-40 cursor-not-allowed': !link.url }"
                            v-html="link.label"
                        ></button>
                    </div>
                </div>
            </div>

            <!-- Mark As Paid modal -->
            <div v-if="payTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-7xl max-h-[90vh] overflow-y-auto">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to pay this LC?</h2>
                    <div class="cvr-form-grid-6-2-2-2 mb-3">
                        <div>
                            <label class="cvr-form-label">Bank Name</label>
                            <input disabled :value="payTarget.bank_name" class="cvr-input w-full  py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Amount</label>
                            <input disabled :value="payTarget.lc_amount_formatted" class="cvr-input w-full  py-2 rounded" />
                        </div>
                        
                            <div>
                                <label class="cvr-form-label">Exchange Rate</label>
                                <input disabled :value="payTarget.lc_exchange_rate" class="cvr-input w-full  py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">In Payment Currency</label>
                                <input disabled :value="payTarget.lc_amount_in_main_currency_formatted" class="cvr-input w-full py-2 rounded" />
                            </div>
                        
                    </div>

                    <!-- Cash Cover -->
                    <div class="cvr-form-grid-6-2-2-2 mb-3">
                        <div>
                            <label class="cvr-form-label">Cash Cover</label>
                            <input disabled value="Cash Cover" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount</label>
                            <input disabled :value="`${payTarget.cash_cover_amount_formatted} ${payTarget.lc_cash_cover_currency?.toUpperCase()}`" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        
                            <div>
                                <label class="cvr-form-label">Exchange Rate</label>
                                <input disabled :value="payTarget.cash_cover_exchange_rate" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">In Payment Currency</label>
                                <input disabled :value="Math.round(payTarget.cash_cover_amount * payTarget.cash_cover_exchange_rate).toLocaleString()" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                        
                    </div>

                    <div class="cvr-form-grid-3-3-2-2-2 mb-3">
                        <div>
                            <label class="cvr-form-label">Payment Date *</label>
                            <input v-model="payForm.payment_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Supplier Invoice</label>
                            <select v-model="payForm.supplier_invoice_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">None</option>
                                <option v-for="inv in payTarget.supplier_invoices" :key="inv.id" :value="inv.id">{{ inv.invoice_number }}</option>
                            </select>
                            <p class="text-xs cvr-text-muted mt-1">Only invoices for this beneficiary, in this LC's currency</p>
                        </div>
                        
                            <div>
                                <label class="cvr-form-label">Invoice Net Balance</label>
                                <input disabled :value="selectedInvoice ? `${Number(selectedInvoice.net_balance).toLocaleString()} ${selectedInvoice.currency}` : 0" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Exchange Rate</label>
                                <input disabled :value="selectedInvoice ? selectedInvoice.exchange_rate : 0" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">NB In Main Currency</label>
                                <input disabled :value="selectedInvoice ? Number(selectedInvoice.net_balance_in_main_currency).toLocaleString() : 0" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                        
                       </div>
                       <div class="cvr-form-grid-4 mb-3">

                        <template v-if="payTarget.is_financed_by_self">
                            <div>
                                <label class="cvr-form-label">LC Payment Currency *</label>
                                <select v-model="payForm.payment_currency" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="" disabled>Select</option>
                                    <option :value="payTarget.company_main_currency">{{ payTarget.company_main_currency }}</option>
                                    <option :value="payTarget.lc_currency">{{ payTarget.lc_currency?.toUpperCase() }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">LC Remaining Amount</label>
                                <input v-model="payForm.lc_remaining_amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Account Type</label>
                                <select v-model="payForm.payment_account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                    <option v-for="t in payTarget.current_account_types" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Account Number</label>
                                <select v-model="payForm.payment_account_number_id" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="">Select</option>
                                    <option v-for="a in filteredPaymentAccounts" :key="a.id" :value="a.id">{{ a.account_number }}</option>
                                </select>
                                <p v-if="payForm.payment_currency && !filteredPaymentAccounts.length" class="text-xs cvr-text-muted mt-1">No accounts in this currency for this bank.</p>
                            </div>
                        </template>
                    </div>

                    <!-- Allocate Payment To Customer Contract -->
                    <h3 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide m-4">Allocate Payment To Customer Contract</h3>
                    <div class="overflow-x-auto mt-3 mb-2">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">Customer</th>
                                    <th class="px-3 py-2 text-left">Contract Name</th>
                                    <th class="px-3 py-2 text-left">Contract Code</th>
                                    <th class="px-3 py-2 text-left">Contract Amount</th>
                                    <th class="px-3 py-2 text-left">Allocate Amount</th>
                                    <th class="px-3 py-2 text-left"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in allocationRows" :key="row._rowId" class="cvr-table-row">
                                    <td class="px-3 py-2 min-w-[10rem]">
                                        <select v-model="row.partner_id" @change="row.contract_id = ''" class="cvr-input px-2 py-1.5 rounded w-full">
                                            <option value="">Select</option>
                                            <option v-for="c in customersWithContracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2 min-w-[10rem]">
                                        <select v-model="row.contract_id" class="cvr-input px-2 py-1.5 rounded w-full">
                                            <option value="">Select</option>
                                            <option v-for="ct in contractsForCustomer(row.partner_id)" :key="ct.id" :value="ct.id">{{ ct.name }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2"><input disabled :value="contractDetails(row)?.code ?? ''" class="cvr-input px-2 py-1.5 rounded w-28" /></td>
                                    <td class="px-3 py-2"><input disabled :value="(contractDetails(row)?.amount ?? 0).toLocaleString()" class="cvr-input px-2 py-1.5 rounded w-28" /></td>
                                    <td class="px-3 py-2"><input v-model="row.allocation_amount" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-28" /></td>
                                    <td class="px-3 py-2">
                                        <button type="button" @click="removeAllocationRow(row._rowId)" class="cvr-btn-remove-row w-auto">🗑</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" @click="addAllocationRow" class="cvr-btn-primary px-2 py-1 rounded text-xs mb-4">+ Add Row</button>

                    <div class="flex justify-end gap-2">
                        <button @click="payTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitPay" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Back To Running modal -->
            <div v-if="backToRunningTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-lg">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to cancel this LC payment?</h2>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="cvr-form-label">Bank Name</label>
                            <input disabled :value="backToRunningTarget.bank_name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Amount</label>
                            <input disabled :value="backToRunningTarget.lc_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="backToRunningTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitBackToRunning" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Expenses modal -->
            <div v-if="expensesTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-4xl">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Expenses — {{ expensesTarget.transaction_name }}</h2>
                    <div class="cvr-form-grid-4 mb-4 items-end">
                        <div>
                            <label class="cvr-form-label">Expense Name</label>
                            <input v-model="newExpenseForm.expense_name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Date *</label>
                            <input v-model="newExpenseForm.date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount</label>
                            <input v-model="newExpenseForm.amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Currency *</label>
                            <select v-model="newExpenseForm.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="c in expensesTarget.bank_currencies" :key="c" :value="c">{{ c.toUpperCase() }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Exchange Rate</label>
                            <input v-model="newExpenseForm.exchange_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>

                    <table class="min-w-full text-sm mb-4">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Expense Name</th>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Amount</th>
                                <th class="px-3 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(e, i) in expensesTarget.expenses" :key="e.id" class="cvr-table-row">
                                <td class="px-3 py-2 cvr-text-secondary">{{ i + 1 }}</td>
                                <td class="px-3 py-2 cvr-text-primary">{{ e.name }}</td>
                                <td class="px-3 py-2 whitespace-nowrap cvr-text-secondary">{{ e.date_formatted }}</td>
                                <td class="px-3 py-2 cvr-num">{{ e.amount_formatted }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <button v-if="permissions.canUpdate" @click="openEditExpense(e)" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">Edit</button>
                                        <button v-if="permissions.canUpdate" @click="confirmDeleteExpense(e)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expensesTarget.expenses.length === 0">
                                <td colspan="5" class="px-3 py-6 text-center cvr-text-muted">No expenses yet.</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-end gap-2">
                        <button @click="expensesTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitNewExpense" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Edit Expense modal -->
            <div v-if="editExpenseTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-2xl">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Edit Expense</h2>
                    <div class="cvr-form-grid-3 mb-4">
                        <div>
                            <label class="cvr-form-label">Expense Name</label>
                            <input v-model="editExpenseForm.expense_name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Date *</label>
                            <input v-model="editExpenseForm.date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount</label>
                            <input v-model="editExpenseForm.amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Currency *</label>
                            <input v-model="editExpenseForm.currency" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Exchange Rate</label>
                            <input v-model="editExpenseForm.exchange_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="editExpenseTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitEditExpense" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Delete Expense confirmation -->
            <div v-if="deleteExpenseTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to delete this expense?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteExpenseTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyExpense" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to delete this item?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
            </div>

            <!-- User Comment -->
            <div v-if="commentTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium cvr-text-primary">User Comment</h2>
                        <button @click="commentTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                    </div>
                    <p class="cvr-text-secondary">{{ commentTarget.user_comment }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>