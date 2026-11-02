<script setup>
/**
 * Statements/ExpiredLgs/Result.vue
 * ------------------------------------------------------------------
 * Served by ExpiredLgsReportController@result. Rows arrive grouped per
 * currency, each group with its own totals. More than one currency
 * shows one tab per currency; amounts in different currencies are never
 * added together.
 */
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    today: String,
    groups: Object, // { [currency]: { rows, totalLgAmount, totalCashCover, count } }
    urls: Object,   // { backUrl, exportUrl, printUrl }
});

const currencies = computed(() => Object.keys(props.groups));
const showTabs = computed(() => currencies.value.length > 1);
const activeCurrency = ref(currencies.value[0]);
const activeGroup = computed(() => props.groups[activeCurrency.value]);

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="urls.backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('← Back to Expired LGs') }}
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mt-3 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    {{ $t('Expired LGs') }}
                    <span class="cvr-text-secondary font-normal">— {{ $t('As of') }} {{ today }}</span>
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
            <p class="text-sm cvr-text-muted mb-6">{{ $t('Export and Print cover every selected currency.') }}</p>

            <!-- One tab per currency -->
            <div v-if="showTabs" class="flex flex-wrap gap-1 border-b cvr-border mb-4">
                <button
                    v-for="currency in currencies"
                    :key="currency"
                    @click="activeCurrency = currency"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    :class="activeCurrency === currency ? 'border-current cvr-text-primary' : 'border-transparent cvr-text-muted'"
                >
                    {{ String(currency).toUpperCase() }} <span class="cvr-text-muted">({{ groups[currency].count }})</span>
                </button>
            </div>

            <!-- KPI row for the shown currency -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">📜</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Amount') }} · {{ String(activeCurrency).toUpperCase() }}</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(activeGroup.totalLgAmount) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">🛡️</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Cash Cover') }} · {{ String(activeCurrency).toUpperCase() }}</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ formatAmount(activeGroup.totalCashCover) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">📋</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Letters of Guarantee') }}</p>
                        <p class="cvr-kpi-value cvr-num">{{ activeGroup.count }}</p>
                    </div>
                </div>
            </div>

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
                                <th class="px-3 py-3 text-right">{{ $t('Amount') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Expiry Date') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Expired Since (Days)') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Cash Cover') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in activeGroup.rows" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ index + 1 }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-primary">{{ row.financialInstitutionName }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.partnerName }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.lgType }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.transactionName }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">
                                    {{ row.lgCode }}
                                    <span v-if="!row.isActivated" class="cvr-badge cvr-badge-pending ms-1">{{ $t('Unactivated') }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.source }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.lgAmount) }}</td>
                                <td class="px-3 py-2.5 text-center cvr-num-red font-medium">{{ row.renewalDate }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.expiredSinceDays }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.cashCoverAmount) }}</td>
                            </tr>
                            <tr class="cvr-table-row font-medium">
                                <td class="px-3 py-2.5 text-center">-</td>
                                <td class="px-3 py-2.5 text-start" colspan="6">{{ $t('Total') }} · {{ String(activeCurrency).toUpperCase() }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(activeGroup.totalLgAmount) }}</td>
                                <td class="px-3 py-2.5 text-center" colspan="2">-</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(activeGroup.totalCashCover) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
