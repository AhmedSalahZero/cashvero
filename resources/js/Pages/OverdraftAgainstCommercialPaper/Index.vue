<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const flashErrors = computed(() => page.props.errors || {});

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

/* ── Tabs: Facilities (running, current terms) vs Archived
   Facilities (any facility that has ever been renewed, grouped one
   row per facility with its full history expandable) ─────────────── */
const activeMainTab = ref('facilities');
const archivedRows = computed(() => props.rows.filter(r => r.has_renewals));
const expandedArchiveId = ref(null);
function toggleArchiveExpand(row) {
    expandedArchiveId.value = expandedArchiveId.value === row.id ? null : row.id;
}
const deleteRenewalTarget = ref(null);
function confirmDeleteRenewal(row) { deleteRenewalTarget.value = row; }
function cancelDeleteRenewal() { deleteRenewalTarget.value = null; }
function destroyRenewal() {
    router.delete(deleteRenewalTarget.value.delete_renewal_url, {
        onFinish: () => { deleteRenewalTarget.value = null; },
    });
}

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
    newRateForm.value = { date_create: '', margin_rate_create: 0, borrowing_rate_create: 0 };
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

/* ── Facility Renewal — Phase 1 ───────────────────────────────────
   Deliberately separate from the Rates modal above (per the agreed
   design brief §7 decision #5): this only ever changes limit,
   Highest-Debt-Balance rate, Admin fees rate, and settlement days —
   never the Borrowing Rate, and never account_number/currency, since
   a renewal continues the SAME facility rather than creating a new
   one. History shown here is read-only; every past chapter stays
   exactly as it was. */
const renewTarget = ref(null);
const renewForm = ref({
    effective_date: '',
    limit: '',
    max_lending_limit_per_customer: '',
    highest_debt_balance_rate: '',
    admin_fees_rate: '',
    to_be_setteled_max_within_days: '',
    contract_end_date: '',
    notes: '',
});
const renewErrors = ref({});
/* ── Renewal's own tier repeater ─────────────────────────────────
   A renewal MUST bring a whole new, complete tier schedule (never
   optional — see RenewOverdraftAgainstCommercialPaperRequest and
   OverdraftAgainstCommercialPaper::renew()). The previous chapter's
   tiers are never touched — a cheque already deposited keeps
   resolving against them forever. */
