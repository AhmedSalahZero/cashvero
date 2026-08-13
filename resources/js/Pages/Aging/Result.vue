<script setup>
/**
 * Aging/Result.vue
 * ------------------------------------------------------------------
 * Served by AgingController@result. The big one: a matrix of every
 * customer/supplier's outstanding balance broken into Past Due
 * (farthest-out first) / Current Due / Coming Due (nearest first)
 * day-interval buckets, with per-invoice drill-down, plus 3 summary
 * breakdowns.
 *
 * All the math is untouched — see the controller's docblock. This
 * page just renders what's already computed.
 *
 * Charts: "Total Aging Analysis" stays a donut (a simple 2-way
 * Coming/Past split reads fine as one) — see buildDonutChart below.
 * "Total Coming Dues" and "Total Past Dues" now use
 * AgingDivergingBarChart, the same shared bar-chart component the
 * Cash Forecast dashboard already uses for this exact kind of
 * ordered-bucket aging data (client-requested, 2026-08-13 — several
 * small/similar buckets are hard to compare by donut wedge angle, and
 * day-interval buckets have a natural order a circle throws away).
 * Both chart types are amCharts4 (already an installed project
 * dependency).
 */
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AgingDivergingBarChart from '@/Components/Charts/AgingDivergingBarChart.vue';
import * as am4core from '@amcharts/amcharts4/core';
import * as am4charts from '@amcharts/amcharts4/charts';
import am4themes_animated from '@amcharts/amcharts4/themes/animated';

am4core.useTheme(am4themes_animated);

const props = defineProps({
    agingDate: String,
    clientNameText: String,
    customersOrSupplierAgingText: String,
    pastDueColumns: Array, // farthest-out first, e.g. ['More Than 150', '121-150', ..., '1-7']
    comingDueColumns: Array, // nearest first, e.g. ['1-7', ..., '121-150', 'More Than 150']
    weeksDates: Object, // { past_due: { [interval]: {start_date, end_date} }, coming_due: {...} }
    clientRows: Array,
    totalsRow: Object,
    charts: Object, // { [chartName]: [{ item, value, percentage }] }
    backUrl: String,
});

/* ── Charts. The ORIGINAL CashVero Aging report (still-Blade version)
   uses 3 donut/pie charts — not bars for the detail breakdowns. That
   was a real design deviation on the first pass; matching the
   original's actual amCharts4 config here (innerRadius 50%, disabled
   slice labels/ticks — info lives in the legend only, radial
   gradient on slices, legend on the right). This also structurally
   removes the earlier "Data fields... not properly defined" error,
   which came from a real mistake in the (now-removed) ColumnSeries
   config — PieSeries doesn't have that failure mode at all, so
   switching back to donuts fixes the actual bug, not just its
   symptom. One deliberate addition beyond the original: a "TOTAL"
   label centered in the donut hole. ─────────────────────────────── */
const donutRefs = { total: ref(null) };
let chartInstances = [];

function cvrColor(varName) {
    return am4core.color(getComputedStyle(document.documentElement).getPropertyValue(varName).trim());
}

const donutPalette = [cvrColor('--cvr-num-amber'), cvrColor('--cvr-num-blue'), cvrColor('--cvr-num-red'), cvrColor('--cvr-green-bright')];

