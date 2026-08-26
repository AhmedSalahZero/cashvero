<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';
import { todayDate } from '@/composables/today';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate();

/*
 * CashExpense/Index.vue
 * ------------------------------------------------------------------
 * Three tabs — Outgoing Transfer (bank), Cash Payment (safe/branch),
 * Payable Cheques (issued, tracked until paid) — all backed by the
 * same CashExpense model with a different `type`.
 *
 * Payable Cheques has two different status-like columns, both from
 * the old page: an early "paid / unpaid" Status column, and a
 * separate colour-coded "Due" / "Not Due Yet" column next to Due Date
 * (shows "–" once paid). Both Outgoing Transfer and Payable Cheques
 * support batch AND per-row "Mark As Paid" (pick an Actual Payment
 * Date, submit) — same two pre-existing endpoints
 * (markChequesAsPaid()/markOutgoingTransfersAsPaid()) the old page
 * used, just reached two ways. Cash Payment never had this — it's
 * already paid at entry.
 */

const props = defineProps({
    company: Object,
    activeTab: String,
    canCreate: Boolean,
    canUpdate: Boolean,
    canDelete: Boolean,
    // Marking a payable cheque / outgoing transfer as actually paid.
    canMarkAsPaid: Boolean,
    outgoingTransferTab: Object, // {label, rows: paginator, startDate, endDate, hasBatchCollection}
    cashPaymentTab: Object,
    payableChequeTab: Object,
    indexUrl: String,
    createUrl: String,
    markChequesAsPaidUrl: String,
    unmarkChequesAsPaidUrl: String,
    markOutgoingTransfersAsPaidUrl: String,
});

const TYPES = {
    OUTGOING_TRANSFER: 'outgoing-transfer',
    CASH_PAYMENT: 'cash_payment',
    PAYABLE_CHEQUE: 'payable_cheque',
};

/**
 * Each tab is now its own separate Inertia prop server-side (see
 * CashExpenseController@index for why — only the active tab's data
 * should be recomputed on a pagination/filter request, not all three).
 * This just puts them back into the { [type]: {...} } shape the rest
 * of this file already expects, so nothing else below needs to change.
 */
const tabs = computed(() => ({
    [TYPES.OUTGOING_TRANSFER]: props.outgoingTransferTab,
    [TYPES.CASH_PAYMENT]: props.cashPaymentTab,
    [TYPES.PAYABLE_CHEQUE]: props.payableChequeTab,
}));
const TAB_PROP_NAMES = {
    [TYPES.OUTGOING_TRANSFER]: 'outgoingTransferTab',
    [TYPES.CASH_PAYMENT]: 'cashPaymentTab',
    [TYPES.PAYABLE_CHEQUE]: 'payableChequeTab',
};

const activeTab = ref(props.activeTab);
function switchTab(type) {
    activeTab.value = type;
}

const columnsByType = {
    [TYPES.OUTGOING_TRANSFER]: ['bank_name', 'account_type_name', 'account_number'],
    [TYPES.CASH_PAYMENT]: ['branch_name', 'receipt_number'],
    [TYPES.PAYABLE_CHEQUE]: ['status', 'cheque_number', 'bank_name', 'account_type_name', 'account_number', 'due_date_formatted', 'due_status'],
};
const columnLabels = {
    bank_name: 'Payment Bank',
    account_type_name: 'Account Type',
    account_number: 'Account Number',
    branch_name: 'Branch',
    receipt_number: 'Receipt Number',
    status: 'Status',
    cheque_number: 'Cheque Number',
    due_date_formatted: 'Due Date',
    due_status: 'Status',
};
// Bank names run long — see the Buy Or Sell Currencies list page fix.
// Same treatment: English on top, Arabic underneath, instead of one
// long column.
const bankNameColumns = ['bank_name'];
/* ── Per-tab search + date range ─────────────────────────────────── */
const filters = ref(
    Object.fromEntries(Object.keys(tabs.value).map(type => [type, {
        startDate: tabs.value[type].startDate,
        endDate: tabs.value[type].endDate,
    }]))
);
const searchField = ref('partner_name');
const searchValue = ref('');
const searchFieldOptionsByType = {
    [TYPES.OUTGOING_TRANSFER]: { partner_name: 'Supplier Name', expense_name: 'Expense Name', currency: 'Currency' },
    [TYPES.CASH_PAYMENT]: { partner_name: 'Supplier Name', expense_name: 'Expense Name', delivery_branch_name: 'Branch', currency: 'Currency', receipt_number: 'Receipt Number' },
    [TYPES.PAYABLE_CHEQUE]: { partner_name: 'Supplier Name', expense_name: 'Expense Name', currency: 'Currency' },
};

