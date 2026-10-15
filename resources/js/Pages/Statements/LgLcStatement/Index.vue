<script setup>
/**
 * Statements/LgLcStatement/Index.vue
 * ------------------------------------------------------------------
 * Served by LGLCSBanktatementController@index. Filter form: date
 * range, Currency, Bank, Report Type (cascades Type/Source dropdowns
 * via the existing getLgOrLcType ajax endpoint), and — only when
 * Report Type is "Letter Of Credit Overdraft Bank Statement" — an LC
 * Facility (cascades from Bank via the existing, shared
 * getLcFacilityBasedOnFinancialInstitution endpoint).
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { todayDate, clampDateToToday } from '@/composables/today';

const maxDate = todayDate();

const props = defineProps({
    company: Object,
    financialInstitutionBanks: Array, // [{ id, name }]
    currencies: Object,
    reportTypes: Object, // { LetterOfCreditIssuance: label, LetterOfGuaranteeIssuance: label, LCOverdraft: label }
    urls: Object, // { result, lgOrLcTypes, lcFacilitiesByBank }
});

const startDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().slice(0, 10));
const endDate = ref(todayDate());
const currency = ref('');
const financialInstitutionId = ref('');
const reportType = ref('');
const source = ref('');
const type = ref('');
const lcFacilityId = ref('');

watch(endDate, (value) => {
    const clamped = clampDateToToday(value);
    if (clamped !== value) {
        endDate.value = clamped;
    }
});

const isLcOverdraft = computed(() => reportType.value === 'LCOverdraft');

const typeOptions = ref({});
const sourceOptions = ref({});
async function loadTypesAndSources() {
    source.value = '';
    type.value = '';
    typeOptions.value = {};
    sourceOptions.value = {};
    if (!reportType.value) return;
    const { data } = await window.axios.get(props.urls.lgOrLcTypes, {
        params: { lcOrLg: reportType.value },
    });
    typeOptions.value = data.types || {};
    sourceOptions.value = data.sources || {};
}
watch(reportType, loadTypesAndSources);

const lcFacilityOptions = ref({});
const loadingFacilities = ref(false);
async function loadLcFacilities() {
    lcFacilityId.value = '';
    lcFacilityOptions.value = {};
    if (!financialInstitutionId.value || !isLcOverdraft.value) return;
    loadingFacilities.value = true;
    try {
        const { data } = await window.axios.get(props.urls.lcFacilitiesByBank, {
            params: { financialInstitutionId: financialInstitutionId.value },
        });
        lcFacilityOptions.value = data.letterOfCreditFacilities || {};
    } finally {
        loadingFacilities.value = false;
    }
}
watch([financialInstitutionId, isLcOverdraft], loadLcFacilities);

const needsType = computed(() => Object.keys(typeOptions.value).length > 0);
const needsSource = computed(() => Object.keys(sourceOptions.value).length > 0);

const canSubmit = computed(() => {
    if (!startDate.value || !endDate.value || endDate.value > maxDate || !currency.value || !financialInstitutionId.value || !reportType.value) return false;
    if (needsType.value && !type.value) return false;
    if (needsSource.value && !source.value) return false;
    if (isLcOverdraft.value && !lcFacilityId.value) return false;
    return true;
});

function submit() {
    if (!canSubmit.value) return;
    endDate.value = clampDateToToday(endDate.value);
    router.get(props.urls.result, {
        start_date: startDate.value,
        end_date: endDate.value,
        currency: currency.value,
        financial_institution_id: financialInstitutionId.value,
        report_type: reportType.value,
        source: source.value,
        type: type.value,
        lc_facility_id: lcFacilityId.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('LG & LC Statement') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ $t('A running-balance ledger for a Letter of Credit, Letter of Guarantee, or LC Overdraft, for a chosen date range.') }}
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-3 mb-5">
                    <div>
                        <label class="cvr-form-label">{{ $t('Start Date') }} *</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('End Date') }} *</label>
                        <input v-model="endDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                        <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select currency') }}</option>
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ String(label).toUpperCase() }}</option>
                        </select>
                    </div>
                </div>

                <div class="cvr-form-grid-2 mb-5">
                    <div>
                        <label class="cvr-form-label">{{ $t('Bank') }} *</label>
                        <select v-model="financialInstitutionId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select bank') }}</option>
                            <option v-for="bank in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Report Type') }} *</label>
                        <select v-model="reportType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select report type') }}</option>
                            <option v-for="(label, key) in reportTypes" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>

                <div class="cvr-form-grid-3 mb-5">
                    <div v-if="needsSource">
                        <label class="cvr-form-label">{{ $t('Source') }} *</label>
                        <select v-model="source" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select') }}</option>
                            <option v-for="(label, key) in sourceOptions" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div v-if="needsType">
                        <label class="cvr-form-label">{{ $t('Type') }} *</label>
                        <select v-model="type" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select') }}</option>
                            <option v-for="(label, key) in typeOptions" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div v-if="isLcOverdraft">
                        <label class="cvr-form-label">{{ $t('LC Facility') }} *</label>
                        <select v-model="lcFacilityId" class="cvr-input w-full px-3 py-2 rounded" :disabled="loadingFacilities || Object.keys(lcFacilityOptions).length === 0">
                            <option value="" disabled>{{ loadingFacilities ? $t('Loading…') : (Object.keys(lcFacilityOptions).length ? $t('Select') : $t('Select a bank first')) }}</option>
                            <option v-for="(label, key) in lcFacilityOptions" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>

                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit }"
                >
                    {{ $t('View Statement') }}
                </button>
                <ul v-if="!canSubmit" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-if="!startDate">{{ $t('— Start Date is not set.') }}</li>
                    <li v-if="!endDate">{{ $t('— End Date is not set.') }}</li>
                    <li v-if="!currency">{{ $t('— Currency is not selected.') }}</li>
                    <li v-if="!financialInstitutionId">{{ $t('— Bank is not selected.') }}</li>
                    <li v-if="!reportType">{{ $t('— Report Type is not selected.') }}</li>
                    <li v-if="needsSource && !source">{{ $t('— Source is not selected.') }}</li>
                    <li v-if="needsType && !type">{{ $t('— Type is not selected.') }}</li>
                    <li v-if="isLcOverdraft && !lcFacilityId">{{ $t('— LC Facility is not selected.') }}</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
