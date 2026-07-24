<script setup>
/**
 * Statements/SafeStatement/Result.vue
 * ------------------------------------------------------------------
 * Served by SafeStatementController@result. A transaction-by-
 * transaction ledger for the cash held in one branch's safe, for the
 * chosen date range and currency.
 *
 * Same "heavy report" treatment as Bank Statement: rows are paginated
 * SERVER-SIDE (50/page — see PaginatesRawCollections), never loaded
 * hundreds-at-once into the browser. KPI totals are computed
 * backend-side from the FULL date-range result set before pagination.
 *
 * Entirely read-only — there are no inline-editable rows here (no
 * commission fees / interest concept for a physical safe), so this
 * page is simpler than Bank Statement's Result.vue by design, not by
 * omission.
 */
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    currency: String,
    branchName: String,
    kpis: Object, // { beginningBalance, endingBalance, totalDebit, totalCredit, transactionCount }
    paginator: Object, // { data, links, current_page, last_page, total }
    urls: Object, // { backUrl }
});

const rows = computed(() => props.paginator?.data || []);

function formatAmount(value) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* Same running end-balance sign convention as Bank Statement /
   Balances/Statement.vue: positive → amber, negative → red, zero → green. */
function endBalanceColorVar(balance) {
    if (balance > 0) return 'var(--cvr-num-amber)';
    if (balance < 0) return 'var(--cvr-num-red)';
    return 'var(--cvr-num-green)';
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
                ← Back to Safe Statement
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mt-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    Cash In Safe Statement
                    <span class="cvr-text-secondary font-normal">
                        — {{ branchName }} · {{ String(currency).toUpperCase() }}
                    </span>
                </h1>
                <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                    ⬇️ Export to Excel
                </a>
            </div>
            <p class="text-sm cvr-text-muted mb-6">{{ kpis.transactionCount }} transactions in this date range.</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">📅</div>
                    <div>
                        <p class="cvr-kpi-label">Beginning Balance</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.beginningBalance) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">⬆️</div>
                    <div>
                        <p class="cvr-kpi-label">Total Debit</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ formatAmount(kpis.totalDebit) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">⬇️</div>
                    <div>
                        <p class="cvr-kpi-label">Total Credit</p>
                        <p class="cvr-kpi-value cvr-num">{{ formatAmount(kpis.totalCredit) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">🏁</div>
                    <div>
                        <p class="cvr-kpi-label">Ending Balance</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.endingBalance) }}</p>
                    </div>
                </div>
            </div>

            <!-- Heavy transaction table — sticky header, horizontal + vertical scroll -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <div class="overflow-auto" style="max-height: 70vh;">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3 text-center">Date</th>
                                <th class="px-3 py-3 text-right">Beginning Balance</th>
                                <th class="px-3 py-3 text-right">Debit</th>
                                <th class="px-3 py-3 text-right">Credit</th>
                                <th class="px-3 py-3 text-right">End Balance</th>
                                <th class="px-3 py-3 text-center">Reviewed</th>
                                <th class="px-3 py-3 text-left min-w-[280px]">Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">
                                    {{ (paginator.current_page - 1) * 50 + index + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.date }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.beginningBalance) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.debit) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.credit) }}</td>
                                <td class="px-3 py-2.5 text-right font-medium" :style="{ color: endBalanceColorVar(row.endBalance) }">{{ formatAmount(row.endBalance) }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <span v-if="reviewedBadgeClass(row.reviewedText)" :class="reviewedBadgeClass(row.reviewedText)">{{ row.reviewedText }}</span>
                                    <span v-else class="cvr-text-muted">{{ row.reviewedText }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-left cvr-text-secondary">
                                    <span class="block max-w-[360px] whitespace-normal">{{ row.comment }}</span>
                                    <span v-if="row.userComment" class="block max-w-[360px] whitespace-normal cvr-text-muted text-xs mt-0.5">{{ row.userComment }}</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center cvr-text-muted">No movements found for this date range.</td>
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