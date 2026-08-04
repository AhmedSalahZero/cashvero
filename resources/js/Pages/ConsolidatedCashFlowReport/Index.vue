<script setup>
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    company: Object,
    mainFunctionalCurrency: String,
    currencies: Array,
    activeContracts: Array, // [{id, name, code, currency}]
    urls: Object,
});

const reportInterval = ref('weekly');
const startDate = ref(new Date().toISOString().slice(0, 10));
const endDate = ref((() => { const d = new Date(); d.setMonth(d.getMonth() + 6); return d.toISOString().slice(0, 10); })());
function normalizeCurrency(value) {
    return String(value || '').trim().toUpperCase();
}

// Options are keyed by currency code; fall back to the first one so the select
// is never blank when the company's main currency isn't in the list.
const defaultCurrency = (props.currencies || []).find(
    c => normalizeCurrency(c.code) === normalizeCurrency(props.mainFunctionalCurrency)
) || (props.currencies || [])[0];
const currency = ref(defaultCurrency ? defaultCurrency.code : '');
const contractIds = ref([]);

// The contracts list must follow the selected currency: the report only ever
// consolidates contracts of one currency (see ConsolidatedCashFlowService).
const contractOptions = computed(() => props.activeContracts
    .filter(c => normalizeCurrency(c.currency) === normalizeCurrency(currency.value))
    .map(c => ({
        value: c.id,
        label: c.code ? `${c.name} [${c.code}]` : c.name,
    })));

watch(currency, () => {
    contractIds.value = [];
});

function submit() {
    router.get(props.urls.result, {
        report_interval: reportInterval.value,
        start_date: startDate.value,
        end_date: endDate.value,
        currency: currency.value,
        contract_ids: contractIds.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 mx-auto">
            <h1 class="text-xl font-semibold cvr-text-primary mb-2">Consolidated Cash Flow Report</h1>
            <p class="text-sm cvr-text-muted mb-1">Note: the report period must include today (same rule as the main cash flow report).</p>
            <p class="text-sm cvr-text-muted mb-6">Tip: leave contracts empty to include all active contracts, or select the ones you need. Monthly interval is faster than daily for long periods.</p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Report Interval *</label>
                            <select v-model="reportInterval" required class="cvr-input w-full px-3 py-2 rounded">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Start Date *</label>
                            <input v-model="startDate" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">End Date *</label>
                            <input v-model="endDate" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Currency</label>
                            <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="cvr-form-label">Contracts (leave empty for all active contracts)</label>
                        <MultiSelectDropdown v-model="contractIds" :options="contractOptions" placeholder="All active contracts" />
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="cvr-btn-primary px-4 py-2 rounded">Run Report</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
