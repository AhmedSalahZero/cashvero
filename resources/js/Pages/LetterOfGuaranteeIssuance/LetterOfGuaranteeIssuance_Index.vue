<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    activeLgType: String,
    filterDates: Object,
    lgTypes: Object,     // { 'bid-bond': 'Bid Bond', ... }
    createUrls: Object,  // { 'lg-facility': url, 'against-cd': url, ... }
    tabDataUrl: String,
    tabs: Object,        // { 'bid-bond': { current_page, last_page, total, loaded, rows: [...] }, ... }
    navUrls: Object,
});

/*
 * ✅ PERFORMANCE FIX — only the active tab is ever queried on initial
 * load (see LetterOfGuaranteeIssuanceController::index()). The other
 * 3 tabs arrive as `loaded: false` placeholders and only get their
 * real data the first time the user actually clicks into them, via a
 * lightweight JSON fetch — not a full Inertia page reload. Once a tab
 * has been loaded, switching back to it is instant, no re-fetch.
 */
const tabsData = ref({ ...props.tabs });
const activeTab = ref(props.activeLgType);
const currentTab = computed(() => tabsData.value[activeTab.value] || { rows: [], current_page: 1, last_page: 1, total: 0, loaded: false });
const tabLoading = ref(false);

async function fetchTab(type, extra = {}) {
    tabLoading.value = true;
    try {
        const params = new URLSearchParams({ type, ...extra });
        const res = await fetch(`${props.tabDataUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
        tabsData.value[type] = await res.json();
    } finally {
        tabLoading.value = false;
    }
}

function switchTab(type) {
    activeTab.value = type;
    if (!tabsData.value[type]?.loaded) {
        fetchTab(type);
    }
}

function goToPage(page) {
    fetchTab(activeTab.value, { page });
}

/* ── Search — now a lightweight fetch against the active tab only,
   not a full page reload (matches the original's server-side search
   on Transaction Name / LG Code / dates). ────────────────────────── */
const searchField = ref('transaction_name');
const searchValue = ref('');
function applySearch() {
    fetchTab(activeTab.value, { field: searchField.value, value: searchValue.value });
}

/* ── Cancel modal ─────────────────────────────────────────────── */
const cancelTarget = ref(null);
const cancelForm = ref({ cancellation_date: '' });
function openCancel(row) {
    cancelTarget.value = row;
    cancelForm.value = { cancellation_date: row.renewal_date || '' };
}
function submitCancel() {
    router.post(cancelTarget.value.cancel_url, cancelForm.value, { onFinish: () => { cancelTarget.value = null; } });
}

/* ── Back To Running modal ───────────────────────────────────── */
const backToRunningTarget = ref(null);
function openBackToRunning(row) { backToRunningTarget.value = row; }
function submitBackToRunning() {
    router.post(backToRunningTarget.value.back_to_running_url, {}, { onFinish: () => { backToRunningTarget.value = null; } });
}

/* ── Advanced Payment modal — only for Advanced Payment LG type ── */
const advancedPaymentTarget = ref(null);
const newAdvancedPaymentForm = ref({ date: '', amount: 0 });
function openAdvancedPayment(row) {
    advancedPaymentTarget.value = row;
    newAdvancedPaymentForm.value = { date: new Date().toISOString().split('T')[0], amount: 0 };
}
function submitNewAdvancedPayment() {
    router.post(advancedPaymentTarget.value.apply_advanced_payment_url, newAdvancedPaymentForm.value, {
        onFinish: () => { advancedPaymentTarget.value = null; },
    });
}

const editAdvancedPaymentTarget = ref(null);
const editAdvancedPaymentForm = ref({ decrease_date: '', amount_to_be_decreased: 0 });
function openEditAdvancedPayment(history) {
    editAdvancedPaymentTarget.value = history;
    editAdvancedPaymentForm.value = { decrease_date: history.date, amount_to_be_decreased: history.amount };
}
function submitEditAdvancedPayment() {
    router.post(editAdvancedPaymentTarget.value.edit_url, editAdvancedPaymentForm.value, {
        onFinish: () => { editAdvancedPaymentTarget.value = null; },
    });
}

/*
 * ⚠️ The original delete-advanced-payment route is registered as a
 * GET request (a link inside a form in the old Blade version, not a
 * real form submission) — an existing quirk, not something introduced
 * here. router.get() matches that registered method exactly.
 */
const deleteAdvancedPaymentTarget = ref(null);
function confirmDeleteAdvancedPayment(history) { deleteAdvancedPaymentTarget.value = history; }
function destroyAdvancedPayment() {
    router.get(deleteAdvancedPaymentTarget.value.delete_url, {}, { onFinish: () => { deleteAdvancedPaymentTarget.value = null; } });
}

/* ── Delete confirmation ──────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

/* ── User Comment / Odoo References — simple read-only popovers ── */
const commentTarget = ref(null);
const odooTarget = ref(null);
const odooErrorTarget = ref(null);
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">LG Issuance</h1>
            <p class="text-sm cvr-text-blue mb-6">Letters Of Guarantee issued across all financial institutions</p>

            <!-- Tabs -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="flex items-center gap-1 flex-wrap">
                    <button
                        v-for="(label, type) in lgTypes"
                        :key="type"
                        @click="switchTab(type)"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': activeTab === type }"
                    >
                        {{ label }}
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="createUrls['lg-facility']" class="cvr-btn-copper px-3 py-1.5 rounded text-sm">+ Via LG Facility</Link>
                    <Link :href="createUrls['against-cd']" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">+ Against CD</Link>
                    <Link :href="createUrls['against-td']" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">+ Against TD</Link>
                    <Link :href="createUrls['hundred-percentage-cash-cover']" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">+ 100% Cash Cover</Link>
                </div>
            </div>

            <!-- Search -->
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="cvr-form-label">Search By</label>
                    <select v-model="searchField" class="cvr-input px-3 py-2 rounded">
                        <option value="transaction_name">Transaction Name</option>
                        <option value="lg_code">LG Code</option>
                    </select>
                </div>
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="searchValue" @keyup.enter="applySearch" type="text" placeholder="Search..." class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
                <button @click="applySearch" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">Search</button>
            </div>

            <!-- Table -->
            <div v-if="tabLoading" class="text-center py-3 text-sm cvr-text-muted">Loading...</div>
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
                            <th class="px-3 py-3 text-left">LG Code</th>
                            <th class="px-3 py-3 text-left">LG Amount</th>
                            <th v-if="activeTab === 'advanced-payment-lgs'" class="px-3 py-3 text-left">LG Current Amount</th>
                            <th class="px-3 py-3 text-left">Issuance Date</th>
                            <th class="px-3 py-3 text-left">Renewal Date</th>
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
                                <span
                                    class="cvr-badge"
                                    :class="row.is_cancelled || row.is_expired ? 'cvr-badge-overdue' : 'cvr-badge-active'"
                                >{{ row.status_formatted }}</span>
                            </td>
                            <td class="px-3 py-3 cvr-text-secondary max-w-[10rem] break-words">{{ row.bank_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.lg_code }}</td>
                            <td class="px-3 py-3 cvr-num">{{ row.lg_amount_formatted }}</td>
                            <td v-if="activeTab === 'advanced-payment-lgs'" class="px-3 py-3 cvr-num-green">{{ row.lg_current_amount_formatted }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.issuance_date_formatted }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.renewal_date_formatted }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button v-if="row.has_comment" @click="commentTarget = row" class="cvr-action-btn" title="User Comment">💬</button>
                                    <button v-if="row.fully_integrated_with_odoo" @click="odooTarget = row" class="cvr-action-btn" title="Odoo References">👍</button>
                                    <button v-if="row.has_odoo_error" @click="odooErrorTarget = row" class="cvr-action-btn" style="color: #EF4444;" title="Odoo Error">🐛</button>
                                    <Link :href="row.renewal_date_url" class="cvr-action-btn" title="Renewal">🔄</Link>

                                    <button v-if="row.is_running || row.is_expired" @click="openCancel(row)" class="cvr-action-btn" title="Cancel Letter">🚫</button>
                                    <button v-if="row.is_running && row.is_advanced_payment" @click="openAdvancedPayment(row)" class="cvr-action-btn" title="Amount To Be Decreased">⚖️</button>
                                    <button v-if="row.is_cancelled" @click="openBackToRunning(row)" class="cvr-action-btn" title="Back To Running">↩️</button>

                                    <Link :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">Edit</Link>
                                    <button @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="currentTab.rows.length === 0">
                            <td colspan="12" class="px-4 py-8 text-center cvr-text-muted">
                                No LG Issuance records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="currentTab.last_page > 1" class="flex items-center justify-between mt-4 text-sm">
                <p class="cvr-text-muted">{{ currentTab.total }} total records</p>
                <div class="flex items-center gap-2">
                    <button
                        v-for="p in currentTab.last_page"
                        :key="p"
                        @click="goToPage(p)"
                        class="px-3 py-1.5 rounded border text-xs"
                        :class="p === currentTab.current_page ? 'cvr-btn-primary' : 'cvr-btn-secondary'"
                    >{{ p }}</button>
                </div>
            </div>

            <!-- Cancel modal -->
            <div v-if="cancelTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-lg">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to cancel this letter?</h2>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="cvr-form-label">Bank Name</label>
                            <input disabled :value="cancelTarget.bank_name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Code</label>
                            <input disabled :value="cancelTarget.lg_code" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Current Amount</label>
                            <input disabled :value="cancelTarget.lg_current_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Cancellation Date *</label>
                            <input v-model="cancelForm.cancellation_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitCancel" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Back To Running modal -->
            <div v-if="backToRunningTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-lg">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        Do you want to change LG status back to Running?
                    </h2>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="cvr-form-label">Bank Name</label>
                            <input disabled :value="backToRunningTarget.bank_name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Amount</label>
                            <input disabled :value="backToRunningTarget.lg_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="backToRunningTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitBackToRunning" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Advanced Payment modal -->
            <div v-if="advancedPaymentTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-3xl">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        Amount To Be Decreased — {{ advancedPaymentTarget.transaction_name }}
                    </h2>
                    <div class="cvr-form-grid-4 mb-4 items-end">
                        <div>
                            <label class="cvr-form-label">Bank Name</label>
                            <input disabled :value="advancedPaymentTarget.bank_name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Amount</label>
                            <input disabled :value="advancedPaymentTarget.lg_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Date *</label>
                            <input v-model="newAdvancedPaymentForm.date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount To Be Decreased *</label>
                            <input v-model="newAdvancedPaymentForm.amount" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>

                    <table class="min-w-full text-sm mb-4">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Amount</th>
                                <th class="px-3 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(h, i) in advancedPaymentTarget.advanced_payment_histories" :key="h.id" class="cvr-table-row">
                                <td class="px-3 py-2 cvr-text-secondary">{{ i + 1 }}</td>
                                <td class="px-3 py-2 whitespace-nowrap cvr-text-secondary">{{ h.date_formatted }}</td>
                                <td class="px-3 py-2 cvr-num">{{ h.amount_formatted }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <button @click="openEditAdvancedPayment(h)" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">Edit</button>
                                        <button @click="confirmDeleteAdvancedPayment(h)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="advancedPaymentTarget.advanced_payment_histories.length === 0">
                                <td colspan="4" class="px-3 py-6 text-center cvr-text-muted">No advanced payments yet.</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-end gap-2">
                        <button @click="advancedPaymentTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitNewAdvancedPayment" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Edit Advanced Payment modal -->
            <div v-if="editAdvancedPaymentTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Edit Amount To Be Decreased</h2>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="cvr-form-label">Date *</label>
                            <input v-model="editAdvancedPaymentForm.decrease_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount To Be Decreased *</label>
                            <input v-model="editAdvancedPaymentForm.amount_to_be_decreased" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="editAdvancedPaymentTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitEditAdvancedPayment" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Delete Advanced Payment confirmation -->
            <div v-if="deleteAdvancedPaymentTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to delete this item?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteAdvancedPaymentTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyAdvancedPayment" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
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

            <!-- Odoo References -->
            <div v-if="odooTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium cvr-text-primary">Odoo References</h2>
                        <button @click="odooTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                    </div>
                    <ul class="text-sm cvr-text-secondary list-disc pl-5 space-y-1">
                        <li v-for="(name, i) in odooTarget.odoo_reference_names" :key="i">{{ name }}</li>
                    </ul>
                </div>
            </div>

            <!-- Odoo Error -->
            <div v-if="odooErrorTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-lg">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium cvr-num-red">Odoo Error</h2>
                        <button @click="odooErrorTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                    </div>
                    <p class="text-sm cvr-text-primary whitespace-pre-wrap">{{ odooErrorTarget.odoo_error }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
