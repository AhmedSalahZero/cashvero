<script setup>
/**
 * CollectionEffectiveness/Result.vue
 * ------------------------------------------------------------------
 * Served by CollectionEffectivenessIndexController@result. One row
 * per customer/supplier, one column per date period, each cell a
 * collection/payment effectiveness percentage (collected ÷ what
 * should have been collected × 100), plus an "All Company" summary
 * row. All math untouched — see the controller's docblock.
 */
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    reportName: String,
    tableHeaders: Array, // ['2025-07-21/2026-07-21'] or one entry per month if isMonthlyReport
    customerOrSupplierNameText: String,
    rows: Array, // [{ name, values: { [header]: percent }, total }]
    allCompanyRow: Object, // { values: { [header]: percent }, total }
    isMonthlyReport: Boolean,
    backUrl: String,
});

function formatPercent(value) {
    return Number(value || 0).toFixed(2) + ' %';
}

// Number Color Rule, applied to effectiveness percentage — this is a
// judgment call made during migration (the original showed plain
// text, no color), not a confirmed business threshold. Easy to
// adjust: green = 90%+ (effective), amber = 50–89% (moderate), red =
// under 50% (poor collection/payment performance).
function percentClass(value) {
    const n = Number(value || 0);
    if (n >= 90) return 'cvr-num-green';
    if (n >= 50) return 'cvr-num-amber';
    return 'cvr-num-red';
}

// A header looks like "2025-07-21/2026-07-21" — split into two lines
// for a cleaner column header instead of one long slashed string.
function splitHeader(header) {
    const [start, end] = header.split('/');
    return { start, end };
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                ← Back to Filters
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ reportName }}</h1>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="text-sm border-collapse w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-center cvr-table-head" style="min-width: 40px">#</th>
                            <th class="px-4 py-2 text-left cvr-table-head" style="min-width: 220px">{{ customerOrSupplierNameText }}</th>
                            <th v-for="header in tableHeaders" :key="header" class="px-3 py-2 text-center cvr-table-head" style="min-width: 140px">
                                <span class="text-xs">{{ splitHeader(header).start }}</span><br />
                                <span class="text-xs">→ {{ splitHeader(header).end }}</span>
                            </th>
                            <th v-if="isMonthlyReport" class="px-3 py-2 text-center cvr-table-head" style="min-width: 100px">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in rows" :key="row.name" class="cvr-table-row">
                            <td class="px-4 py-2 text-center cvr-text-secondary">{{ i + 1 }}</td>
                            <td class="px-4 py-2 text-left cvr-text-primary">
                                <span class="block truncate max-w-[280px]" :title="row.name">{{ row.name }}</span>
                            </td>
                            <td v-for="header in tableHeaders" :key="header" class="px-3 py-2 text-center font-medium" :class="percentClass(row.values[header])">{{ formatPercent(row.values[header]) }}</td>
                            <td v-if="isMonthlyReport" class="px-3 py-2 text-center font-medium" :class="percentClass(row.total)">{{ formatPercent(row.total) }}</td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td :colspan="tableHeaders.length + (isMonthlyReport ? 3 : 2)" class="px-4 py-8 text-center cvr-text-muted">No data found.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length">
                        <tr class="font-semibold" style="background: var(--cvr-green-deep)">
                            <td class="px-4 py-2 text-center">-</td>
                            <td class="px-4 py-2 text-left">All Company</td>
                            <td v-for="header in tableHeaders" :key="header" class="px-3 py-2 text-center">{{ formatPercent(allCompanyRow.values[header]) }}</td>
                            <td v-if="isMonthlyReport" class="px-3 py-2 text-center">{{ formatPercent(allCompanyRow.total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
