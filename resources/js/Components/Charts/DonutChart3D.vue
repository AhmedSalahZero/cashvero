<script setup>
/**
 * Charts/DonutChart3D.vue
 * ------------------------------------------------------------------
 * Shared 3D-style donut chart used across all 3 Dashboard tabs
 * (Cash Status' "Available Room" per bank, LG & LC Status' "Outstanding
 * per Type" / "Outstanding per Financial Institution", Cash Forecast's
 * invoice/cheque aging breakdowns).
 *
 * Built with amCharts4 (already an installed project dependency —
 * see Aging/Result.vue, the first Vue page to use it) using the exact
 * same radial-gradient-on-slice technique the ORIGINAL still-Blade
 * CashVero dashboards already used for their own donut charts
 * (RadialGradientModifier with negative brightness steps toward the
 * slice edges) — that's what gives the glossy, lit-from-above "3D"
 * look, not a literal extruded/3D chart type. A soft drop-shadow
 * filter is layered on top here, beyond what the original had, for a
 * more polished, professional finish requested for this rebuild.
 */
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import * as am4core from '@amcharts/amcharts4/core';
import * as am4charts from '@amcharts/amcharts4/charts';
import am4themes_animated from '@amcharts/amcharts4/themes/animated';

am4core.useTheme(am4themes_animated);

const props = defineProps({
    data: { type: Array, required: true }, // [{ category, value }]
    categoryField: { type: String, default: 'category' },
    valueField: { type: String, default: 'value' },
    height: { type: [Number, String], default: 260 },
    showTotal: { type: Boolean, default: true },
    totalLabel: { type: String, default: 'TOTAL' },
    colors: { type: Array, default: null }, // array of CSS var names, e.g. ['--cvr-num-blue', ...]
    valuePrefix: { type: String, default: '' },
});

const el = ref(null);
let chart = null;

function cvrColor(varName) {
    return am4core.color(getComputedStyle(document.documentElement).getPropertyValue(varName).trim());
}

const defaultPalette = ['--cvr-blue', '--cvr-green-bright', '--cvr-copper-bright', '--cvr-num-amber', '--cvr-num-red', '--cvr-copper'];

function build() {
    // dispose() before the guards: when the data empties out the template
    // swaps this chart's host for the placeholder and `el` goes null, so
    // an early return would strand the previous chart.
    dispose();
    if (!el.value) return;

    const rows = (props.data || []).filter(r => Number(r[props.valueField]) > 0);
    if (!rows.length) return;

    chart = am4core.create(el.value, am4charts.PieChart);
    chart.logo.disabled = true;
    chart.data = rows;
    chart.innerRadius = am4core.percent(52);
    chart.numberFormatter.numberFormat = '#,###';
    const textColor = cvrColor('--cvr-text-secondary');

    const series = chart.series.push(new am4charts.PieSeries());
    series.dataFields.value = props.valueField;
    series.dataFields.category = props.categoryField;
    series.ticks.template.disabled = true;
    series.labels.template.disabled = true;
    series.slices.template.tooltipText = `{category}: ${props.valuePrefix}{value.formatNumber("#,###")} ({value.percent.formatNumber("#.0")}%)`;
    series.slices.template.strokeWidth = 1;
    series.slices.template.stroke = cvrColor('--cvr-bg-card');

    // "3D" glossy look — radial gradient darkening toward the edges,
    // matching the original CashVero donut config exactly.
    const rgm = new am4core.RadialGradientModifier();
    rgm.brightnesses.push(-0.75, -0.75, -0.45, 0.05, -0.45);
    series.slices.template.fillModifier = rgm;
    series.slices.template.strokeModifier = rgm;
    series.slices.template.strokeOpacity = 0.5;

    // Soft drop shadow beneath the whole donut for extra depth —
    // a deliberate polish beyond the original's flat pie.
    series.slices.template.filters.push(new am4core.DropShadowFilter());
    const shadow = series.slices.template.filters.getIndex(0);
    shadow.opacity = 0.28;
    shadow.blur = 6;
    shadow.dy = 3;

    // Grow-on-hover for a livelier, more interactive feel.
    const hoverState = series.slices.template.states.getKey('hover');
    hoverState.properties.scale = 1.04;

    series.colors.list = (props.colors && props.colors.length ? props.colors : defaultPalette).map(cvrColor);

    // chart.legend = new am4charts.Legend();
    // chart.legend.position = 'buttom';
    // chart.legend.scrollable = true;
    // chart.legend.labels.template.fill = textColor;
    // chart.legend.labels.template.fontSize = 10;
    // chart.legend.valueLabels.template.fill = textColor;
    // chart.legend.valueLabels.template.text = '{value.percent.formatNumber("#.0")}%';

    if (props.showTotal) {
        const total = rows.reduce((sum, r) => sum + Number(r[props.valueField] || 0), 0);
        const totalLabelObj = chart.seriesContainer.createChild(am4core.Label);
        totalLabelObj.horizontalCenter = 'middle';
        totalLabelObj.verticalCenter = 'middle';
        totalLabelObj.fontSize = 10;
        totalLabelObj.fill = cvrColor('--cvr-text-muted');
        totalLabelObj.textAlign = 'middle';
        totalLabelObj.text = `${props.totalLabel}\n[bold font-size: 15]${props.valuePrefix}${total.toLocaleString(undefined, { maximumFractionDigits: 0 })}[/]`;
    }
}

function dispose() {
    if (chart) {
        chart.dispose();
        chart = null;
    }
}

onMounted(async () => {
    await nextTick();
    build();
});
onBeforeUnmount(dispose);
watch(() => props.data, async () => {
    await nextTick();
    build();
}, { deep: true });
</script>

<template>
    <!-- Chart host and placeholder are v-if/v-else siblings, never nested:
         am4core.create() wipes the element it is given, and Vue crashed
         patching the placeholder nodes amCharts had deleted. Same fix as
         MultiLineChart.vue — see its template comment. -->
    <div :style="{ height: typeof height === 'number' ? height + 'px' : height }">
        <div v-if="!(data || []).some(r => Number(r[valueField]) > 0)" class="h-full flex items-center justify-center text-xs cvr-text-muted">
            No data for this selection.
        </div>
        <div v-else ref="el" class="h-full"></div>
    </div>
</template>
