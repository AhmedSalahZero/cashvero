<script setup>
/**
 * Statements/BankChargesAnalysis/Result.vue
 * ------------------------------------------------------------------
 * Served by BankChargesAnalysisReportController@result.
 *
 * Two tables:
 *   - Summary: every bank with its facilities under it — interest,
 *     commission, charges and total, plus each one's share of the grand
 *     total. Always the whole range.
 *   - Details: every line the summary was built from, paginated
 *     server-side (50/page).
 *
 * All summary figures are in `displayCurrency`: the one selected
 * currency, or the main functional currency when the amounts were
 * converted (`isConverted`). Converted detail lines also show their
 * original amount and the rate used.
 */
import { computed, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    startDate: String,
    endDate: String,
    filterSummary: Object, // { banks, facilityTypes, currencies }
    displayCurrency: String,
    isConverted: Boolean,
    mainCurrency: String,
    missingRates: Array,
    summary: Object, // { banks: [{ bank_id, bank_name, interest, commission, charges, total, facilities: [...] }], grand }
    kpis: Object,
    paginator: Object,
    perPage: Number,
    urls: Object, // { backUrl, exportUrl, printUrl }
});

const rows = computed(() => props.paginator?.data || []);
const grand = computed(() => props.summary?.grand || {});

/* Banks start expanded; a click on the bank row folds its facilities away. */
const collapsed = ref({});
function toggleBank(bankId) {
    collapsed.value = { ...collapsed.value, [bankId]: !collapsed.value[bankId] };
}

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatRate(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}

function share(value) {
    const total = Number(grand.value.total || 0);
    return total ? ((Number(value || 0) / total) * 100).toFixed(1) : '0.0';
}

