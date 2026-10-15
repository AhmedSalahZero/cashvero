<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';
import Dropdown from '@/Components/Dropdown.vue';

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    financialInstitution: Object,
    activeTab: String,
    filterDates: Object,
    canCreate: Boolean,
    deposits: Object,       // { running: [...], matured: [...], broken: [...] }
    createUrl: String,
    tabUrls: Object,
    backUrl: String,
    navUrls: Object,
});

/* ── Tabs ─────────────────────────────────────────────────────────
   All three tabs' data arrives already loaded, so switching tabs is
   instant and client-side — no reload needed just to look at another
   tab. */
const tabs = [
    { key: 'running', label: 'Running Time Of Deposit' },
    { key: 'matured', label: 'Matured Time Of Deposit' },
    { key: 'broken', label: 'Broken Time Of Deposit' },
];
const activeTab = ref(props.activeTab || 'running');

const currentRows = computed(() => props.deposits[activeTab.value] || []);

/* ── KPIs for the active tab ──────────────────────────────────── */
const totalCount = computed(() => currentRows.value.length);
const totalAmount = computed(() =>
    currentRows.value.reduce((sum, d) => sum + Number(d.amount || 0), 0).toLocaleString('en-EG')
);
const totalInterest = computed(() =>
    currentRows.value.reduce((sum, d) => sum + Number(d.interest_amount || 0), 0).toLocaleString('en-EG')
);

/* ── Date range filter — reloads from the server since date
   filtering happens server-side (filterByStartDate on the model).
   Running has no default cutoff (see controller); Matured/Broken
   default to a rolling 3-year window, shown explicitly below so
   it's never a silent restriction. ─────────────────────────────── */
const fromDate = ref(props.filterDates?.[activeTab.value]?.startDate || '');
const toDate = ref(props.filterDates?.[activeTab.value]?.endDate || '');

