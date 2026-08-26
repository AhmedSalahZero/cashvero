<script setup>
/**
 * Statements/LgByBankName/Result.vue
 * ------------------------------------------------------------------
 * Served by LgByBankNameReportController@result. A flat list of
 * Letters of Guarantee for the selected banks/currency/status,
 * renewing on or after the chosen date.
 */
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    currency: String,
    status: String,
    startDate: String,
    kpis: Object,
    paginator: Object,
    urls: Object,
});

const rows = computed(() => props.paginator?.data || []);

const statusLabel = computed(() => ({
    running: 'Running',
    expired: 'Expired',
    cancelled: 'Cancelled',
    all: 'All',
}[props.status] || props.status));

// Only Cancelled/All actually carry a meaningful date — Running/Expired
// are fully defined by status alone (see LgByBankNameReportController).
const subtitle = computed(() => {
    if (props.status === 'cancelled') return `Cancelled from ${props.startDate}`;
    if (props.status === 'all') return `All statuses · cancelled from ${props.startDate}`;
    return statusLabel.value;
});

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Status column only makes sense when 'All' is picked — for every other
// filter every row already shares the same status, so the column would
// just repeat itself.
const showStatusColumn = computed(() => props.status === 'all');
const rowStatusLabel = (row) => ({
    running: 'Running',
    expired: 'Expired',
    cancelled: 'Cancelled',
}[row.status] || row.status);
const rowStatusClass = (row) => ({
    running: 'cvr-num-green font-medium',
    expired: 'cvr-num font-medium',
    cancelled: 'cvr-num-red font-medium',
}[row.status] || 'cvr-text-secondary');

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="urls.backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('← Back to LG By Bank Name') }}
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    {{ $t('LG Report By Bank Name') }}
                    <span class="cvr-text-secondary font-normal">— {{ subtitle }} · {{ String(currency).toUpperCase() }}</span>
                </h1>
                <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                    {{ $t('⬇️ Export to Excel') }}
                </a>
            </div>
            <p class="text-sm cvr-text-muted mb-6">{{ kpis.transactionCount }} {{ $t('Letters of Guarantee.') }}</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">📜</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Amount') }}</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.totalLgAmount) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">🛡️</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Cash Cover') }}</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ formatAmount(kpis.totalCashCoverAmount) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">📋</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Letters of Guarantee') }}</p>
                        <p class="cvr-kpi-value cvr-num">{{ kpis.transactionCount }}</p>
                    </div>
                </div>
            </div>

            <!-- Heavy list table — sticky header, horizontal + vertical scroll -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <div class="overflow-auto cvr-table-scroll" style="max-height: 150vh;">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3 text-start">{{ $t('Bank Name') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Beneficiary Name') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('LG Type') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Transaction Name') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('LG Code') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Source') }}</th>
                                <th v-if="showStatusColumn" class="px-3 py-3 text-center">{{ $t('Status') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Amount') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Renewal Date') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Cash Cover') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Commission Rate %') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">
                                    {{ (paginator.current_page - 1) * 50 + index + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-start cvr-text-primary">{{ row.financialInstitutionName }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.partnerName }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.lgType }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.transactionName }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.lgCode }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.source }}</td>
                                <td v-if="showStatusColumn" class="px-3 py-2.5 text-center" :class="rowStatusClass(row)">{{ rowStatusLabel(row) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.lgAmount) }}</td>
                                <td class="px-3 py-2.5 text-center" :class="row.renewalDate === 'cancelled' ? 'cvr-num-red font-medium' : 'cvr-text-secondary'">{{ row.renewalDate }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.cashCoverAmount) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ row.lgCommissionRate }}</td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td :colspan="showStatusColumn ? 12 : 11" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No Letters of Guarantee found for these filters.') }}</td>
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
