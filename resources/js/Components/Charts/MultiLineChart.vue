<script setup>
/**
 * Charts/MultiLineChart.vue
 * ------------------------------------------------------------------
 * Shared professional multi-series line/area chart used across the
 * Dashboard: Cash Status' per-overdraft-type "Bank Movement" chart
 * (Cash In / Cash Out / End Balance — the original's own
 * "chartdiv_two_lines" config, generalized here to any number of
 * series) and Cash Forecast's "Monthly Cash Flow" / "Accumulated Net
 * Cash" charts. Built with amCharts4 (see DonutChart3D.vue's docblock
 * for why this library, already an installed project dependency).
 *
 * Polish beyond the original: each line gets a soft gradient area
 * fill under it (fillOpacity fading to 0), rounded tooltips, and a
 * panning+zooming cursor — a deliberate upgrade over the original's
 * flat, fill-less line charts, requested for this rebuild.
 */
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import * as am4core from '@amcharts/amcharts4/core';
import * as am4charts from '@amcharts/amcharts4/charts';
import am4themes_animated from '@amcharts/amcharts4/themes/animated';

am4core.useTheme(am4themes_animated);

const props = defineProps({
    data: { type: Array, required: true }, // [{ date: 'YYYY-MM-DD', ...fields }]
    series: {
        type: Array,
        required: true,
        // [{ field, name, color: '--cvr-blue', fill: true, opposite: false }]
    },
    height: { type: [Number, String], default: 275 },
    dateFormat: { type: String, default: 'yyyy-MM-dd' },
    syncAxes: { type: Boolean, default: true }, // multiple independent Y axes (like the original), or one shared axis
});

const el = ref(null);
let chart = null;

function cvrColor(varName) {
    return am4core.color(getComputedStyle(document.documentElement).getPropertyValue(varName).trim());
}

