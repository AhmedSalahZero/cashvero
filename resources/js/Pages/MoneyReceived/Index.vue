<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const errors = computed(() => page.props.errors || {});

const props = defineProps({
    company: Object,
    activeTab: String,
    tabs: Object,          // keyed by tab type — see MoneyReceivedController::tabDefinitions()
    filterDates: Object,
    search: Object,        // { field, value, from, to } — applies to whichever tab is active
    financialInstitutionBanks: Array,
    accountTypes: Array,
    permissions: Object,
    companyHasOdoo: Boolean,
    urls: Object,
});

/* ── Tabs ─────────────────────────────────────────────────────────
   Order matches the original Blade page's nav-tabs order exactly.
   Only the ACTIVE tab's rows are sent from the server (see the
   controller docblock) — switching tabs is a real Inertia visit,
   not a client-side toggle, since the other 6 tabs' row data isn't
   loaded. Their count/total badges are still accurate (computed
   server-side from the full, unpaginated query). */
const tabOrder = [
    'cheque',
    'cheque-under-collection',
    'cheque-collected',
    'cheque-rejected',
    'incoming-transfer',
    'cash-in-safe',
    'cash-in-bank',
];

function switchTab(key) {
    if (key === props.activeTab) return;
    router.get(props.urls.index, { active: key }, { preserveScroll: true });
}

const currentTab = computed(() => props.tabs[props.activeTab] || {});
const rows = computed(() => currentTab.value.paginator?.data || []);

/* ── Batch-collection checkbox selection ─────────────────────────
   Only meaningful on the two tabs that support it (matches the
   original's has-batch-collection=1 on Cheques In Safe / Rejected
   Cheques only). Cleared whenever the tab changes. */
const hasBatchCollection = computed(() => ['cheque', 'cheque-rejected'].includes(props.activeTab));
const selectedIds = ref([]);
watch(() => props.activeTab, () => { selectedIds.value = []; });
function toggleSelect(id) {
    const i = selectedIds.value.indexOf(id);
    if (i === -1) selectedIds.value.push(id); else selectedIds.value.splice(i, 1);
}

/* ── Date-range filter (per tab, server-side) ────────────────────
   Mirrors the original x-table-title.with-two-dates component:
   defaults to an 18-month rolling window unless changed. */
const fromDate = ref(props.filterDates?.[props.activeTab]?.startDate || '');
const toDate = ref(props.filterDates?.[props.activeTab]?.endDate || '');
watch(() => props.activeTab, (tab) => {
    fromDate.value = props.filterDates?.[tab]?.startDate || '';
    toDate.value = props.filterDates?.[tab]?.endDate || '';
});
function applyDateFilter() {
    router.get(props.urls.index, {
        active: props.activeTab,
        startDate: { [props.activeTab]: fromDate.value },
        endDate: { [props.activeTab]: toDate.value },
    }, { preserveScroll: true });
}

/* ── Field search (per tab, server-side) ──────────────────────────
   The original's "Filter" modal — field dropdown + free-text value,
   or a from/to range for date-type fields. Real, server-side search
   (implemented deep in each Company::getReceivedXxx() query builder,
   gated to the active tab only) — not simplified away here. Shown
   as an inline collapsible bar rather than a Bootstrap modal. */
const showFilter = ref(false);
const filterField = ref(props.search?.field || '');
const filterValue = ref(props.search?.value || '');
const filterFrom = ref(props.search?.from || '');
const filterTo = ref(props.search?.to || '');
const isDateField = computed(() => ['receiving_date', 'due_date', 'deposit_date'].includes(filterField.value));

