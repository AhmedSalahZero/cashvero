<script setup>
/**
 * Statements/DownPaymentReport/Result.vue
 * ------------------------------------------------------------------
 * Served by DownPaymentReportController@result. One row per down payment:
 * who it is with, how much it was, how much has been settled against
 * invoices and what is left - zero balances included.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    company: Object,
    type: String,
    typeLabel: String,
    partnerHeading: String,
    fromDate: String,
    currencies: Array,   // upper-cased codes picked on the form; empty = all
    kpis: Object,        // { count, perCurrency: [{ currency, amount, settled, balance }] }
    paginator: Object,
    urls: Object,
});

const rows = computed(() => props.paginator?.data || []);
const rowOffset = computed(() => ((props.paginator?.current_page || 1) - 1) * (props.paginator?.per_page || 0));

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* Same meaning the app gives numbers elsewhere: nothing left = green,
   still open = amber. */
function balanceClass(balance) {
    return Number(balance) > 0 ? 'cvr-num-amber' : 'cvr-num-green';
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="urls.index" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                {{ $t('← Back to Down Payment Report') }}
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    {{ $t('Down Payment Report') }}
                    <span class="cvr-text-secondary font-normal">— {{ typeLabel }} · {{ $t('From Date') }} {{ fromDate }}</span>
                </h1>
                <div class="flex items-center gap-2">
                    <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        {{ $t('⬇️ Export to Excel') }}
                    </a>
                    <a :href="urls.printUrl" target="_blank" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        {{ $t('🖨️ Print') }}
                    </a>
                </div>
            </div>
            <p class="text-sm cvr-text-muted mb-6">
                {{ kpis.count }} {{ $t('Down Payments') }}
                <span v-if="currencies.length"> · {{ currencies.join(', ') }}</span>
                <span v-else> · {{ $t('All currencies') }}</span>
            </p>

            <!-- KPI row: one card per currency - they cannot be added together -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">📋</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Down Payments') }}</p>
                        <p class="cvr-kpi-value cvr-num">{{ kpis.count }}</p>
                    </div>
                </div>
                <div v-for="total in kpis.perCurrency" :key="total.currency" class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">💰</div>
                    <div>
                        <p class="cvr-kpi-label">{{ total.currency }} · {{ $t('Down Payment Amount') }}</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(total.amount) }}</p>
                        <p class="cvr-kpi-label mt-2">{{ $t('Settled Amount') }}</p>
                        <p class="cvr-kpi-value cvr-num">{{ formatAmount(total.settled) }}</p>
                        <p class="cvr-kpi-label mt-2">{{ $t('Balance') }}</p>
                        <p class="cvr-kpi-value" :class="balanceClass(total.balance)">{{ formatAmount(total.balance) }}</p>
                    </div>
                </div>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <div class="overflow-auto cvr-table-scroll" style="max-height: 150vh;">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3 text-start">{{ partnerHeading }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Date') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Contract') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Down Payment Amount') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Settled Amount') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Balance') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Currency') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ rowOffset + index + 1 }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-primary">{{ row.partnerName }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary whitespace-nowrap">{{ row.date }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.contractName }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.amount) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.settled) }}</td>
                                <td class="px-3 py-2.5 text-right font-medium" :class="balanceClass(row.balance)">{{ formatAmount(row.balance) }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.currency }}</td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No down payments found for these filters.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <Pagination :paginator="paginator" label="down payments" />
        </div>
    </AppLayout>
</template>
