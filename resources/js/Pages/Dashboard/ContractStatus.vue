<script setup>
/**
 * Dashboard/ContractStatus.vue
 * ------------------------------------------------------------------
 * Served by ContractDashboardController@index. Customer Contract
 * Dashboard — count KPIs by status, per-currency billing value /
 * invoiced / remaining / utilization, collections, expiry alerts,
 * and Top 10 customers. Clickable KPI cards expand an inline detail
 * table (same pattern as CashStatus.vue).
 *
 * All math comes from ContractDashboardService::build() — this page
 * only renders what the controller already computed.
 */
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardTabs from '@/Components/DashboardTabs.vue';

const props = defineProps({
    company: Object,
    counts: Object,
    currencies: Array,
    byCurrency: Object,
    alerts: Object,
    topByValue: Object,
    topByRemaining: Object,
    topByCount: Object,
    topByUncollected: Object,
    mainCurrency: String,
    mainCurrencyTotals: Object,
    dataQuality: Object,
    details: Object,
    aging: Object,
    agingBuckets: Object,
    trend: Object,
    trendMonths: Number,
    asOfDate: String,
    asOfDateFormatted: String,
    isAsOfToday: Boolean,
    filterUrl: String,
    exportUrl: String,
    nearExpiryDays: Number,
    canViewContracts: Boolean,
    contractsIndexUrl: String,
    dashboardTabUrls: Object,
});

const activeCurrency = ref(props.currencies?.[0] || '');
const openDetail = ref(null);

watch(
    () => props.currencies,
    (list) => {
        if (!list?.length) {
            activeCurrency.value = '';
            return;
        }
        if (!list.includes(activeCurrency.value)) {
            activeCurrency.value = list[0];
        }
    },
    { immediate: true }
);

const currencyKpis = computed(() => props.byCurrency?.[activeCurrency.value] || {
    contract_count: 0,
    value: 0,
    invoiced: 0,
    remaining: 0,
    utilization: 0,
    billed: 0,
    collected: 0,
    deductions: 0,
    uncollected: 0,
    collection_rate: 0,
    reconciliation_gap: 0,
});

const detailTitle = computed(() => {
    const map = {
        all: 'All Contracts',
        running: 'Running Contracts',
        running_and_against: 'Running & Against Contracts',
        finished: 'Finished Contracts',
        value: `Contracts in ${activeCurrency.value}`,
        invoiced: `Contracts in ${activeCurrency.value}`,
        remaining: `Contracts in ${activeCurrency.value}`,
        expired: 'Expired (Past End Date, Still Open)',
        past_end_date: 'Past End Date (Still Open)',
        ending_soon: `Ending Within ${props.nearExpiryDays} Days`,
        not_invoiced: 'Contracts With No Invoices Yet',
        over_billed: 'Over-Billed (Invoiced Above Contract Value)',
    };
    return map[openDetail.value] || 'Contracts';
});

const detailRows = computed(() => {
    if (!openDetail.value) return [];
    if (['value', 'invoiced', 'remaining'].includes(openDetail.value)) {
        return props.details?.by_currency?.[activeCurrency.value] || [];
    }
    return props.details?.[openDetail.value] || [];
});

const topValueRows = computed(() => props.topByValue?.[activeCurrency.value] || []);
const topRemainingRows = computed(() => props.topByRemaining?.[activeCurrency.value] || []);
const topCountRows = computed(() => props.topByCount?.[activeCurrency.value] || []);
const topUncollectedRows = computed(() => props.topByUncollected?.[activeCurrency.value] || []);

const showDataQuality = computed(() => {
    const q = props.dataQuality || {};
    return (q.mismatched_currency_count || 0) + (q.exchange_rate_issue_count || 0)
        + (q.contracts_without_code_count || 0) + (q.unbalanced_invoice_count || 0) > 0;
});

const openQuality = ref(null);
function toggleQuality(key) {
    openQuality.value = openQuality.value === key ? null : key;
}

// ── as-of date filter ───────────────────────────────────────────────
const filterDate = ref(props.asOfDate || '');
watch(() => props.asOfDate, (value) => { filterDate.value = value || ''; });

function applyDate() {
    router.get(props.filterUrl, filterDate.value ? { date: filterDate.value } : {}, {
        preserveScroll: true,
        preserveState: false,
    });
}