function applyFilters(type) {
    const startDate = {};
    const endDate = {};
    Object.keys(filters.value).forEach(t => {
        startDate[t] = filters.value[t].startDate;
        endDate[t] = filters.value[t].endDate;
    });
    router.get(props.indexUrl, {
        active: type,
        startDate,
        endDate,
        field: searchField.value,
        value: searchValue.value,
    }, { preserveState: true, preserveScroll: true, only: [TAB_PROP_NAMES[type]] });
}

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true, only: [TAB_PROP_NAMES[activeTab.value]] });
}

/* ── Batch Mark As Paid (Outgoing Transfer, Payable Cheques) ──────── */
const selectedIds = ref({});
function toggleSelect(type, id) {
    if (!selectedIds.value[type]) selectedIds.value[type] = [];
    const list = selectedIds.value[type];
    const idx = list.indexOf(id);
    if (idx === -1) list.push(id); else list.splice(idx, 1);
}
function isSelected(type, id) {
    return (selectedIds.value[type] || []).includes(id);
}

const markPaidTarget = ref(null); // { type, ids } | null
const actualPaymentDate = ref(todayDate());
function openMarkPaidModal(type) {
    if (!(selectedIds.value[type] || []).length) return;
    markPaidTarget.value = { type, ids: selectedIds.value[type] };
}
function openMarkPaidModalForRow(type, id) {
    markPaidTarget.value = { type, ids: [id] };
}
function confirmMarkPaid() {
    const { type, ids } = markPaidTarget.value;
    const url = type === TYPES.PAYABLE_CHEQUE ? props.markChequesAsPaidUrl : props.markOutgoingTransfersAsPaidUrl;
    router.post(url, {
        cheques: ids,
        actual_payment_date: actualPaymentDate.value,
    }, {
        onFinish: () => {
            selectedIds.value[type] = [];
            markPaidTarget.value = null;
        },
    });
}

const unmarkTarget = ref(null);
function confirmUnmarkPaid() {
    router.post(props.unmarkChequesAsPaidUrl, {
        cheques: [unmarkTarget.value.id],
    }, {
        onFinish: () => { unmarkTarget.value = null; },
    });
}