function categoryClass(category) {
    return {
        interest: 'cvr-num-blue',
        commission: 'cvr-num-amber',
        charges: 'cvr-num',
    }[category] || 'cvr-num';
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
                {{ $t('← Back to Bank Interest, Commissions & Charges Analysis') }}
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    {{ $t('Bank Interest, Commissions & Charges Analysis') }}
                    <span class="cvr-text-secondary font-normal">— {{ startDate }} — {{ endDate }}</span>
                </h1>
                <!-- One group, so justify-between does not pull the two apart. -->
                <div class="flex items-center gap-2">
                    <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        {{ $t('⬇️ Export to Excel') }}
                    </a>
                    <!-- Print covers the whole range, like the export. -->
                    <a v-if="urls.printUrl" :href="urls.printUrl" target="_blank" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        {{ $t('🖨️ Print') }}
                    </a>
                </div>
            </div>
            <p class="text-sm cvr-text-muted mb-4">
                {{ $t('Banks') }}: {{ filterSummary.banks }} · {{ $t('Facility Types') }}: {{ filterSummary.facilityTypes }} · {{ $t('Currencies') }}: {{ filterSummary.currencies }}
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg px-4 py-2.5 mb-4 text-sm cvr-text-secondary">
                <template v-if="isConverted">
                    {{ $t('Amounts are converted to the main currency at the exchange rate on each transaction date') }}:
                    <strong class="cvr-text-primary">{{ displayCurrency }}</strong>
                </template>
                <template v-else>
                    {{ $t('Amounts are shown in') }} <strong class="cvr-text-primary">{{ displayCurrency }}</strong>, {{ $t('without conversion') }}
                </template>
            </div>

            <div
                v-if="isConverted && missingRates.length"
                class="rounded-lg border px-4 py-2.5 mb-4 text-sm"
                style="border-color: var(--cvr-danger-text); color: var(--cvr-danger-text);"
            >
                ⚠️ {{ $t('No exchange rate found for') }} {{ missingRates.join(', ') }} → {{ mainCurrency }}.
                {{ $t('Those amounts were counted at a rate of 1. Add the missing rates under Foreign Exchange Rates for correct totals.') }}
            </div>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">%</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Interest') }} · {{ kpis.interestShare }}%</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.interest) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">🤝</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Commission') }} · {{ kpis.commissionShare }}%</p>
                        <p class="cvr-kpi-value cvr-num-amber">{{ formatAmount(kpis.commission) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">🧾</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Charges') }} · {{ kpis.chargesShare }}%</p>
                        <p class="cvr-kpi-value cvr-num">{{ formatAmount(kpis.charges) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">💰</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total') }} ({{ displayCurrency }}) · {{ kpis.bankCount }} {{ $t('banks') }} / {{ kpis.facilityCount }} {{ $t('facilities') }}</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ formatAmount(kpis.total) }}</p>
                    </div>
                </div>
            </div>

            <!-- Summary: bank → facility -->
            <h2 class="text-base font-semibold cvr-text-primary mb-2">{{ $t('Summary by Bank and Facility') }}</h2>
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden mb-8">
                <div class="overflow-auto cvr-table-scroll">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-start">{{ $t('Bank') }} / {{ $t('Facility') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Facility Type') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Interest') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Commission') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Charges') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Total') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Share %') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="bank in summary.banks" :key="bank.bank_id">
                                <tr class="cvr-table-row cursor-pointer font-semibold" @click="toggleBank(bank.bank_id)">
                                    <td class="px-3 py-2.5 text-start cvr-text-primary">
                                        <span class="inline-block w-4 cvr-text-muted">{{ collapsed[bank.bank_id] ? '▸' : '▾' }}</span>
                                        {{ bank.bank_name }}
                                        <span class="cvr-text-muted font-normal text-xs">({{ bank.facilities.length }})</span>
                                    </td>
                                    <td class="px-3 py-2.5"></td>
                                    <td class="px-3 py-2.5 text-right cvr-num-blue">{{ formatAmount(bank.interest) }}</td>
                                    <td class="px-3 py-2.5 text-right cvr-num-amber">{{ formatAmount(bank.commission) }}</td>
                                    <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(bank.charges) }}</td>
                                    <td class="px-3 py-2.5 text-right cvr-num-green">{{ formatAmount(bank.total) }}</td>
                                    <td class="px-3 py-2.5 text-right cvr-text-secondary">{{ share(bank.total) }}%</td>
                                </tr>
                                <template v-if="!collapsed[bank.bank_id]">
                                    <tr v-for="facility in bank.facilities" :key="facility.key" class="cvr-table-row">
                                        <td class="px-3 py-2 text-start cvr-text-secondary ps-10">
                                            {{ facility.facility_label }}
                                            <span v-if="isConverted && facility.currencies.length" class="cvr-text-muted text-xs">· {{ facility.currencies.join(', ') }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-start cvr-text-secondary">{{ facility.facility_type_label }}</td>
                                        <td class="px-3 py-2 text-right cvr-num-blue">{{ formatAmount(facility.interest) }}</td>
                                        <td class="px-3 py-2 text-right cvr-num-amber">{{ formatAmount(facility.commission) }}</td>
                                        <td class="px-3 py-2 text-right cvr-num">{{ formatAmount(facility.charges) }}</td>
                                        <td class="px-3 py-2 text-right cvr-text-primary">{{ formatAmount(facility.total) }}</td>
                                        <td class="px-3 py-2 text-right cvr-text-muted">{{ share(facility.total) }}%</td>
                                    </tr>
                                </template>
                            </template>
                        </tbody>
                        <tfoot class="cvr-table-head">
                            <tr class="font-semibold">
                                <td class="px-3 py-3 text-start">{{ $t('Grand Total') }} ({{ displayCurrency }})</td>
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3 text-right">{{ formatAmount(grand.interest) }}</td>
                                <td class="px-3 py-3 text-right">{{ formatAmount(grand.commission) }}</td>
                                <td class="px-3 py-3 text-right">{{ formatAmount(grand.charges) }}</td>
                                <td class="px-3 py-3 text-right">{{ formatAmount(grand.total) }}</td>
                                <td class="px-3 py-3 text-right">{{ grand.total ? '100.0%' : '' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Details -->
            <h2 class="text-base font-semibold cvr-text-primary mb-2">
                {{ $t('Details') }} <span class="cvr-text-muted font-normal text-sm">({{ kpis.lineCount }})</span>
            </h2>
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <div class="overflow-auto cvr-table-scroll" style="max-height: 150vh;">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3 text-center">{{ $t('Date') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Bank') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Facility') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Cost Type') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Item') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Currency') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Amount') }}</th>
                                <th v-if="isConverted" class="px-3 py-3 text-right">{{ $t('Exchange Rate') }}</th>
                                <th v-if="isConverted" class="px-3 py-3 text-right">{{ $t('Amount In Main Currency') }}</th>
                                <th class="px-3 py-3 text-start min-w-[280px]">{{ $t('Comment') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="index" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ (paginator.current_page - 1) * perPage + index + 1 }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary whitespace-nowrap">{{ row.date }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-primary">{{ row.bank_name }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">
                                    {{ row.facility_label }}
                                    <span class="block text-xs cvr-text-muted">{{ row.facility_type_label }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-start" :class="categoryClass(row.category)">{{ row.category_label }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-primary">{{ row.item }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.currency }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.amount) }}</td>
                                <td v-if="isConverted" class="px-3 py-2.5 text-right cvr-text-secondary">{{ formatRate(row.exchange_rate) }}</td>
                                <td v-if="isConverted" class="px-3 py-2.5 text-right cvr-num-green">{{ formatAmount(row.amount_main) }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">
                                    <span class="block max-w-[400px] whitespace-normal">{{ row.comment }}</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td :colspan="isConverted ? 11 : 9" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No Data Found') }}</td>
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
