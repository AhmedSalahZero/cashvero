<script setup>
/**
 * Dashboard/Forecast.vue
 * ------------------------------------------------------------------
 * Served by CustomerInvoiceDashboardController@viewForecastDashboard.
 * The "Cash Forecast" tab of the Dashboard sidebar section:
 *   - Monthly Cash Flow (Cash Inflow vs Cash Outflow) and Accumulated
 *     Net Cash — two professional line/area charts, one per currency,
 *     fed by CashFlowReportController::result() (company-wide) or
 *     ContractCashFlowReportController::result() (a single contract),
 *     both UNCHANGED.
 *   - Customer/Supplier Invoice Aging, shown as a diverging bar chart
 *     (Past Due left/negative, Current & Coming Due right/positive,
 *     Current Due in its own color) — and Cheque Aging, still a 3D
 *     donut. Both fed by InvoiceAgingService / ChequeAgingService,
 *     UNCHANGED.
 *   - Past Due summaries (customer invoices, supplier invoices, loan
 *     installments) for the report's current currency.
 *   - Start Date / End Date + Report Interval filters, submitted to
 *     the same CashFlowReportController params it already reads
 *     (`start_date`/`end_date`/`report_interval`, all UNCHANGED).
 *     Note: the underlying report requires today's date to fall
 *     within the chosen range (an existing, untouched validation
 *     rule) — an out-of-range pick redirects back with a flash error,
 *     which the shared toast mechanism already displays.
 *
 * ⚠️ Deliberately scoped down from the original 1,494-line Blade page,
 * flagged explicitly rather than silently dropped (Roadmap §3.7/§13
 * convention): the original also let a specific Customer Contract be
 * picked to re-run the whole report against just that contract's cash
 * flow. That contract-drill-down is NOT wired up on this first pass —
 * the report always renders in its default "whole company" mode. The
 * controller already accepts `contract_id`/`partner_id` query params
 * (untouched), so wiring a contract picker here later is additive,
 * not a rebuild.
 */
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DashboardTabs from '@/Components/DashboardTabs.vue';
import AgingDivergingBarChart from '@/Components/Charts/AgingDivergingBarChart.vue';
import MultiLineChart from '@/Components/Charts/MultiLineChart.vue';

const props = defineProps({
    company: Object,
    dashboardResult: Object,
    invoiceTypesModels: Array,
    reportInterval: String,
    selectedCurrencies: Array,
    allCurrencies: Array,
    cashFlowReport: Object,
    contractCode: String,
    currencyName: String,
    pastDueCustomerInvoices: Object,
    pastDueSupplierInvoices: Array,
    pastDueInstallments: Array,
    selectedReportInterval: String,
    cashFlowStartDate: String,
    cashFlowEndDate: String,
    filterUrl: String,
    dashboardTabUrls: Object,
});

const activeCurrency = ref(
    props.dashboardResult?.currencies?.[0]
    || props.currencyName
    || (props.allCurrencies || [])[0]
);
const reportIntervalModel = ref(props.selectedReportInterval || 'weekly');
const startDateModel = ref(props.cashFlowStartDate);
const endDateModel = ref(props.cashFlowEndDate);
/**
 * CORRECTION (per follow-up, 2026-08-13): the previous version of this
 * fix auto-recalculated End Date to Start Date + 3 months every time
 * Start Date changed — per clarification, that's not wanted. The
 * +3-months default should only apply once, on first entering the
 * page with no dates chosen yet (that part is handled entirely on the
 * backend, see viewForecastDashboard()) — once a Start Date is
 * already set, End Date is the person's own to edit independently and
 * should never be silently overwritten.
 */

function applyFilter() {
    router.get(props.filterUrl, {
        report_interval: reportIntervalModel.value,
        start_date: startDateModel.value,
        end_date: endDateModel.value,
    }, { preserveScroll: true, preserveState: true });
}

/**
 * FIX (per audit, 2026-08-13): the backend now only computes data for
 * the active currency by default (previously it eagerly computed EVERY
 * company currency's full Cash Flow report and Aging summary on every
 * visit — the actual cause of this page's load delay). That means a
 * currency pill you haven't clicked yet may genuinely have no data
 * loaded — switching to it locally would just show empty charts. This
 * checks for that and, only when needed, does a real page visit asking
 * the server to compute that one currency — same one extra request a
 * person would expect from clicking something new, instead of a
 * silently blank chart. Already-loaded currencies (including the
 * initial one) still switch instantly, no request at all.
 */