function build() {
    // dispose() first, unconditionally: when the data empties out, the
    // template swaps the chart host for the placeholder and `el` goes
    // null. Returning before disposing would strand the old chart.
    dispose();
    if (!el.value) return;
    if (!(props.data || []).length) return;

    chart = am4core.create(el.value, am4charts.XYChart);
    chart.logo.disabled = true;
    /**
     * FIX (per request, 2026-08-13): relying on amCharts' own
     * "#,###" number format to hide decimals turned out not to be
     * reliable on its own (confirmed on the Aging report — see
     * Aging/Result.vue) — long floating-point tails from upstream
     * arithmetic could still leak through in places like the cursor's
     * value-axis tooltip. Rounding every numeric field here, once, at
     * the source, guarantees clean whole numbers everywhere this
     * chart displays a value, regardless of how amCharts formats
     * things — applied generally since this component is shared
     * across the whole Dashboard, not just one page.
     */
    chart.data = (props.data || []).map(row => {
        const rounded = { ...row };
        Object.keys(rounded).forEach(key => {
            if (key !== 'date' && typeof rounded[key] === 'number') {
                rounded[key] = Math.round(rounded[key]);
            }
        });
        return rounded;
    });
    chart.dateFormatter.inputDateFormat = props.dateFormat;
    // Whole numbers everywhere the chart prints one — axis labels, axis
    // tooltips, legend values. Without this amCharts falls back to raw
    // floats, which is where the 12-decimal hover values came from: the
    // series tooltip below was already formatted, but the value-axis
    // tooltip that follows the cursor was not.
    chart.numberFormatter.numberFormat = '#,###';
    chart.paddingRight = 12;

    const textColor = cvrColor('--cvr-text-secondary');
    const gridColor = cvrColor('--cvr-border-md');

    const dateAxis = chart.xAxes.push(new am4charts.DateAxis());
    dateAxis.renderer.minGridDistance = 55;
    dateAxis.renderer.labels.template.fill = textColor;
    dateAxis.renderer.grid.template.stroke = gridColor;
    dateAxis.renderer.grid.template.strokeOpacity = 0.35;
    dateAxis.renderer.line.stroke = gridColor;
    dateAxis.renderer.line.strokeOpacity = 0.6;
    dateAxis.tooltip.disabled = true;

    let sharedValueAxis = null;
    if (props.syncAxes) {
        sharedValueAxis = chart.yAxes.push(new am4charts.ValueAxis());
        sharedValueAxis.renderer.grid.template.stroke = gridColor;
        sharedValueAxis.renderer.grid.template.strokeOpacity = 0.25;
        sharedValueAxis.renderer.labels.template.fill = textColor;
        // The cursor's own axis tooltip — an interpolated pixel position,
        // not a data point, so it is meaningless at 12 decimal places and
        // just competes with the series tooltip for attention.
        sharedValueAxis.cursorTooltipEnabled = false;
    }

    (props.series || []).forEach((seriesDef, index) => {
        let valueAxis = sharedValueAxis;
        if (!props.syncAxes) {
            valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
            if (index !== 0) {
                valueAxis.syncWithAxis = chart.yAxes.getIndex(0);
            }
            valueAxis.renderer.grid.template.stroke = gridColor;
            valueAxis.renderer.grid.template.strokeOpacity = 0.2;
            valueAxis.renderer.opposite = !!seriesDef.opposite;
            valueAxis.cursorTooltipEnabled = false;
        }

        const color = seriesDef.color ? cvrColor(seriesDef.color) : chart.colors.getIndex(index);

        const line = chart.series.push(new am4charts.LineSeries());
        line.dataFields.valueY = seriesDef.field;
        line.dataFields.dateX = 'date';
        line.name = seriesDef.name;
        line.stroke = color;
        line.strokeWidth = 2.5;
        line.yAxis = valueAxis;
        line.tensionX = 0.85;
        line.tooltipText = '{name}: [bold]{valueY.formatNumber("#,###")}[/]';
        line.showOnInit = true;
        // ⚠️ Fixed a real low-contrast bug here: amCharts4 series
        // tooltips default to using the series' OWN line color as
        // their background (getFillFromObject = true), with white
        // text on top — fine for a dark, saturated color, but nearly
        // unreadable for paler series colors (e.g. the light green
        // "Cash Inflow" line), especially against this app's light
        // theme. Forcing a fixed dark navy background + white text on
        // every tooltip guarantees readability regardless of the
        // series' own color or which page theme is active.
        line.tooltip.getFillFromObject = false;
        line.tooltip.background.fill = am4core.color('#0A1930');
        line.tooltip.background.fillOpacity = 0.96;
        line.tooltip.background.cornerRadius = 6;
        line.tooltip.background.strokeOpacity = 0;
        line.tooltip.label.fill = am4core.color('#FFFFFF');
        line.tooltip.label.fontSize = 12;

        if (seriesDef.fill !== false) {
            line.fill = color;
            line.fillOpacity = 0.16;
            const gradient = new am4core.LinearGradient();
            gradient.addColor(color, 0.32);
            gradient.addColor(color, 0);
            line.fillModifier = new am4core.LinearGradientModifier();
            line.fillModifier.opacities = [0.32, 0];
            line.fill = gradient;
        }

        const bullet = line.bullets.push(new am4charts.CircleBullet());
        bullet.circle.radius = 3;
        bullet.circle.strokeWidth = 2;
        bullet.circle.stroke = cvrColor('--cvr-bg-card');
        bullet.circle.fill = color;
        const bulletHover = bullet.states.create('hover');
        bulletHover.properties.scale = 1.4;

        if (!props.syncAxes && index !== 0) {
            valueAxis.renderer.line.strokeOpacity = 1;
            valueAxis.renderer.line.stroke = color;
            valueAxis.renderer.labels.template.fill = color;
        }
    });

    chart.legend = new am4charts.Legend();
    chart.legend.labels.template.fill = textColor;
    chart.legend.labels.template.fontSize = 11;
    chart.legend.valueLabels.template.fill = textColor;

    chart.cursor = new am4charts.XYCursor();
    chart.cursor.lineX.stroke = gridColor;
    chart.cursor.lineY.stroke = gridColor;
    chart.cursor.behavior = 'panXY';

    chart.tooltip.background.cornerRadius = 8;
    chart.tooltip.background.strokeOpacity = 0;
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
    <!-- amCharts and Vue must never own the same DOM node. am4core.create()
         empties the element it is handed and fills it with its own nodes —
         including the placeholder below, when the placeholder lived INSIDE
         the chart host. Vue's vdom kept pointing at those deleted nodes, so
         the next patch threw "Cannot read properties of null (reading
         'insertBefore')" / "'parentNode'" / "Cannot destructure property
         'bum' of 'h' as it is null" on unmount. The host div is now empty
         and a v-if/v-else sibling of the placeholder, so exactly one of the
         two exists at a time and amCharts only ever touches its own. -->
    <div :style="{ height: typeof height === 'number' ? height + 'px' : height }">
        <div v-if="!(data || []).length" class="h-full flex items-center justify-center text-xs cvr-text-muted">
            {{ $t('No data for this selection yet.') }}
        </div>
        <div v-else ref="el" class="h-full" dir="ltr"></div>
    </div>
</template>
