<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    weeks: Object,                    // { weekAndYear: weekNumber }
    dates: Object,                    // { weekAndYear: { start_date, end_date } }
    reportInterval: String,
    banksSection: Object,             // { label: { total: { weekKey: amount } } }
    contractsSection: Array,          // [{ contract_id, contract_name, contract_code, cash_inflow, cash_outflow, net_cash }]
    companyUnallocatedCashOut: Object,
    companyUnallocatedCashIn: Object,
    grandTotal: Object,               // { cash_and_banks, cash_inflow, cash_outflow, net_cash, accumulated_net }
    currencyName: String,              // عملة الفلتر — تختار العقود فقط
    displayCurrency: String,           // العملة الوظيفية — كل الأرقام المعروضة بها
    title: String,
    filters: Object,
    urls: Object,
});

const weekKeys = computed(() => Object.keys(props.weeks || {}));

function periodLabel(wk) {
    if (props.reportInterval === 'weekly') {
        const year = wk.split('-')[1];
        return `Week ${props.weeks[wk]} [${year}]`;
    }
    if (props.reportInterval === 'monthly') {
        const sd = props.dates?.[wk]?.start_date;
        if (sd) {
            const d = new Date(sd);
            return `${String(d.getMonth() + 1).padStart(2, '0')}-${d.getFullYear()}`;
        }
        return props.weeks[wk] ?? wk;
    }
    return props.dates?.[wk]?.start_date ?? wk;
}