function switchCurrency(currency) {
    const alreadyLoaded = props.cashFlowReport?.[currency] || props.dashboardResult?.invoices_aging?.CustomerInvoice?.[currency];
    if (alreadyLoaded) {
        activeCurrency.value = currency;
        return;
    }
    router.get(props.filterUrl, {
        report_interval: reportIntervalModel.value,
        start_date: startDateModel.value,
        end_date: endDateModel.value,
        currencies: [currency],
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            activeCurrency.value = currency;
        },
    });
}

function fmt(value) {
    const n = Number(value || 0);
    return n.toLocaleString('en-EG', { maximumFractionDigits: 0 });
}

const invoiceTypeLabels = { CustomerInvoice: 'Customer Invoices', SupplierInvoice: 'Supplier Invoices' };

/* The cheque charts are named after the instrument, not the invoice
   behind it: a customer's cheque is a receivable, a supplier's is a
   payable. Kept as its own map rather than renaming the one above,
   because the Aging row next to it plots INVOICE aging
   (InvoiceAgingService), not cheques — one map for both would put
   "Cheques" on a chart of invoices. */
const chequeTypeLabels = { CustomerInvoice: 'Customer Receivable Cheques', SupplierInvoice: 'Supplier Payable Cheques' };

/* The cash flow chart is bucketed by the Report Interval, so the title
   has to say which one — it was hard-coded "Monthly Cash Flow" and read
   as a lie whenever the report ran weekly or daily. Driven by
   `reportInterval` (what the report actually used), not by the dropdown
   model, so the title never gets ahead of the rendered data: changing
   the dropdown alone does nothing until Apply reloads the page. */
const intervalLabels = { daily: 'Daily', weekly: 'Weekly', monthly: 'Monthly' };
const cashFlowChartTitle = computed(() => `${intervalLabels[props.reportInterval] || 'Periodic'} Cash Flow`);

/* Monthly Cash Flow / Accumulated Net Cash — already the exact shape
   the original amCharts4 config consumed ({date, cash_in, cash_out}
   and {date, value} respectively). */
const cashFlowSeries = [
    { field: 'cash_in', name: 'Cash Inflow', color: '--cvr-green-bright' },
    { field: 'cash_out', name: 'Cash Outflow', color: '--cvr-num-red' },
];
const netCashSeries = [
    { field: 'value', name: 'Accumulated Net Cash', color: '--cvr-blue' },
];

/* ── Invoice Aging — diverging bar chart ─────────────────────────
   Past Due buckets render to the left (negative), Current Due and
   Coming Due render to the right (positive), Current Due in its own
   color so it doesn't read as "just another coming-due bucket" —
   per the project owner's request, replacing the earlier donut.
   `.chart` entries also include 2 pseudo-rows per due-type coming
   from the underlying $result['total'][$dueType] array carrying its
   own 'no_invoices' and 'total' sub-keys (a pre-existing quirk in
   InvoiceAgingService::formatForDashboard, not touched here) — those
   two are filtered out so only real day-interval buckets render. */
function intervalStart(label) {
    const match = String(label).match(/(\d+)/);
    return match ? parseInt(match[1], 10) : 9999; // 'More Than 150' sorts last
}
/* بيحوّل صفوف {region, state, sales} لأعمدة الرسم المتفرّع:
   المتأخر شمال بالسالب، والحالي والجاي يمين بالموجب.
   الفواتير والشيكات الاتنين بيعدّوا من هنا — نفس الشكل من الـ trait المشترك. */
