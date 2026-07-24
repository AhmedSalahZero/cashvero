<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    activeLcType: String,
    filterDates: Object,
    lcTypes: Object,     // { 'sight-lc': 'Sight LC', ... }
    createUrls: Object,  // { 'lc-facility': url, 'hundred-percentage-cash-cover': url }
    tabs: Object,        // { 'sight-lc': { rows: [...] }, ... } — all loaded eagerly, matches original (no pagination in the original controller)
    navUrls: Object,
});

/*
 * Unlike LG Issuance, the original LC Issuance index() has no
 * pagination at all — it queries and returns every row for all 3 LC
 * types on every request, filtering only the active tab server-side.
 * Matched here exactly: no on-demand tab fetching needed, since
 * everything is already loaded up front.
 */
const activeTab = ref(props.activeLcType);
const currentTab = computed(() => props.tabs[activeTab.value] || { rows: [] });

const searchField = ref('transaction_name');
const searchValue = ref('');
function applySearch() {
    router.get(route_view_url(), { active: activeTab.value, field: searchField.value, value: searchValue.value }, { preserveState: true });
}
function route_view_url() {
    return window.location.pathname;
}

/* ── Mark As Paid modal ──────────────────────────────────────────
   Covers the core payment settlement (date, supplier invoice,
   financed-by-bank-vs-self, interest). The nested "Allocate Payment
   To Customer Contract" repeater from the original is deliberately
   NOT included here — a genuinely separate sub-feature for manually
   splitting a payment across multiple customer contracts, scoped as
   its own follow-up. Submitting without it still settles correctly
   against the chosen supplier invoice (that part is automatic
   server-side); it only skips the manual-split override, and sends
   an empty allocations array — the same safe default the backend
   already falls back to when none is submitted. ───────────────────── */
const payTarget = ref(null);
const payForm = ref({
    supplier_invoice_id: '',
    payment_date: '',
    payment_currency: '',
    payment_account_type_id: '',
    payment_account_number_id: '',
    interest_currency: '',
    interest_amount: 0,
    lc_remaining_amount: 0,
    lc_type: '',
});
function openPay(row) {
    payTarget.value = row;
    payForm.value = {
        supplier_invoice_id: row.supplier_invoice_id ?? '',
        payment_date: row.due_date ?? '',
        payment_currency: row.payment_currency ?? '',
        payment_account_type_id: row.payment_account_type_id ?? '',
        payment_account_number_id: row.payment_account_number_id ?? '',
        interest_currency: row.interest_currency ?? '',
        interest_amount: row.interest_amount ?? 0,
        lc_remaining_amount: row.lc_amount ?? 0,
        lc_type: row.lc_type,
    };
}
function submitPay() {
    router.post(payTarget.value.mark_as_paid_url, payForm.value, { onFinish: () => { payTarget.value = null; } });
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
                    <Link :href="createUrls['lc-facility']" class="cvr-btn-copper px-3 py-1.5 rounded text-sm">+ Via LC Facility</Link>
                    <Link :href="createUrls['hundred-percentage-cash-cover']" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">+ 100% Cash Cover</Link>
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
                        <tr v-for="(row, index) in currentTab.rows" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-3 py-3 cvr-text-primary max-w-[12rem] break-words">{{ row.transaction_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary max-w-[12rem] break-words">{{ row.beneficiary_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.source_formatted }}</td>
                            <td class="px-3 py-3">
                                <span class="cvr-badge" :class="row.is_paid ? 'cvr-badge-current' : 'cvr-badge-active'">{{ row.status_formatted }}</span>
                            </td>
                            <td class="px-3 py-3 cvr-text-secondary max-w-[10rem] break-words">{{ row.bank_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.lc_code }}</td>
                            <td class="px-3 py-3 cvr-num">{{ row.lc_amount_formatted }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.issuance_date_formatted }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.due_date_formatted }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button v-if="row.has_comment" @click="commentTarget = row" class="cvr-action-btn" title="User Comment">💬</button>
                                    <button v-if="row.is_running" @click="openExpenses(row)" class="cvr-action-btn" title="Expenses">💵</button>
                                    <button @click="openPay(row)" class="cvr-action-btn" title="Apply Payment">💰</button>
                                    <button v-if="row.is_paid" @click="openBackToRunning(row)" class="cvr-action-btn" title="Back To Running">↩️</button>

                                    <Link :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">Edit</Link>
                                    <button @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="currentTab.rows.length === 0">
                            <td colspan="11" class="px-4 py-8 text-center cvr-text-muted">
                                No LC Issuance records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mark As Paid modal -->
            <div v-if="payTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-3xl">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to pay this LC?</h2>
                    <div class="cvr-form-grid-3 mb-4">
                        <div>
                            <label class="cvr-form-label">Bank Name</label>
                            <input disabled :value="payTarget.bank_name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Amount</label>
                            <input disabled :value="payTarget.lc_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
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
                            <label class="cvr-form-label">Payment Date *</label>
                            <input v-model="payForm.payment_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>

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
                                <input v-model="payForm.lc_remaining_amount" type="number" class="cvr-input w-full px-3 py-2 rounded" />
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
                                    <option v-for="a in payTarget.payment_accounts" :key="a.id" :value="a.id">{{ a.account_number }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Interest Currency *</label>
                                <select v-model="payForm.interest_currency" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="" disabled>Select</option>
                                    <option :value="payTarget.company_main_currency">{{ payTarget.company_main_currency }}</option>
                                    <option :value="payTarget.lc_currency">{{ payTarget.lc_currency?.toUpperCase() }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Interest Amount</label>
                                <input v-model="payForm.interest_amount" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                        </template>
                    </div>
                    <p class="text-xs cvr-text-muted mb-4">
                        Manually splitting this payment across specific customer contracts isn't available here yet — it settles against the invoice above automatically. Flagged as a follow-up.
                    </p>
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
                            <input v-model="newExpenseForm.amount" type="number" class="cvr-input w-full px-3 py-2 rounded" />
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
                                        <button @click="openEditExpense(e)" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">Edit</button>
                                        <button @click="confirmDeleteExpense(e)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">Delete</button>
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
                            <input v-model="editExpenseForm.amount" type="number" class="cvr-input w-full px-3 py-2 rounded" />
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
