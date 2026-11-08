<script setup>
/**
 * Statements/BankChargesAnalysis/Index.vue
 * ------------------------------------------------------------------
 * Served by BankChargesAnalysisReportController@index. Filter form for
 * the Bank Interest, Commissions & Charges Analysis: a date range (never
 * past today — overdraft statements carry month-end interest rows for
 * months that have not happened yet), and optional banks, facility types
 * and currencies. Leaving a multi-select empty means "all of them".
 *
 * One currency shows the report in that currency as stored; more than
 * one (or none) converts every amount to the main functional currency.
 */
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import { todayDate, clampDateToToday } from '@/composables/today';

const props = defineProps({
    company: Object,
    banks: Array, // [{ value, label }]
    facilityTypes: Array, // [{ value, label }]
    currencies: Array, // [{ value, label }]
    mainCurrency: String,
    urls: Object, // { result }
});

const maxDate = todayDate();
const startDate = ref(`${maxDate.slice(0, 4)}-01-01`);
const endDate = ref(maxDate);
const selectedBankIds = ref([]);
const selectedFacilityTypes = ref([]);
const selectedCurrencies = ref([]);

watch(endDate, (value) => {
    const clamped = clampDateToToday(value);
    if (clamped !== value) endDate.value = clamped;
});

// A filter the server refused (a bank of another company, a date past
// today typed by hand) comes back as a validation error on this page.
const serverErrors = computed(() => Object.values(usePage().props.errors || {}));

const isConverted = computed(() => selectedCurrencies.value.length !== 1);

const canSubmit = computed(() =>
    startDate.value && endDate.value && startDate.value <= endDate.value
);

function submit() {
    if (!canSubmit.value) return;
    router.get(props.urls.result, {
        start_date: startDate.value,
        end_date: clampDateToToday(endDate.value),
        financial_institution_ids: selectedBankIds.value,
        facility_types: selectedFacilityTypes.value,
        currencies: selectedCurrencies.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Bank Interest, Commissions & Charges Analysis') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ $t('What each bank cost the company over a period - interest, commissions and charges - by bank and facility.') }}
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-4 mb-5">
                    <div>
                        <label class="cvr-form-label">{{ $t('Start Date') }} *</label>
                        <input v-model="startDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('End Date') }} *</label>
                        <input v-model="endDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>

                <div class="cvr-form-grid-4 mb-5">
                    <div>
                        <label class="cvr-form-label">{{ $t('Banks') }} <span class="cvr-text-muted font-normal">{{ $t('(empty = all)') }}</span></label>
                        <MultiSelectDropdown v-model="selectedBankIds" :options="banks" :placeholder="$t('All Banks')" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Facility Types') }} <span class="cvr-text-muted font-normal">{{ $t('(empty = all)') }}</span></label>
                        <MultiSelectDropdown v-model="selectedFacilityTypes" :options="facilityTypes" :placeholder="$t('All Facility Types')" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Currencies') }} <span class="cvr-text-muted font-normal">{{ $t('(empty = all)') }}</span></label>
                        <MultiSelectDropdown v-model="selectedCurrencies" :options="currencies" :placeholder="$t('All Currencies')" />
                        <p class="text-xs mt-1 cvr-text-muted">
                            <template v-if="isConverted">{{ $t('More than one currency: every amount is shown in the main currency') }} ({{ mainCurrency }})</template>
                            <template v-else>{{ $t('One currency: amounts are shown in that currency, without conversion') }}</template>
                        </p>
                    </div>
                </div>

                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit }"
                >
                    {{ $t('View Report') }}
                </button>
                <ul v-if="serverErrors.length" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-for="(message, i) in serverErrors" :key="i">— {{ message }}</li>
                </ul>
                <ul v-if="!canSubmit" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-if="!startDate">{{ $t('— Start Date is not set.') }}</li>
                    <li v-if="!endDate">{{ $t('— End Date is not set.') }}</li>
                    <li v-if="startDate && endDate && startDate > endDate">{{ $t('— Start Date is after End Date.') }}</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