/* ── Delete confirmation ─────────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

/* ── User comment / Odoo references modals ───────────────────────── */
const commentTarget = ref(null);
const odooRefTarget = ref(null);
const odooErrorTarget = ref(null);
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                <h1 class="text-xl font-semibold cvr-text-primary">{{ $t('Cash Expense') }}</h1>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                    {{ $t('+ Cash Expense') }}
                </Link>
            </div>

            <!-- Tabs -->
            <div class="flex items-center gap-1 mb-4 flex-wrap">
                <button
                    v-for="(tab, type) in tabs"
                    :key="type"
                    @click="switchTab(type)"
                    class="cvr-filter-pill"
                    :class="{ 'cvr-filter-pill-active': activeTab === type }"
                >
                    {{ tab.label }}
                </button>
            </div>

            <template v-for="(tab, type) in tabs" :key="type">
                <div v-show="activeTab === type">
                    <!-- Filters -->
                    <div class="flex flex-wrap items-end gap-3 mb-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Search In') }}</label>
                            <select v-model="searchField" class="cvr-input px-3 py-2 rounded">
                                <option v-for="(flabel, field) in searchFieldOptionsByType[type]" :key="field" :value="field">{{ flabel }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Value') }}</label>
                            <input v-model="searchValue" type="text" :placeholder="$t('Search...')" class="cvr-input px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Start Date') }}</label>
                            <input v-model="filters[type].startDate" type="date" class="cvr-input px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('End Date') }}</label>
                            <input v-model="filters[type].endDate" type="date" class="cvr-input px-3 py-2 rounded" />
                        </div>
                        <button @click="applyFilters(type)" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Apply') }}</button>
                        <button
                            v-if="canMarkAsPaid && tab.hasBatchCollection && (selectedIds[type] || []).length"
                            @click="openMarkPaidModal(type)"
                            class="cvr-btn-primary px-4 py-2 rounded ms-auto"
                        >
                            {{ $t('Mark') }} {{ (selectedIds[type] || []).length }} {{ $t('Selected As Paid') }}
                        </button>
                    </div>

                    <!-- Table -->
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th v-if="canMarkAsPaid && tab.hasBatchCollection" class="px-3 py-3 text-start">{{ $t('Select') }}</th>
                                    <th class="px-3 py-3 text-start whitespace-nowrap">{{ $t('Category') }}</th>
                                    <th class="px-3 py-3 text-start whitespace-nowrap">{{ $t('Expense Name') }}</th>
                                    <th class="px-3 py-3 text-start whitespace-nowrap">{{ $t('Payment Date') }}</th>
                                    <th v-for="col in columnsByType[type]" :key="col" class="px-3 py-3 text-start whitespace-nowrap">
                                        {{ $t(columnLabels[col]) }}
                                    </th>
                                    <th class="px-3 py-3 text-start">{{ $t('Amount') }}</th>
                                    <th class="px-3 py-3 text-start">{{ $t('Currency') }}</th>
                                    <th v-if="canUpdate || canDelete || canMarkAsPaid" class="px-3 py-3 text-start">{{ $t('Control') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in tab.rows.data" :key="row.id" class="cvr-table-row">
                                    <td v-if="canMarkAsPaid && tab.hasBatchCollection" class="px-3 py-3">
                                        <input
                                            type="checkbox"
                                            :checked="isSelected(type, row.id)"
                                            :disabled="type === TYPES.PAYABLE_CHEQUE && row.is_paid"
                                            @change="toggleSelect(type, row.id)"
                                        />
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.expense_category_name }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap cvr-text-primary">{{ row.expense_name }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.payment_date_formatted }}</td>
                                    <template v-for="col in columnsByType[type]" :key="col">
                                        <td v-if="bankNameColumns.includes(col)" class="px-3 py-3 cvr-text-secondary align-top">
                                            <div class="leading-tight">{{ row[col + '_en'] || row[col] }}</div>
                                            <div v-if="row[col + '_ar']" class="leading-tight text-xs cvr-text-muted" dir="rtl">{{ row[col + '_ar'] }}</div>
                                        </td>
                                        <td v-else-if="col === 'due_status'" class="px-3 py-3 whitespace-nowrap font-semibold" :style="{ color: row.due_status ? row.due_status.color : undefined }">
                                            {{ row.is_paid ? '-' : (row.due_status ? row.due_status.status : '') }}
                                        </td>
                                        <td v-else class="px-3 py-3 cvr-text-secondary whitespace-nowrap">
                                            {{ row[col] }}
                                        </td>
                                    </template>
                                    <td class="px-3 py-3 cvr-num">{{ row.paid_amount_formatted }}</td>
                                    <td class="px-3 py-3 cvr-text-primary">{{ row.currency }}</td>
                                    <td v-if="canUpdate || canDelete || canMarkAsPaid" class="px-3 py-3">
                                        <div class="flex items-center gap-2">
                                            <RecordLogButton subject="CashExpense" :id="row.id" :company-id="company.id" />
                                            <button v-if="row.user_comment" @click="commentTarget = row" class="cvr-action-btn" :title="$t('User Comment')">💬</button>
                                            <button v-if="row.has_odoo_error" @click="odooErrorTarget = row" class="cvr-action-btn-danger cvr-action-btn" :title="$t('Odoo Error')">🐞</button>
                                            <button v-if="row.is_fully_integrated_with_odoo" @click="odooRefTarget = row" class="cvr-action-btn" :title="$t('Fully Integrated')">👍</button>
                                            <Link v-if="canUpdate && row.edit_url" :href="row.edit_url" class="cvr-action-btn" :title="$t('Edit')">✏️</Link>
                                            <!--
                                                Copy — opens the CREATE form already filled in from
                                                this row, ready to save as a new expense. Gated by
                                                canCreate (what it leads to), not canUpdate: it
                                                never changes the row it was opened from.
                                            -->
                                            <Link v-if="canCreate && row.copy_url" :href="row.copy_url" class="cvr-action-btn" :title="$t('Copy')">📋</Link>
                                            <button
                                                v-if="canMarkAsPaid && tab.hasBatchCollection && row.can_mark_paid"
                                                @click="openMarkPaidModalForRow(type, row.id)"
                                                class="cvr-action-btn"
                                                :title="$t('Mark As Paid')"
                                            >💵</button>
                                            <button
                                                v-if="canMarkAsPaid && type === TYPES.PAYABLE_CHEQUE && row.can_unmark_paid"
                                                @click="unmarkTarget = row"
                                                class="cvr-action-btn"
                                                :title="$t('Mark As Unpaid')"
                                            >↩️</button>
                                            <button v-if="canDelete && row.delete_url" @click="confirmDelete(row)" class="cvr-action-btn" :title="$t('Delete')">🗑️</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="tab.rows.data.length === 0">
                                    <td :colspan="8 + columnsByType[type].length" class="px-4 py-8 text-center cvr-text-muted">
                                        {{ $t('No') }} {{ tab.label.toLowerCase() }} {{ $t('found.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="tab.rows.last_page > 1" class="flex items-center justify-between mt-4 flex-wrap gap-3">
                        <p class="text-xs cvr-text-muted">
                            {{ $t('Showing') }} {{ tab.rows.from }}–{{ tab.rows.to }} {{ $t('of') }} {{ tab.rows.total }}
                        </p>
                        <div class="flex items-center gap-1 flex-wrap">
                            <button
                                v-for="(link, i) in tab.rows.links"
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
            </template>

            <!-- Mark As Paid modal -->
            <div v-if="markPaidTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ $t('Mark') }} {{ markPaidTarget.ids.length }} {{ $t('item(s) as paid?') }}
                    </h2>
                    <label class="cvr-form-label">{{ $t('Actual Payment Date') }}</label>
                    <input v-model="actualPaymentDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded mb-4" />
                    <div class="flex justify-end gap-2">
                        <button @click="markPaidTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="confirmMarkPaid" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm') }}</button>
                    </div>
                </div>
            </div>

            <!-- Return paid cheque to unpaid -->
            <div v-if="unmarkTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-2">{{ $t('Return this cheque to unpaid?') }}</h2>
                    <p class="text-sm cvr-text-secondary mb-4">
                        {{ $t('The bank statement date will move back to the cheque due date, reversing the paid-date movement.') }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <button @click="unmarkTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="confirmUnmarkPaid" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm') }}</button>
                    </div>
                </div>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Do you want to delete this item?') }}</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>

            <!-- User comment modal -->
            <div v-if="commentTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('User Comment') }}</h2>
                    <p class="cvr-text-secondary whitespace-pre-wrap">{{ commentTarget.user_comment }}</p>
                    <div class="flex justify-end mt-4">
                        <button @click="commentTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>

            <!-- Odoo error modal -->
            <div v-if="odooErrorTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Odoo Error') }}</h2>
                    <p class="cvr-text-secondary mb-4">{{ odooErrorTarget.odoo_error }}</p>
                    <div class="flex justify-end mt-4">
                        <button @click="odooErrorTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>

            <!-- Odoo references modal -->
            <div v-if="odooRefTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Odoo References') }}</h2>
                    <ul class="list-disc ps-5 cvr-text-secondary">
                        <li v-for="(ref, i) in odooRefTarget.odoo_reference_names" :key="i">{{ ref }}</li>
                    </ul>
                    <div class="flex justify-end mt-4">
                        <button @click="odooRefTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>