function agingBarRows(rows) {
    const clean = (rows || []).filter(r => typeof r.sales === 'number' && !/total|no_invoices/i.test(r.state));

    const past = clean.filter(r => /past/i.test(r.region)).sort((a, b) => intervalStart(b.state) - intervalStart(a.state));
    const current = clean.filter(r => /current/i.test(r.region));
    const coming = clean.filter(r => /coming/i.test(r.region)).sort((a, b) => intervalStart(a.state) - intervalStart(b.state));

    const pastRows = past.map(r => ({ category: `Past Due · ${String(r.state).replace(/^-/, '')}`, value: -Math.abs(r.sales), colorVar: '--cvr-num-red' }));
    const currentRows = current.map(r => ({ category: 'Current Due', value: Math.abs(r.sales), colorVar: '--cvr-blue' }));
    const comingRows = coming.map(r => ({ category: `Coming Due · ${r.state}`, value: Math.abs(r.sales), colorVar: '--cvr-green-bright' }));

    return [...pastRows, ...currentRows, ...comingRows];
}

function agingBarData(modelType) {
    return agingBarRows(props.dashboardResult?.invoices_aging?.[modelType]?.[activeCurrency.value]?.chart);
}

/* ── Cheque Aging ───────────────────────────────────────────────────
   كانت دونات بتقرا cheques_aging_for_chart (قايمة تاريخ/مبلغ مسطّحة،
   الجاي بس). بقت نفس رسم أعمار الفواتير: ChequeAgingService بيحسب نفس
   تقسيمة past/current/coming بالظبط، بس ماكانش بيطلّعها بالشكل ده —
   دلوقتي بيطلّعها في aging_chart من نفس الدالة المشتركة. */
