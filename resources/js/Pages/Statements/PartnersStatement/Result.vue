<script setup>
/**
 * Statements/PartnersStatement/Result.vue
 * ------------------------------------------------------------------
 * Served by PartnersStatementController@result. A running-balance
 * ledger for every selected partner, grouped by partner (each group
 * collapsible — expanded by default, matching the original Blade
 * page's behavior of auto-expanding every group on load).
 *
 * Pagination is at the PARTNER level (10 groups/page) rather than the
 * row level — see the controller's docblock for why: splitting one
 * partner's own transactions mid-group across two pages would make
 * the running balance meaningless.
 */
import { computed, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    currency: String,
    kpis: Object, // { partnerCount, transactionCount, totalDebit, totalCredit, totalEndBalance }
    paginator: Object, // { data, links, current_page, last_page, total }
    urls: Object, // { backUrl, exportUrl }
});

const groups = computed(() => props.paginator?.data || []);

// Expanded by default, matching the original page's auto-expand-all behavior.
const collapsed = reactive({});
function isCollapsed(partnerId) {
    return !!collapsed[partnerId];
}
function toggleGroup(partnerId) {
    collapsed[partnerId] = !collapsed[partnerId];
}

function formatAmount(value) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
                ← Back to Partner Statement
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    Partners Statements
                    <span class="cvr-text-secondary font-normal">— {{ String(currency).toUpperCase() }}</span>
                </h1>
                <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                    ⬇️ Export to Excel
                </a>
            </div>
            <p class="text-sm cvr-text-muted mb-6">
                Just a note: partners without any transactions won't appear in this report.
            </p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">👥</div>
                    <div>
                        <p class="cvr-kpi-label">Partners</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ kpis.partnerCount }}</p>
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
                        <p class="cvr-kpi-label">Total Ending Balance</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.totalEndBalance) }}</p>
                    </div>
                </div>
            </div>

            <!-- Partner groups -->
            <div class="space-y-4">
                <div v-for="group in groups" :key="group.partnerId" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                    <button
                        @click="toggleGroup(group.partnerId)"
                        class="w-full flex items-center justify-between px-4 py-3 text-left"
                        style="background-color: var(--cvr-bg-surface);"
                    >
                        <span class="font-semibold cvr-text-primary">{{ group.partnerName }}</span>
                        <span class="cvr-text-muted text-xs">{{ isCollapsed(group.partnerId) ? '▸ Show' : '▾ Hide' }} ({{ group.rows.length }})</span>
                    </button>

                    <div v-show="!isCollapsed(group.partnerId)" class="overflow-auto" style="max-height: 150vh;">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head sticky top-0 z-10">
                                <tr>
                                    <th class="px-3 py-2.5 text-center">#</th>
                                    <th class="px-3 py-2.5 text-center">Date</th>
                                    <th class="px-3 py-2.5 text-right">Beginning Balance</th>
                                    <th class="px-3 py-2.5 text-right">Debit</th>
                                    <th class="px-3 py-2.5 text-right">Credit</th>
                                    <th class="px-3 py-2.5 text-right">End Balance</th>
                                    <th class="px-3 py-2.5 text-left min-w-[240px]">Comment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in group.rows" :key="row.id" class="cvr-table-row">
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ index + 1 }}</td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ row.date }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ formatAmount(row.beginningBalance) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ formatAmount(row.debit) }}</td>
                                    <td class="px-3 py-2 text-right cvr-num">{{ formatAmount(row.credit) }}</td>
                                    <td class="px-3 py-2 text-right font-medium" :style="{ color: endBalanceColorVar(row.endBalance) }">{{ formatAmount(row.endBalance) }}</td>
                                    <td class="px-3 py-2 text-left cvr-text-secondary">
                                        <span class="block max-w-[320px] whitespace-normal">{{ row.comment }}</span>
                                        <span v-if="row.userComment" class="block max-w-[320px] whitespace-normal cvr-text-muted text-xs mt-0.5">{{ row.userComment }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="groups.length === 0" class="cvr-card-bg cvr-border border rounded-lg px-4 py-8 text-center cvr-text-muted">
                    No movements found for this date range.
                </div>
            </div>

            <!-- Pagination (by partner group) -->
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
