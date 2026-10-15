<script setup>
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    company: Object,
    mainFunctionalCurrency: String,
    currencies: Array,
    activeContracts: Array, // [{id, name, code, currency, end_date}] — Customer contracts only
    urls: Object,
});

const reportInterval = ref('weekly');
const startDate = ref(new Date().toISOString().slice(0, 10));
const endDate = ref((() => { const d = new Date(); d.setMonth(d.getMonth() + 6); return d.toISOString().slice(0, 10); })());
function normalizeCurrency(value) {
    return String(value || '').trim().toUpperCase();
}

// Options are keyed by currency code; pre-select the company's main currency
// (matching the old single-select default) but the user can add more or
// clear it entirely — an empty selection means "all currencies", same as
// how leaving Contracts empty already means "all active contracts".
const defaultCurrency = (props.currencies || []).find(
    c => normalizeCurrency(c.code) === normalizeCurrency(props.mainFunctionalCurrency)
) || (props.currencies || [])[0];
const currencies = ref(defaultCurrency ? [defaultCurrency.code] : []);
const currencyOptions = computed(() => (props.currencies || []).map(c => ({ value: c.code, label: c.label })));

const minEndYear = ref('');
// Every distinct year a Customer contract's end date falls in, so the
// dropdown only ever offers years that actually apply to something.
const endYearOptions = computed(() => {
    const years = new Set();
    (props.activeContracts || []).forEach(c => {
        if (c.end_date) years.add(new Date(c.end_date).getFullYear());
    });
    return Array.from(years).sort((a, b) => a - b);
});

const contractIds = ref([]);

// The contracts list follows both filters above: currency (any of the
// selected ones, or all if none picked) and end date (contract must end
// in minEndYear or later, if set) — mirrored server-side in
// ConsolidatedCashFlowService::resolveContractIds() so "leave Contracts
// empty" picks up the same restricted set the dropdown shows.
const contractOptions = computed(() => props.activeContracts
    .filter(c => currencies.value.length === 0 || currencies.value.some(sel => normalizeCurrency(sel) === normalizeCurrency(c.currency)))
    .filter(c => {
        if (!minEndYear.value) return true;
        if (!c.end_date) return false;
        return new Date(c.end_date).getFullYear() >= Number(minEndYear.value);
    })
    .map(c => ({
        value: c.id,
        label: c.code ? `${c.name} [${c.code}]` : c.name,
    })));

watch([currencies, minEndYear], () => {
    contractIds.value = [];
});

// Fixed 4-tier past-due collection/payment plan, one set for Customer past-due
// invoices and a separate one for Supplier past-due invoices — e.g. "30% within
// 30 days, 30% within 45 days, 20% within 60 days, 20% within 90 days". Every
// row is optional; an empty/zero row is simply skipped server-side, so leaving
// everything blank keeps today's exact behavior (past-due invoices don't
// affect any weekly figure at all — see the report's own explanation).
function emptyTiers() {
    return [
        { percentage: '', days: '' },
        { percentage: '', days: '' },
        { percentage: '', days: '' },
        { percentage: '', days: '' },
    ];
}
const customerPastDueTiers = ref(emptyTiers());
const supplierPastDueTiers = ref(emptyTiers());