function chequeBarData(modelType) {
    return agingBarRows(props.dashboardResult?.cheques_aging_for_table?.[modelType]?.[activeCurrency.value]?.aging_chart);
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Dashboard') }}</h1>
            <p class="text-sm cvr-text-muted mb-4">{{ $t('Cash flow projection, invoice & cheque aging, past-due summary') }}</p>

            <DashboardTabs active="forecast" :urls="dashboardTabUrls" />

            <div class="cvr-card-bg cvr-border border rounded-lg p-3 mb-6 flex items-end gap-3 flex-wrap">
                <!-- The select was as narrow as its shortest option and clipped
                     the label; w-44 gives every option room. "Daily" is offered
                     here now too — CashFlowReportController has always accepted
                     it, the dropdown just never listed it. -->
                <div>
                    <label class="cvr-form-label">{{ $t('Report Interval') }}</label>
                    <select v-model="reportIntervalModel" class="cvr-input px-3 py-2 rounded w-44">
                        <option value="daily">{{ $t('Daily') }}</option>
                        <option value="weekly">{{ $t('Weekly') }}</option>
                        <option value="monthly">{{ $t('Monthly') }}</option>
                    </select>
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('Start Date') }}</label>
                    <input v-model="startDateModel" type="date" class="cvr-input px-3 py-2 rounded w-44" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('End Date') }}</label>
                    <input v-model="endDateModel" type="date" class="cvr-input px-3 py-2 rounded w-44" />
                </div>
                <button class="cvr-btn-primary px-4 py-2 rounded" @click="applyFilter">{{ $t('Apply') }}</button>
                <span v-if="contractCode" class="cvr-badge cvr-badge-info self-center">{{ $t('Contract:') }} {{ contractCode }}</span>
                <p class="text-xs cvr-text-muted w-full">{{ $t('Today\'s date must fall within the Start/End Date range.') }}</p>
            </div>

            <div class="flex items-center gap-2 flex-wrap mb-6">
                <button
                    v-for="currency in (allCurrencies?.length ? allCurrencies : selectedCurrencies)"
                    :key="currency"
                    class="cvr-filter-pill"
                    :class="{ 'cvr-filter-pill-active': activeCurrency === currency }"
                    @click="switchCurrency(currency)"
                >
                    {{ currency }}
                </button>
            </div>

            <!-- Cash Flow charts -->
            <div class="cvr-section-heading"><h2>{{ $t('Cash Flow Projection [') }}{{ activeCurrency }}]</h2></div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
                <div class="cvr-chart-card" style="border-top-color: var(--cvr-green-bright)">
                    <h4 class="text-sm font-semibold cvr-text-primary mb-2">{{ $t(cashFlowChartTitle) }}</h4>
                    <MultiLineChart :data="cashFlowReport?.[activeCurrency]?.total_cash_in_out_flow || []" :series="cashFlowSeries" :height="300" />
                </div>
                <div class="cvr-chart-card" style="border-top-color: var(--cvr-blue)">
                    <h4 class="text-sm font-semibold cvr-text-primary mb-2">{{ $t('Accumulated Net Cash') }}</h4>
                    <MultiLineChart :data="cashFlowReport?.[activeCurrency]?.accumulated_net_cash || []" :series="netCashSeries" :height="300" />
                </div>
            </div>

            <!-- Invoice & Cheque Aging -->
            <div class="cvr-section-heading"><h2>{{ $t('Aging Summary [') }}{{ activeCurrency }}]</h2></div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <div v-for="modelType in invoiceTypesModels" :key="'inv-'+modelType" class="cvr-chart-card" style="border-top-color: var(--cvr-num-amber)">
                    <h4 class="text-sm font-semibold cvr-text-primary mb-2">{{ $t(invoiceTypeLabels[modelType] || modelType) }} {{ $t('Aging') }}</h4>
                    <p class="text-xs cvr-text-muted mb-1">{{ $t('Past Due (left) · Current & Coming Due (right)') }}</p>
                    <AgingDivergingBarChart :data="agingBarData(modelType)" :height="300" />
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
                <div v-for="modelType in invoiceTypesModels" :key="'chq-'+modelType" class="cvr-chart-card" style="border-top-color: var(--cvr-copper-bright)">
                    <h4 class="text-sm font-semibold cvr-text-primary mb-2">{{ $t(chequeTypeLabels[modelType] || modelType) }} {{ $t('Aging') }}</h4>
                    <p class="text-xs cvr-text-muted mb-1">{{ $t('Past Due (left) · Current & Coming Due (right)') }}</p>
                    <AgingDivergingBarChart :data="chequeBarData(modelType)" :height="300" />
                </div>
            </div>

            <!-- Past Due Summary -->
            <div class="cvr-section-heading"><h2>{{ $t('Past Due Summary [') }}{{ currencyName }}]</h2></div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                    <div class="px-4 py-2 cvr-table-head text-xs">{{ $t('Past Due Customer Invoices') }}</div>
                    <table class="min-w-full text-xs">
                        <tbody>
                            <tr v-for="(row, i) in (pastDueCustomerInvoices?.[currencyName] || [])" :key="i" class="cvr-table-row">
                                <td class="px-3 py-1.5">{{ row.invoice_number }}</td>
                                <td class="px-3 py-1.5">{{ row.invoice_due_date }}</td>
                                <td class="px-3 py-1.5 text-right cvr-num-red">{{ fmt(row.net_balance) }}</td>
                            </tr>
                            <tr v-if="!(pastDueCustomerInvoices?.[currencyName] || []).length"><td class="px-3 py-4 text-center cvr-text-muted">{{ $t('None') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                    <div class="px-4 py-2 cvr-table-head text-xs">{{ $t('Past Due Supplier Invoices') }}</div>
                    <table class="min-w-full text-xs">
                        <tbody>
                            <tr v-for="(row, i) in (pastDueSupplierInvoices || [])" :key="i" class="cvr-table-row">
                                <td class="px-3 py-1.5">{{ row.invoice_number }}</td>
                                <td class="px-3 py-1.5">{{ row.invoice_due_date }}</td>
                                <td class="px-3 py-1.5 text-right cvr-num-red">{{ fmt(row.net_balance) }}</td>
                            </tr>
                            <tr v-if="!(pastDueSupplierInvoices || []).length"><td class="px-3 py-4 text-center cvr-text-muted">{{ $t('None') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                    <div class="px-4 py-2 cvr-table-head text-xs">{{ $t('Past Due Loans & Leasing Installments') }}</div>
                    <table class="min-w-full text-xs">
                        <tbody>
                            <tr v-for="(row, i) in (pastDueInstallments || [])" :key="i" class="cvr-table-row">
                                <td class="px-3 py-1.5">{{ row.date }}</td>
                                <td class="px-3 py-1.5 text-right cvr-num-red">{{ fmt(row.remaining) }}</td>
                            </tr>
                            <tr v-if="!(pastDueInstallments || []).length"><td class="px-3 py-4 text-center cvr-text-muted">{{ $t('None') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>