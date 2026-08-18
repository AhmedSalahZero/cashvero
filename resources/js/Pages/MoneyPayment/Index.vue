<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';
import { todayDate } from '@/composables/today';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate();

const page = usePage();
const errors = computed(() => page.props.errors || {});

const props = defineProps({
    company: Object,
    activeTab: String,
    tabs: Object,          // keyed by tab type — see MoneyPaymentController::tabDefinitions()
    filterDates: Object,
    search: Object,        // { field, value, from, to } — applies to whichever tab is active
    financialInstitutionBanks: Array,
    accountTypes: Array,
    permissions: Object,
    companyHasOdoo: Boolean,
    urls: Object,
});

/* ── Tabs ─────────────────────────────────────────────────────────
   Only 3, matching the original exactly — Money Payment has no
   cheque-collection sub-lifecycle (Under Collection/Collected/
   Rejected) the way Money Received does; a payable cheque goes
   straight from "payable" to "paid" in one step. */
const tabOrder = ['payable_cheque', 'outgoing-transfer', 'cash_payment', 'leasing_payment'];

function switchTab(key) {
    if (key === props.activeTab) return;
    router.get(props.urls.index, { active: key }, { preserveScroll: true });
}

const currentTab = computed(() => props.tabs[props.activeTab] || {});
const rows = computed(() => currentTab.value.paginator?.data || []);

/* ── Batch "Mark As Paid" — Payable Cheques only. The Outgoing
   Transfer tab's equivalent batch action is NOT wired here on
   purpose: its route in the original app (outgoing.transfer.mark.as
   .paid) points at a controller method that doesn't exist, and the
   original's own checkbox column for that tab is commented out too
   — confirmed already-disabled, not a gap this migration introduces.
   See MoneyPaymentController's class docblock. */
const hasBatchMarkAsPaid = computed(() =>
    props.activeTab === 'payable_cheque' && Boolean(props.permissions.canMarkAsPaid));
const selectedIds = ref([]);
watch(() => props.activeTab, () => { selectedIds.value = []; });
function toggleSelect(id) {
    const i = selectedIds.value.indexOf(id);
    if (i === -1) selectedIds.value.push(id); else selectedIds.value.splice(i, 1);
}

/* ── Date-range filter (per tab, server-side) ────────────────────── */
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

/* ── Field search (per tab, server-side) ─────────────────────────── */
const showFilter = ref(false);
const filterField = ref(props.search?.field || '');
const filterValue = ref(props.search?.value || '');
const filterFrom = ref(props.search?.from || '');
const filterTo = ref(props.search?.to || '');
const isDateField = computed(() => ['delivery_date', 'due_date'].includes(filterField.value));

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

/* ── User comment / Odoo error display ───────────────────────────
   No "Resend" action here — see class docblock on the controller:
   the shared Odoo-error modal's Resend button is hard-wired to a
   Money Received route and 404s for Money Payment records in the
   original app too. Comment and error text still display fine. */
const commentTarget = ref(null);
const odooErrorTarget = ref(null);
const integratedTarget = ref(null);

/* ── Mark As Paid (single or batch) ───────────────────────────────
   Only one field needed — Actual Payment Date — since the bank/
   account are already fixed on the cheque at creation, unlike Money
   Received's cheque-collection modal which needs bank/account/type. */
const markPaidTarget = ref(null); // { ids: [...], maxDueDate }
const actualPaymentDate = ref('');