function applySearch() {
    router.get(props.urls.index, {
        active: props.activeTab,
        field: filterField.value || undefined,
        value: isDateField.value ? undefined : filterValue.value,
        from: isDateField.value ? filterFrom.value : undefined,
        to: isDateField.value ? filterTo.value : undefined,
    }, { preserveScroll: true });
}
function resetSearch() {
    filterField.value = '';
    filterValue.value = '';
    filterFrom.value = '';
    filterTo.value = '';
    router.get(props.urls.index, { active: props.activeTab }, { preserveScroll: true });
}

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveScroll: true });
}

/* ── Delete confirmation ──────────────────────────────────────── */
const deleteTarget = ref(null);
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

/* ── Review confirmation ──────────────────────────────────────── */
const reviewTarget = ref(null);
function confirmReview() {
    router.post(reviewTarget.value.review_url, {
        model_name: 'MoneyReceived',
        table_name: 'money_received',
    }, { onFinish: () => { reviewTarget.value = null; } });
}

/* ── User comment / Odoo error / Odoo references modals ─────────── */
const commentTarget = ref(null);
const odooErrorTarget = ref(null);
const integratedTarget = ref(null);
function resendOdoo() {
    router.post(odooErrorTarget.value.resend_odoo_url, {}, { onFinish: () => { odooErrorTarget.value = null; } });
}

/* ── Send To Under Collection (single row or batch) ──────────────
   Posts to the shared cheque.send.to.collection route with either
   one id or every currently-selected id. Account Number is a real,
   server-side lookup (existing AJAX endpoints, not "fetch everything
   up front") because the list can be long and is scoped by bank +
   account type + currency, same pattern already proven on Fully
   Secured Overdraft's CD/TD picker.
   NOTE — deliberate simplification: when batch-sending, every
   selected cheque is assumed to share one currency (the first
   selected row's currency is used for the account-number lookup),
   matching what the original page's single hidden `current-currency`
   field effectively did. */
const collectionTarget = ref(null); // { mode: 'single'|'batch', ids: [...], currency }
const collectionForm = ref({ deposit_date: '', drawl_bank_id: '', account_type: '', account_number: '', clearance_days: 0 });
const collectionAccountNumbers = ref([]);
const collectionBalance = ref({ balance: 0, net_balance: 0, balance_date: '', net_balance_date: '' });