function buildDonutChart(el, data) {
    const chart = am4core.create(el, am4charts.PieChart);
    /**
     * FIX (per request, 2026-08-13): matches DonutChart3D.vue (the LG
     * & LC Status dashboard's donut component) — chart.logo.disabled
     * is amCharts4's own built-in property for hiding the "Created
     * using amCharts" hover badge, already an established pattern
     * elsewhere in this app. No license key involved.
     */
    chart.logo.disabled = true;
    chart.data = data;
    chart.innerRadius = am4core.percent(50);
    chart.numberFormatter.numberFormat = '#,###';
    const textColor = cvrColor('--cvr-text-secondary');

    const series = chart.series.push(new am4charts.PieSeries());
    series.dataFields.value = 'value';
    series.dataFields.category = 'item';
    // Matches the original exactly: no slice labels/ticks — the
    // legend (right side) is the only place item/value/% appears.
    series.ticks.template.disabled = true;
    series.labels.template.disabled = true;
    series.slices.template.tooltipText = '{category}: {value.formatNumber("#,###")} ({value.percent.formatNumber("#.0")}%)';

    const rgm = new am4core.RadialGradientModifier();
    rgm.brightnesses.push(-0.8, -0.8, -0.5, 0, -0.5);
    series.slices.template.fillModifier = rgm;
    series.slices.template.strokeModifier = rgm;
    series.slices.template.strokeOpacity = 0.4;
    series.slices.template.strokeWidth = 0;
    series.colors.list = donutPalette;

    chart.legend = new am4charts.Legend();
    chart.legend.position = 'buttom';
    chart.legend.scrollable = true;
    chart.legend.labels.template.fill = textColor;
    chart.legend.labels.template.fontSize = 11;
    chart.legend.valueLabels.template.fill = textColor;
    chart.legend.valueLabels.template.text = '{value.percent.formatNumber("#.0")}%';

    // Center "TOTAL" label — a deliberate addition beyond the
    // original, inside the donut hole.
    const total = data.reduce((sum, r) => sum + Number(r.value || 0), 0);
    const totalLabel = chart.seriesContainer.createChild(am4core.Label);
    totalLabel.horizontalCenter = 'middle';
    totalLabel.verticalCenter = 'middle';
    totalLabel.fontSize = 11;
    totalLabel.fill = cvrColor('--cvr-text-muted');
    totalLabel.text = 'TOTAL\n[bold font-size: 14]' + total.toLocaleString(undefined, { maximumFractionDigits: 0 }) + '[/]';
    totalLabel.textAlign = 'middle';

    return chart;
}

onMounted(async () => {
    await nextTick();
    const chartDefs = [
        [donutRefs.total, props.charts['Total Aging Analysis Chart']],
    ];
    chartInstances = chartDefs
        .filter(([elRef, data]) => elRef.value && (data || []).some(r => r.value > 0))
        .map(([elRef, data]) => buildDonutChart(
            elRef.value,
            data.filter(r => r.value > 0).map(r => ({ ...r, value: Math.round(Number(r.value)) }))
        ));
});

onBeforeUnmount(() => {
    // amCharts instances hold their own DOM/animation frames — not
    // disposing them leaks memory across Inertia page navigations
    // (the component unmounts but the chart keeps running).
    chartInstances.forEach(c => c?.dispose());
});

/**
 * FIX (per request, 2026-08-13): originally built as two separate
 * bar charts (Coming Due, Past Due) — corrected per follow-up
 * clarification to be ONE diverging chart instead, exactly like
 * Forecast.vue's agingBarData(): Past Due bars extend left
 * (negative/red), Coming Due bars extend right (positive/green), in
 * a single AgingDivergingBarChart. Order matches the screenshot
 * reference: Past Due farthest-out first (top), Coming Due nearest
 * first (bottom) — trusting props.charts' own existing row order,
 * same as every other total on this page. Values are rounded to
 * whole numbers before the chart ever sees them, same reasoning as
 * buildDonutChart's data above — never hand a chart a raw
 * floating-point amount.
 */
function agingBarRows(chartName, colorVar, negate) {
    const rows = props.charts[chartName] || [];
    return rows
        .filter(r => r.value > 0)
        .map(r => ({
            category: `${negate ? 'Past Due' : 'Coming Due'} · ${r.item}`,
            value: negate ? -Math.round(Number(r.value)) : Math.round(Number(r.value)),
            colorVar,
        }));
}
const combinedAgingBarData = computed(() => [
    ...agingBarRows('Total Past Dues Aging Analysis Chart', '--cvr-num-red', true),
    ...agingBarRows('Total Coming Dues Aging Analysis Chart', '--cvr-green-bright', false),
]);