function resetDate() {
    filterDate.value = '';
    router.get(props.filterUrl, {}, { preserveScroll: true, preserveState: false });
}

const exportHref = computed(() => {
    const params = new URLSearchParams();
    if (props.asOfDate) params.set('date', props.asOfDate);
    if (activeCurrency.value) params.set('currency', activeCurrency.value);
    const qs = params.toString();
    return qs ? `${props.exportUrl}?${qs}` : props.exportUrl;
});

// ── aging ───────────────────────────────────────────────────────────
const agingRow = computed(() => props.aging?.[activeCurrency.value] || {});
const agingBucketKeys = computed(() => Object.keys(props.agingBuckets || {}));

function agingAmount(bucket) {
    return agingRow.value?.[bucket]?.amount ?? 0;
}
function agingCount(bucket) {
    return agingRow.value?.[bucket]?.invoice_count ?? 0;
}
function agingShare(bucket) {
    const total = agingRow.value?.total?.amount || 0;
    if (!total) return 0;
    return (agingAmount(bucket) / total) * 100;
}

// ── trend ───────────────────────────────────────────────────────────
const trendRows = computed(() => props.trend?.[activeCurrency.value] || []);
const trendPeak = computed(() => {
    const values = trendRows.value.flatMap((m) => [Number(m.invoiced || 0), Number(m.collected || 0)]);
    return values.length ? Math.max(...values, 0) : 0;
});
function barWidth(value) {
    if (!trendPeak.value) return '0%';
    return `${Math.max((Number(value || 0) / trendPeak.value) * 100, 0)}%`;
}

function toggleDetail(key) {
    openDetail.value = openDetail.value === key ? null : key;
}