async function openSendToCollection(row) {
    collectionTarget.value = { mode: 'single', ids: [row.id], currency: row.currency, maxReceivingDate: row.receiving_date };
    collectionForm.value = {
        deposit_date: row.deposit_date || '',
        drawl_bank_id: row.drawl_bank_id || '',
        account_type: row.account_type || '',
        account_number: row.account_number || '',
        clearance_days: row.clearance_days ?? 0,
    };
    collectionAccountNumbers.value = [];
    collectionBalance.value = { balance: 0, net_balance: 0, balance_date: '', net_balance_date: '' };
    if (collectionForm.value.account_type && collectionForm.value.drawl_bank_id && collectionTarget.value.currency) {
        const url = `${props.urls.accountNumbersForType}/${collectionForm.value.account_type}/${collectionTarget.value.currency}/${collectionForm.value.drawl_bank_id}`;
        const res = await fetch(url);
        const data = await res.json();
        collectionAccountNumbers.value = Object.values(data?.data || {});
        if (collectionForm.value.account_number) {
            onCollectionAccountNumberChange();
        }
    }
}
function openBatchSendToCollection() {
    if (!selectedIds.value.length) return;
    const selectedRows = rows.value.filter(r => selectedIds.value.includes(r.id));
    const firstRow = selectedRows[0];
    // Matches the server's own check exactly: deposit date must be >=
    // the LATEST receiving date among every cheque in the batch.
    const maxReceivingDate = selectedRows.reduce((max, r) => (!max || r.receiving_date > max ? r.receiving_date : max), null);
    collectionTarget.value = { mode: 'batch', ids: [...selectedIds.value], currency: firstRow?.currency, maxReceivingDate };
    collectionForm.value = { deposit_date: '', drawl_bank_id: '', account_type: '', account_number: '', clearance_days: 0 };
    collectionAccountNumbers.value = [];
    collectionBalance.value = { balance: 0, net_balance: 0, balance_date: '', net_balance_date: '' };
}
// Live check, mirroring the server's DateMustBeGreaterThanOrEqualDate
// rule exactly (see SendToUnderCollectionChequeRequest) — catches the
// mistake before submitting rather than only after a round trip.
const depositDateWarning = computed(() => {
    if (!collectionForm.value.deposit_date || !collectionTarget.value?.maxReceivingDate) return null;
    if (collectionForm.value.deposit_date < collectionTarget.value.maxReceivingDate) {
        return `Deposit Date must be on or after the Receiving Date (${collectionTarget.value.maxReceivingDate}).`;
    }
    return null;
});
async function onCollectionAccountTypeChange() {
    collectionForm.value.account_number = '';
    collectionAccountNumbers.value = [];
    if (!collectionForm.value.account_type || !collectionForm.value.drawl_bank_id || !collectionTarget.value?.currency) return;
    const url = `${props.urls.accountNumbersForType}/${collectionForm.value.account_type}/${collectionTarget.value.currency}/${collectionForm.value.drawl_bank_id}`;
    const res = await fetch(url);
    const data = await res.json();
    collectionAccountNumbers.value = Object.values(data?.data || {});
}
async function onCollectionAccountNumberChange() {
    collectionBalance.value = { balance: 0, net_balance: 0, balance_date: '', net_balance_date: '' };
    if (!collectionForm.value.account_number) return;
    const params = new URLSearchParams({
        accountType: collectionForm.value.account_type,
        accountNumber: collectionForm.value.account_number,
        financialInstitutionId: collectionForm.value.drawl_bank_id,
    });
    const res = await fetch(`${props.urls.balanceForAccountNumber}?${params.toString()}`);
    collectionBalance.value = await res.json();
}
function submitSendToCollection() {
    router.post(props.urls.sendToCollection, {
        cheques: collectionTarget.value.ids,
        deposit_date: collectionForm.value.deposit_date,
        drawl_bank_id: collectionForm.value.drawl_bank_id,
        account_type: collectionForm.value.account_type,
        account_number: collectionForm.value.account_number,
        clearance_days: collectionForm.value.clearance_days,
    }, {
        onFinish: () => { collectionTarget.value = null; selectedIds.value = []; },
    });
}