function applyDateFilter() {
    router.get(props.tabUrls[activeTab.value], {
        startDate: { [activeTab.value]: fromDate.value },
        endDate: { [activeTab.value]: toDate.value },
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function clearDateFilter() {
    fromDate.value = '';
    toDate.value = '';
    router.get(props.tabUrls[activeTab.value], {
        startDate: { [activeTab.value]: '' },
        endDate: { [activeTab.value]: '' },
    }, { preserveState: true, preserveScroll: true, replace: true });
}

const dateRangeLabel = computed(() => {
    const dates = props.filterDates?.[activeTab.value];
    if (activeTab.value === 'running') {
        return dates?.startDate && dates?.endDate
            ? `Filtered: showing deposits starting between ${dates.startDate} and ${dates.endDate}`
            : 'Showing every currently running deposit — no date filter applied';
    }
    if (!dates?.startDate || !dates?.endDate) return null;
    return dates.isDefaultWindow
        ? `Showing the default last-3-years window: ${dates.startDate} to ${dates.endDate}`
        : `Showing: ${dates.startDate} to ${dates.endDate}`;
});

function switchTab(key) {
    activeTab.value = key;
    fromDate.value = props.filterDates?.[key]?.startDate || '';
    toDate.value = props.filterDates?.[key]?.endDate || '';
}

/* ── Search (client-side, on top of the already-loaded tab data) ─ */
const search = ref('');
const filteredRows = computed(() => {
    if (!search.value) return currentRows.value;
    const q = search.value.toLowerCase();
    return currentRows.value.filter(d =>
        (d.account_number || '').toLowerCase().includes(q) ||
        (d.currency || '').toLowerCase().includes(q)
    );
});

/* ── Delete confirmation ──────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

/* ── Apply Deposit modal (Running → Matured) ─────────────────── */
const depositTarget = ref(null);
const depositForm = ref({ deposit_date: '', actual_interest_amount: 0, settlement_account_id: '' });
function openApplyDeposit(row) {
    depositTarget.value = row;
    /* الحساب اللي الوديعة هتترد عليه — بيتعبى مبدئيا بحساب الخصم الاصلي،
       وبيفضل فاضي لو الوديعة اتسجلت opening balance فا اليوزر لازم يختار. */
    depositForm.value = { deposit_date: '', actual_interest_amount: row.interest_amount || 0, settlement_account_id: row.settlement_account_id || '' };
}
function submitApplyDeposit() {
    router.post(depositTarget.value.apply_deposit_url, depositForm.value, {
        onFinish: () => { depositTarget.value = null; },
    });
}

/* ── Apply Break modal (Running → Broken) ────────────────────── */
const breakTarget = ref(null);
const breakForm = ref({ break_date: '', break_interest_amount: 0, break_charge_amount: 0, amount: 0, settlement_account_id: '' });
function openApplyBreak(row) {
    breakTarget.value = row;
    breakForm.value = { break_date: '', break_interest_amount: 0, break_charge_amount: 0, amount: row.amount || 0, settlement_account_id: row.settlement_account_id || '' };
}
function submitApplyBreak() {
    router.post(breakTarget.value.apply_break_url, breakForm.value, {
        onFinish: () => { breakTarget.value = null; },
    });
}

/* ── Apply Periodic Interest modal — available on any status, matches
   the original's behaviour (its disabled-check was commented out
   there too, so this one is intentionally never disabled) ────────── */
const periodInterestTarget = ref(null);
const periodInterestForm = ref({ periodic_interest_amount: 0, periodic_interest_date: '' });
const today = new Date().toISOString().split('T')[0];
function openApplyPeriodInterest(row) {
    periodInterestTarget.value = row;
    periodInterestForm.value = { periodic_interest_amount: 0, periodic_interest_date: '' };
}
function submitApplyPeriodInterest() {
    router.post(periodInterestTarget.value.apply_period_interest_url, periodInterestForm.value, {
        onFinish: () => { periodInterestTarget.value = null; },
    });
}

/* ── Reverse confirmations (Matured → Running, Broken → Running) ─ */
const reverseTarget = ref(null);
function openReverse(row) { reverseTarget.value = row; }
function cancelReverse() { reverseTarget.value = null; }
function submitReverse() {
    const url = reverseTarget.value.status === 'matured'
        ? reverseTarget.value.reverse_deposit_url
        : reverseTarget.value.reverse_broken_url;
    router.post(url, {}, { onFinish: () => { reverseTarget.value = null; } });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="px-6 pt-4">
            <Link v-if="instructionsUrl" :href="instructionsUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('📖 Instructions') }}
            </Link>
        </div>
        <div class="p-6">
            <!-- Back link + title -->
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Accounts') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Time Of Deposit') }}</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ financialInstitution.name }}</p>

            <!-- KPI cards -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">📄</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Records') }}</p>
                        <p class="cvr-kpi-value">{{ totalCount }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">💰</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Amount') }}</p>
                        <p class="cvr-kpi-value">{{ totalAmount }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">％</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Interest') }}</p>
                        <p class="cvr-kpi-value">{{ totalInterest }}</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="flex items-center gap-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="switchTab(tab.key)"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': activeTab === tab.key }"
                    >
                        {{ $t(tab.label) }}
                    </button>
                </div>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                    {{ $t('+ New Record') }}
                </Link>
            </div>

            <!-- Date range + search -->
            <div class="flex flex-wrap items-end gap-3 mb-2">
                <div>
                    <label class="cvr-form-label">{{ $t('From') }}</label>
                    <input v-model="fromDate" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('To') }}</label>
                    <input v-model="toDate" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <button @click="applyDateFilter" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">
                    {{ $t('Apply Date Filter') }}
                </button>
                <button v-if="fromDate || toDate" @click="clearDateFilter" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">
                    {{ $t('Clear') }}
                </button>
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 ms-auto w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" :placeholder="$t('Search account or currency...')" class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
            </div>

            <!-- Always-visible date window banner — this exists so a
                 date filter never silently hides records without the
                 user realizing it's applied. -->
            <p v-if="dateRangeLabel" class="text-xs cvr-text-muted mb-4">
                {{ dateRangeLabel }}
            </p>
            <div v-else class="mb-4"></div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Start Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('End Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Account Number') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Amount') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Currency') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Interest Rate') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Interest Amount') }}</th>
                            <th v-if="activeTab === 'running'" class="px-4 py-3 text-start">{{ $t('Blocked Against') }}</th>
                            <th v-if="activeTab === 'broken'" class="px-4 py-3 text-start">{{ $t('Break Date') }}</th>
                            <th v-if="activeTab === 'broken'" class="px-4 py-3 text-start">{{ $t('Break Interest') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.start_date_formatted }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.end_date_formatted }}</td>
                            <td class="px-4 py-3 cvr-text-primary">{{ row.account_number }}</td>
                            <td class="px-4 py-3 cvr-num">{{ row.amount_formatted }}</td>
                            <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency }}</td>
                            <td class="px-4 py-3 cvr-num-blue">{{ row.interest_rate_formatted }}</td>
                            <td class="px-4 py-3 cvr-num-green">{{ row.interest_amount_formatted }}</td>
                            <td v-if="activeTab === 'running'" class="px-4 py-3 cvr-text-secondary">{{ row.blocked_against_formatted }}</td>
                            <td v-if="activeTab === 'broken'" class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.break_date_formatted }}</td>
                            <td v-if="activeTab === 'broken'" class="px-4 py-3 cvr-num-amber">{{ row.break_interest_amount_formatted }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <RecordLogButton subject="TimeOfDeposit" :id="row.id" :company-id="company.id" />
                                    <Link :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">
                                        {{ $t('Edit') }}
                                    </Link>

                                    <button
                                        v-if="activeTab === 'running'"
                                        @click="!row.is_due_today_or_greater && openApplyDeposit(row)"
                                        :disabled="row.is_due_today_or_greater"
                                        class="cvr-action-btn"
                                        :class="{ 'opacity-40 cursor-not-allowed pointer-events-none': row.is_due_today_or_greater }"
                                        :title="row.is_due_today_or_greater ? 'Not yet due' : 'Apply TD Deposit Maturity'"
                                    >🪙</button>

                                    <button
                                        v-if="activeTab === 'running'"
                                        @click="openApplyBreak(row)"
                                        class="cvr-action-btn"
                                        :title="$t('Apply Break')"
                                    >✂️</button>

                                    <button
                                        @click="openApplyPeriodInterest(row)"
                                        class="cvr-action-btn"
                                        :title="$t('Apply Periodic Interest')"
                                    >⚡</button>

                                    <Link :href="row.renewal_date_url" class="cvr-action-btn" :title="$t('Renewal')">🔄</Link>

                                    <button
                                        v-if="activeTab === 'matured' || activeTab === 'broken'"
                                        @click="openReverse(row)"
                                        class="cvr-action-btn"
                                        :title="$t('Reverse')"
                                    >↺</button>

                                    <Dropdown>
                                        <template #trigger="{ toggle }">
                                            <button @click="toggle" class="cvr-tag">{{ $t('Options ▾') }}</button>
                                        </template>
                                        <template #content>
                                            <Link :href="row.view_period_interest_url" class="block px-3 py-2 text-xs cvr-dropdown-item">
                                                {{ $t('View Period Interest') }}
                                            </Link>
                                            <button @click="confirmDelete(row)" class="block w-full text-start px-3 py-2 text-xs cvr-dropdown-item">
                                                {{ $t('Delete') }}
                                            </button>
                                        </template>
                                    </Dropdown>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="11" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No time of deposit records found.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Do you want to delete this item?') }}</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>

            <!-- Apply Deposit modal -->
            <div v-if="depositTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ $t('Do you want to apply deposit to this Time Of Deposit?') }}
                    </h2>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('TD Amount') }}</label>
                            <input disabled :value="depositTarget.amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Actual Interest Amount') }}</label>
                            <input v-model="depositForm.actual_interest_amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div class="col-span-2">
                            <label class="cvr-form-label">{{ $t('Deposit Date') }} *</label>
                            <input v-model="depositForm.deposit_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div class="col-span-2">
                            <label class="cvr-form-label">{{ $t('Settlement Account #') }} *</label>
                            <select v-model="depositForm.settlement_account_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="account in depositTarget.settlement_account_options" :key="account.id" :value="account.id">
                                    {{ account.account_number }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="depositTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitApplyDeposit" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm') }}</button>
                    </div>
                </div>
            </div>

            <!-- Apply Break modal -->
            <div v-if="breakTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ $t('Do you want to break this Time Of Deposit?') }}
                    </h2>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Amount') }}</label>
                            <input v-model="breakForm.amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Break Interest Amount') }}</label>
                            <input v-model="breakForm.break_interest_amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Break Charge Amount') }}</label>
                            <input v-model="breakForm.break_charge_amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Break Date') }} *</label>
                            <input v-model="breakForm.break_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div class="col-span-2">
                            <label class="cvr-form-label">{{ $t('Settlement Account #') }} *</label>
                            <select v-model="breakForm.settlement_account_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="account in breakTarget.settlement_account_options" :key="account.id" :value="account.id">
                                    {{ account.account_number }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="breakTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitApplyBreak" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Break') }}</button>
                    </div>
                </div>
            </div>

            <!-- Apply Periodic Interest modal -->
            <div v-if="periodInterestTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ $t('Do you want to apply periodic interest to this Time Of Deposit?') }}
                    </h2>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Interest Amount') }}</label>
                            <input v-model="periodInterestForm.periodic_interest_amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Deposit Date') }} *</label>
                            <input v-model="periodInterestForm.periodic_interest_date" type="date" :max="today" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <Link :href="periodInterestTarget.view_period_interest_url" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                            {{ $t('View Periodic Interests') }}
                        </Link>
                        <div class="flex gap-2">
                            <button @click="periodInterestTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                            <button @click="submitApplyPeriodInterest" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reverse confirmation -->
            <div v-if="reverseTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ $t('Do you want to send this Time Of Deposit back to Running?') }}
                    </h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelReverse" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitReverse" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