function formatAmount(value) {
    const n = Number(value || 0);
    if (!n) return '-';
    return n.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

// Number Color Rule: past due = red (overdue, needs attention),
// coming due = amber (pending), current due = blue (informational),
// zero = muted so the eye can quickly scan a sparse matrix.
function cellClass(value, kind) {
    if (!Number(value)) return 'cvr-text-muted';
    if (kind === 'past_due') return 'cvr-num-red';
    if (kind === 'coming_due') return 'cvr-num-amber';
    return 'cvr-num-blue';
}

const expandedClients = ref(new Set());
function toggleExpand(name) {
    const next = new Set(expandedClients.value);
    if (next.has(name)) next.delete(name); else next.add(name);
    expandedClients.value = next;
}

/**
 * FIX (per request, 2026-08-13): a Total column per section (Past
 * Due, Coming Due) — summed from that row's own day-interval buckets,
 * same object the existing per-interval cells already read from — plus
 * an independent collapse toggle per section so the day-by-day detail
 * can be hidden down to just its Total, leaving Grand Total untouched
 * either way.
 */
function sectionTotal(bucketObject) {
    return Object.values(bucketObject || {}).reduce((sum, v) => sum + (Number(v) || 0), 0);
}
const pastDueExpanded = ref(true);
const comingDueExpanded = ref(true);

function dateRange(kind, interval) {
    const r = props.weeksDates?.[kind]?.[interval];
    return r ? `${r.start_date} – ${r.end_date}` : '';
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                ← Back to {{ customersOrSupplierAgingText }} Filters
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ customersOrSupplierAgingText }}</h1>
            <p class="text-sm cvr-text-muted mb-6">As of {{ agingDate }}</p>

            <!-- Chart breakdowns -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <div class="cvr-card-bg cvr-border border rounded-lg p-4" style="border-top: 3px solid var(--cvr-num-amber)">
                    <h3 class="text-sm font-semibold cvr-text-primary mb-2">Total Aging Analysis</h3>
                    <div :ref="donutRefs.total" style="height: 400px"></div>
                </div>
                <div class="cvr-card-bg cvr-border border rounded-lg p-4 lg:col-span-2" style="border-top: 3px solid var(--cvr-num-red)">
                    <h3 class="text-sm font-semibold cvr-text-primary mb-1">Coming &amp; Past Dues Aging Analysis</h3>
                    <p class="text-xs cvr-text-muted mb-2">Past Due (left) · Coming Due (right)</p>
                    <AgingDivergingBarChart :data="combinedAgingBarData" :height="400" />
                </div>
            </div>

            <!-- Matrix table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="text-sm border-collapse">
                    <thead>
                        <tr>
                            <th rowspan="2" class="px-4 py-2 text-left sticky left-0 z-10 cvr-table-head" style="min-width: 220px">{{ clientNameText }}</th>
                            <!--
                                FIX (per screenshot, 2026-08-13): the collapsed
                                group header used to keep occupying 1 column
                                in row 1 with nothing underneath it in row 2 —
                                HTML tables don't tolerate that gap, so the
                                browser silently compacted every column after
                                it, which is exactly the "shifted right" bug.
                                Now the group header only exists at all while
                                expanded (colspan = N always pairs correctly
                                with N real cells in row 2 below it); Total
                                becomes the toggle control once collapsed, so
                                there's never a header cell with no row-2
                                counterpart.
                            -->
                            <th v-if="pastDueExpanded" :colspan="pastDueColumns.length" class="px-2 py-2 text-center border-l cvr-border cvr-table-head cursor-pointer select-none" @click="pastDueExpanded = false" title="Click to collapse Past Due detail">
                                <span class="inline-block transition-transform mr-1 rotate-90">▸</span>Past Due
                            </th>
                            <th rowspan="2" class="px-3 py-2 text-center border-l cvr-border cvr-table-head cursor-pointer select-none" style="min-width: 100px" @click="pastDueExpanded = !pastDueExpanded" :title="pastDueExpanded ? 'Click to collapse Past Due detail' : 'Click to expand Past Due detail'">
                                <span class="inline-block transition-transform mr-1" :class="{ 'rotate-90': pastDueExpanded }">▸</span>Past Due<br />Total
                            </th>
                            <th rowspan="2" class="px-3 py-2 text-center border-l cvr-border cvr-table-head" style="min-width: 100px">Current Due<br />[{{ agingDate }}]</th>
                            <th v-if="comingDueExpanded" :colspan="comingDueColumns.length" class="px-2 py-2 text-center border-l cvr-border cvr-table-head cursor-pointer select-none" @click="comingDueExpanded = false" title="Click to collapse Coming Due detail">
                                <span class="inline-block transition-transform mr-1 rotate-90">▸</span>Coming Due
                            </th>
                            <th rowspan="2" class="px-3 py-2 text-center border-l cvr-border cvr-table-head cursor-pointer select-none" style="min-width: 100px" @click="comingDueExpanded = !comingDueExpanded" :title="comingDueExpanded ? 'Click to collapse Coming Due detail' : 'Click to expand Coming Due detail'">
                                <span class="inline-block transition-transform mr-1" :class="{ 'rotate-90': comingDueExpanded }">▸</span>Coming Due<br />Total
                            </th>
                            <th rowspan="2" class="px-3 py-2 text-center border-l cvr-border cvr-table-head" style="min-width: 110px">Grand Total</th>
                        </tr>
                        <tr>
                            <template v-if="pastDueExpanded">
                                <th v-for="interval in pastDueColumns" :key="'pdh-'+interval" class="px-2 py-1 text-center border-l cvr-border cvr-table-head" style="min-width: 90px">
                                    <span class="text-xs">{{ interval }}</span><br />
                                    <span class="font-normal" style="font-size: 0.72rem; color: var(--cvr-text-secondary)">{{ dateRange('past_due', interval) }}</span>
                                </th>
                            </template>
                            <template v-if="comingDueExpanded">
                                <th v-for="interval in comingDueColumns" :key="'cdh-'+interval" class="px-2 py-1 text-center border-l cvr-border cvr-table-head" style="min-width: 90px">
                                    <span class="text-xs">{{ interval }}</span><br />
                                    <span class="font-normal" style="font-size: 0.72rem; color: var(--cvr-text-secondary)">{{ dateRange('coming_due', interval) }}</span>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="client in clientRows" :key="client.name">
                            <tr class="cvr-table-row cursor-pointer" @click="toggleExpand(client.name)">
                                <td class="px-4 py-2 text-left sticky left-0 z-10 cvr-card-bg cvr-text-primary">
                                    <span class="cvr-text-muted inline-block mr-1 transition-transform" :class="{ 'rotate-90': expandedClients.has(client.name) }">▸</span>
                                    {{ client.name }}
                                </td>
                                <template v-if="pastDueExpanded">
                                    <td v-for="interval in pastDueColumns" :key="'pd-'+interval" class="px-2 py-2 text-center border-l cvr-border" :class="cellClass(client.past_due[interval], 'past_due')">{{ formatAmount(client.past_due[interval]) }}</td>
                                </template>
                                <td class="px-3 py-2 text-center border-l cvr-border font-medium" :class="cellClass(sectionTotal(client.past_due), 'past_due')">{{ formatAmount(sectionTotal(client.past_due)) }}</td>
                                <td class="px-3 py-2 text-center border-l cvr-border" :class="cellClass(client.current_due, 'current_due')">{{ formatAmount(client.current_due) }}</td>
                                <template v-if="comingDueExpanded">
                                    <td v-for="interval in comingDueColumns" :key="'cd-'+interval" class="px-2 py-2 text-center border-l cvr-border" :class="cellClass(client.coming_due[interval], 'coming_due')">{{ formatAmount(client.coming_due[interval]) }}</td>
                                </template>
                                <td class="px-3 py-2 text-center border-l cvr-border font-medium" :class="cellClass(sectionTotal(client.coming_due), 'coming_due')">{{ formatAmount(sectionTotal(client.coming_due)) }}</td>
                                <td class="px-3 py-2 text-center border-l cvr-border font-medium cvr-num-amber">{{ formatAmount(client.total) }}</td>
                            </tr>
                            <template v-if="expandedClients.has(client.name)">
                                <tr v-for="invoice in client.invoices" :key="client.name + '-' + invoice.invoice_number" class="cvr-sub-row">
                                    <td class="px-4 py-1.5 text-left sticky left-0 z-10 cvr-text-secondary text-xs cvr-sub-row-sticky">
                                        <span class="inline-block pl-4" style="border-left: 2px solid var(--cvr-green-bright)">↳ {{ invoice.invoice_number }}</span>
                                    </td>
                                    <template v-if="pastDueExpanded">
                                        <td v-for="interval in pastDueColumns" :key="'ipd-'+interval" class="px-2 py-1.5 text-center border-l cvr-border text-xs" :class="cellClass(invoice.past_due[interval], 'past_due')">{{ formatAmount(invoice.past_due[interval]) }}</td>
                                    </template>
                                    <td class="px-3 py-1.5 text-center border-l cvr-border text-xs font-medium" :class="cellClass(sectionTotal(invoice.past_due), 'past_due')">{{ formatAmount(sectionTotal(invoice.past_due)) }}</td>
                                    <td class="px-3 py-1.5 text-center border-l cvr-border text-xs" :class="cellClass(invoice.current_due, 'current_due')">{{ formatAmount(invoice.current_due) }}</td>
                                    <template v-if="comingDueExpanded">
                                        <td v-for="interval in comingDueColumns" :key="'icd-'+interval" class="px-2 py-1.5 text-center border-l cvr-border text-xs" :class="cellClass(invoice.coming_due[interval], 'coming_due')">{{ formatAmount(invoice.coming_due[interval]) }}</td>
                                    </template>
                                    <td class="px-3 py-1.5 text-center border-l cvr-border text-xs font-medium" :class="cellClass(sectionTotal(invoice.coming_due), 'coming_due')">{{ formatAmount(sectionTotal(invoice.coming_due)) }}</td>
                                    <td class="px-3 py-1.5 text-center border-l cvr-border text-xs cvr-num-amber">{{ formatAmount(invoice.total) }}</td>
                                </tr>
                            </template>
                        </template>
                        <tr v-if="clientRows.length === 0">
                            <td :colspan="(pastDueExpanded ? pastDueColumns.length : 0) + (comingDueExpanded ? comingDueColumns.length : 0) + 5" class="px-4 py-8 text-center cvr-text-muted">No outstanding balances found.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="clientRows.length">
                        <tr class="font-semibold" style="background: var(--cvr-green-deep)">
                            <td class="px-4 py-2 text-left sticky left-0 z-10" style="background: var(--cvr-green-deep)">Total</td>
                            <template v-if="pastDueExpanded">
                                <td v-for="interval in pastDueColumns" :key="'tpd-'+interval" class="px-2 py-2 text-center border-l cvr-border cvr-num-red">{{ formatAmount(totalsRow.past_due[interval]) }}</td>
                            </template>
                            <td class="px-3 py-2 text-center border-l cvr-border cvr-num-red">{{ formatAmount(sectionTotal(totalsRow.past_due)) }}</td>
                            <td class="px-3 py-2 text-center border-l cvr-border cvr-num-blue">{{ formatAmount(totalsRow.current_due) }}</td>
                            <template v-if="comingDueExpanded">
                                <td v-for="interval in comingDueColumns" :key="'tcd-'+interval" class="px-2 py-2 text-center border-l cvr-border cvr-num-amber">{{ formatAmount(totalsRow.coming_due[interval]) }}</td>
                            </template>
                            <td class="px-3 py-2 text-center border-l cvr-border cvr-num-amber">{{ formatAmount(sectionTotal(totalsRow.coming_due)) }}</td>
                            <td class="px-3 py-2 text-center border-l cvr-border cvr-num-amber">{{ formatAmount(totalsRow.total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>