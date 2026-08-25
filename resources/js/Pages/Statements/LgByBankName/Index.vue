<script setup>
/**
 * Statements/LgByBankName/Index.vue
 * ------------------------------------------------------------------
 * Served by LgByBankNameReportController@index. Mirror of LG By
 * Beneficiary Name's form — Status, Currency, then Banks of that
 * currency (cascading multi-select, reloaded via
 * LetterOfGuaranteeIssuanceController@getBankNameByCurrency — an
 * existing, untouched, shared endpoint).
 *
 * ⚠️ REAL BUG FIXED HERE (2026-07-25, confirmed with project owner):
 * a date used to be required no matter which Status was picked, even
 * though "Running" and "Expired" are both fully defined by
 * LetterOfGuaranteeIssuance::getStatus() without needing one at all —
 * Running = renewal date still in the future, Expired = it's passed.
 * A date only means something for Cancelled LGs (their real
 * cancellation_date), and for All (as the floor for which cancelled
 * LGs to include alongside every running/expired one). Status is also
 * now the first field, per the project owner's own suggestion — it's
 * what determines whether a date is even asked for, so it makes more
 * sense to pick first.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    company: Object,
    currencies: Object,
    selectedCurrency: String,
    urls: Object, // { result, banksByCurrency }
});

const status = ref('running');
const startDate = ref(new Date().toISOString().slice(0, 10));
const currency = ref(props.selectedCurrency || '');
const selectedBankIds = ref([]);

// Only Cancelled and All actually use the date — Running/Expired are
// fully defined by status + today's date, no user input needed.
const needsDate = computed(() => status.value === 'cancelled' || status.value === 'all');
const dateLabel = computed(() => status.value === 'cancelled'
    ? 'Cancelled From Date *'
    : 'Cancelled LGs From Date *');

const bankOptions = ref([]);
const loadingBanks = ref(false);
const loadError = ref('');

async function loadBanks() {
    selectedBankIds.value = [];
    bankOptions.value = [];
    loadError.value = '';
    if (!currency.value) return;
    loadingBanks.value = true;
    try {
        const { data } = await window.axios.get(props.urls.banksByCurrency, {
            params: { currencyName: currency.value },
        });
        bankOptions.value = Object.entries(data.banks || {}).map(([value, label]) => ({ value: Number(value), label }));
    } catch (error) {
        loadError.value = 'Could not load banks for this currency. Please try again.';
        console.error('Failed to load banks by currency:', error);
    } finally {
        loadingBanks.value = false;
    }
}
watch(currency, loadBanks, { immediate: true });

const canSubmit = computed(() =>
    status.value
    && currency.value
    && selectedBankIds.value.length > 0
    && (!needsDate.value || startDate.value)
);

function submit() {
    if (!canSubmit.value) return;
    router.get(props.urls.result, {
        status: status.value,
        start_date: needsDate.value ? startDate.value : null,
        currency_name: currency.value,
        bank_id: selectedBankIds.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('LG By Bank Name') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ $t('Letters of Guarantee for one or more banks, filtered by status.') }}
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-3 mb-5">
                    <div>
                        <label class="cvr-form-label">{{ $t('Status') }} *</label>
                        <select v-model="status" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="running">{{ $t('Running') }}</option>
                            <option value="expired">{{ $t('Expired') }}</option>
                            <option value="cancelled">{{ $t('Cancelled') }}</option>
                            <option value="all">{{ $t('All') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                        <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select currency') }}</option>
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ String(label).toUpperCase() }}</option>
                        </select>
                    </div>
                    <div v-if="needsDate">
                        <label class="cvr-form-label">{{ dateLabel }}</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        <p class="text-xs mt-1 cvr-text-muted">
                            {{ status === 'cancelled' ? $t('Cancelled LGs from this date through today.') : $t('Every Running and Expired LG, plus Cancelled LGs from this date through today.') }}
                        </p>
                    </div>
                </div>

                <div class="max-w-xl">
                    <label class="cvr-form-label">
                        {{ $t('Bank') }} * <span class="cvr-text-muted font-normal">{{ $t('(pick one or more)') }}</span>
                    </label>
                    <MultiSelectDropdown
                        v-model="selectedBankIds"
                        :options="bankOptions"
                        :placeholder="!currency ? 'Select a currency first' : (loadingBanks ? 'Loading…' : 'Select banks')"
                    />
                    <p v-if="loadError" class="text-xs mt-1" style="color: var(--cvr-danger-text);">{{ loadError }}</p>
                    <p v-else-if="currency && !loadingBanks && bankOptions.length === 0" class="text-xs cvr-text-muted mt-1">
                        {{ $t('No banks with LGs in this currency yet.') }}
                    </p>
                </div>

                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-5"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit }"
                >
                    {{ $t('View Report') }}
                </button>
                <ul v-if="!canSubmit" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-if="needsDate && !startDate">— {{ status === 'cancelled' ? $t('Cancelled From Date') : $t('From Date') }} is not set.</li>
                    <li v-if="!currency">{{ $t('— Currency is not selected.') }}</li>
                    <li v-if="selectedBankIds.length === 0">{{ $t('— No bank is selected yet (open the dropdown and pick at least one, or Select All).') }}</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
