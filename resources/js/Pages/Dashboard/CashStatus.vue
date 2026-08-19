<script setup>
/**
 * Dashboard/CashStatus.vue
 * ------------------------------------------------------------------
 * Served by CustomerInvoiceDashboardController@viewCashDashboard. The
 * "Cash Status" tab of the Dashboard sidebar section — per-currency
 * cash position (Cash & Banks / Time Deposit / Certificate of
 * Deposit / Total), the combined "Total Cash Facilities" card, and
 * one section per overdraft type the company actually has, each with
 * its own mini KPI row, a 3D "Available Room" donut, and a live
 * "Bank Movement" multi-line chart (Cash In / Cash Out / End
 * Balance) fed by the existing refreshBankMovementChart endpoint.
 *
 * All the math (CashDashboardService::build()) is UNCHANGED — this
 * page only renders what the controller already computed. The date
 * filter re-visits this same route (a real Inertia GET, replacing
 * the original's plain HTML form submit).
 */
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardTabs from '@/Components/DashboardTabs.vue';
import DonutChart3D from '@/Components/Charts/DonutChart3D.vue';
import MultiLineChart from '@/Components/Charts/MultiLineChart.vue';

const props = defineProps({
    company: Object,
    mainFunctionalCurrency: String,
    financialInstitutionBanks: Array,
    reports: Object,
    selectedCurrencies: Array,
    allCurrencies: Array,
    totalCard: Object,
    details: Object,
    date: String,
    cleanOverdraftCardData: Object,
    totalRoomForEachCleanOverdraftId: Object,
    allCleanOverdraftBanks: Array,
    hasCleanOverdraft: Object,
    fullySecuredOverdraftCardData: Object,
    totalRoomForEachFullySecuredOverdraftId: Object,
    allFullySecuredOverdraftBanks: Array,
    hasFullySecuredOverdraft: Object,
    overdraftAgainstCommercialPaperCardData: Object,
    totalRoomForEachOverdraftAgainstCommercialPaperId: Object,
    allOverdraftAgainstCommercialPaperBanks: Array,
    hasOverdraftAgainstCommercialPaper: Object,
    overdraftAgainstAssignmentOfContractCardData: Object,
    totalRoomForEachOverdraftAgainstAssignmentOfContractId: Object,
    allOverdraftAgainstAssignmentOfContractBanks: Array,
    hasOverdraftAgainstAssignmentOfContract: Object,
    mediumTermLoansArr: Object,
    leasingContractsArr: Object,
    overdraftTypeLabels: Object,
    overdraftAccountTypeIds: Object,
    bankStatementUrls: Object,
    withdrawalReportUrls: Object,
    filterUrl: String,
    refreshChartUrl: String,
    accountNumbersUrl: String,
    dashboardTabUrls: Object,

    /* ── Account owner filter — docs/shareholder-accounts.md ──────
       Rendered only for a user holding shareholder_account.view (D6).
       The backend pins everyone else to Company accounts regardless of
       what the query string says, so hiding the control here is
       presentation, not the guarantee. */
    canManageShareholderAccounts: { type: Boolean, default: false },
    accountOwner: { type: String, default: 'company' },
    accountOwnerShareholderId: { type: [Number, String, null], default: null },
    shareholders: { type: Array, default: () => [] },
});

/* ── Currency tabs ────────────────────────────────────────────── */
const activeCurrency = ref(props.selectedCurrencies[0] || props.mainFunctionalCurrency);

/* ── Date + account owner filters ─────────────────────────────── */
const filterDate = ref(props.date);

/*
 * Default is "Company accounts", never "All" (decision D2) — the page
 * opens on official company figures and owner data is opt-in.
 */
const accountOwner = ref(props.accountOwner || 'company');
const shareholderPartnerId = ref(props.accountOwnerShareholderId ?? '');

/* An empty value here means "All shareholders" (decision D3). */
const showsShareholderPicker = computed(() => accountOwner.value === 'shareholders');

function onAccountOwnerChange() {
    // A specific owner only means anything inside the shareholders view.
    if (!showsShareholderPicker.value) {
        shareholderPartnerId.value = '';
    }
    applyFilters();
}

