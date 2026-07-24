<script setup>
/**
 * Dashboard/LGLCStatus.vue
 * ------------------------------------------------------------------
 * Served by CustomerInvoiceDashboardController@viewLGLCDashboard. The
 * "LG & LC Status" tab of the Dashboard sidebar section — Letters of
 * Guarantee and Letters of Credit, each with Limit/Outstanding/Room/
 * Cash Cover KPIs, a 3D "Outstanding per Type" donut, a 3D
 * "Outstanding per Financial Institution" donut, and a per-facility
 * breakdown table.
 *
 * All the aggregation (the $reports/$details/$tablesData/$charts
 * loop in the controller, and every LetterOfGuaranteeStatement /
 * LetterOfCreditStatement::getDashboard*() helper it calls) is
 * UNCHANGED — this page only renders what's already computed.
 */
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardTabs from '@/Components/DashboardTabs.vue';
import DonutChart3D from '@/Components/Charts/DonutChart3D.vue';

const props = defineProps({
    company: Object,
    mainFunctionalCurrency: String,
    financialInstitutionBanks: Array,
    reports: Object,
    selectedCurrencies: Array,
    allCurrencies: Array,
    details: Object,
    charts: Object,
    lgTypes: Object,
    lcTypes: Object,
    lgSources: Object,
    lcSources: Object,
    tablesData: Object,
    financialInstitutions: Array,
    canShowDashboardPerCurrency: Object,
    date: String,
    selectedLgSource: String,
    filterUrl: String,
    dashboardTabUrls: Object,
});

// Defaults to the company's main functional currency, same as Cash
// Status — falling back to the first available currency if the main
// one isn't in this company's currency list for some reason.
const currencyList = props.selectedCurrencies.length ? props.selectedCurrencies : (props.allCurrencies || []);
const activeCurrency = ref(
    currencyList.includes(props.mainFunctionalCurrency) ? props.mainFunctionalCurrency : (currencyList[0] || '')
);
const filterDate = ref(props.date);

function applyFilter() {
    router.get(props.filterUrl, { date: filterDate.value }, { preserveScroll: true, preserveState: true });
}