function openMarkAsPaid(row) {
    markPaidTarget.value = { ids: [row.id], maxDueDate: row.due_date || null };
    actualPaymentDate.value = '';
}
function openBatchMarkAsPaid() {
    if (!selectedIds.value.length) return;
    const selectedRows = rows.value.filter(r => selectedIds.value.includes(r.id));
    const maxDueDate = selectedRows.reduce((max, r) => (!max || (r.due_date && r.due_date > max) ? r.due_date : max), null);
    markPaidTarget.value = { ids: [...selectedIds.value], maxDueDate };
    actualPaymentDate.value = '';
}
// Live check mirroring the server's own rule exactly (see
// MarkChequeAsPaidRequest): payment date must be >= the LATEST due
// date among the selected cheques.
const paymentDateWarning = computed(() => {
    if (!actualPaymentDate.value || !markPaidTarget.value?.maxDueDate) return null;
    if (actualPaymentDate.value < markPaidTarget.value.maxDueDate) {
        return `Payment Date must be on or after the cheque's Due Date (${markPaidTarget.value.maxDueDate}).`;
    }
    return null;
});
function submitMarkAsPaid() {
    router.post(props.urls.markChequesAsPaid, {
        cheques: markPaidTarget.value.ids,
        actual_payment_date: actualPaymentDate.value,
    }, {
        onFinish: () => { markPaidTarget.value = null; selectedIds.value = []; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Money Payment</h1>
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
                    <Link :href="urls.createMoneyPayment" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                        + Money Payment
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

            <!-- Batch Mark As Paid -->
            <div v-if="hasBatchMarkAsPaid" class="mb-3">
                <button
                    @click="openBatchMarkAsPaid"
                    :disabled="!selectedIds.length"
                    class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm"
                    :class="{ 'opacity-40 cursor-not-allowed': !selectedIds.length }"
                >
                    📖 Batch Mark As Paid ({{ selectedIds.length }})
                </button>
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th v-if="hasBatchMarkAsPaid" class="px-4 py-3 text-left">Select</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th v-if="activeTab === 'payable_cheque'" class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Supplier Name</th>
                            <th class="px-4 py-3 text-left">Payment Date</th>

                            <template v-if="activeTab === 'payable_cheque'">
                                <th class="px-4 py-3 text-left">Cheque Number</th>
                                <th class="px-4 py-3 text-left">Cheque Amount</th>
                                <th class="px-4 py-3 text-left">Currency</th>
                                <th class="px-8 py-3 text-left">Payment Bank</th>
                                <th class="px-4 py-3 text-left">Account Type</th>
                                <th class="px-4 py-3 text-left">Account No</th>
                                <th class="px-4 py-3 text-left">Due Date</th>
                                <th class="px-4 py-3 text-left">Due After Days</th>
                                <th class="px-4 py-3 text-left">Status</th>
                            </template>

                            <template v-else-if="activeTab === 'outgoing-transfer'">
                                <th class="px-4 py-3 text-left">Payment Bank</th>
                                <th class="px-4 py-3 text-left">Transfer Amount</th>
                                <th class="px-4 py-3 text-left">Currency</th>
                                <th class="px-4 py-3 text-left">Account Type</th>
                                <th class="px-4 py-3 text-left">Account Number</th>
                            </template>

                            <template v-else-if="activeTab === 'cash_payment'">
                                <th class="px-4 py-3 text-left">Branch</th>
                                <th class="px-4 py-3 text-left">Payment Amount</th>
                                <th class="px-4 py-3 text-left">Currency</th>
                                <th class="px-4 py-3 text-left">Receipt Number</th>
                            </template>

                            <!-- No bank / account type / account number here:
                                 the leasing company is the paying party. -->
                            <template v-else-if="activeTab === 'leasing_payment'">
                                <th class="px-4 py-3 text-left">Leasing Company</th>
                                <th class="px-4 py-3 text-left">Contract Name</th>
                                <th class="px-4 py-3 text-left">Paid Amount</th>
                                <th class="px-4 py-3 text-left">Currency</th>
                            </template>

                            <th class="px-4 py-3 text-left">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id" class="cvr-table-row">
                            <td v-if="hasBatchMarkAsPaid" class="px-4 py-3">
                                <input type="checkbox" :checked="selectedIds.includes(row.id)" @change="toggleSelect(row.id)" />
                            </td>
                            <td class="px-4 py-3 cvr-text-secondary">{{ row.type_formatted }}</td>
                            <td v-if="activeTab === 'payable_cheque'" class="px-4 py-3" :class="row.is_paid ? 'font-semibold text-green-600' : 'cvr-text-secondary'">{{ row.status_formatted }}</td>
                            <td class="px-4 py-3 cvr-text-primary">{{ row.partner_name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.delivery_date_formatted }}</td>

                            <template v-if="activeTab === 'payable_cheque'">
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.cheque_number }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.paid_amount_formatted }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary min-w-64">{{ row.payment_bank_name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_type_name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_number }}</td>
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.due_date_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.due_after_days }}</td>
                                <td class="px-4 py-3 font-semibold" :style="{ color: row.due_status?.color }">
                                    {{ row.is_paid ? '-' : row.due_status?.status }}
                                </td>
                            </template>

                            <template v-else-if="activeTab === 'outgoing-transfer'">
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.payment_bank_name }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.paid_amount_formatted }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_type_name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.account_number }}</td>
                            </template>

                            <template v-else-if="activeTab === 'cash_payment'">
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.branch_name }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.paid_amount_formatted }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.receipt_number }}</td>
                            </template>

                            <template v-else-if="activeTab === 'leasing_payment'">
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.leasing_company_name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.leasing_contract_name }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.paid_amount_formatted }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency_formatted }}</td>
                            </template>

                            <!-- Control -->
                            <td class="px-4 py-3 min-w-32">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <RecordLogButton subject="MoneyPayment" :id="row.id" :company-id="company.id" />
                                    <button v-if="row.has_comment" @click="commentTarget = row" class="cvr-action-btn" title="User Comment">💬</button>
                                    <button v-if="row.has_odoo_error" @click="odooErrorTarget = row" class="cvr-action-btn-danger cvr-action-btn" title="Odoo Error">🐞</button>
                                    <button v-if="row.is_fully_integrated_with_odoo" @click="integratedTarget = row" class="cvr-action-btn" title="Fully Integrated">👍</button>

                                    <template v-if="activeTab === 'payable_cheque'">
                                        <Link v-if="!row.is_open_balance" :href="row.edit_url" class="cvr-action-btn" title="Edit Cheque">✏️</Link>
                                        <button v-if="permissions.canMarkAsPaid && row.is_due" @click="openMarkAsPaid(row)" class="cvr-action-btn" title="Mark As Paid">🏦</button>
                                        <button v-if="!row.is_open_balance && permissions.canDelete" @click="deleteTarget = row" class="cvr-action-btn-danger cvr-action-btn" title="Delete">🗑️</button>
                                    </template>

                                    <template v-else>
                                        <template v-if="!row.is_open_balance">
                                            <Link v-if="permissions.canUpdate" :href="row.edit_url" class="cvr-action-btn" title="Edit">✏️</Link>
                                            <button v-if="permissions.canDelete" @click="deleteTarget = row" class="cvr-action-btn-danger cvr-action-btn" title="Delete">🗑️</button>
                                        </template>
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

            <!-- Odoo error (display only — no Resend, see docblock) -->
            <div v-if="odooErrorTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-lg">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Odoo Error</h2>
                    <p class="cvr-text-secondary mb-4">{{ odooErrorTarget.odoo_error }}</p>
                    <div class="flex justify-end">
                        <button @click="odooErrorTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
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

            <!-- Mark As Paid (single / batch) -->
            <div v-if="markPaidTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        Mark {{ markPaidTarget.ids.length > 1 ? 'these cheques' : 'this cheque' }} as paid?
                    </h2>
                    <div class="mb-4">
                        <label class="cvr-form-label">Actual Payment Date *</label>
                        <input v-model="actualPaymentDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="paymentDateWarning" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ paymentDateWarning }}</p>
                        <p v-else-if="errors.actual_payment_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.actual_payment_date }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="markPaidTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitMarkAsPaid" :disabled="!!paymentDateWarning" class="cvr-btn-primary px-3 py-1.5 rounded" :class="{ 'opacity-40 cursor-not-allowed': paymentDateWarning }">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