/*
 * One place that builds the query string, so changing the date never
 * silently drops the owner selection (or the other way round).
 */
function applyFilters() {
    const params = { date: filterDate.value };

    if (props.canManageShareholderAccounts) {
        params.account_owner = accountOwner.value;
        if (accountOwner.value === 'shareholders' && shareholderPartnerId.value !== '') {
            params.shareholder_partner_id = shareholderPartnerId.value;
        }
    }

    router.get(props.filterUrl, params, { preserveScroll: true, preserveState: true });
}

/** Kept for the existing "Apply" button next to the date. */
function applyDateFilter() {
    applyFilters();
}

/* ── Overdraft sections (generic over the 4 real types) ─────────
   Built as a computed array rather than 4 hand-copied template
   blocks — the 4 overdraft types are structurally identical on this
   dashboard (same card shape, same chart shape), matching how the
   rest of this migration already treats this "overdraft family"
   (see Roadmap §3.8 / §9). ─────────────────────────────────────── */
const overdraftSections = computed(() => [
    {
        type: 'FullySecuredOverdraft',
        label: props.overdraftTypeLabels?.FullySecuredOverdraft,
        cardData: props.fullySecuredOverdraftCardData,
        room: props.totalRoomForEachFullySecuredOverdraftId,
        banks: props.allFullySecuredOverdraftBanks,
        has: props.hasFullySecuredOverdraft,
        accountTypeId: props.overdraftAccountTypeIds?.FullySecuredOverdraft,
    },
    {
        type: 'CleanOverdraft',
        label: props.overdraftTypeLabels?.CleanOverdraft,
        cardData: props.cleanOverdraftCardData,
        room: props.totalRoomForEachCleanOverdraftId,
        banks: props.allCleanOverdraftBanks,
        has: props.hasCleanOverdraft,
        accountTypeId: props.overdraftAccountTypeIds?.CleanOverdraft,
    },
    {
        type: 'OverdraftAgainstCommercialPaper',
        label: props.overdraftTypeLabels?.OverdraftAgainstCommercialPaper,
        cardData: props.overdraftAgainstCommercialPaperCardData,
        room: props.totalRoomForEachOverdraftAgainstCommercialPaperId,
        banks: props.allOverdraftAgainstCommercialPaperBanks,
        has: props.hasOverdraftAgainstCommercialPaper,
        accountTypeId: props.overdraftAccountTypeIds?.OverdraftAgainstCommercialPaper,
    },
    {
        type: 'OverdraftAgainstAssignmentOfContract',
        label: props.overdraftTypeLabels?.OverdraftAgainstAssignmentOfContract,
        cardData: props.overdraftAgainstAssignmentOfContractCardData,
        room: props.totalRoomForEachOverdraftAgainstAssignmentOfContractId,
        banks: props.allOverdraftAgainstAssignmentOfContractBanks,
        has: props.hasOverdraftAgainstAssignmentOfContract,
        accountTypeId: props.overdraftAccountTypeIds?.OverdraftAgainstAssignmentOfContract,
    },
].filter(section => section.has?.[activeCurrency.value]));

/* ── Per-section "Bank Movement" chart state ─────────────────────
   Keyed by overdraft type so each section's bank/account pickers and
   chart data are independent of the others and survive currency-tab
   switches without cross-talk. ─────────────────────────────────── */
const movementState = ref({}); // { [type]: { bankId, accountOptions, accountNumber, chartData, loading } }

function stateFor(type) {
    if (!movementState.value[type]) {
        movementState.value[type] = { bankId: '', accountOptions: [], accountNumber: '', chartData: [], loading: false };
    }
    return movementState.value[type];
}

async function onBankChange(section) {
    const state = stateFor(section.type);
    state.accountOptions = [];
    state.accountNumber = '';
    state.chartData = [];
    if (!state.bankId || !section.accountTypeId) return;
    try {
        const { data } = await window.axios.get(props.accountNumbersUrl, {
            params: {
                account_type: section.accountTypeId,
                currency: activeCurrency.value,
                financial_institution_id: state.bankId,
            },
        });
        const entries = Object.entries(data?.data || {});
        state.accountOptions = entries.map(([number, label]) => ({ value: number, label }));
        if (state.accountOptions.length) {
            state.accountNumber = state.accountOptions[0].value;
            await onAccountChange(section);
        }
    } catch (e) {
        console.warn('Failed to load account numbers', e);
    }
}

