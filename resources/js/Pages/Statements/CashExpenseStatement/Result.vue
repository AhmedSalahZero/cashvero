<script setup>
/**
 * Statements/CashExpenseStatement/Result.vue
 * ------------------------------------------------------------------
 * Served by CashExpenseStatementController@result. A transaction-by-
 * transaction list of cash expenses for the chosen date range,
 * currency, and categories.
 *
 * Same "heavy report" treatment as Bank/Safe Statement: rows are
 * paginated SERVER-SIDE (50/page). KPI total (Paid Amount) is computed
 * backend-side from the FULL filtered result set before pagination.
 *
 * Feature (client requested, 2026-08-15): dropped Supplier Name,
 * Withhold Amount, Amount In Paying Currency, and Reviewed columns.
 * Added Currency (always shown). Exchange Rate and Equivalent In Main
 * Currency only show when the filtered currency isn't the company's
 * main functional currency — for a main-currency report exchange_rate
 * is always 1 and the amount already IS the main-currency amount, so
 * both columns would just be noise.
 */
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    currency: String,
    isMainCurrency: Boolean,
    kpis: Object, // { totalPaidAmount, transactionCount }
    paginator: Object, // { data, links, current_page, last_page, total }
    urls: Object, // { backUrl, exportUrl }
});

const rows = computed(() => props.paginator?.data || []);

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="urls.backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('← Back to Cash Expense Statement') }}
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    {{ $t('Cash Expense Statement') }}
                    <span class="cvr-text-secondary font-normal">— {{ String(currency).toUpperCase() }}</span>
                </h1>
                <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                    {{ $t('⬇️ Export to Excel') }}
                </a>
            </div>
            <p class="text-sm cvr-text-muted mb-6">{{ kpis.transactionCount }} {{ $t('transactions in this date range.') }}</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">💳</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Paid Amount') }}</p>
                        <p class="cvr-kpi-value cvr-num">{{ formatAmount(kpis.totalPaidAmount) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">📋</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Transactions') }}</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ kpis.transactionCount }}</p>
                    </div>
                </div>
            </div>

            <!-- Heavy transaction table — sticky header, horizontal + vertical scroll -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <div class="overflow-auto cvr-table-scroll" style="max-height: 150vh;">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3 text-center">{{ $t('Date') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Main Category') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Sub Category') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Currency') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Paid Amount') }}</th>
                                <template v-if="!isMainCurrency">
                                    <th class="px-3 py-3 text-right">{{ $t('Exchange Rate') }}</th>
                                    <th class="px-3 py-3 text-right">{{ $t('Equivalent In Main Currency') }}</th>
                                </template>
                                <th class="px-3 py-3 text-start min-w-[240px]">{{ $t('Comment') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">
                                    {{ (paginator.current_page - 1) * 50 + index + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.date }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-primary">{{ row.mainCategoryName }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.subCategoryName }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.currency }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.paidAmount) }}</td>
                                <template v-if="!isMainCurrency">
                                    <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.exchangeRate) }}</td>
                                    <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.equivalentInMainCurrency) }}</td>
                                </template>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">
                                    <span class="block max-w-[320px] whitespace-normal">{{ row.comment }}</span>
                                    <span v-if="row.userComment" class="block max-w-[320px] whitespace-normal cvr-text-muted text-xs mt-0.5">{{ row.userComment }}</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td :colspan="isMainCurrency ? 7 : 9" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No expenses found for this date range.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="paginator.last_page > 1" class="flex items-center justify-center gap-1 mt-4 flex-wrap">
                <button
                    v-for="(link, i) in paginator.links"
                    :key="i"
                    v-html="link.label"
                    :disabled="!link.url"
                    @click="goToPage(link.url)"
                    class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm"
                    :class="{ 'cvr-nav-item-active': link.active, 'opacity-40 cursor-not-allowed': !link.url }"
                ></button>
            </div>
        </div>
    </AppLayout>
</template>
