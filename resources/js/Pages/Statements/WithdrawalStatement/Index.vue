<script setup>
/**
 * Statements/WithdrawalStatement/Index.vue
 * ------------------------------------------------------------------
 * Served by WithdrawalsSettlementReportController@index. Filter form:
 * date range, Account Type, Currency, then Banks (cascading multi-select
 * reloaded via banksByAccountType when Account Type changes).
 *
 * Submits via POST (router.post), not GET — this page's result route
 * is also posted to directly by the still-Blade Cash Forecast
 * dashboard, so its HTTP verb was deliberately left unchanged (see
 * the controller's docblock).
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    company: Object,
    accountTypes: Array, // [{ id, name }]
    currencies: Object, // { code: label }
    urls: Object, // { result, banks }
});

const selectedBankIds = ref([]);
const accountTypeId = ref('');
const currency = ref('');
const startDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().slice(0, 10));
const endDate = ref(new Date().toISOString().slice(0, 10));

const bankOptions = ref([]);
const loadingBanks = ref(false);
const loadError = ref('');

async function loadBanks() {
    selectedBankIds.value = [];
    bankOptions.value = [];
    loadError.value = '';
    if (!accountTypeId.value) return;
    loadingBanks.value = true;
    try {
        const { data } = await window.axios.get(props.urls.banks, {
            params: { account_type: accountTypeId.value },
        });
        bankOptions.value = (data.banks || []).map(b => ({ value: b.id, label: b.name }));
    } catch (error) {
        loadError.value = 'Could not load banks for this account type. Please try again.';
        console.error('Failed to load banks by account type:', error);
    } finally {
        loadingBanks.value = false;
    }
}
watch(accountTypeId, loadBanks);

const canSubmit = computed(() =>
    selectedBankIds.value.length > 0 && accountTypeId.value && currency.value && startDate.value && endDate.value
);

function submit() {
    if (!canSubmit.value) return;
    router.post(props.urls.result, {
        start_date: startDate.value,
        end_date: endDate.value,
        financial_institution_ids: selectedBankIds.value,
        account_type: accountTypeId.value,
        currency: currency.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Withdrawal Statement') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ $t('Overdraft withdrawals and how much of each is still outstanding, for a chosen date range.') }}
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-4 mb-5">
                    <div>
                        <label class="cvr-form-label">{{ $t('Start Date') }} *</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('End Date') }} *</label>
                        <input v-model="endDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Account Type') }} *</label>
                        <select v-model="accountTypeId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select account type') }}</option>
                            <option v-for="type in accountTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                        <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select currency') }}</option>
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ String(label).toUpperCase() }}</option>
                        </select>
                    </div>
                </div>

                <div class="max-w-xl mb-5">
                    <label class="cvr-form-label">{{ $t('Banks') }} * <span class="cvr-text-muted font-normal">{{ $t('(pick one or more)') }}</span></label>
                    <MultiSelectDropdown
                        v-model="selectedBankIds"
                        :options="bankOptions"
                        :placeholder="!accountTypeId ? 'Select account type first' : (loadingBanks ? 'Loading banks…' : 'Select banks')"
                    />
                    <p v-if="loadError" class="text-xs mt-1" style="color: var(--cvr-danger-text);">{{ loadError }}</p>
                    <p v-else-if="accountTypeId && !loadingBanks && bankOptions.length === 0" class="text-xs mt-1 cvr-text-muted">
                        {{ $t('No banks have this account type.') }}
                    </p>
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
                    <li v-if="!accountTypeId">{{ $t('— Account Type is not selected.') }}</li>
                    <li v-if="!currency">{{ $t('— Currency is not selected.') }}</li>
                    <li v-if="accountTypeId && selectedBankIds.length === 0">{{ $t('— No bank is selected yet (open the Banks dropdown and pick at least one, or Select All).') }}</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