async function onAccountChange(section) {
    const state = stateFor(section.type);
    if (!state.accountNumber) {
        state.chartData = [];
        return;
    }
    state.loading = true;
    try {
        const { data } = await window.axios.get(props.refreshChartUrl, {
            params: {
                modelName: section.type,
                currencyName: activeCurrency.value,
                bankId: state.bankId,
                date: filterDate.value,
                accountNumber: state.accountNumber,
            },
        });
        state.chartData = data?.chart_date || [];
    } catch (e) {
        console.warn('Failed to load bank movement chart', e);
    } finally {
        state.loading = false;
    }
}

async function ensureDefaultBank(section) {
    const banks = section.banks || [];
    if (!banks.length) return;
    const state = stateFor(section.type);
    const firstId = String(banks[0].id);
    if (!state.bankId || !banks.some(b => String(b.id) === String(state.bankId))) {
        state.bankId = firstId;
        await onBankChange(section);
    }
}

watch(
    [overdraftSections, activeCurrency],
    async ([sections]) => {
        for (const section of sections || []) {
            await ensureDefaultBank(section);
        }
    },
    { immediate: true },
);

/** All banks' available_room for the donut (legacy: category = bank name). */
function roomDonutData(section) {
    const rows = section.room?.[activeCurrency.value] || [];
    const byBank = {};
    for (const row of rows) {
        const name = row.item || '—';
        byBank[name] = (byBank[name] || 0) + Number(row.available_room || 0);
    }
    return Object.entries(byBank)
        .filter(([, value]) => value !== 0)
        .map(([category, value]) => ({ category, value }));
}

const movementSeries = [
    { field: 'debit', name: 'Cash In', color: '--cvr-green-bright' },
    { field: 'credit', name: 'Cash Out', color: '--cvr-num-red' },
    { field: 'end_balance', name: 'End Balance', color: '--cvr-blue' },
];

