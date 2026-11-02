<script setup>
/**
 * Statements/ExpiredLgs/Index.vue
 * ------------------------------------------------------------------
 * Served by ExpiredLgsReportController@index. Every LG past its expiry
 * as of today and never cancelled.
 *
 * The date is today and cannot be changed (the server ignores any date
 * anyway). The four filters are multi-selects with Select All built into
 * MultiSelectDropdown. Picking several currencies opens one tab per
 * currency on the result page.
 */
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    company: Object,
    today: String,
    currencies: Array, // [{value, label}]
    banks: Array,      // [{value: financial_institution_id, label}]
    lgTypes: Array,    // [{value, label}]
    lgSources: Array,  // [{value, label}]
    urls: Object,      // { result }
});

// Everything selected to start with — the common case is "all of them".
const selectedCurrencies = ref(props.currencies.map(o => o.value));
const selectedBankIds = ref(props.banks.map(o => o.value));
const selectedLgTypes = ref(props.lgTypes.map(o => o.value));
const selectedSources = ref(props.lgSources.map(o => o.value));

const canSubmit = computed(() =>
    selectedCurrencies.value.length > 0
    && selectedBankIds.value.length > 0
    && selectedLgTypes.value.length > 0
    && selectedSources.value.length > 0
);

const submitting = ref(false);
function submit() {
    if (!canSubmit.value || submitting.value) return;
    submitting.value = true;
    router.get(props.urls.result, {
        currencies: selectedCurrencies.value,
        bank_id: selectedBankIds.value,
        lg_types: selectedLgTypes.value,
        sources: selectedSources.value,
    }, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Expired LGs') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ $t('Letters of Guarantee whose expiry date has passed as of today and that were never cancelled.') }}
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-3 mb-5">
                    <div>
                        <label class="cvr-form-label">{{ $t('Date') }}</label>
                        <input :value="today" type="date" disabled class="cvr-input w-full px-3 py-2 rounded opacity-70 cursor-not-allowed" />
                        <p class="text-xs mt-1 cvr-text-muted">{{ $t('Always today.') }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                        <MultiSelectDropdown v-model="selectedCurrencies" :options="currencies" :placeholder="$t('Select currencies')" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Bank') }} *</label>
                        <MultiSelectDropdown v-model="selectedBankIds" :options="banks" :placeholder="$t('Select banks')" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('LG Type') }} *</label>
                        <MultiSelectDropdown v-model="selectedLgTypes" :options="lgTypes" :placeholder="$t('Select LG types')" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('LG Source') }} *</label>
                        <MultiSelectDropdown v-model="selectedSources" :options="lgSources" :placeholder="$t('Select LG sources')" />
                    </div>
                </div>

                <p v-if="currencies.length === 0" class="text-sm cvr-text-muted mb-3">{{ $t('No Letters of Guarantee yet.') }}</p>

                <button
                    @click="submit"
                    :disabled="!canSubmit || submitting"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit || submitting }"
                >
                    {{ $t('View Report') }}
                </button>
                <ul v-if="!canSubmit" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-if="selectedCurrencies.length === 0">{{ $t('— Currency is not selected.') }}</li>
                    <li v-if="selectedBankIds.length === 0">{{ $t('— No bank is selected yet (open the dropdown and pick at least one, or Select All).') }}</li>
                    <li v-if="selectedLgTypes.length === 0">{{ $t('— No LG type is selected.') }}</li>
                    <li v-if="selectedSources.length === 0">{{ $t('— No LG source is selected.') }}</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
