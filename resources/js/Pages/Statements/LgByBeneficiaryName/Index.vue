<script setup>
/**
 * Statements/LgByBeneficiaryName/Index.vue
 * ------------------------------------------------------------------
 * Served by LgByBeneficiaryNameReportController@index. Filter form:
 * a renewal-date cutoff ("renewal date >= this"), Currency, then
 * Beneficiaries of that currency (cascading multi-select, reloaded via
 * LetterOfGuaranteeIssuanceController@getBeneficiaryNameByCurrency —
 * an existing, untouched, shared endpoint), and Status.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    company: Object,
    currencies: Object, // { code: code } — pre-filtered to currencies this company actually has LGs in
    selectedCurrency: String,
    urls: Object, // { result, beneficiariesByCurrency }
});

const startDate = ref(new Date().toISOString().slice(0, 10));
const currency = ref(props.selectedCurrency || '');
const status = ref('running');
const selectedBeneficiaryIds = ref([]);

const beneficiaryOptions = ref([]);
const loadingBeneficiaries = ref(false);
const loadError = ref('');

async function loadBeneficiaries() {
    selectedBeneficiaryIds.value = [];
    beneficiaryOptions.value = [];
    loadError.value = '';
    if (!currency.value) return;
    loadingBeneficiaries.value = true;
    try {
        const { data } = await window.axios.get(props.urls.beneficiariesByCurrency, {
            params: { currencyName: currency.value },
        });
        beneficiaryOptions.value = Object.entries(data.beneficiaries || {}).map(([value, label]) => ({ value: Number(value), label }));
    } catch (error) {
        loadError.value = 'Could not load beneficiaries for this currency. Please try again.';
        console.error('Failed to load beneficiaries by currency:', error);
    } finally {
        loadingBeneficiaries.value = false;
    }
}
watch(currency, loadBeneficiaries, { immediate: true });

const canSubmit = computed(() => startDate.value && currency.value && status.value && selectedBeneficiaryIds.value.length > 0);

function submit() {
    if (!canSubmit.value) return;
    router.get(props.urls.result, {
        start_date: startDate.value,
        currency_name: currency.value,
        status: status.value,
        beneficiary_id: selectedBeneficiaryIds.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">LG By Beneficiary Name</h1>
            <p class="text-sm cvr-text-muted mb-6">
                Letters of Guarantee for one or more beneficiaries, renewing on or after a chosen date.
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-3 mb-5">
                    <div>
                        <label class="cvr-form-label">Renewal Date (≥) *</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Currency *</label>
                        <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>Select currency</option>
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ String(label).toUpperCase() }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Status *</label>
                        <select v-model="status" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="running">Running</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                </div>

                <div class="max-w-xl">
                    <label class="cvr-form-label">
                        Beneficiary * <span class="cvr-text-muted font-normal">(pick one or more)</span>
                    </label>
                    <MultiSelectDropdown
                        v-model="selectedBeneficiaryIds"
                        :options="beneficiaryOptions"
                        :placeholder="!currency ? 'Select a currency first' : (loadingBeneficiaries ? 'Loading…' : 'Select beneficiaries')"
                    />
                    <p v-if="loadError" class="text-xs mt-1" style="color: var(--cvr-danger-text);">{{ loadError }}</p>
                    <p v-else-if="currency && !loadingBeneficiaries && beneficiaryOptions.length === 0" class="text-xs cvr-text-muted mt-1">
                        No beneficiaries with LGs in this currency yet.
                    </p>
                </div>

                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-5"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit }"
                >
                    View Report
                </button>
                <ul v-if="!canSubmit" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-if="!startDate">— Renewal Date is not set.</li>
                    <li v-if="!currency">— Currency is not selected.</li>
                    <li v-if="selectedBeneficiaryIds.length === 0">— No beneficiary is selected yet (open the dropdown and pick at least one, or Select All).</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