/* ── Formatting / Number Color Rule helpers ──────────────────── */
function fmt(value) {
    const n = Number(value || 0);
    return n.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

/* ── Details panel toggle (replaces the original's Bootstrap modals
   with an inline expandable panel — consistent with this rebuild's
   preference for inline drill-down over modals, e.g. Aging/Result). */
const openDetail = ref(null); // 'cash' | 'td' | 'cd' | 'loans' | 'leasing' | null
function toggleDetail(key) {
    openDetail.value = openDetail.value === key ? null : key;
}

const openLimitDetail = ref(null); // overdraft section.type or null
function toggleLimitDetail(sectionType) {
    openLimitDetail.value = openLimitDetail.value === sectionType ? null : sectionType;
}

function limitDetailRows(section) {
    return section.room?.[activeCurrency.value] || [];
}

function sumLimitDetail(section, key) {
    return limitDetailRows(section).reduce((s, r) => s + Number(r[key] || 0), 0);
}

const expandedLoanId = ref(null);
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Dashboard</h1>
            <p class="text-sm cvr-text-muted mb-4">Current cash position, facility utilization &amp; bank movement</p>

            <DashboardTabs active="cash" :urls="dashboardTabUrls" />

            <!-- Date + account owner filters -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-3 mb-6 flex items-end gap-3 flex-wrap">
                <div>
                    <label class="cvr-form-label">As Of Date</label>
                    <input v-model="filterDate" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>

                <div v-if="canManageShareholderAccounts">
                    <label class="cvr-form-label">Accounts</label>
                    <select v-model="accountOwner" @change="onAccountOwnerChange" class="cvr-select px-3 py-2 rounded">
                        <option value="company">Company accounts</option>
                        <option value="all">All accounts</option>
                        <option value="shareholders">Shareholders accounts</option>
                    </select>
                </div>

                <div v-if="canManageShareholderAccounts && showsShareholderPicker">
                    <label class="cvr-form-label">Shareholder</label>
                    <select v-model="shareholderPartnerId" @change="applyFilters" class="cvr-select px-3 py-2 rounded">
                        <option value="">All shareholders</option>
                        <option v-for="shareholder in shareholders" :key="shareholder.id" :value="shareholder.id">
                            {{ shareholder.name }}
                        </option>
                    </select>
                </div>

                <button class="cvr-btn-primary px-4 py-2 rounded" @click="applyDateFilter">Apply</button>

                <p v-if="canManageShareholderAccounts && accountOwner === 'shareholders'" class="text-xs cvr-text-muted w-full">
                    Cash in safe, leasing and all overdraft facilities are company-only instruments, so they are excluded from this view.
                </p>
            </div>

            <!-- Currency tabs -->
            <div class="flex items-center gap-2 flex-wrap mb-6">
                <button
                    v-for="currency in selectedCurrencies"
                    :key="currency"
                    class="cvr-filter-pill"
                    :class="{ 'cvr-filter-pill-active': activeCurrency === currency }"
                    @click="activeCurrency = currency"
                >
                    {{ currency }}
                </button>
            </div>

            <template v-if="activeCurrency">
                <!-- Current Cash Position -->
                <div class="cvr-section-heading"><h2>Current Cash Position [{{ activeCurrency }}]</h2></div>
                <div class="cvr-kpi-row-4 mb-6">
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('cash')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">🏦</div>
                        <div>
                            <p class="cvr-kpi-label">Cash &amp; Banks</p>
                            <p class="cvr-kpi-value cvr-num-blue">{{ fmt(reports?.cash_and_banks?.[activeCurrency]) }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('td')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">⏱</div>
                        <div>
                            <p class="cvr-kpi-label">Time Deposit</p>
                            <p class="cvr-kpi-value cvr-num-amber">{{ fmt(reports?.time_deposits?.[activeCurrency]) }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('cd')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">📄</div>
                        <div>
                            <p class="cvr-kpi-label">Certificate Of Deposit</p>
                            <p class="cvr-kpi-value cvr-num-amber">{{ fmt(reports?.certificate_of_deposits?.[activeCurrency]) }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-green">Σ</div>
                        <div>
                            <p class="cvr-kpi-label">Total</p>
                            <p class="cvr-kpi-value cvr-num-green">{{ fmt(reports?.total?.[activeCurrency]) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Expandable details panels -->
                <div v-if="openDetail === 'cash'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-4 py-2 text-left">Source</th>
                                <th class="px-4 py-2 text-left">Financial Institution / Branch Name</th>
                                <th class="px-4 py-2 text-left">Account Number</th>
                                <th class="px-4 py-2 text-right">Amount [ {{ activeCurrency }} ]</th>
                                <th v-if="activeCurrency !== mainFunctionalCurrency" class="px-4 py-2 text-right">Exchange Rate</th>
                                <th v-if="activeCurrency !== mainFunctionalCurrency" class="px-4 py-2 text-right">Amount [ {{ mainFunctionalCurrency }} ]</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Sorted largest balance first (per explicit product decision) —
                                 done server-side in CashDashboardService, combining Current
                                 Account (bank) and Cash In Safe (branch) rows into one list so
                                 they sort together rather than as two separate sections. -->
                            <tr v-for="(row, i) in (details?.[activeCurrency]?.cash_and_banks || [])" :key="i" class="cvr-table-row">
                                <td class="px-4 py-2">{{ row.source }}</td>
                                <td class="px-4 py-2">{{ row.financial_institution_name }}</td>
                                <td class="px-4 py-2">{{ row.account_number }}</td>
                                <td class="px-4 py-2 text-right cvr-num">{{ fmt(row.amount) }}</td>
                                <td v-if="activeCurrency !== mainFunctionalCurrency" class="px-4 py-2 text-right cvr-num">{{ row.exchange_rate }}</td>
                                <td v-if="activeCurrency !== mainFunctionalCurrency" class="px-4 py-2 text-right cvr-num">{{ fmt(row.amount_in_main_currency) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="openDetail === 'td'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head"><tr><th class="px-4 py-2 text-left">Bank</th><th class="px-4 py-2 text-right">Amount</th></tr></thead>
                        <tbody>
                            <tr v-for="(row, i) in (details?.[activeCurrency]?.time_of_deposits || [])" :key="i" class="cvr-table-row">
                                <td class="px-4 py-2">{{ row.financial_institution_name }}</td>
                                <td class="px-4 py-2 text-right cvr-num">{{ fmt(row.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="openDetail === 'cd'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head"><tr><th class="px-4 py-2 text-left">Bank</th><th class="px-4 py-2 text-right">Amount</th></tr></thead>
                        <tbody>
                            <tr v-for="(row, i) in (details?.[activeCurrency]?.certificate_of_deposits || [])" :key="i" class="cvr-table-row">
                                <td class="px-4 py-2">{{ row.financial_institution_name }}</td>
                                <td class="px-4 py-2 text-right cvr-num">{{ fmt(row.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Total Cash Facilities -->
                <div class="cvr-section-heading"><h2>Total Cash Facilities [{{ activeCurrency }}]</h2></div>
                <div class="cvr-kpi-row-4 mb-8">
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">🎯</div>
                        <div><p class="cvr-kpi-label">Limit</p><p class="cvr-kpi-value cvr-num-blue">{{ fmt(totalCard?.[activeCurrency]?.limit) }}</p></div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">📉</div>
                        <div><p class="cvr-kpi-label">Outstanding</p><p class="cvr-kpi-value cvr-num-amber">{{ fmt(totalCard?.[activeCurrency]?.outstanding) }}</p></div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-green">✅</div>
                        <div><p class="cvr-kpi-label">Available</p><p class="cvr-kpi-value cvr-num-green">{{ fmt(totalCard?.[activeCurrency]?.room) }}</p></div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">%</div>
                        <div><p class="cvr-kpi-label">Interest</p><p class="cvr-kpi-value cvr-num">{{ fmt(totalCard?.[activeCurrency]?.interest_amount) }}</p></div>
                    </div>
                </div>

                <!-- Per overdraft-type sections -->
                <div v-for="section in overdraftSections" :key="section.type" class="mb-8">
                    <div class="cvr-section-heading justify-between w-full">
                        <h3>{{ section.label }}</h3>
                        <div class="flex gap-2 ms-auto">
                            <Link v-if="bankStatementUrls?.[section.type]?.[activeCurrency]" :href="bankStatementUrls[section.type][activeCurrency]" class="cvr-btn-secondary px-3 py-1.5 rounded border text-xs">📈 Bank Statement Report</Link>
                            <Link v-if="withdrawalReportUrls?.[section.type]?.[activeCurrency]" :href="withdrawalReportUrls[section.type][activeCurrency]" class="cvr-btn-secondary px-3 py-1.5 rounded border text-xs">📉 Withdrawal Report</Link>
                        </div>
                    </div>

                    <div class="cvr-form-grid-4 mb-4">
                        <div
                            class="cvr-mini-kpi cursor-pointer"
                            :class="{ 'ring-1 ring-[var(--cvr-blue)]': openLimitDetail === section.type }"
                            @click="toggleLimitDetail(section.type)"
                        >
                            <p class="cvr-mini-kpi-label">Limit</p>
                            <p class="cvr-mini-kpi-value cvr-num-blue">{{ fmt(section.cardData?.[activeCurrency]?.limit) }}</p>
                        </div>
                        <div class="cvr-mini-kpi"><p class="cvr-mini-kpi-label">Outstanding</p><p class="cvr-mini-kpi-value cvr-num-amber">{{ fmt(section.cardData?.[activeCurrency]?.outstanding) }}</p></div>
                        <div class="cvr-mini-kpi"><p class="cvr-mini-kpi-label">Available Room</p><p class="cvr-mini-kpi-value cvr-num-green">{{ fmt(section.cardData?.[activeCurrency]?.room) }}</p></div>
                        <div class="cvr-mini-kpi"><p class="cvr-mini-kpi-label">Interest</p><p class="cvr-mini-kpi-value cvr-num">{{ fmt(section.cardData?.[activeCurrency]?.interest_amount) }}</p></div>
                    </div>

                    <div v-if="openLimitDetail === section.type" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-4">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-4 py-2 text-left">Bank Name</th>
                                    <th class="px-4 py-2 text-right">Limit</th>
                                    <th class="px-4 py-2 text-right">Outstanding</th>
                                    <th class="px-4 py-2 text-right">Available Room</th>
                                    <th class="px-4 py-2 text-right">Interest</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in limitDetailRows(section)" :key="i" class="cvr-table-row">
                                    <td class="px-4 py-2">{{ row.item || '—' }}</td>
                                    <td class="px-4 py-2 text-right cvr-num-blue">{{ fmt(row.limit) }}</td>
                                    <td class="px-4 py-2 text-right cvr-num-amber">{{ fmt(row.end_balance) }}</td>
                                    <td class="px-4 py-2 text-right cvr-num-green">{{ fmt(row.available_room) }}</td>
                                    <td class="px-4 py-2 text-right cvr-num">{{ fmt(row.interest) }}</td>
                                </tr>
                                <tr v-if="!limitDetailRows(section).length">
                                    <td colspan="5" class="px-4 py-4 text-center cvr-text-muted">No facility rows for this currency.</td>
                                </tr>
                                <tr v-else class="cvr-table-row cvr-summary-row">
                                    <td class="px-4 py-2 font-semibold">Total</td>
                                    <td class="px-4 py-2 text-right cvr-num-blue font-semibold">{{ fmt(sumLimitDetail(section, 'limit')) }}</td>
                                    <td class="px-4 py-2 text-right cvr-num-amber font-semibold">{{ fmt(sumLimitDetail(section, 'end_balance')) }}</td>
                                    <td class="px-4 py-2 text-right cvr-num-green font-semibold">{{ fmt(sumLimitDetail(section, 'available_room')) }}</td>
                                    <td class="px-4 py-2 text-right cvr-num font-semibold">{{ fmt(sumLimitDetail(section, 'interest')) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="cvr-chart-card" style="border-top-color: var(--cvr-copper-bright)">
                            <h4 class="text-sm font-semibold cvr-text-primary mb-2">Available Room by Bank</h4>
                            <DonutChart3D
                                :key="`${section.type}-${activeCurrency}`"
                                :data="roomDonutData(section)"
                                :colors="['--cvr-blue', '--cvr-green-bright', '--cvr-copper-bright', '--cvr-num-amber', '--cvr-num-red']"
                            />
                        </div>
                        <div class="cvr-chart-card lg:col-span-2" style="border-top-color: var(--cvr-blue)">
                            <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                                <h4 class="text-sm font-semibold cvr-text-primary">Bank Movement</h4>
                                <div class="flex gap-2">
                                    <select v-model="stateFor(section.type).bankId" class="cvr-input px-2 py-1 rounded text-xs" @change="onBankChange(section)">
                                        <option v-for="bank in section.banks" :key="bank.id" :value="String(bank.id)">{{ bank.name }}</option>
                                    </select>
                                    <select v-model="stateFor(section.type).accountNumber" class="cvr-input px-2 py-1 rounded text-xs" @change="onAccountChange(section)">
                                        <option value="">Select Account</option>
                                        <option v-for="opt in stateFor(section.type).accountOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <MultiLineChart :data="stateFor(section.type).chartData" :series="movementSeries" :sync-axes="false" />
                        </div>
                    </div>
                </div>

                <!-- Medium/Long Term Loans -->
                <template v-if="(mediumTermLoansArr?.[activeCurrency] || []).length">
                    <div class="cvr-section-heading"><h3>Medium/Long Term Loans [{{ activeCurrency }}]</h3></div>
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto mb-8">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-4 py-2 text-left">Bank</th>
                                    <th class="px-4 py-2 text-left">Loan</th>
                                    <th class="px-4 py-2 text-right">Limit</th>
                                    <th class="px-4 py-2 text-right">Paid</th>
                                    <th class="px-4 py-2 text-right">Outstanding</th>
                                    <th class="px-4 py-2 text-left">Next Installment</th>
                                    <th class="px-4 py-2 text-right">Past Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="loan in mediumTermLoansArr[activeCurrency]" :key="loan.id">
                                    <tr class="cvr-table-row cursor-pointer" @click="expandedLoanId = expandedLoanId === 'ml-'+loan.id ? null : 'ml-'+loan.id">
                                        <td class="px-4 py-2">{{ loan.institution_name }}</td>
                                        <td class="px-4 py-2">{{ loan.name }}</td>
                                        <td class="px-4 py-2 text-right cvr-num-blue">{{ loan.limit_formatted }}</td>
                                        <td class="px-4 py-2 text-right cvr-num-green">{{ loan.paid_formatted }}</td>
                                        <td class="px-4 py-2 text-right cvr-num-amber">{{ loan.outstanding_formatted }}</td>
                                        <td class="px-4 py-2">{{ loan.next_installment_date || '—' }} <span v-if="loan.next_installment_amount" class="cvr-num">({{ loan.next_installment_amount }})</span></td>
                                        <td class="px-4 py-2 text-right cvr-num-red">{{ loan.total_past_due_remaining_formatted }}</td>
                                    </tr>
                                    <tr v-if="expandedLoanId === 'ml-'+loan.id" class="cvr-sub-row">
                                        <td colspan="7" class="px-4 py-2">
                                            <div v-if="(loan.past_dues || []).length" class="text-xs">
                                                <span v-for="(pd, i) in loan.past_dues" :key="i" class="inline-block me-4 mb-1">
                                                    {{ pd.date }}: <span class="cvr-num-red">{{ fmt(pd.remaining) }}</span>
                                                </span>
                                            </div>
                                            <div v-else class="text-xs cvr-text-muted">No past-due installments.</div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- Leasing Contracts -->
                <template v-if="(leasingContractsArr?.[activeCurrency] || []).length">
                    <div class="cvr-section-heading"><h3>Leasing Contracts [{{ activeCurrency }}]</h3></div>
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto mb-8">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-4 py-2 text-left">Leasing Company</th>
                                    <th class="px-4 py-2 text-left">Contract</th>
                                    <th class="px-4 py-2 text-right">Limit</th>
                                    <th class="px-4 py-2 text-right">Paid</th>
                                    <th class="px-4 py-2 text-right">Outstanding</th>
                                    <th class="px-4 py-2 text-left">Next Installment</th>
                                    <th class="px-4 py-2 text-right">Past Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="lease in leasingContractsArr[activeCurrency]" :key="lease.id">
                                    <tr class="cvr-table-row cursor-pointer" @click="expandedLoanId = expandedLoanId === 'lc-'+lease.id ? null : 'lc-'+lease.id">
                                        <td class="px-4 py-2">{{ lease.institution_name }}</td>
                                        <td class="px-4 py-2">{{ lease.name }}</td>
                                        <td class="px-4 py-2 text-right cvr-num-blue">{{ lease.limit_formatted }}</td>
                                        <td class="px-4 py-2 text-right cvr-num-green">{{ lease.paid_formatted }}</td>
                                        <td class="px-4 py-2 text-right cvr-num-amber">{{ lease.outstanding_formatted }}</td>
                                        <td class="px-4 py-2">{{ lease.next_installment_date || '—' }} <span v-if="lease.next_installment_amount" class="cvr-num">({{ lease.next_installment_amount }})</span></td>
                                        <td class="px-4 py-2 text-right cvr-num-red">{{ lease.total_past_due_remaining_formatted }}</td>
                                    </tr>
                                    <tr v-if="expandedLoanId === 'lc-'+lease.id" class="cvr-sub-row">
                                        <td colspan="7" class="px-4 py-2">
                                            <div v-if="(lease.past_dues || []).length" class="text-xs">
                                                <span v-for="(pd, i) in lease.past_dues" :key="i" class="inline-block me-4 mb-1">
                                                    {{ pd.date }}: <span class="cvr-num-red">{{ fmt(pd.remaining) }}</span>
                                                </span>
                                            </div>
                                            <div v-else class="text-xs cvr-text-muted">No past-due installments.</div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </template>
        </div>
    </AppLayout>
</template>