let nextRenewTierRowId = 1;
function blankRenewTierRow() {
    return { _rowId: nextRenewTierRowId++, for_commercial_papers_due_within_days: '', lending_rate: '' };
}
const renewTiers = ref([blankRenewTierRow()]);
function addRenewTierRow() { renewTiers.value.push(blankRenewTierRow()); }
function removeRenewTierRow(rowId) {
    if (renewTiers.value.length <= 1) return;
    renewTiers.value = renewTiers.value.filter(r => r._rowId !== rowId);
}
function openRenew(row) {
    renewTarget.value = row;
    renewErrors.value = {};
    renewForm.value = {
        effective_date: '',
        limit: '',
        max_lending_limit_per_customer: '',
        highest_debt_balance_rate: '',
        admin_fees_rate: '',
        to_be_setteled_max_within_days: '',
        contract_end_date: '',
        notes: '',
    };
    renewTiers.value = [blankRenewTierRow()];
}
function cancelRenew() { renewTarget.value = null; }
const currentContractEndDate = computed(() => {
    if (!renewTarget.value) return '';
    const history = renewTarget.value.terms_history || [];
    // Latest chapter's end date (original, or latest renewal if any) —
    // mirrors exactly what OverdraftAgainstCommercialPaper::renew() checks against.
    const latest = history[history.length - 1];
    return (latest && latest.contract_end_date_formatted) || renewTarget.value.contract_end_date_formatted || 'N/A';
});
function submitRenew() {
    // Blank fields are sent as empty strings; the backend treats a
    // missing/blank value as "unchanged from the previous chapter" —
    // the user only has to fill in what actually changed. Tiers are
    // the one exception — always required in full, per the rule above.
    const payload = {
        ...renewForm.value,
        tiers: renewTiers.value.map(({ _rowId, ...rest }) => rest),
    };
    router.post(renewTarget.value.renew_url, payload, {
        onError: (errors) => { renewErrors.value = errors; },
        onSuccess: () => { renewTarget.value = null; },
    });
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

            <div v-if="Object.keys(flashErrors).length" class="mb-4 rounded border border-red-400 bg-red-50 text-red-700 text-sm px-4 py-3">
                <p v-for="(msg, key) in flashErrors" :key="key">{{ msg }}</p>
            </div>

            <div class="flex items-center gap-2 mb-4 border-b cvr-border">
                <button
                    @click="activeMainTab = 'facilities'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    :class="activeMainTab === 'facilities' ? 'border-current cvr-text-primary' : 'border-transparent cvr-text-muted'"
                >Facilities</button>
                <button
                    @click="activeMainTab = 'archived'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    :class="activeMainTab === 'archived' ? 'border-current cvr-text-primary' : 'border-transparent cvr-text-muted'"
                >Archived Facilities ({{ archivedRows.length }})</button>
            </div>

            <div v-if="activeMainTab === 'facilities'">
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
                                    <button
                                        v-if="canUpdate"
                                        @click="openRenew(row)"
                                        class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs"
                                        title="Record a renewal — new limit/terms from a chosen date, without losing history"
                                    >Renew</button>
                                    <Link
                                        v-if="canUpdate"
                                        :href="row.edit_url"
                                        class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs"
                                        title="Edits the current, running terms of this facility. Past (archived) chapters are never affected."
                                    >Edit</Link>
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
            </div>

            <!-- Archived Facilities tab: one row per facility that has
                 ever been renewed, expandable to its full chapter
                 history. No action buttons on the historical chapters
                 themselves — only the current facility's own actions
                 (in the Facilities tab above) and, on the single most
                 recent chapter, the option to delete that renewal. -->
            <div v-if="activeMainTab === 'archived'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Account Number</th>
                            <th class="px-4 py-3 text-left">Currency</th>
                            <th class="px-4 py-3 text-left">Current Limit</th>
                            <th class="px-4 py-3 text-left">Renewals</th>
                            <th class="px-4 py-3 text-left"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(row, index) in archivedRows" :key="row.id">
                            <tr class="cvr-table-row cursor-pointer" @click="toggleArchiveExpand(row)">
                                <td class="px-4 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                                <td class="px-4 py-3 cvr-text-primary">{{ row.account_number }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.limit_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.terms_history.length - 1 }}</td>
                                <td class="px-4 py-3 cvr-text-muted">{{ expandedArchiveId === row.id ? '▲ Hide History' : '▼ Show History' }}</td>
                            </tr>
                            <tr v-if="expandedArchiveId === row.id">
                                <td colspan="6" class="px-4 py-3 cvr-card-bg">
                                    <table class="w-full text-xs border rounded overflow-hidden">
                                        <thead class="cvr-table-head">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Effective Date</th>
                                                <th class="px-3 py-2 text-left">Contract End Date</th>
                                                <th class="px-3 py-2 text-left">Limit</th>
                                                <th class="px-3 py-2 text-left">Max/Customer</th>
                                                <th class="px-3 py-2 text-left">Highest-Debt Rate</th>
                                                <th class="px-3 py-2 text-left">Admin Fees Rate</th>
                                                <th class="px-3 py-2 text-left">Settlement Days</th>
                                                <th class="px-3 py-2 text-left">Tiers</th>
                                                <th class="px-3 py-2 text-left">Chapter</th>
                                                <th class="px-3 py-2 text-left"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(term, tIndex) in row.terms_history" :key="term.id" class="cvr-table-row">
                                                <td class="px-3 py-2 whitespace-nowrap">{{ term.effective_date_formatted }}</td>
                                                <td class="px-3 py-2 whitespace-nowrap">{{ term.contract_end_date_formatted }}</td>
                                                <td class="px-3 py-2 cvr-num">{{ term.limit_formatted }}</td>
                                                <td class="px-3 py-2 cvr-num">{{ Number(term.max_lending_limit_per_customer || 0).toLocaleString() }}</td>
                                                <td class="px-3 py-2">{{ term.highest_debt_balance_rate }} %</td>
                                                <td class="px-3 py-2">{{ term.admin_fees_rate }} %</td>
                                                <td class="px-3 py-2">{{ term.to_be_setteled_max_within_days }}</td>
                                                <td class="px-3 py-2 text-xs">{{ (term.tiers || []).map(t => t.for_commercial_papers_due_within_days + 'd→' + t.lending_rate + '%').join(', ') }}</td>
                                                <td class="px-3 py-2 cvr-text-muted">{{ term.is_original ? 'Original' : 'Renewal' }}</td>
                                                <td class="px-3 py-2">
                                                    <button
                                                        v-if="canUpdate && tIndex === row.terms_history.length - 1 && !term.is_original"
                                                        @click.stop="confirmDeleteRenewal(row)"
                                                        class="cvr-btn-danger px-2 py-1 rounded border text-xs"
                                                        title="Undo this renewal — reverts the facility to its previous chapter's terms"
                                                    >Delete This Renewal</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="archivedRows.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center cvr-text-muted">
                                No facility has been renewed yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Delete renewal confirmation -->
            <div v-if="deleteRenewalTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-2">Delete this renewal?</h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        The facility will revert to its previous chapter's terms. This is blocked if any transactions
                        are already dated on or after the renewal's effective date.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDeleteRenewal" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRenewal" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
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
                <div class="cvr-modal rounded-lg p-6 w-full max-w-4xl">
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
                            <label class="cvr-form-label">Interest Rate</label>
                            <input disabled :value="newRateInterest" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Min Interest Rate</label>
                            <input v-model="newRateForm.min_interest_rate_create" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
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

            <!-- Facility Renewal modal -->
            <div v-if="renewTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <h2 class="text-lg font-medium cvr-text-primary mb-1">Renew Facility — {{ renewTarget.account_number }}</h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        Leave a field blank to keep it unchanged from the current terms. Nothing dated before the
                        effective date is ever recalculated — past interest, fees, and due dates stay exactly as they were.
                        The Borrowing Rate is not part of this form; use the separate Rates (％) action for that.
                    </p>

                    <!-- Read-only terms history -->
                    <div v-if="renewTarget.terms_history && renewTarget.terms_history.length" class="mb-4 border rounded overflow-hidden">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="cvr-table-head">
                                    <th class="px-3 py-2 text-left">Effective Date</th>
                                    <th class="px-3 py-2 text-left">Contract End Date</th>
                                    <th class="px-3 py-2 text-left">Limit</th>
                                    <th class="px-3 py-2 text-left">Max/Customer</th>
                                    <th class="px-3 py-2 text-left">Highest-Debt Rate</th>
                                    <th class="px-3 py-2 text-left">Admin Fees Rate</th>
                                    <th class="px-3 py-2 text-left">Settlement Days</th>
                                    <th class="px-3 py-2 text-left">Tiers</th>
                                                <th class="px-3 py-2 text-left">Chapter</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="term in renewTarget.terms_history" :key="term.id" class="cvr-table-row">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ term.effective_date_formatted }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ term.contract_end_date_formatted }}</td>
                                    <td class="px-3 py-2 cvr-num">{{ term.limit_formatted }}</td>
                                    <td class="px-3 py-2 cvr-num">{{ Number(term.max_lending_limit_per_customer || 0).toLocaleString() }}</td>
                                    <td class="px-3 py-2">{{ term.highest_debt_balance_rate }} %</td>
                                    <td class="px-3 py-2">{{ term.admin_fees_rate }} %</td>
                                    <td class="px-3 py-2">{{ term.to_be_setteled_max_within_days }}</td>
                                    <td class="px-3 py-2 text-xs">{{ (term.tiers || []).map(t => t.for_commercial_papers_due_within_days + 'd→' + t.lending_rate + '%').join(', ') }}</td>
                                                <td class="px-3 py-2 cvr-text-muted">{{ term.is_original ? 'Original' : 'Renewal' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">Renewal Effective Date *</label>
                            <input type="date" v-model="renewForm.effective_date" class="cvr-input w-full" />
                            <p class="text-xs cvr-text-muted mt-1">
                                Current contract end date: <strong>{{ currentContractEndDate }}</strong> — the renewal date must be after this.
                            </p>
                            <p v-if="renewErrors.effective_date" class="text-xs text-red-600 mt-1">{{ renewErrors.effective_date }}</p>
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">New Contract End Date *</label>
                            <input type="date" v-model="renewForm.contract_end_date" class="cvr-input w-full" />
                            <p v-if="renewErrors.contract_end_date" class="text-xs text-red-600 mt-1">{{ renewErrors.contract_end_date }}</p>
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">New Overall Limit</label>
                            <input type="number" v-model="renewForm.limit" class="cvr-input w-full" placeholder="Unchanged" />
                            <p v-if="renewErrors.limit" class="text-xs text-red-600 mt-1">{{ renewErrors.limit }}</p>
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">New Max Lending Limit Per Customer</label>
                            <input type="number" v-model="renewForm.max_lending_limit_per_customer" class="cvr-input w-full" placeholder="Unchanged" />
                            <p v-if="renewErrors.max_lending_limit_per_customer" class="text-xs text-red-600 mt-1">{{ renewErrors.max_lending_limit_per_customer }}</p>
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">New Settlement Days</label>
                            <input type="number" v-model="renewForm.to_be_setteled_max_within_days" class="cvr-input w-full" placeholder="Unchanged" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">New Highest-Debt-Balance Rate %</label>
                            <input type="number" v-model="renewForm.highest_debt_balance_rate" class="cvr-input w-full" placeholder="Unchanged" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">New Admin Fees Rate %</label>
                            <input type="number" v-model="renewForm.admin_fees_rate" class="cvr-input w-full" placeholder="Unchanged" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs cvr-text-secondary mb-1">Notes</label>
                            <textarea v-model="renewForm.notes" class="cvr-input w-full" rows="2" placeholder="e.g. Bank renewal letter reference"></textarea>
                        </div>
                    </div>

                    <!-- Renewal's own tier schedule — always required in
                         full, never optional (see the note above the
                         effective date). Never mixed with the previous
                         chapter's tiers. -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs cvr-text-secondary">New Lending-Rate Tiers *</label>
                            <button type="button" @click="addRenewTierRow" class="cvr-btn-primary px-2 py-1 rounded text-xs">+ Add Tier</button>
                        </div>
                        <p class="text-xs cvr-text-muted mb-2">
                            e.g. "cheques due within 30 days → 80%". Cheques already deposited under the previous
                            chapter keep using its tiers forever — only cheques deposited from the renewal date
                            onward use this new schedule.
                        </p>
                        <div v-for="row in renewTiers" :key="row._rowId" class="cvr-form-grid-3 mb-2 items-end">
                            <div>
                                <label class="cvr-form-label">Due Within (Days)</label>
                                <input v-model="row.for_commercial_papers_due_within_days" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Lending Rate (%)</label>
                                <input v-model="row.lending_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <button
                                type="button"
                                @click="removeRenewTierRow(row._rowId)"
                                class="cvr-btn-remove-row justify-self-start w-auto"
                            >🗑 Remove</button>
                        </div>
                        <p v-if="renewErrors.tiers" class="text-xs text-red-600 mt-1">{{ renewErrors.tiers }}</p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button @click="cancelRenew" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitRenew" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm Renewal</button>
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
