<script setup>
/**
 * Statements/WithdrawalStatement/Result.vue
 * ------------------------------------------------------------------
 * Served by WithdrawalsSettlementReportController@result. A list of
 * overdraft withdrawals and how much of each is still outstanding,
 * for the chosen filters.
 *
 * Pagination note: the underlying route is POST (shared with the
 * still-Blade Cash Forecast dashboard — see the controller's
 * docblock), so "go to page N" can't use a plain GET link the way
 * Bank/Safe/Cash Expense/Partners Statement do. Laravel's paginator
 * reads the `page` parameter from the QUERY STRING regardless of HTTP
 * verb, so each page button re-POSTs the same stored filters to
 * `${urls.resultUrl}?page=N`.
 */
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    tableNameFormatted: String,
    kpis: Object, // { totalWithdrawalAmount, totalSettlementAmount, totalOutstandingBalance, transactionCount }
    paginator: Object, // { data, links, current_page, last_page, total }
    filters: Object, // resubmitted on page change
    urls: Object, // { backUrl, resultUrl, exportUrl }
});

const rows = computed(() => props.paginator?.data || []);

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* Balance (outstanding amount) coloring: amber while still owed,
   green once fully settled (0), red only as an anomaly flag if it
   were ever negative. */
function balanceColorVar(balance) {
    if (balance > 0) return 'var(--cvr-num-amber)';
    if (balance < 0) return 'var(--cvr-num-red)';
    return 'var(--cvr-num-green)';
}

function goToPage(page) {
    if (!page || page < 1 || page > props.paginator.last_page) return;
    router.post(`${props.urls.resultUrl}?page=${page}`, props.filters, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="urls.backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('← Back to Withdrawal Statement') }}
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    {{ tableNameFormatted }} {{ $t('Withdrawals Settlement Report') }}
                </h1>
                <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                    {{ $t('⬇️ Export to Excel') }}
                </a>
            </div>
            <p class="text-sm cvr-text-muted mb-6">{{ kpis.transactionCount }} {{ $t('withdrawals in this date range.') }}</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">💸</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Withdrawal Amount') }}</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.totalWithdrawalAmount) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">✅</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Settlement Amount') }}</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ formatAmount(kpis.totalSettlementAmount) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">⏳</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Outstanding Balance') }}</p>
                        <p class="cvr-kpi-value cvr-num-amber">{{ formatAmount(kpis.totalOutstandingBalance) }}</p>
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
                                <th class="px-3 py-3 text-start">{{ $t('Bank Name') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Account Type') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Account Number') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Withdrawal Date') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Withdrawal Amount') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Settlement Amount') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Balance') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Due Date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="row.id ?? index" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">
                                    {{ (paginator.current_page - 1) * 50 + index + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-start cvr-text-primary">{{ row.bankName }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.accountType }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.accountNumber }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.withdrawalDate }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.withdrawalAmount) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.settlementAmount) }}</td>
                                <td class="px-3 py-2.5 text-right font-medium" :style="{ color: balanceColorVar(row.balance) }">{{ formatAmount(row.balance) }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.dueDate }}</td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="9" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No withdrawals found for this date range.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination (re-POSTs the same filters with page in the query string) -->
            <div v-if="paginator.last_page > 1" class="flex items-center justify-center gap-1 mt-4 flex-wrap">
                <button
                    v-for="page in paginator.last_page"
                    :key="page"
                    @click="goToPage(page)"
                    class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm"
                    :class="{ 'cvr-nav-item-active': page === paginator.current_page }"
                >{{ page }}</button>
            </div>
        </div>
    </AppLayout>
</template>