/* ── Apply Collection (Cheques Under Collection tab only) ────────── */
const applyCollectionTarget = ref(null);
const applyCollectionDate = ref('');
function openApplyCollection(row) {
    applyCollectionTarget.value = row;
    applyCollectionDate.value = '';
}
function submitApplyCollection() {
    router.post(applyCollectionTarget.value.apply_collection_url, {
        actual_collection_date: applyCollectionDate.value,
    }, { onFinish: () => { applyCollectionTarget.value = null; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Money Received</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ company.name }}</p>

            <!-- KPI cards (active tab) -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">📄</div>
                    <div>
                        <p class="cvr-kpi-label">Records</p>
                        <p class="cvr-kpi-value">{{ currentTab.totalCount }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">💰</div>
                    <div>
                        <p class="cvr-kpi-label">Total Amount</p>
                        <p class="cvr-kpi-value">{{ Number(currentTab.totalAmount || 0).toLocaleString() }}</p>
                    </div>
                </div>
            </div>

            <!-- Tabs + create buttons -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="flex items-center gap-1 flex-wrap">
                    <button
                        v-for="key in tabOrder"
                        :key="key"
                        @click="switchTab(key)"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': activeTab === key }"
                    >
                        {{ tabs[key]?.label }}
                    </button>
                </div>
                <div v-if="permissions.canCreate" class="flex items-center gap-2">
                    <Link :href="urls.createMoneyReceived" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                        + Money Received
                    </Link>
                    <Link :href="urls.createDownPayment" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm inline-flex items-center gap-1">
                        + Down Payment
                    </Link>
                </div>
            </div>

            <!-- Date range + Filter -->
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="cvr-form-label">Start Date</label>
                    <input v-model="fromDate" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">End Date</label>
                    <input v-model="toDate" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <button @click="applyDateFilter" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">Submit</button>

                <button @click="showFilter = !showFilter" class="cvr-btn-secondary px-3 py-2 rounded border text-sm ml-auto">
                    🔍 Filter
                </button>
            </div>

            <div v-if="showFilter" class="cvr-card-bg cvr-border border rounded-lg p-4 mb-4 cvr-form-grid-4">
                <div>
                    <label class="cvr-form-label">Field Name</label>
                    <select v-model="filterField" class="cvr-input w-full px-3 py-2 rounded">
                        <option value="">Select</option>
                        <option v-for="(label, key) in currentTab.searchFields" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div v-if="!isDateField">
                    <label class="cvr-form-label">Search Text</label>
                    <input v-model="filterValue" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                </div>
                <template v-else>
                    <div>
                        <label class="cvr-form-label">From</label>
                        <input v-model="filterFrom" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">To</label>
                        <input v-model="filterTo" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </template>
                <div class="flex items-end gap-2">
                    <button @click="applySearch" class="cvr-btn-primary px-3 py-2 rounded text-sm">Search</button>
                    <button @click="resetSearch" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">Reset</button>
                </div>
            </div>

            <!-- Batch send to collection -->
            <div v-if="hasBatchCollection" class="mb-3">
                <button
                    @click="openBatchSendToCollection"
                    :disabled="!selectedIds.length"
                    class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm"
                    :class="{ 'opacity-40 cursor-not-allowed': !selectedIds.length }"
                >
                    📖 Batch Send To Collection ({{ selectedIds.length }})
                </button>
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th v-if="hasBatchCollection" class="px-4 py-3 text-left">Select</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Customer Name</th>

                            <template v-if="['cheque', 'cheque-rejected'].includes(activeTab)">
                                <th class="px-4 py-3 text-left">Receiving Date</th>
                                <th class="px-4 py-3 text-left">Cheque Number</th>
                                <th class="px-4 py-3 text-left">Cheque Amount</th>
                                <th class="px-4 py-3 text-left">Currency</th>
                                <th class="px-4 py-3 text-left">Drawee Bank</th>
                                <th class="px-4 py-3 text-left">Due Date</th>
                                <th v-if="activeTab === 'cheque'" class="px-4 py-3 text-left">Due After Days</th>
                                <th class="px-4 py-3 text-left">Status</th>
                            </template>

                            <template v-else-if="activeTab === 'cheque-under-collection'">
                                <th class="px-4 py-3 text-left">Cheque Number</th>
                                <th class="px-4 py-3 text-left">Cheque Amount</th>
                                <th class="px-4 py-3 text-left">Deposit Date</th>
                                <th class="px-4 py-3 text-left">Drawal Bank</th>
                                <th class="px-4 py-3 text-left">Account Type</th>
                                <th class="px-4 py-3 text-left">Account Number</th>
                                <th class="px-4 py-3 text-left">Cheque Due Date</th>
                                <th class="px-4 py-3 text-left">Clearance Days</th>
                                <th class="px-4 py-3 text-left">Expected Collection Date</th>
                                <th class="px-4 py-3 text-left">Status</th>
                            </template>

                            <template v-else-if="activeTab === 'cheque-collected'">
                                <th class="px-4 py-3 text-left">Cheque Number</th>
                                <th class="px-4 py-3 text-left">Cheque Amount</th>
                                <th class="px-4 py-3 text-left">Due Date</th>
                                <th class="px-4 py-3 text-left">Deposit Date</th>
                                <th class="px-4 py-3 text-left">Drawal Bank</th>
                                <th class="px-4 py-3 text-left">Account Type</th>
                                <th class="px-4 py-3 text-left">Account Number</th>
                                <th class="px-4 py-3 text-left">Actual Collection Date</th>
                            </template>

                            <template v-else-if="activeTab === 'incoming-transfer'">
                                <th class="px-4 py-3 text-left">Receiving Date</th>
                                <th class="px-4 py-3 text-left">Receiving Bank</th>
                                <th class="px-4 py-3 text-left">Transfer Amount</th>
                                <th class="px-4 py-3 text-left">Currency</th>
                                <th class="px-4 py-3 text-left">Account Type</th>
                                <th class="px-4 py-3 text-left">Account Number</th>
                            </template>

                            <template v-else-if="activeTab === 'cash-in-safe'">
                                <th class="px-4 py-3 text-left">Receiving Date</th>
                                <th class="px-4 py-3 text-left">Branch</th>
                                <th class="px-4 py-3 text-left">Received Amount</th>
                                <th class="px-4 py-3 text-left">Currency</th>
                                <th class="px-4 py-3 text-left">Receipt Number</th>
                            </template>

                            <template v-else-if="activeTab === 'cash-in-bank'">
                                <th class="px-4 py-3 text-left">Receiving Date</th>
                                <th class="px-4 py-3 text-left">Receiving Bank</th>
                                <th class="px-4 py-3 text-left">Deposit Amount</th>
                                <th class="px-4 py-3 text-left">Currency</th>
                                <th class="px-4 py-3 text-left">Account Type</th>
                                <th class="px-4 py-3 text-left">Account Number</th>
                            </template>

                            <th class="px-4 py-3 text-left">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id" class="cvr-table-row">
                            <td v-if="hasBatchCollection" class="px-4 py-3">
                                <input type="checkbox" :checked="selectedIds.includes(row.id)" @change="toggleSelect(row.id)" />
                            </td>
                            <td class="px-4 py-3 cvr-text-secondary">{{ row.type_formatted }}</td>
                            <td class="px-4 py-3 cvr-text-primary">{{ row.customer_name }}</td>

                            <template v-if="['cheque', 'cheque-rejected'].includes(activeTab)">
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.receiving_date_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.cheque_number }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.received_amount_formatted }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.drawee_bank_name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.due_date_formatted }}</td>
                                <td v-if="activeTab === 'cheque'" class="px-4 py-3 cvr-text-secondary">{{ row.due_after_days }}</td>
                                <td class="px-4 py-3 font-semibold" :style="{ color: row.due_status?.color }">
                                    {{ activeTab === 'cheque' ? row.due_status?.status : row.status_formatted }}
                                </td>
                            </template>

                            <template v-else-if="activeTab === 'cheque-under-collection'">
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.cheque_number }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.received_amount_formatted }} {{ row.currency }}</td>
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.deposit_date_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.drawl_bank_name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_type_name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_number }}</td>
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.due_date_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.clearance_days }}</td>
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.expected_collection_date_formatted }}</td>
                                <td class="px-4 py-3 font-semibold" :style="{ color: row.due_status?.color }">{{ row.due_status?.status }}</td>
                            </template>

                            <template v-else-if="activeTab === 'cheque-collected'">
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.cheque_number }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.received_amount_formatted }}</td>
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.due_date_formatted }}</td>
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.deposit_date_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.drawl_bank_name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_type_name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_number }}</td>
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.actual_collection_date_formatted }}</td>
                            </template>

                            <template v-else-if="activeTab === 'incoming-transfer'">
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.receiving_date_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.receiving_bank_name }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.received_amount_formatted }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_type_name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_number }}</td>
                            </template>

                            <template v-else-if="activeTab === 'cash-in-safe'">
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.receiving_date_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.branch_name }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.received_amount_formatted }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.receipt_number }}</td>
                            </template>

                            <template v-else-if="activeTab === 'cash-in-bank'">
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.receiving_date_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.receiving_bank_name }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.received_amount_formatted }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_type_name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_number }}</td>
                            </template>

                            <!-- Control -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <button v-if="row.has_comment" @click="commentTarget = row" class="cvr-action-btn" title="User Comment">💬</button>
                                    <button v-if="row.has_odoo_error" @click="odooErrorTarget = row" class="cvr-action-btn-danger cvr-action-btn" title="Odoo Error">🐞</button>
                                    <button v-if="row.is_fully_integrated_with_odoo" @click="integratedTarget = row" class="cvr-action-btn" title="Fully Integrated">👍</button>

                                    <!-- Cheques In Safe -->
                                    <template v-if="activeTab === 'cheque'">
                                        <button v-if="permissions.canReview && !row.is_reviewed" @click="reviewTarget = row" class="cvr-action-btn" title="Reviewed">✅</button>
                                        <Link v-if="permissions.canUpdate && !row.is_open_balance" :href="row.edit_url" class="cvr-action-btn" title="Edit">✏️</Link>
                                        <button @click="openSendToCollection(row)" class="cvr-action-btn" title="Send Under Collection">🏦</button>
                                        <button v-if="permissions.canDelete" @click="deleteTarget = row" class="cvr-action-btn-danger cvr-action-btn" title="Delete">🗑️</button>
                                    </template>

                                    <!-- Rejected Cheques (no Edit — matches original, which had it commented out) -->
                                    <template v-else-if="activeTab === 'cheque-rejected'">
                                        <button v-if="permissions.canReview && !row.is_reviewed && !row.is_open_balance" @click="reviewTarget = row" class="cvr-action-btn" title="Reviewed">✅</button>
                                        <button @click="openSendToCollection(row)" class="cvr-action-btn" title="Send Under Collection">🏦</button>
                                        <button v-if="permissions.canDelete && !row.is_open_balance" @click="deleteTarget = row" class="cvr-action-btn-danger cvr-action-btn" title="Delete">🗑️</button>
                                    </template>

                                    <!-- Cheques Under Collection -->
                                    <template v-else-if="activeTab === 'cheque-under-collection'">
                                        <button v-if="permissions.canUpdate && !row.is_open_balance" @click="openSendToCollection(row)" class="cvr-action-btn" title="Edit Deposit Info">✏️</button>
                                        <button v-if="row.due_status_bool" @click="openApplyCollection(row)" class="cvr-action-btn" title="Apply Collection">🪙</button>
                                        <Link :href="row.send_to_safe_url" class="cvr-action-btn" title="Send In Safe">↩️</Link>
                                        <Link v-if="row.due_status_bool && permissions.canDelete" :href="row.send_to_rejected_safe_url" class="cvr-action-btn-danger cvr-action-btn" title="Rejected">🚫</Link>
                                    </template>

                                    <!-- Collected Cheques -->
                                    <template v-else-if="activeTab === 'cheque-collected'">
                                        <Link :href="row.send_to_under_collection_url" class="cvr-action-btn" title="Under Collection">↩️</Link>
                                    </template>

                                    <!-- Incoming Transfer / Cash In Safe / Cash In Bank -->
                                    <template v-else>
                                        <Link v-if="permissions.canUpdate" :href="row.edit_url" class="cvr-action-btn" title="Edit">✏️</Link>
                                        <button v-if="permissions.canDelete" @click="deleteTarget = row" class="cvr-action-btn-danger cvr-action-btn" title="Delete">🗑️</button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="14" class="px-4 py-8 text-center cvr-text-muted">
                                No records found for this tab.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="currentTab.paginator?.last_page > 1" class="flex items-center justify-center gap-1 mt-4">
                <button
                    v-for="(link, i) in currentTab.paginator.links"
                    :key="i"
                    v-html="link.label"
                    :disabled="!link.url"
                    @click="goToPage(link.url)"
                    class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm"
                    :class="{ 'cvr-nav-item-active': link.active, 'opacity-40 cursor-not-allowed': !link.url }"
                ></button>
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

            <!-- Review confirmation -->
            <div v-if="reviewTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Mark this as reviewed?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="reviewTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="confirmReview" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- User comment -->
            <div v-if="commentTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">User Comment</h2>
                    <p class="cvr-text-secondary mb-4">{{ commentTarget.user_comment }}</p>
                    <div class="flex justify-end">
                        <button @click="commentTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                    </div>
                </div>
            </div>

            <!-- Odoo error -->
            <div v-if="odooErrorTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-lg">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Odoo Error</h2>
                    <p class="cvr-text-secondary mb-4">{{ odooErrorTarget.odoo_error }}</p>
                    <div class="flex justify-end gap-2">
                        <button @click="odooErrorTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="resendOdoo" class="cvr-btn-primary px-3 py-1.5 rounded">Resend</button>
                    </div>
                </div>
            </div>

            <!-- Odoo references -->
            <div v-if="integratedTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-blue mb-4">Odoo References</h2>
                    <ul class="list-disc list-inside cvr-text-secondary mb-4">
                        <li v-for="(ref, i) in integratedTarget.odoo_reference_names" :key="i">{{ ref }}</li>
                    </ul>
                    <div class="flex justify-end">
                        <button @click="integratedTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                    </div>
                </div>
            </div>

            <!-- Send To Under Collection (single / batch) -->
            <div v-if="collectionTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-2xl">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        Send {{ collectionTarget.ids.length > 1 ? 'these cheques' : 'this cheque' }} to under collection?
                    </h2>
                    <div class="cvr-form-grid-3 mb-4">
                        <div>
                            <label class="cvr-form-label">Cheque Deposit Date *</label>
                            <input v-model="collectionForm.deposit_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="depositDateWarning" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ depositDateWarning }}</p>
                            <p v-else-if="errors.deposit_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.deposit_date }}</p>
                        </div>
                        <div class="col-span-2">
                            <label class="cvr-form-label">Drawal Bank *</label>
                            <select v-model="collectionForm.drawl_bank_id" @change="onCollectionAccountTypeChange" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="bank in financialInstitutionBanks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Account Type *</label>
                            <select v-model="collectionForm.account_type" @change="onCollectionAccountTypeChange" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="type in accountTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Account Number *</label>
                            <select v-model="collectionForm.account_number" @change="onCollectionAccountNumberChange" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="num in collectionAccountNumbers" :key="num" :value="num">{{ num }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Balance <span class="cvr-text-muted text-xs">{{ collectionBalance.balance_date }}</span></label>
                            <input disabled :value="collectionBalance.balance" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Net Balance <span class="cvr-text-muted text-xs">{{ collectionBalance.net_balance_date }}</span></label>
                            <input disabled :value="collectionBalance.net_balance" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Clearance Days *</label>
                            <input v-model="collectionForm.clearance_days" type="number" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="collectionTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitSendToCollection" :disabled="!!depositDateWarning" class="cvr-btn-primary px-3 py-1.5 rounded" :class="{ 'opacity-40 cursor-not-allowed': depositDateWarning }">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Apply Collection -->
            <div v-if="applyCollectionTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-lg">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Mark this cheque as collected?</h2>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="cvr-form-label">Customer Name</label>
                            <input disabled :value="applyCollectionTarget.customer_name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Cheque Number</label>
                            <input disabled :value="applyCollectionTarget.cheque_number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Cheque Amount</label>
                            <input disabled :value="applyCollectionTarget.received_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Due Date</label>
                            <input disabled :value="applyCollectionTarget.due_date_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div class="col-span-2">
                            <label class="cvr-form-label">Collection Date *</label>
                            <input v-model="applyCollectionDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="applyCollectionTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitApplyCollection" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>