function fmt(n) {
    return (Number(n) || 0).toLocaleString('en-EG', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
function rowTotal(series) {
    return weekKeys.value.reduce((s, wk) => s + (Number(series?.[wk]) || 0), 0);
}
function netClass(v) {
    return v < 0 ? 'cvr-num-red' : 'cvr-num-green';
}

const accumulatedTotal = computed(() => {
    const keys = weekKeys.value;
    if (!keys.length) return 0;
    return Number(props.grandTotal?.accumulated_net?.[keys[keys.length - 1]]) || 0;
});
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-full mx-auto">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ title }}</h1>
                    <Link :href="urls.index" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Consolidated Cash Flow') }}
                </Link>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="urls.exportExcel" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">{{ $t('Export Excel') }}</a>
                    <button type="button" @click="window.print()" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">{{ $t('Print') }}</button>
                </div>
            </div>

            <p class="text-sm cvr-text-muted mb-4"><strong class="cvr-text-primary">{{ $t('All amounts are shown in:') }}</strong> {{ displayCurrency }} — <strong class="cvr-text-primary">{{ $t('Contracts filter currencies:') }}</strong> {{ (filters?.currencies?.length ? filters.currencies.join(', ') : 'All') }} — <strong v-if="filters?.min_end_year" class="cvr-text-primary">{{ $t('Contracts ending in/after:') }}</strong> <span v-if="filters?.min_end_year">{{ filters.min_end_year }} — </span><strong class="cvr-text-primary">{{ $t('Interval:') }}</strong> {{ reportInterval }}</p>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-2 py-2 text-start whitespace-nowrap">{{ $t('Item') }}</th>
                            <th v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center whitespace-nowrap">{{ periodLabel(wk) }}</th>
                            <th class="px-2 py-2 text-center whitespace-nowrap">{{ $t('Total') }}</th>
                        </tr>
                        <tr v-if="reportInterval === 'weekly'">
                            <th class="px-2 py-1 text-start text-xs opacity-80">{{ $t('Start Date') }}</th>
                            <th v-for="wk in weekKeys" :key="'sd'+wk" class="px-2 py-1 text-center text-xs opacity-80 whitespace-nowrap">{{ dates[wk]?.start_date }}</th>
                            <th></th>
                        </tr>
                        <tr v-if="reportInterval === 'weekly'">
                            <th class="px-2 py-1 text-start text-xs opacity-80">{{ $t('End Date') }}</th>
                            <th v-for="wk in weekKeys" :key="'ed'+wk" class="px-2 py-1 text-center text-xs opacity-80 whitespace-nowrap">{{ dates[wk]?.end_date }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Section A: Company level -->
                        <tr class="cvr-table-head">
                            <td :colspan="weekKeys.length + 2" class="px-2 py-2 font-semibold">{{ $t('Section A — Company level (Cash & Banks Balance)') }}</td>
                        </tr>
                        <tr v-for="(row, label) in banksSection" :key="'bank-'+label" class="cvr-table-row">
                            <td class="px-2 py-2 whitespace-nowrap">{{ label }}</td>
                            <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center cvr-num whitespace-nowrap">{{ fmt(row.total?.[wk]) }}</td>
                            <td class="px-2 py-2 text-center cvr-num font-semibold whitespace-nowrap">{{ fmt(rowTotal(row.total)) }}</td>
                        </tr>
                        <tr v-if="!Object.keys(banksSection || {}).length">
                            <td :colspan="weekKeys.length + 2" class="px-2 py-4 text-center cvr-text-muted">{{ $t('No bank-level rows returned.') }}</td>
                        </tr>
                        <tr class="cvr-table-row">
                            <td class="px-2 py-2 whitespace-nowrap">{{ $t('Cash Inflow (unallocated)') }}</td>
                            <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center cvr-num whitespace-nowrap">{{ fmt(companyUnallocatedCashIn?.[wk]) }}</td>
                            <td class="px-2 py-2 text-center cvr-num font-semibold whitespace-nowrap">{{ fmt(rowTotal(companyUnallocatedCashIn)) }}</td>
                        </tr>

                        <!-- Per-contract blocks -->
                        <template v-for="block in contractsSection" :key="block.contract_id">
                            <tr class="cvr-table-head">
                                <td :colspan="weekKeys.length + 2" class="px-2 py-2 font-semibold">
                                    {{ block.contract_name }}
                                    <span v-if="block.contract_code" class="opacity-80 font-normal ms-1">[{{ block.contract_code }}]</span>
                                </td>
                            </tr>
                            <tr class="cvr-table-row cvr-summary-row">
                                <td class="px-2 py-2 whitespace-nowrap">{{ $t('Total Cash Inflow') }}</td>
                                <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center cvr-num whitespace-nowrap">{{ fmt(block.cash_inflow?.[wk]) }}</td>
                                <td class="px-2 py-2 text-center cvr-num font-semibold whitespace-nowrap">{{ fmt(rowTotal(block.cash_inflow)) }}</td>
                            </tr>
                            <tr class="cvr-table-row cvr-summary-row">
                                <td class="px-2 py-2 whitespace-nowrap">{{ $t('Total Cash Outflow') }}</td>
                                <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center cvr-num whitespace-nowrap">{{ fmt(block.cash_outflow?.[wk]) }}</td>
                                <td class="px-2 py-2 text-center cvr-num font-semibold whitespace-nowrap">{{ fmt(rowTotal(block.cash_outflow)) }}</td>
                            </tr>
                            <tr class="cvr-table-row cvr-summary-row">
                                <td class="px-2 py-2 whitespace-nowrap font-medium">{{ $t('Net Cash (+/-)') }}</td>
                                <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center font-medium whitespace-nowrap" :class="netClass(block.net_cash?.[wk])">{{ fmt(block.net_cash?.[wk]) }}</td>
                                <td class="px-2 py-2 text-center font-semibold whitespace-nowrap" :class="netClass(rowTotal(block.net_cash))">{{ fmt(rowTotal(block.net_cash)) }}</td>
                            </tr>
                        </template>
                        <tr v-if="!contractsSection.length">
                            <td :colspan="weekKeys.length + 2" class="px-2 py-4 text-center cvr-text-muted">{{ $t('No contracts selected/available for this run.') }}</td>
                        </tr>

                        <!-- Unallocated -->
                        <tr class="cvr-table-head">
                            <td :colspan="weekKeys.length + 2" class="px-2 py-2 font-semibold">{{ $t('Company cash out (unallocated)') }}</td>
                        </tr>
                        <tr class="cvr-table-row">
                            <td class="px-2 py-2 whitespace-nowrap">{{ $t('Company cash out (unallocated)') }}</td>
                            <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center cvr-num whitespace-nowrap">{{ fmt(companyUnallocatedCashOut?.[wk]) }}</td>
                            <td class="px-2 py-2 text-center cvr-num font-semibold whitespace-nowrap">{{ fmt(rowTotal(companyUnallocatedCashOut)) }}</td>
                        </tr>

                        <!-- Section C: Grand total -->
                        <tr class="cvr-table-head">
                            <td :colspan="weekKeys.length + 2" class="px-2 py-2 font-semibold">{{ $t('Section C — Grand total') }}</td>
                        </tr>
                        <tr class="cvr-table-row cvr-summary-row">
                            <td class="px-2 py-2 whitespace-nowrap">{{ $t('Cash & Banks Balance') }}</td>
                            <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center cvr-num whitespace-nowrap">{{ fmt(grandTotal.cash_and_banks?.[wk]) }}</td>
                            <td class="px-2 py-2 text-center cvr-num font-semibold whitespace-nowrap">{{ fmt(rowTotal(grandTotal.cash_and_banks)) }}</td>
                        </tr>
                        <tr class="cvr-table-row cvr-summary-row">
                            <td class="px-2 py-2 whitespace-nowrap">{{ $t('Total Cash Inflow') }}</td>
                            <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center cvr-num whitespace-nowrap">{{ fmt(grandTotal.cash_inflow?.[wk]) }}</td>
                            <td class="px-2 py-2 text-center cvr-num font-semibold whitespace-nowrap">{{ fmt(rowTotal(grandTotal.cash_inflow)) }}</td>
                        </tr>
                        <tr class="cvr-table-row cvr-summary-row">
                            <td class="px-2 py-2 whitespace-nowrap">{{ $t('Total Cash Outflow') }}</td>
                            <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center cvr-num whitespace-nowrap">{{ fmt(grandTotal.cash_outflow?.[wk]) }}</td>
                            <td class="px-2 py-2 text-center cvr-num font-semibold whitespace-nowrap">{{ fmt(rowTotal(grandTotal.cash_outflow)) }}</td>
                        </tr>
                        <tr class="cvr-table-row cvr-summary-row">
                            <td class="px-2 py-2 whitespace-nowrap font-medium">{{ $t('Net Cash (+/-)') }}</td>
                            <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center font-medium whitespace-nowrap" :class="netClass(grandTotal.net_cash?.[wk])">{{ fmt(grandTotal.net_cash?.[wk]) }}</td>
                            <td class="px-2 py-2 text-center font-semibold whitespace-nowrap" :class="netClass(rowTotal(grandTotal.net_cash))">{{ fmt(rowTotal(grandTotal.net_cash)) }}</td>
                        </tr>
                        <tr class="cvr-table-row cvr-summary-row">
                            <td class="px-2 py-2 whitespace-nowrap">{{ $t('Accumulated Net Cash (+/-)') }}</td>
                            <td v-for="wk in weekKeys" :key="wk" class="px-2 py-2 text-center whitespace-nowrap" :class="netClass(grandTotal.accumulated_net?.[wk])">{{ fmt(grandTotal.accumulated_net?.[wk]) }}</td>
                            <td class="px-2 py-2 text-center whitespace-nowrap" :class="netClass(accumulatedTotal)">{{ fmt(accumulatedTotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
