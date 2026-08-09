<script setup>
/**
 * Statements/FactoringStatement/Index.vue
 * ------------------------------------------------------------------
 * Served by FactoringStatementController@index. Filter form: date
 * range, then Factoring Company → Currency → Factoring Contract, each
 * cascading from the previous pick.
 *
 * Has a sibling report, Factoring Charges Statement — shown as a tab
 * here (matching the original's <x-factoring-statement-tabs>), still
 * a plain link since that page isn't migrated yet.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { todayDate, clampDateToToday } from '@/composables/today';

const maxDate = todayDate();

const props = defineProps({
    company: Object,
    factoringCompanies: Array, // [{ id, name }]
    urls: Object, // { result, currencies, contracts, chargesStatementUrl }
});

const startDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().slice(0, 10));
const endDate = ref(todayDate());
const factoringCompanyId = ref('');
const currency = ref('');
const factoringContractId = ref('');

watch(endDate, (value) => {
    const clamped = clampDateToToday(value);
    if (clamped !== value) {
        endDate.value = clamped;
    }
});

const currencyOptions = ref({});
const loadingCurrencies = ref(false);
async function loadCurrencies() {
    currency.value = '';
    currencyOptions.value = {};
    factoringContractId.value = '';
    contractOptions.value = [];
    if (!factoringCompanyId.value) return;
    loadingCurrencies.value = true;
    try {
        const { data } = await window.axios.get(props.urls.currencies, {
            params: { factoring_company_id: factoringCompanyId.value },
        });
        currencyOptions.value = data.currencies || {};
    } finally {
        loadingCurrencies.value = false;
    }
}
watch(factoringCompanyId, loadCurrencies);

const contractOptions = ref([]);
const loadingContracts = ref(false);
async function loadContracts() {
    factoringContractId.value = '';
    contractOptions.value = [];
    if (!factoringCompanyId.value || !currency.value) return;
    loadingContracts.value = true;
    try {
        const { data } = await window.axios.get(props.urls.contracts, {
            params: {
                factoring_company_id: factoringCompanyId.value,
                currency: currency.value,
                start_date: startDate.value,
                end_date: endDate.value,
            },
        });
        contractOptions.value = data.contracts || [];
    } finally {
        loadingContracts.value = false;
    }
}
watch(currency, loadContracts);

const canSubmit = computed(() =>
    startDate.value && endDate.value && endDate.value <= maxDate && factoringCompanyId.value && currency.value && factoringContractId.value
);

function submit() {
    if (!canSubmit.value) return;
    endDate.value = clampDateToToday(endDate.value);
    router.get(props.urls.result, {
        start_date: startDate.value,
        end_date: endDate.value,
        factoring_company_id: factoringCompanyId.value,
        factoring_contract_id: factoringContractId.value,
        currency: currency.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Factoring Statement</h1>
            <p class="text-sm cvr-text-muted mb-4">
                A running-balance ledger for one factoring contract, for a chosen date range.
            </p>

            <!-- Tabs (Charges Statement isn't migrated yet — plain link) -->
            <div class="flex items-center gap-1 mb-5 border-b cvr-border">
                <span class="px-4 py-2 text-sm font-medium border-b-2" style="border-color: var(--cvr-green-bright); color: var(--cvr-green-bright);">
                    📄 Factoring Statement
                </span>
                <a :href="urls.chargesStatementUrl" class="px-4 py-2 text-sm font-medium cvr-text-muted hover:cvr-text-primary">
                    🧾 Factoring Charges Statement
                </a>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-2 mb-5">
                    <div>
                        <label class="cvr-form-label">Start Date *</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">End Date *</label>
                        <input v-model="endDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>

                <div class="cvr-form-grid-3">
                    <div>
                        <label class="cvr-form-label">Factoring Company *</label>
                        <select v-model="factoringCompanyId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>Select</option>
                            <option v-for="fc in factoringCompanies" :key="fc.id" :value="fc.id">{{ fc.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Currency *</label>
                        <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded" :disabled="loadingCurrencies || Object.keys(currencyOptions).length === 0">
                            <option value="" disabled>{{ loadingCurrencies ? 'Loading…' : (Object.keys(currencyOptions).length ? 'Select' : 'Select a factoring company first') }}</option>
                            <option v-for="(label, code) in currencyOptions" :key="code" :value="code">{{ String(label).toUpperCase() }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Factoring Contract *</label>
                        <select v-model="factoringContractId" class="cvr-input w-full px-3 py-2 rounded" :disabled="loadingContracts || contractOptions.length === 0">
                            <option value="" disabled>{{ loadingContracts ? 'Loading…' : (contractOptions.length ? 'Select' : 'Select a currency first') }}</option>
                            <option v-for="c in contractOptions" :key="c.id" :value="c.id">{{ c.label }}</option>
                        </select>
                    </div>
                </div>

                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-5"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit }"
                >
                    View Statement
                </button>
                <ul v-if="!canSubmit" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-if="!startDate">— Start Date is not set.</li>
                    <li v-if="!endDate">— End Date is not set.</li>
                    <li v-if="!factoringCompanyId">— Factoring Company is not selected.</li>
                    <li v-if="!currency">— Currency is not selected.</li>
                    <li v-if="!factoringContractId">— Factoring Contract is not selected.</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
