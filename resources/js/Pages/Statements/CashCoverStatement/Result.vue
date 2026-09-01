<script setup>
/**
 * Cash Cover Statement — the rows.
 *
 * Same shape as the LG & LC Bank Statement result: range-wide KPIs above,
 * the running ledger below. Beginning and ending balances come from the
 * oldest and newest rows in the range, not from summing the page, so
 * paging never changes them.
 */
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    company: Object,
    instrumentLabel: String,
    bankName: String,
    currency: String,
    startDate: String,
    endDate: String,
    kpis: Object,
    paginator: Object,
    urls: Object,
});

function money(value) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="urls.backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                <span aria-hidden="true">{{ $i18n.locale === 'ar' ? '→' : '←' }}</span> {{ $t('Back') }}
            </Link>

            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Cash Cover Statement') }}</h1>
            <p class="text-sm cvr-text-muted mb-4">
                {{ instrumentLabel }} — {{ bankName }} — {{ currency }} — {{ startDate }} → {{ endDate }}
            </p>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
                <div class="cvr-card rounded-lg p-3">
                    <p class="text-xs cvr-text-muted">{{ $t('Beginning Balance') }}</p>
                    <p class="text-lg font-semibold cvr-text-primary">{{ money(kpis.beginningBalance) }}</p>
                </div>
                <div class="cvr-card rounded-lg p-3">
                    <p class="text-xs cvr-text-muted">{{ $t('Total Debit') }}</p>
                    <p class="text-lg font-semibold cvr-num-amber">{{ money(kpis.totalDebit) }}</p>
                </div>
                <div class="cvr-card rounded-lg p-3">
                    <p class="text-xs cvr-text-muted">{{ $t('Total Credit') }}</p>
                    <p class="text-lg font-semibold cvr-num-green">{{ money(kpis.totalCredit) }}</p>
                </div>
                <div class="cvr-card rounded-lg p-3">
                    <p class="text-xs cvr-text-muted">{{ $t('Ending Balance') }}</p>
                    <p class="text-lg font-semibold cvr-text-primary">{{ money(kpis.endingBalance) }}</p>
                </div>
                <div class="cvr-card rounded-lg p-3">
                    <p class="text-xs cvr-text-muted">{{ $t('Transactions') }}</p>
                    <p class="text-lg font-semibold cvr-text-primary">{{ kpis.transactionCount }}</p>
                </div>
            </div>

            <div class="cvr-card rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ $t('Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Type') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Source') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Movement') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Debit') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Credit') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('End Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!paginator.data.length">
                            <td colspan="7" class="px-4 py-6 text-center cvr-text-muted">{{ $t('No movements found for this date range.') }}</td>
                        </tr>
                        <tr v-for="(row, i) in paginator.data" :key="i" class="cvr-table-row border-t" style="border-color: var(--cvr-border);">
                            <td class="px-4 py-3 whitespace-nowrap">{{ row.date }}</td>
                            <td class="px-4 py-3">{{ row.type }}</td>
                            <td class="px-4 py-3">{{ row.source }}</td>
                            <td class="px-4 py-3">{{ row.movement }}</td>
                            <td class="px-4 py-3 text-right cvr-num-amber">{{ money(row.debit) }}</td>
                            <td class="px-4 py-3 text-right cvr-num-green">{{ money(row.credit) }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ money(row.end_balance) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="paginator.last_page > 1" class="flex items-center gap-2 mt-3 text-sm">
                <button @click="goToPage(paginator.prev_page_url)" :disabled="!paginator.prev_page_url" class="cvr-btn-secondary px-3 py-1 rounded border">{{ $t('‹ Prev') }}</button>
                <span class="cvr-text-muted">{{ $t('Page') }} {{ paginator.current_page }} / {{ paginator.last_page }}</span>
                <button @click="goToPage(paginator.next_page_url)" :disabled="!paginator.next_page_url" class="cvr-btn-secondary px-3 py-1 rounded border">{{ $t('Next ›') }}</button>
            </div>
        </div>
    </AppLayout>
</template>