function fmt(value) {
    const n = Number(value || 0);
    return n.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

/* ── LG / LC sections — structurally identical, generalized rather
   than hand-copied twice (same "family" treatment as the overdraft
   types on Cash Status). ────────────────────────────────────────── */
const facilitySections = computed(() => [
    {
        key: 'lg',
        label: 'Letters of Guarantee',
        accent: '--cvr-copper-bright',
        typeChartKey: 'outstanding_per_lg_type',
        institutionChartKey: 'lg_outstanding_per_financial_institution',
        tableKey: 'lg_outstanding_for_table',
        canShow: props.canShowDashboardPerCurrency?.lg?.[activeCurrency.value],
    },
    {
        key: 'lc',
        label: 'Letters of Credit',
        accent: '--cvr-blue',
        typeChartKey: 'outstanding_per_lc_type',
        institutionChartKey: 'lc_outstanding_per_financial_institution',
        tableKey: 'lc_outstanding_for_table',
        canShow: props.canShowDashboardPerCurrency?.lc?.[activeCurrency.value],
    },
]);

function reportFor(sectionKey) {
    return props.reports?.[sectionKey]?.[activeCurrency.value] || {};
}
function typeChartData(section) {
    return (props.charts?.[section.typeChartKey]?.[activeCurrency.value] || []).map(r => ({ category: r.type, value: r.outstanding }));
}
function institutionChartData(section) {
    return (props.charts?.[section.institutionChartKey]?.[activeCurrency.value] || []).map(r => ({ category: r.type, value: r.outstanding }));
}
function tableRows(section) {
    return props.tablesData?.[section.tableKey]?.[activeCurrency.value] || [];
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Dashboard</h1>
            <p class="text-sm cvr-text-muted mb-4">Letters of Guarantee &amp; Letters of Credit — limits, outstanding balances &amp; cash cover</p>

            <DashboardTabs active="lglc" :urls="dashboardTabUrls" />

            <div class="cvr-card-bg cvr-border border rounded-lg p-3 mb-6 flex items-end gap-3 flex-wrap">
                <div>
                    <label class="cvr-form-label">As Of Date</label>
                    <input v-model="filterDate" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <button class="cvr-btn-primary px-4 py-2 rounded" @click="applyFilter">Apply</button>
            </div>

            <div class="flex items-center gap-2 flex-wrap mb-6">
                <button
                    v-for="currency in (selectedCurrencies.length ? selectedCurrencies : allCurrencies)"
                    :key="currency"
                    class="cvr-filter-pill"
                    :class="{ 'cvr-filter-pill-active': activeCurrency === currency }"
                    @click="activeCurrency = currency"
                >
                    {{ currency }}
                </button>
            </div>

            <template v-for="section in facilitySections" :key="section.key">
                <template v-if="section.canShow">
                    <div class="cvr-section-heading"><h2>{{ section.label }} [{{ activeCurrency }}]</h2></div>

                    <div class="cvr-kpi-row-4 mb-6">
                        <div class="cvr-kpi-card">
                            <div class="cvr-kpi-icon cvr-kpi-icon-blue">🎯</div>
                            <div><p class="cvr-kpi-label">Limit</p><p class="cvr-kpi-value cvr-num-blue">{{ fmt(reportFor(section.key).limit) }}</p></div>
                        </div>
                        <div class="cvr-kpi-card">
                            <div class="cvr-kpi-icon cvr-kpi-icon-copper">📉</div>
                            <div><p class="cvr-kpi-label">Outstanding Balance</p><p class="cvr-kpi-value cvr-num-amber">{{ fmt(reportFor(section.key).outstanding_balance) }}</p></div>
                        </div>
                        <div class="cvr-kpi-card">
                            <div class="cvr-kpi-icon cvr-kpi-icon-green">✅</div>
                            <div><p class="cvr-kpi-label">Available Room</p><p class="cvr-kpi-value cvr-num-green">{{ fmt(reportFor(section.key).room) }}</p></div>
                        </div>
                        <div class="cvr-kpi-card">
                            <div class="cvr-kpi-icon cvr-kpi-icon-copper">🛡</div>
                            <div><p class="cvr-kpi-label">Cash Cover</p><p class="cvr-kpi-value cvr-num">{{ fmt(reportFor(section.key).cash_cover) }}</p></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                        <div class="cvr-chart-card" :style="{ borderTopColor: `var(${section.accent})` }">
                            <h4 class="text-sm font-semibold cvr-text-primary mb-2">Outstanding per Type</h4>
                            <DonutChart3D :data="typeChartData(section)" :colors="['--cvr-blue', '--cvr-green-bright', '--cvr-copper-bright', '--cvr-num-amber']" />
                        </div>
                        <div class="cvr-chart-card" :style="{ borderTopColor: `var(${section.accent})` }">
                            <h4 class="text-sm font-semibold cvr-text-primary mb-2">Outstanding per Financial Institution</h4>
                            <DonutChart3D :data="institutionChartData(section)" :colors="['--cvr-copper-bright', '--cvr-blue', '--cvr-green-bright', '--cvr-num-amber', '--cvr-num-red']" />
                        </div>
                    </div>

                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto mb-10">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-4 py-2 text-left">Financial Institution</th>
                                    <th class="px-4 py-2 text-left">Type</th>
                                    <th class="px-4 py-2 text-left">Source</th>
                                    <th class="px-4 py-2 text-right">Limit</th>
                                    <th class="px-4 py-2 text-right">Outstanding</th>
                                    <th class="px-4 py-2 text-right">Cash Cover</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in tableRows(section)" :key="i" class="cvr-table-row">
                                    <td class="px-4 py-2">{{ row.financial_institution_name }}</td>
                                    <td class="px-4 py-2"><span class="cvr-badge cvr-badge-facility">{{ row.type }}</span></td>
                                    <td class="px-4 py-2 cvr-text-secondary text-xs">{{ row.source }}</td>
                                    <td class="px-4 py-2 text-right cvr-num-blue">{{ fmt(row.limit) }}</td>
                                    <td class="px-4 py-2 text-right cvr-num-amber">{{ fmt(row.outstanding) }}</td>
                                    <td class="px-4 py-2 text-right cvr-num">{{ fmt(row.cash_cover) }}</td>
                                </tr>
                                <tr v-if="!tableRows(section).length">
                                    <td colspan="6" class="px-4 py-8 text-center cvr-text-muted">No {{ section.label.toLowerCase() }} outstanding for {{ activeCurrency }}.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </template>
        </div>
    </AppLayout>
</template>