function fmt(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

function fmtPct(value) {
    return `${Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })}%`;
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Dashboard</h1>
            <p class="text-sm cvr-text-muted mb-4">
                Customer contract counts, billing progress &amp; top customers
            </p>

            <DashboardTabs active="contracts" :urls="dashboardTabUrls" />

            <!--
                An AS-OF date, not a range: moving it back re-asks every
                question at that point in time — what had been invoiced,
                which contracts had expired, how overdue the receivables
                were. Same meaning the LG/LC dashboard's date filter has.
            -->
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="block text-xs cvr-text-muted mb-1" for="contract-dashboard-as-of">As Of Date</label>
                    <input
                        id="contract-dashboard-as-of"
                        v-model="filterDate"
                        type="date"
                        class="cvr-input px-2 py-1.5 rounded border text-sm"
                        @keyup.enter="applyDate"
                    />
                </div>
                <button type="button" class="cvr-btn-primary px-3 py-1.5 rounded border text-sm" @click="applyDate">Apply</button>
                <button v-if="!isAsOfToday" type="button" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm" @click="resetDate">Today</button>

                <a :href="exportHref" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    Export Excel<span v-if="activeCurrency"> [{{ activeCurrency }}]</span>
                </a>

                <Link
                    v-if="canViewContracts && contractsIndexUrl"
                    :href="contractsIndexUrl"
                    class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm"
                >
                    Open Customer Contracts
                </Link>
            </div>

            <p v-if="!isAsOfToday" class="text-sm cvr-num-amber mb-4">
                Showing everything as of {{ asOfDateFormatted }} — invoices raised after that date are not counted.
            </p>

            <!-- Status counts (company-wide) -->
            <div class="cvr-section-heading"><h2>Contract Counts</h2></div>
            <div class="cvr-kpi-row-4 mb-6">
                <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('all')">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">Σ</div>
                    <div>
                        <p class="cvr-kpi-label">Total</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ counts?.total ?? 0 }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('running')">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">▶</div>
                    <div>
                        <p class="cvr-kpi-label">Running</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ counts?.running ?? 0 }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('running_and_against')">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">⧉</div>
                    <div>
                        <p class="cvr-kpi-label">Running &amp; Against</p>
                        <p class="cvr-kpi-value cvr-num-amber">{{ counts?.running_and_against ?? 0 }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('expired')">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">⌛</div>
                    <div>
                        <p class="cvr-kpi-label">Expired</p>
                        <p class="cvr-kpi-value cvr-num-amber">{{ counts?.expired ?? 0 }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('finished')">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">✓</div>
                    <div>
                        <p class="cvr-kpi-label">Finished</p>
                        <p class="cvr-kpi-value cvr-num">{{ counts?.finished ?? 0 }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('not_invoiced')">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">◌</div>
                    <div>
                        <p class="cvr-kpi-label">Not Invoiced Yet</p>
                        <p class="cvr-kpi-value cvr-num">{{ counts?.not_invoiced ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <!--
                Running counts only contracts still inside their end date;
                anything open but past it is Expired, so the four add up to
                Total exactly.
            -->
            <p class="text-xs cvr-text-muted mb-4">
                Running + Running &amp; Against + Expired + Finished = Total.
                “Not Invoiced Yet” overlaps the others — it counts contracts with no invoice raised against them.
            </p>

            <div
                v-if="['all', 'running', 'running_and_against', 'expired', 'finished', 'not_invoiced', 'over_billed'].includes(openDetail)"
                class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6"
            >
                <div class="px-4 py-3 border-b cvr-border flex items-center justify-between">
                    <h3 class="text-sm font-semibold cvr-text-primary">{{ detailTitle }}</h3>
                    <button type="button" class="text-xs cvr-text-muted" @click="openDetail = null">Close</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-left">Customer</th>
                                <th class="px-3 py-2 text-left">Code</th>
                                <th class="px-3 py-2 text-left">Name</th>
                                <th class="px-3 py-2 text-center">Currency</th>
                                <th class="px-3 py-2 text-right">Value</th>
                                <th class="px-3 py-2 text-right">Invoiced</th>
                                <th class="px-3 py-2 text-right">Remaining</th>
                                <th class="px-3 py-2 text-center">End Date</th>
                                <th class="px-3 py-2 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in detailRows" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2 cvr-text-primary">{{ row.customer_name }}</td>
                                <td class="px-3 py-2 cvr-text-secondary">{{ row.code }}</td>
                                <td class="px-3 py-2 cvr-text-secondary">{{ row.name }}</td>
                                <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.currency }}</td>
                                <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.amount) }}</td>
                                <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.invoiced) }}</td>
                                <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.remaining) }}</td>
                                <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.end_date_formatted || '—' }}</td>
                                <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.status_label }}</td>
                            </tr>
                            <tr v-if="!detailRows.length">
                                <td colspan="9" class="px-4 py-8 text-center cvr-text-muted">No contracts in this group.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Company-wide, in the company's own currency -->
            <div v-if="currencies?.length" class="cvr-section-heading"><h2>Company Total [{{ mainCurrency }}]</h2></div>
            <div v-if="currencies?.length" class="cvr-kpi-row-4 mb-2">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">Σ</div>
                    <div>
                        <p class="cvr-kpi-label">Contract Value</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ fmt(mainCurrencyTotals?.value) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">⬆</div>
                    <div>
                        <p class="cvr-kpi-label">Invoiced</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ fmt(mainCurrencyTotals?.invoiced) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">✅</div>
                    <div>
                        <p class="cvr-kpi-label">Collected</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ fmt(mainCurrencyTotals?.collected) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">💳</div>
                    <div>
                        <p class="cvr-kpi-label">Uncollected</p>
                        <p class="cvr-kpi-value cvr-num-amber">{{ fmt(mainCurrencyTotals?.uncollected) }}</p>
                    </div>
                </div>
            </div>
            <!--
                Invoiced / Collected / Uncollected use the rate each invoice
                recorded. Contract Value can only use contracts.exchange_rate,
                so contracts whose rate is missing or left at 1 are left out
                rather than converted at a rate nobody set.
            -->
            <p v-if="currencies?.length" class="text-xs cvr-text-muted mb-6">
                Invoice figures use each invoice's own recorded rate.
                <span v-if="mainCurrencyTotals?.value_unconvertible_count">
                    ⚠ Contract Value excludes
                    {{ mainCurrencyTotals.value_unconvertible_count }} contract(s) with no usable exchange rate — see Data Quality below.
                </span>
            </p>

            <!-- Currency pills -->
            <div v-if="currencies?.length" class="flex items-center gap-2 flex-wrap mb-6">
                <button
                    v-for="currency in currencies"
                    :key="currency"
                    type="button"
                    class="cvr-filter-pill"
                    :class="{ 'cvr-filter-pill-active': activeCurrency === currency }"
                    @click="activeCurrency = currency"
                >
                    {{ currency }}
                </button>
            </div>
            <p v-else class="text-sm cvr-text-muted mb-6">No customer contracts yet.</p>

            <template v-if="activeCurrency">
                <!-- Value row -->
                <div class="cvr-section-heading"><h2>Contract Billing [{{ activeCurrency }}]</h2></div>
                <div class="cvr-kpi-row-4 mb-6">
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('value')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">📄</div>
                        <div>
                            <p class="cvr-kpi-label">Contract Value</p>
                            <p class="cvr-kpi-value cvr-num-blue">{{ fmt(currencyKpis.value) }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('invoiced')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-green">⬆</div>
                        <div>
                            <p class="cvr-kpi-label">Invoiced</p>
                            <p class="cvr-kpi-value cvr-num-green">{{ fmt(currencyKpis.invoiced) }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('remaining')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">⏳</div>
                        <div>
                            <p class="cvr-kpi-label">Remaining to Invoice</p>
                            <p class="cvr-kpi-value cvr-num-amber">{{ fmt(currencyKpis.remaining) }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">%</div>
                        <div>
                            <p class="cvr-kpi-label">Utilization</p>
                            <p class="cvr-kpi-value cvr-num">{{ fmtPct(currencyKpis.utilization) }}</p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="['value', 'invoiced', 'remaining'].includes(openDetail)"
                    class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6"
                >
                    <div class="px-4 py-3 border-b cvr-border flex items-center justify-between">
                        <h3 class="text-sm font-semibold cvr-text-primary">{{ detailTitle }}</h3>
                        <button type="button" class="text-xs cvr-text-muted" @click="openDetail = null">Close</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">Customer</th>
                                    <th class="px-3 py-2 text-left">Code</th>
                                    <th class="px-3 py-2 text-left">Name</th>
                                    <th class="px-3 py-2 text-right">Value</th>
                                    <th class="px-3 py-2 text-right">Invoiced</th>
                                    <th class="px-3 py-2 text-right">Remaining</th>
                                    <th class="px-3 py-2 text-center">End Date</th>
                                    <th class="px-3 py-2 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in detailRows" :key="'ccy-' + row.id" class="cvr-table-row">
                                    <td class="px-3 py-2 cvr-text-primary">{{ row.customer_name }}</td>
                                    <td class="px-3 py-2 cvr-text-secondary">{{ row.code }}</td>
                                    <td class="px-3 py-2 cvr-text-secondary">{{ row.name }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.amount) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.invoiced) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.remaining) }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.end_date_formatted || '—' }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.status_label }}</td>
                                </tr>
                                <tr v-if="!detailRows.length">
                                    <td colspan="8" class="px-4 py-8 text-center cvr-text-muted">No contracts in this currency.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!--
                    Collections is a different money base from Billing above:
                    contract value and Invoiced are before tax, these are the
                    VAT-inclusive invoice figures, which reconcile among
                    themselves. Mixing the two is what made the old row
                    impossible to balance.
                -->
                <div class="cvr-section-heading"><h2>Collections [{{ activeCurrency }}] — including tax</h2></div>
                <div class="cvr-kpi-row-4 mb-2">
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">🧾</div>
                        <div>
                            <p class="cvr-kpi-label">Billed (incl. tax)</p>
                            <p class="cvr-kpi-value cvr-num-blue">{{ fmt(currencyKpis.billed) }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-green">✅</div>
                        <div>
                            <p class="cvr-kpi-label">Collected</p>
                            <p class="cvr-kpi-value cvr-num-green">{{ fmt(currencyKpis.collected) }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">💳</div>
                        <div>
                            <p class="cvr-kpi-label">Uncollected AR</p>
                            <p class="cvr-kpi-value cvr-num-amber">{{ fmt(currencyKpis.uncollected) }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">%</div>
                        <div>
                            <p class="cvr-kpi-label">Collection Rate</p>
                            <p class="cvr-kpi-value cvr-num">{{ fmtPct(currencyKpis.collection_rate) }}</p>
                        </div>
                    </div>
                </div>
                <p class="text-xs cvr-text-muted mb-6">
                    Billed − Collected − Deductions ({{ fmt(currencyKpis.deductions) }}) = Uncollected.
                    <span v-if="Math.abs(Number(currencyKpis.reconciliation_gap || 0)) > 0.01" class="cvr-num-amber">
                        ⚠ Off by {{ fmt(currencyKpis.reconciliation_gap) }} — the invoice rows themselves do not add up; see Data Quality below.
                    </span>
                </p>

                <!-- Alerts -->
                <div class="cvr-section-heading"><h2>Alerts</h2></div>
                <div class="cvr-kpi-row-4 mb-6">
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('past_end_date')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">⚠</div>
                        <div>
                            <p class="cvr-kpi-label">Past End Date (Still Open)</p>
                            <p class="cvr-kpi-value cvr-num-amber">{{ alerts?.past_end_date_count ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('ending_soon')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">📅</div>
                        <div>
                            <p class="cvr-kpi-label">Ending Within {{ nearExpiryDays }} Days</p>
                            <p class="cvr-kpi-value cvr-num-blue">{{ alerts?.ending_soon_count ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('not_invoiced')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">◌</div>
                        <div>
                            <p class="cvr-kpi-label">No Invoices Raised</p>
                            <p class="cvr-kpi-value cvr-num">{{ alerts?.not_invoiced_count ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleDetail('over_billed')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">⚠</div>
                        <div>
                            <p class="cvr-kpi-label">Over-Billed</p>
                            <p class="cvr-kpi-value cvr-num-amber">{{ alerts?.over_billed_count ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="['past_end_date', 'ending_soon'].includes(openDetail)"
                    class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6"
                >
                    <div class="px-4 py-3 border-b cvr-border flex items-center justify-between">
                        <h3 class="text-sm font-semibold cvr-text-primary">{{ detailTitle }}</h3>
                        <button type="button" class="text-xs cvr-text-muted" @click="openDetail = null">Close</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">Customer</th>
                                    <th class="px-3 py-2 text-left">Code</th>
                                    <th class="px-3 py-2 text-center">Currency</th>
                                    <th class="px-3 py-2 text-right">Value</th>
                                    <th class="px-3 py-2 text-right">Remaining</th>
                                    <th class="px-3 py-2 text-center">End Date</th>
                                    <th class="px-3 py-2 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in detailRows" :key="'alert-' + row.id" class="cvr-table-row">
                                    <td class="px-3 py-2 cvr-text-primary">{{ row.customer_name }}</td>
                                    <td class="px-3 py-2 cvr-text-secondary">{{ row.code }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.currency }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.amount) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.remaining) }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.end_date_formatted || '—' }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.status_label }}</td>
                                </tr>
                                <tr v-if="!detailRows.length">
                                    <td colspan="7" class="px-4 py-8 text-center cvr-text-muted">No contracts in this alert.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top 10 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                        <div class="px-4 py-3 border-b cvr-border">
                            <h3 class="text-sm font-semibold cvr-text-primary">Top 10 Customers by Value [{{ activeCurrency }}]</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="cvr-table-head">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Customer</th>
                                        <th class="px-3 py-2 text-center">Contracts</th>
                                        <th class="px-3 py-2 text-right">Value</th>
                                        <th class="px-3 py-2 text-right">Invoiced</th>
                                        <th class="px-3 py-2 text-right">Remaining</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in topValueRows" :key="'tv-' + row.partner_id" class="cvr-table-row">
                                        <td class="px-3 py-2 cvr-text-primary">{{ row.customer_name }}</td>
                                        <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.contract_count }}</td>
                                        <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.value) }}</td>
                                        <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.invoiced) }}</td>
                                        <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.remaining) }}</td>
                                    </tr>
                                    <tr v-if="!topValueRows.length">
                                        <td colspan="5" class="px-4 py-8 text-center cvr-text-muted">No data.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                        <div class="px-4 py-3 border-b cvr-border">
                            <h3 class="text-sm font-semibold cvr-text-primary">Top 10 by Remaining to Invoice [{{ activeCurrency }}]</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="cvr-table-head">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Customer</th>
                                        <th class="px-3 py-2 text-center">Contracts</th>
                                        <th class="px-3 py-2 text-right">Value</th>
                                        <th class="px-3 py-2 text-right">Invoiced</th>
                                        <th class="px-3 py-2 text-right">Remaining</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in topRemainingRows" :key="'tr-' + row.partner_id" class="cvr-table-row">
                                        <td class="px-3 py-2 cvr-text-primary">{{ row.customer_name }}</td>
                                        <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.contract_count }}</td>
                                        <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.value) }}</td>
                                        <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.invoiced) }}</td>
                                        <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.remaining) }}</td>
                                    </tr>
                                    <tr v-if="!topRemainingRows.length">
                                        <td colspan="5" class="px-4 py-8 text-center cvr-text-muted">No data.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!--
                    Aging splits the SAME uncollected total above by how
                    far past its due date each invoice is — the buckets
                    add up to Uncollected exactly.
                -->
                <div class="cvr-section-heading">
                    <h2>Receivables Aging [{{ activeCurrency }}] — as of {{ asOfDateFormatted }}</h2>
                </div>
                <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-2">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">Bucket</th>
                                    <th class="px-3 py-2 text-center">Invoices</th>
                                    <th class="px-3 py-2 text-right">Uncollected</th>
                                    <th class="px-3 py-2 text-left" style="width: 40%;">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="bucket in agingBucketKeys" :key="'ag-' + bucket" class="cvr-table-row">
                                    <td class="px-3 py-2 cvr-text-primary">{{ agingBuckets[bucket] }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ agingCount(bucket) }}</td>
                                    <td
                                        class="px-3 py-2 text-right cvr-num"
                                        :class="bucket === 'not_due' ? 'cvr-num-green' : 'cvr-num-amber'"
                                    >{{ fmt(agingAmount(bucket)) }}</td>
                                    <td class="px-3 py-2">
                                        <div class="w-full h-2 rounded cvr-border border overflow-hidden">
                                            <div
                                                class="h-full"
                                                :class="bucket === 'not_due' ? 'cvr-bar-green' : 'cvr-bar-amber'"
                                                :style="{ width: agingShare(bucket) + '%' }"
                                            ></div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="cvr-table-row font-semibold">
                                    <td class="px-3 py-2 cvr-text-primary">Total Uncollected</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ agingRow?.total?.invoice_count ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(agingRow?.total?.amount) }}</td>
                                    <td class="px-3 py-2 cvr-text-muted text-xs">
                                        Overdue: {{ fmt(agingRow?.overdue_total?.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-xs cvr-text-muted mb-6">
                    The buckets add up to Uncollected AR above ({{ fmt(currencyKpis.uncollected) }}).
                </p>

                <!--
                    Standing balances say where things are; this says how
                    fast they got there. Every month in the window is
                    listed even when nothing happened in it — a gap is
                    not the same as a zero.
                -->
                <div class="cvr-section-heading">
                    <h2>Last {{ trendMonths }} Months [{{ activeCurrency }}]</h2>
                </div>
                <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">Month</th>
                                    <th class="px-3 py-2 text-center">Invoices</th>
                                    <th class="px-3 py-2 text-right">Invoiced</th>
                                    <th class="px-3 py-2 text-right">Collected</th>
                                    <th class="px-3 py-2 text-left" style="width: 35%;">Invoiced vs Collected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="month in trendRows" :key="'tm-' + month.month" class="cvr-table-row">
                                    <td class="px-3 py-2 cvr-text-primary">{{ month.label }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ month.invoice_count }}</td>
                                    <td class="px-3 py-2 text-right cvr-num-blue">{{ fmt(month.invoiced) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num-green">{{ fmt(month.collected) }}</td>
                                    <td class="px-3 py-2">
                                        <div class="w-full h-1.5 rounded cvr-border border overflow-hidden mb-1">
                                            <div class="h-full cvr-bar-blue" :style="{ width: barWidth(month.invoiced) }"></div>
                                        </div>
                                        <div class="w-full h-1.5 rounded cvr-border border overflow-hidden">
                                            <div class="h-full cvr-bar-green" :style="{ width: barWidth(month.collected) }"></div>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!trendRows.length">
                                    <td colspan="5" class="px-4 py-8 text-center cvr-text-muted">No invoices in this window.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top 10 — who I do the most business with -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                        <div class="px-4 py-3 border-b cvr-border">
                            <h3 class="text-sm font-semibold cvr-text-primary">Top 10 Customers by Contract Count [{{ activeCurrency }}]</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="cvr-table-head">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Customer</th>
                                        <th class="px-3 py-2 text-center">Contracts</th>
                                        <th class="px-3 py-2 text-right">Value</th>
                                        <th class="px-3 py-2 text-right">Invoiced</th>
                                        <th class="px-3 py-2 text-right">Remaining</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in topCountRows" :key="'tc-' + row.partner_id" class="cvr-table-row">
                                        <td class="px-3 py-2 cvr-text-primary">{{ row.customer_name }}</td>
                                        <td class="px-3 py-2 text-center cvr-num-blue">{{ row.contract_count }}</td>
                                        <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.value) }}</td>
                                        <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.invoiced) }}</td>
                                        <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.remaining) }}</td>
                                    </tr>
                                    <tr v-if="!topCountRows.length">
                                        <td colspan="5" class="px-4 py-8 text-center cvr-text-muted">No data.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                        <div class="px-4 py-3 border-b cvr-border">
                            <h3 class="text-sm font-semibold cvr-text-primary">Top 10 by Uncollected AR [{{ activeCurrency }}]</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="cvr-table-head">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Customer</th>
                                        <th class="px-3 py-2 text-center">Contracts</th>
                                        <th class="px-3 py-2 text-right">Collected</th>
                                        <th class="px-3 py-2 text-right">Uncollected</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in topUncollectedRows" :key="'tu-' + row.partner_id" class="cvr-table-row">
                                        <td class="px-3 py-2 cvr-text-primary">{{ row.customer_name }}</td>
                                        <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.contract_count }}</td>
                                        <td class="px-3 py-2 text-right cvr-num-green">{{ fmt(row.collected) }}</td>
                                        <td class="px-3 py-2 text-right cvr-num-amber">{{ fmt(row.uncollected) }}</td>
                                    </tr>
                                    <tr v-if="!topUncollectedRows.length">
                                        <td colspan="4" class="px-4 py-8 text-center cvr-text-muted">No data.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>

            <!--
                Everything the numbers above could not account for, named
                rather than hidden. Each row here is something the user can
                go and fix.
            -->
            <template v-if="showDataQuality">
                <div class="cvr-section-heading"><h2>Data Quality</h2></div>
                <div class="cvr-kpi-row-4 mb-4">
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleQuality('mismatch')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">⇄</div>
                        <div>
                            <p class="cvr-kpi-label">Invoices In Another Currency</p>
                            <p class="cvr-kpi-value cvr-num-amber">{{ dataQuality?.mismatched_currency_count ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleQuality('rate')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">💱</div>
                        <div>
                            <p class="cvr-kpi-label">No Usable Exchange Rate</p>
                            <p class="cvr-kpi-value cvr-num-amber">{{ dataQuality?.exchange_rate_issue_count ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleQuality('unbalanced')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">≠</div>
                        <div>
                            <p class="cvr-kpi-label">Invoices That Don't Add Up</p>
                            <p class="cvr-kpi-value cvr-num-amber">{{ dataQuality?.unbalanced_invoice_count ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card cursor-pointer" @click="toggleQuality('nocode')">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">#</div>
                        <div>
                            <p class="cvr-kpi-label">Contracts Without A Code</p>
                            <p class="cvr-kpi-value cvr-num-amber">{{ dataQuality?.contracts_without_code_count ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="openQuality === 'mismatch'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6">
                    <div class="px-4 py-3 border-b cvr-border flex items-center justify-between">
                        <h3 class="text-sm font-semibold cvr-text-primary">Invoices posted against a contract in another currency — excluded from every total above</h3>
                        <button type="button" class="text-xs cvr-text-muted" @click="openQuality = null">Close</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">Contract</th>
                                    <th class="px-3 py-2 text-center">Contract Currency</th>
                                    <th class="px-3 py-2 text-left">Invoice #</th>
                                    <th class="px-3 py-2 text-center">Invoice Currency</th>
                                    <th class="px-3 py-2 text-right">Invoice Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in dataQuality?.mismatched_currency_invoices || []" :key="'mm-' + row.id" class="cvr-table-row">
                                    <td class="px-3 py-2 cvr-text-primary">{{ row.contract_code }} — {{ row.contract_name }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.contract_currency }}</td>
                                    <td class="px-3 py-2 cvr-text-secondary">{{ row.invoice_number }}</td>
                                    <td class="px-3 py-2 text-center cvr-num-amber">{{ row.invoice_currency }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.invoice_amount) }}</td>
                                </tr>
                                <tr v-if="!(dataQuality?.mismatched_currency_invoices || []).length">
                                    <td colspan="5" class="px-4 py-8 text-center cvr-text-muted">None.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="openQuality === 'rate'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6">
                    <div class="px-4 py-3 border-b cvr-border flex items-center justify-between">
                        <h3 class="text-sm font-semibold cvr-text-primary">Contracts left out of the {{ mainCurrency }} Contract Value total</h3>
                        <button type="button" class="text-xs cvr-text-muted" @click="openQuality = null">Close</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">Customer</th>
                                    <th class="px-3 py-2 text-left">Code</th>
                                    <th class="px-3 py-2 text-center">Currency</th>
                                    <th class="px-3 py-2 text-right">Value</th>
                                    <th class="px-3 py-2 text-right">Exchange Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in dataQuality?.exchange_rate_issues || []" :key="'er-' + row.id" class="cvr-table-row">
                                    <td class="px-3 py-2 cvr-text-primary">{{ row.customer_name }}</td>
                                    <td class="px-3 py-2 cvr-text-secondary">{{ row.code }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.currency }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.amount) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num-amber">{{ fmt(row.exchange_rate) }}</td>
                                </tr>
                                <tr v-if="!(dataQuality?.exchange_rate_issues || []).length">
                                    <td colspan="5" class="px-4 py-8 text-center cvr-text-muted">None.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="openQuality === 'unbalanced'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6">
                    <div class="px-4 py-3 border-b cvr-border flex items-center justify-between">
                        <h3 class="text-sm font-semibold cvr-text-primary">Invoices where Billed − Collected − Deductions ≠ Uncollected</h3>
                        <button type="button" class="text-xs cvr-text-muted" @click="openQuality = null">Close</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">Contract</th>
                                    <th class="px-3 py-2 text-left">Invoice #</th>
                                    <th class="px-3 py-2 text-right">Billed</th>
                                    <th class="px-3 py-2 text-right">Collected</th>
                                    <th class="px-3 py-2 text-right">Deductions</th>
                                    <th class="px-3 py-2 text-right">Uncollected</th>
                                    <th class="px-3 py-2 text-right">Gap</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in dataQuality?.unbalanced_invoices || []" :key="'ub-' + row.id" class="cvr-table-row">
                                    <td class="px-3 py-2 cvr-text-primary">{{ row.contract_code }}</td>
                                    <td class="px-3 py-2 cvr-text-secondary">{{ row.invoice_number }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.net_invoice_amount) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.total_collected_amount) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.total_deductions) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.net_balance) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num-amber">
                                        {{ fmt(Number(row.net_invoice_amount) - Number(row.total_collected_amount) - Number(row.total_deductions) - Number(row.net_balance)) }}
                                    </td>
                                </tr>
                                <tr v-if="!(dataQuality?.unbalanced_invoices || []).length">
                                    <td colspan="7" class="px-4 py-8 text-center cvr-text-muted">None.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="openQuality === 'nocode'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-6">
                    <div class="px-4 py-3 border-b cvr-border flex items-center justify-between">
                        <h3 class="text-sm font-semibold cvr-text-primary">Contracts with no code — no invoice can be matched to them</h3>
                        <button type="button" class="text-xs cvr-text-muted" @click="openQuality = null">Close</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">Customer</th>
                                    <th class="px-3 py-2 text-left">Name</th>
                                    <th class="px-3 py-2 text-center">Currency</th>
                                    <th class="px-3 py-2 text-right">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in dataQuality?.contracts_without_code || []" :key="'nc-' + row.id" class="cvr-table-row">
                                    <td class="px-3 py-2 cvr-text-primary">{{ row.customer_name }}</td>
                                    <td class="px-3 py-2 cvr-text-secondary">{{ row.name }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.currency }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.amount) }}</td>
                                </tr>
                                <tr v-if="!(dataQuality?.contracts_without_code || []).length">
                                    <td colspan="4" class="px-4 py-8 text-center cvr-text-muted">None.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
