<script setup>
/**
 * Charts/AgingDivergingBarChart.vue
 * ------------------------------------------------------------------
 * Replaces the Cash Forecast tab's aging donuts with a diverging
 * horizontal bar chart, per the project owner's request: Past Due
 * buckets extend left (negative), Coming Due buckets extend right
 * (positive), and Current Due also extends right but in its own
 * distinct color so it doesn't read as "just another coming-due
 * bucket". Built with amCharts4 (same library as the rest of the
 * Dashboard's charts).
 *
 * Each data row already carries its own resolved color (a CSS
 * variable name, e.g. '--cvr-num-red') — resolved to an actual color
 * here via an adapter rather than amCharts' propertyFields, since
 * propertyFields expects the fill to already be an am4core.Color
 * instance, not a raw CSS custom-property name.
 */
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import * as am4core from '@amcharts/amcharts4/core';
import * as am4charts from '@amcharts/amcharts4/charts';
import am4themes_animated from '@amcharts/amcharts4/themes/animated';

am4core.useTheme(am4themes_animated);

const props = defineProps({
    // [{ category: 'Past Due · 1-7 Days', value: -1234, colorVar: '--cvr-num-red' }, ...]
    // Negative values render left, positive render right — the sign
    // is decided by the caller (Past Due should already be negative).
    data: { type: Array, required: true },
    height: { type: [Number, String], default: 320 },
    valuePrefix: { type: String, default: '' },
});

const el = ref(null);
let chart = null;

function cvrColor(varName) {
    return am4core.color(getComputedStyle(document.documentElement).getPropertyValue(varName).trim());
}

function build() {
    // dispose() before the guards: when the data empties out the template
    // swaps this chart's host for the placeholder and `el` goes null, so
    // an early return would strand the previous chart.
    dispose();
    if (!el.value) return;
    const rows = props.data || [];
    if (!rows.length) return;

    chart = am4core.create(el.value, am4charts.XYChart);
    chart.logo.disabled = true;
    chart.data = rows;
    chart.numberFormatter.numberFormat = '#,###';
    chart.paddingLeft = 0;

    const textColor = cvrColor('--cvr-text-secondary');
    const gridColor = cvrColor('--cvr-border-md');

    const categoryAxis = chart.yAxes.push(new am4charts.CategoryAxis());
    categoryAxis.dataFields.category = 'category';
    categoryAxis.renderer.grid.template.disabled = true;
    categoryAxis.renderer.minGridDistance = 8;
    categoryAxis.renderer.labels.template.fill = textColor;
    categoryAxis.renderer.labels.template.fontSize = 11;
    categoryAxis.renderer.inversed = true;
    categoryAxis.renderer.cellStartLocation = 0.15;
    categoryAxis.renderer.cellEndLocation = 0.85;

    const valueAxis = chart.xAxes.push(new am4charts.ValueAxis());
    valueAxis.renderer.grid.template.stroke = gridColor;
    valueAxis.renderer.grid.template.strokeOpacity = 0.25;
    valueAxis.renderer.labels.template.fill = textColor;
    valueAxis.renderer.labels.template.fontSize = 10;
    valueAxis.renderer.line.strokeOpacity = 0.6;
    valueAxis.renderer.line.stroke = gridColor;
    valueAxis.extraMax = 0.12;
    valueAxis.extraMin = 0.12;
    valueAxis.numberFormatter.numberFormat = '#,###';
    // Cursor axis tooltip — an interpolated pixel position, not a data
    // point, and unformatted, so it showed a long float on hover.
    valueAxis.cursorTooltipEnabled = false;

    // Emphasize the zero line — the visual "hinge" the whole chart
    // diverges around (past due to the left, due/coming due to the right).
    const zeroRange = valueAxis.axisRanges.create();
    zeroRange.value = 0;
    zeroRange.grid.stroke = textColor;
    zeroRange.grid.strokeOpacity = 0.55;
    zeroRange.grid.strokeWidth = 1.5;

    const series = chart.series.push(new am4charts.ColumnSeries());
    series.dataFields.valueX = 'value';
    series.dataFields.categoryY = 'category';
    series.tooltipText = `{category}: [bold]${props.valuePrefix}{valueX.formatNumber("#,###")}[/]`;
    // Same low-contrast tooltip fix as the other chart components —
    // a pale column color as the tooltip's own background makes the
    // white tooltip text nearly unreadable.
    series.tooltip.getFillFromObject = false;
    series.tooltip.background.fill = am4core.color('#0A1930');
    series.tooltip.background.fillOpacity = 0.96;
    series.tooltip.background.strokeOpacity = 0;
    series.tooltip.label.fill = am4core.color('#FFFFFF');
    series.tooltip.label.fontSize = 12;
    series.columns.template.height = am4core.percent(72);
    series.columns.template.strokeOpacity = 0;
    series.columns.template.column.cornerRadiusTopRight = 5;
    series.columns.template.column.cornerRadiusBottomRight = 5;
    series.columns.template.column.cornerRadiusTopLeft = 5;
    series.columns.template.column.cornerRadiusBottomLeft = 5;

    // Soft depth shadow, matching the donut charts' 3D-glossy finish.
    series.columns.template.filters.push(new am4core.DropShadowFilter());
    const shadow = series.columns.template.filters.getIndex(0);
    shadow.opacity = 0.28;
    shadow.blur = 6;
    shadow.dy = 3;

    series.columns.template.adapter.add('fill', (fill, target) => {
        const colorVar = target.dataItem?.dataContext?.colorVar;
        return colorVar ? cvrColor(colorVar) : fill;
    });
    series.columns.template.adapter.add('stroke', (stroke, target) => {
        const colorVar = target.dataItem?.dataContext?.colorVar;
        return colorVar ? cvrColor(colorVar) : stroke;
    });

    // Glossy "3D" gradient across each bar — a lighter highlight along
    // the top edge fading to the base color, matching the same
    // lit-from-above treatment used on the donut charts (see
    // DonutChart3D.vue's RadialGradientModifier) so this chart doesn't
    // look flatter than the rest of the Dashboard's charts.
    const columnGradientModifier = new am4core.LinearGradientModifier();
    columnGradientModifier.brightnesses = [0.35, -0.15];
    series.columns.template.fillModifier = columnGradientModifier;
    series.columns.template.strokeModifier = columnGradientModifier;

    const hoverState = series.columns.template.states.create('hover');
    hoverState.properties.fillOpacity = 0.85;

    chart.cursor = new am4charts.XYCursor();
    chart.cursor.behavior = 'none';
    chart.cursor.lineY.disabled = true;
    chart.cursor.lineX.strokeOpacity = 0;
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
        <div v-if="!(data || []).length" class="h-full flex items-center justify-center text-xs cvr-text-muted">
            No aging data for this selection.
        </div>
        <div v-else ref="el" class="h-full"></div>
    </div>
</template>
