<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    financialInstitution: Object,
    canCreate: Boolean,
    canUpdate: Boolean,
    canDelete: Boolean,
    canCreateRate: Boolean,
    createUrl: String,
    rows: Array,
    backUrl: String,
    navUrls: Object,
});

/* ── Search (client-side) ─────────────────────────────────────── */
const search = ref('');
const filteredRows = computed(() => {
    if (!search.value) return props.rows;
    const q = search.value.toLowerCase();
    return props.rows.filter(r =>
        (r.account_number || '').toLowerCase().includes(q) ||
        (r.currency || '').toLowerCase().includes(q)
    );
});

/* ── KPIs ─────────────────────────────────────────────────────── */
const totalCount = computed(() => props.rows.length);
const currencyCount = computed(() => new Set(props.rows.map(r => r.currency)).size);

/* ── Delete confirmation ──────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

/* ── Lock / Unlock confirmation ──────────────────────────────────
   Uses the same generic LockBankAccountController endpoint already
   used on Bank Accounts — Overdraft Against Commercial Paper is one of the 7
   lockable account/facility types it covers. */
const lockTarget = ref(null);
function confirmLockToggle(row) { lockTarget.value = row; }
function cancelLockToggle() { lockTarget.value = null; }
function toggleLock() {
    router.put(lockTarget.value.lock_url, {}, { onFinish: () => { lockTarget.value = null; } });
}

/* ── Rates modal — view history, add a new rate, edit/delete the
   last entry. Only the last rate is editable/deletable, same rule
   as Time Of Deposit's renewal history. ─────────────────────────── */
const ratesTarget = ref(null);
const newRateForm = ref({ date_create: '', margin_rate_create: 0, borrowing_rate_create: 0, min_interest_rate_create: 0 });
const newRateInterest = computed(() =>
    (Number(newRateForm.value.margin_rate_create || 0) + Number(newRateForm.value.borrowing_rate_create || 0)).toFixed(2)
);
function openRates(row) {
    ratesTarget.value = row;
    newRateForm.value = { date_create: '', margin_rate_create: 0, borrowing_rate_create: 0, min_interest_rate_create: 0 };
}
function submitNewRate() {
    router.post(ratesTarget.value.apply_rate_url, {
        ...newRateForm.value,
        company_id: props.company.id,
    }, { onFinish: () => { ratesTarget.value = null; } });
}

const editRateTarget = ref(null);
const editRateForm = ref({ date_edit: '', margin_rate_edit: 0, borrowing_rate_edit: 0, min_interest_rate_edit: 0 });
const editRateInterest = computed(() =>
    (Number(editRateForm.value.margin_rate_edit || 0) + Number(editRateForm.value.borrowing_rate_edit || 0)).toFixed(2)
);
function openEditRate(rate) {
    editRateTarget.value = rate;
    editRateForm.value = {
        date_edit: rate.date,
        margin_rate_edit: rate.margin_rate,
        borrowing_rate_edit: rate.borrowing_rate,
        min_interest_rate_edit: rate.min_interest_rate,
    };
}
function submitEditRate() {
    router.post(editRateTarget.value.edit_url, editRateForm.value, {
        onFinish: () => { editRateTarget.value = null; },
    });
}

/*
 * ⚠️ The original delete-rate route is registered as a GET request
 * (a plain link in the old Blade version, not a form) — an existing
 * quirk, not something introduced here. router.get() matches that
 * registered method exactly; using router.delete() would send a
 * DELETE request the route was never set up to accept.
 */
