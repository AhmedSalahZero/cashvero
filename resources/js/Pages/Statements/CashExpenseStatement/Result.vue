<script setup>
/**
 * Statements/CashExpenseStatement/Result.vue
 * ------------------------------------------------------------------
 * Served by CashExpenseStatementController@result. A transaction-by-
 * transaction list of cash expenses for the chosen date range,
 * currency, and categories.
 *
 * Same "heavy report" treatment as Bank/Safe Statement: rows are
 * paginated SERVER-SIDE (50/page). KPI totals (Paid Amount, Withhold
 * Amount) are computed backend-side from the FULL filtered result set
 * before pagination.
 */
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    currency: String,
    kpis: Object, // { totalPaidAmount, totalWithholdAmount, transactionCount }
    paginator: Object, // { data, links, current_page, last_page, total }
    urls: Object, // { backUrl, exportUrl }
});

const rows = computed(() => props.paginator?.data || []);

function formatAmount(value) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function reviewedBadgeClass(text) {
    if (text === 'Yes') return 'cvr-badge cvr-badge-active';
    if (text === 'No') return 'cvr-badge cvr-badge-pending';
    return null;
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
                ← Back to Cash Expense Statement
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    Cash Expense Statement
                    <span class="cvr-text-secondary font-normal">— {{ String(currency).toUpperCase() }}</span>
                </h1>
                <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                    ⬇️ Export to Excel
                </a>
            </div>
            <p class="text-sm cvr-text-muted mb-6">{{ kpis.transactionCount }} transactions in this date range.</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">💳</div>
                    <div>
                        <p class="cvr-kpi-label">Total Paid Amount</p>
                        <p class="cvr-kpi-value cvr-num">{{ formatAmount(kpis.totalPaidAmount) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">🧾</div>
                    <div>
                        <p class="cvr-kpi-label">Total Withhold Amount</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.totalWithholdAmount) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">📋</div>
                    <div>
                        <p class="cvr-kpi-label">Transactions</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ kpis.transactionCount }}</p>
                    </div>
                </div>
            </div>

            <!-- Heavy transaction table — sticky header, horizontal + vertical scroll -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <div class="overflow-auto" style="max-height: 150vh;">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3 text-center">Date</th>
                                <th class="px-3 py-3 text-left">Main Category</th>
                                <th class="px-3 py-3 text-left">Sub Category</th>
                                <th class="px-3 py-3 text-left">Supplier Name</th>
                                <th class="px-3 py-3 text-right">Paid Amount</th>
                                <th class="px-3 py-3 text-right">Withhold Amount</th>
                                <th class="px-3 py-3 text-right">Amount In Paying Currency</th>
                                <th class="px-3 py-3 text-right">Exchange Rate</th>
                                <th class="px-3 py-3 text-center">Reviewed</th>
                                <th class="px-3 py-3 text-left min-w-[240px]">Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">
                                    {{ (paginator.current_page - 1) * 50 + index + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.date }}</td>
                                <td class="px-3 py-2.5 text-left cvr-text-primary">{{ row.mainCategoryName }}</td>
                                <td class="px-3 py-2.5 text-left cvr-text-secondary">{{ row.subCategoryName }}</td>
                                <td class="px-3 py-2.5 text-left cvr-text-secondary">{{ row.supplierName }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.paidAmount) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.withholdAmount) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.amountInPayingCurrency) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.exchangeRate) }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <span v-if="reviewedBadgeClass(row.reviewedText)" :class="reviewedBadgeClass(row.reviewedText)">{{ row.reviewedText }}</span>
                                    <span v-else class="cvr-text-muted">{{ row.reviewedText }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-left cvr-text-secondary">
                                    <span class="block max-w-[320px] whitespace-normal">{{ row.comment }}</span>
                                    <span v-if="row.userComment" class="block max-w-[320px] whitespace-normal cvr-text-muted text-xs mt-0.5">{{ row.userComment }}</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="11" class="px-4 py-8 text-center cvr-text-muted">No expenses found for this date range.</td>
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