function submit() {
    // ⚠️ Bug fix: sending an array of {percentage, days} objects over a GET
    // request doesn't survive query-string serialization reliably — each
    // object's keys were landing in separate, misaligned array slots
    // instead of staying together (confirmed: [{percentage:100},{days:120}]
    // instead of [{percentage:100,days:120}]), so the backend always saw
    // empty tiers no matter what was filled in. Two flat, parallel arrays
    // (matched by index) avoid the issue entirely — same reliable pattern
    // contract_ids/currencies already use successfully.
    const filledTiers = tiers => tiers.filter(t => Number(t.percentage) > 0 && Number(t.days) > 0);
    const customerFilled = filledTiers(customerPastDueTiers.value);
    const supplierFilled = filledTiers(supplierPastDueTiers.value);

    router.get(props.urls.result, {
        report_interval: reportInterval.value,
        start_date: startDate.value,
        end_date: endDate.value,
        currencies: currencies.value,
        min_end_year: minEndYear.value || null,
        contract_ids: contractIds.value,
        customer_past_due_percentages: customerFilled.map(t => t.percentage),
        customer_past_due_days: customerFilled.map(t => t.days),
        supplier_past_due_percentages: supplierFilled.map(t => t.percentage),
        supplier_past_due_days: supplierFilled.map(t => t.days),
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 mx-auto">
            <h1 class="text-xl font-semibold cvr-text-primary mb-2">{{ $t('Consolidated Cash Flow Report') }}</h1>
            <p class="text-sm cvr-text-muted mb-1">{{ $t('Note: the report period must include today (same rule as the main cash flow report).') }}</p>
            <p class="text-sm cvr-text-muted mb-6">{{ $t('Tip: leave currencies or contracts empty to include everything available, or narrow them down. Monthly interval is faster than daily for long periods.') }}</p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Report Interval') }} *</label>
                            <select v-model="reportInterval" required class="cvr-input w-full px-3 py-2 rounded">
                                <option value="daily">{{ $t('Daily') }}</option>
                                <option value="weekly">{{ $t('Weekly') }}</option>
                                <option value="monthly">{{ $t('Monthly') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Start Date') }} *</label>
                            <input v-model="startDate" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('End Date') }} *</label>
                            <input v-model="endDate" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Currencies (leave empty for all)') }}</label>
                            <MultiSelectDropdown v-model="currencies" :options="currencyOptions" :placeholder="$t('All currencies')" />
                        </div>
                    </div>

                    <div>
                        <label class="cvr-form-label">{{ $t('For Years Equal or Greater Than') }}</label>
                        <select v-model="minEndYear" class="cvr-input w-full px-3 py-2 rounded md:w-64">
                            <option value="">{{ $t('Any end date') }}</option>
                            <option v-for="y in endYearOptions" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="cvr-form-label">{{ $t('Contracts (leave empty for all active contracts)') }}</label>
                        <MultiSelectDropdown v-model="contractIds" :options="contractOptions" :placeholder="$t('All active contracts')" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Customer Past Due Collection Plan (optional)') }}</label>
                            <p class="text-xs cvr-text-muted mb-2">{{ $t('E.g. 30% within 30 days, 30% within 45 days, 20% within 60 days, 20% within 90 days.') }}</p>
                            <div class="space-y-2">
                                <div v-for="(tier, i) in customerPastDueTiers" :key="'cust-'+i" class="flex items-center gap-2">
                                    <input v-model.number="tier.percentage" type="number" min="0" max="100" step="1" placeholder="%" class="cvr-input w-24 px-3 py-2 rounded" />
                                    <span class="text-sm cvr-text-muted">{{ $t('% within') }}</span>
                                    <input v-model.number="tier.days" type="number" min="1" step="1" :placeholder="$t('Days')" class="cvr-input w-28 px-3 py-2 rounded" />
                                    <span class="text-sm cvr-text-muted">{{ $t('days') }}</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Supplier Past Due Payment Plan (optional)') }}</label>
                            <p class="text-xs cvr-text-muted mb-2">{{ $t('E.g. 30% within 30 days, 30% within 45 days, 20% within 60 days, 20% within 90 days.') }}</p>
                            <div class="space-y-2">
                                <div v-for="(tier, i) in supplierPastDueTiers" :key="'supp-'+i" class="flex items-center gap-2">
                                    <input v-model.number="tier.percentage" type="number" min="0" max="100" step="1" placeholder="%" class="cvr-input w-24 px-3 py-2 rounded" />
                                    <span class="text-sm cvr-text-muted">{{ $t('% within') }}</span>
                                    <input v-model.number="tier.days" type="number" min="1" step="1" :placeholder="$t('Days')" class="cvr-input w-28 px-3 py-2 rounded" />
                                    <span class="text-sm cvr-text-muted">{{ $t('days') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="cvr-btn-primary px-4 py-2 rounded">{{ $t('Run Report') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>