const deleteRateTarget = ref(null);
function confirmDeleteRate(rate) { deleteRateTarget.value = rate; }
function cancelDeleteRate() { deleteRateTarget.value = null; }
function destroyRate() {
    router.get(deleteRateTarget.value.delete_url, {}, { onFinish: () => { deleteRateTarget.value = null; } });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    ← Back to Banks
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Overdraft Against Commercial Paper</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ financialInstitution.name }}</p>

            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">📄</div>
                    <div>
                        <p class="cvr-kpi-label">Contracts</p>
                        <p class="cvr-kpi-value">{{ totalCount }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">⇄</div>
                    <div>
                        <p class="cvr-kpi-label">Currencies</p>
                        <p class="cvr-kpi-value">{{ currencyCount }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" placeholder="Search account or currency..." class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                    + New Record
                </Link>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Start Date</th>
                            <th class="px-4 py-3 text-left">End Date</th>
                            <th class="px-4 py-3 text-left">Account Number</th>
                            <th class="px-4 py-3 text-left">Currency</th>
                            <th class="px-4 py-3 text-left">Limit</th>
                            <th class="px-4 py-3 text-left">Borrowing Rate</th>
                            <th class="px-4 py-3 text-left">Margin Rate</th>
                            <th class="px-4 py-3 text-left">Interest Rate</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.contract_start_date_formatted }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.contract_end_date_formatted }}</td>
                            <td class="px-4 py-3 cvr-text-primary">{{ row.account_number }}</td>
                            <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency }}</td>
                            <td class="px-4 py-3 cvr-num">{{ row.limit_formatted }}</td>
                            <td class="px-4 py-3 cvr-num-blue">{{ row.borrowing_rate_formatted }} %</td>
                            <td class="px-4 py-3 cvr-num-blue">{{ row.margin_rate_formatted }} %</td>
                            <td class="px-4 py-3 cvr-num-green">{{ row.interest_rate_formatted }} %</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button @click="openRates(row)" class="cvr-action-btn" title="Rates">％</button>
                                    <Link v-if="canUpdate" :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">
                                        Edit
                                    </Link>
                                    <button
                                        v-if="row.lock_url"
                                        @click="confirmLockToggle(row)"
                                        class="cvr-action-btn"
                                        :class="row.is_active ? '' : 'cvr-action-btn-danger'"
                                        :title="row.is_active ? 'Lock' : 'Unlock'"
                                    >{{ row.is_active ? '🔓' : '🔒' }}</button>
                                    <button
                                        v-if="canDelete"
                                        @click="confirmDelete(row)"
                                        class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs"
                                    >Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="10" class="px-4 py-8 text-center cvr-text-muted">
                                No Overdraft Against Commercial Paper records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to delete this item?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
            </div>

            <!-- Lock/Unlock confirmation -->
            <div v-if="lockTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ lockTarget.is_active ? 'Do you want to lock this account?' : 'Do you want to unlock this account?' }}
                    </h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelLockToggle" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="toggleLock" class="cvr-btn-danger px-3 py-1.5 rounded border">
                            {{ lockTarget.is_active ? 'Confirm Lock' : 'Confirm Unlock' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Rates modal -->
            <div v-if="ratesTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-2xl">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Rates Information</h2>

                    <div v-if="canCreateRate" class="cvr-form-grid-5 mb-4 items-end">
                        <div>
                            <label class="cvr-form-label">Date *</label>
                            <input v-model="newRateForm.date_create" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Borrowing Rate</label>
                            <input v-model="newRateForm.borrowing_rate_create" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Margin Rate</label>
                            <input v-model="newRateForm.margin_rate_create" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Min Interest Rate</label>
                            <input v-model="newRateForm.min_interest_rate_create" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Interest Rate</label>
                            <input disabled :value="newRateInterest" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                    </div>

                    <table class="min-w-full text-sm mb-4">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Borrowing Rate</th>
                                <th class="px-3 py-2 text-left">Margin Rate</th>
                                <th class="px-3 py-2 text-left">Interest Rate</th>
                                <th class="px-3 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(rate, index) in ratesTarget.rates" :key="rate.id" class="cvr-table-row">
                                <td class="px-3 py-2 cvr-text-secondary">{{ index + 1 }}</td>
                                <td class="px-3 py-2 whitespace-nowrap cvr-text-secondary">{{ rate.date_formatted }}</td>
                                <td class="px-3 py-2 cvr-num-blue">{{ rate.borrowing_rate_formatted }}</td>
                                <td class="px-3 py-2 cvr-num-blue">{{ rate.margin_rate_formatted }}</td>
                                <td class="px-3 py-2 cvr-num-green">{{ rate.interest_rate_formatted }}</td>
                                <td class="px-3 py-2">
                                    <div v-if="index === ratesTarget.rates.length - 1" class="flex items-center gap-2">
                                        <button v-if="canUpdate" @click="openEditRate(rate)" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">
                                            Edit
                                        </button>
                                        <button v-if="canDelete" @click="confirmDeleteRate(rate)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-end gap-2">
                        <button @click="ratesTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button v-if="canCreateRate" @click="submitNewRate" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm New Rate</button>
                    </div>
                </div>
            </div>

            <!-- Edit rate modal -->
            <div v-if="editRateTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Edit Rate</h2>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="cvr-form-label">Date *</label>
                            <input v-model="editRateForm.date_edit" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Borrowing Rate</label>
                            <input v-model="editRateForm.borrowing_rate_edit" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Margin Rate</label>
                            <input v-model="editRateForm.margin_rate_edit" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Min Interest Rate</label>
                            <input v-model="editRateForm.min_interest_rate_edit" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Interest Rate</label>
                            <input disabled :value="editRateInterest" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="editRateTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitEditRate" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Delete rate confirmation -->
            <div v-if="deleteRateTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to delete this item?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDeleteRate" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRate" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
