<script setup>
/**
 * Statements/FactoringStatement/Result.vue
 * ------------------------------------------------------------------
 * Served by FactoringStatementController@result. A running-balance
 * ledger for one factoring contract, for the chosen date range. Same
 * "heavy report" treatment as Bank Statement: rows are paginated
 * SERVER-SIDE (50/page — the original never paginated this report at
 * all). KPI totals are computed backend-side from the FULL result set
 * before pagination.
 */
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    currency: String,
    factoringCompanyName: String,
    contractLabel: String,
    kpis: Object, // { totalDebit, totalCredit, endingBalance, transactionCount }
    paginator: Object,
    urls: Object, // { backUrl, chargesStatementUrl, exportUrl }
});

const rows = computed(() => props.paginator?.data || []);

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function endBalanceColorVar(balance) {
    if (balance > 0) return 'var(--cvr-num-amber)';
    if (balance < 0) return 'var(--cvr-num-red)';
    return 'var(--cvr-num-green)';
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
                {{ $t('← Back to Factoring Statement') }}
            </Link>

            <!-- Tabs -->
            <div class="flex items-center gap-1 mb-4 border-b cvr-border">
                <span class="px-4 py-2 text-sm font-medium border-b-2" style="border-color: var(--cvr-green-bright); color: var(--cvr-green-bright);">
                    {{ $t('📄 Factoring Statement') }}
                </span>
                <a :href="urls.chargesStatementUrl" class="px-4 py-2 text-sm font-medium cvr-text-muted hover:cvr-text-primary">
                    {{ $t('🧾 Factoring Charges Statement') }}
                </a>
            </div>

            <div class="flex items-start justify-between flex-wrap gap-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    {{ $t('Factoring Statement') }}
                    <span class="cvr-text-secondary font-normal">
                        — {{ factoringCompanyName }} · {{ contractLabel }} · {{ String(currency).toUpperCase() }}
                    </span>
                </h1>
                <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                    {{ $t('⬇️ Export to Excel') }}
                </a>
            </div>
            <p class="text-sm cvr-text-muted mb-6">{{ kpis.transactionCount }} {{ $t('transactions in this date range.') }}</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">⬆️</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Debit') }}</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ formatAmount(kpis.totalDebit) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">⬇️</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Credit') }}</p>
                        <p class="cvr-kpi-value cvr-num">{{ formatAmount(kpis.totalCredit) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">🏁</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Ending Balance') }}</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.endingBalance) }}</p>
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
                                <th class="px-3 py-3 text-right">{{ $t('Debit') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Credit') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('End Balance') }}</th>
                                <th class="px-3 py-3 text-start min-w-[280px]">{{ $t('Comment') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="index" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">
                                    {{ (paginator.current_page - 1) * 50 + index + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.date }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ row.debit > 0 ? formatAmount(row.debit) : '' }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ row.credit > 0 ? formatAmount(row.credit) : '' }}</td>
                                <td class="px-3 py-2.5 text-right font-medium" :style="{ color: endBalanceColorVar(row.endBalance) }">{{ formatAmount(row.endBalance) }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">
                                    <span class="block max-w-[360px] whitespace-normal">{{ row.comment }}</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No movements found for this date range.') }}</td>
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
