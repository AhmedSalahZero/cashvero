<script setup>
/**
 * Statements/FactoringChargesStatement/Result.vue
 * ------------------------------------------------------------------
 * Served by FactoringChargesStatementController@result. Every
 * factoring charge (interest, other charges, uncollected invoice
 * charges) for the selected company/currency/contract(s), with a
 * running total. Same "heavy report" treatment as its sibling: rows
 * are paginated SERVER-SIDE (50/page — the original never paginated
 * this report at all).
 */
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    currency: String,
    factoringCompanyName: String,
    contractLabel: String,
    startDate: String,
    endDate: String,
    kpis: Object, // { totalCharges, endingRunningTotal, transactionCount }
    paginator: Object,
    urls: Object, // { backUrl, statementUrl, exportUrl }
});

const rows = computed(() => props.paginator?.data || []);

function formatAmount(value) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="urls.backUrl" class="text-sm cvr-text-muted hover:cvr-text-primary inline-flex items-center gap-1 mb-3">
                ← Back to Factoring Charges Statement
            </Link>

            <!-- Tabs -->
            <div class="flex items-center gap-1 mb-4 border-b cvr-border">
                <a :href="urls.statementUrl" class="px-4 py-2 text-sm font-medium cvr-text-muted hover:cvr-text-primary">
                    📄 Factoring Statement
                </a>
                <span class="px-4 py-2 text-sm font-medium border-b-2" style="border-color: var(--cvr-green-bright); color: var(--cvr-green-bright);">
                    🧾 Factoring Charges Statement
                </span>
            </div>

            <div class="flex items-start justify-between flex-wrap gap-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    Factoring Charges Statement
                    <span class="cvr-text-secondary font-normal">
                        — {{ factoringCompanyName }} · {{ contractLabel }} · {{ String(currency).toUpperCase() }} · {{ startDate }} — {{ endDate }}
                    </span>
                </h1>
                <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                    ⬇️ Export to Excel
                </a>
            </div>
            <p class="text-sm cvr-text-muted mb-6">{{ kpis.transactionCount }} charges in this date range.</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">💰</div>
                    <div>
                        <p class="cvr-kpi-label">Total Charges</p>
                        <p class="cvr-kpi-value cvr-num">{{ formatAmount(kpis.totalCharges) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">🏁</div>
                    <div>
                        <p class="cvr-kpi-label">Ending Running Total</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.endingRunningTotal) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">📋</div>
                    <div>
                        <p class="cvr-kpi-label">Charges</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ kpis.transactionCount }}</p>
                    </div>
                </div>
            </div>

            <!-- Heavy list table — sticky header, horizontal + vertical scroll -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <div class="overflow-auto" style="max-height: 70vh;">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3 text-center">Date</th>
                                <th class="px-3 py-3 text-left">Charge Type</th>
                                <th class="px-3 py-3 text-right">Amount</th>
                                <th class="px-3 py-3 text-right">Running Total</th>
                                <th class="px-3 py-3 text-left min-w-[320px]">Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="index" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">
                                    {{ (paginator.current_page - 1) * 50 + index + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.date }}</td>
                                <td class="px-3 py-2.5 text-left cvr-text-primary">{{ row.charge_type }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.amount) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num-blue font-medium">{{ formatAmount(row.running_total) }}</td>
                                <td class="px-3 py-2.5 text-left cvr-text-secondary">
                                    <span class="block max-w-[400px] whitespace-normal">{{ row.comment }}</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center cvr-text-muted">No charges found for this date range.</td>
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
