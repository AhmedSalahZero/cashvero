<script setup>
/**
 * Statements/WithdrawalStatement/Index.vue
 * ------------------------------------------------------------------
 * Served by WithdrawalsSettlementReportController@index. Filter form:
 * date range, Banks (via the shared MultiSelectDropdown component —
 * same one used on the Aging report's form), Account Type, Currency.
 *
 * Submits via POST (router.post), not GET — this page's result route
 * is also posted to directly by the still-Blade Cash Forecast
 * dashboard, so its HTTP verb was deliberately left unchanged (see
 * the controller's docblock).
 */
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    company: Object,
    financialInstitutionBanks: Array, // [{ id, name }]
    accountTypes: Array, // [{ id, name }]
    currencies: Object, // { code: label }
    urls: Object, // { result }
});

const selectedBankIds = ref([]);
const accountTypeId = ref('');
const currency = ref('');
const startDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().slice(0, 10));
const endDate = ref(new Date().toISOString().slice(0, 10));

const bankOptions = computed(() => props.financialInstitutionBanks.map(b => ({ value: b.id, label: b.name })));

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
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Withdrawal Statement</h1>
            <p class="text-sm cvr-text-muted mb-6">
                Overdraft withdrawals and how much of each is still outstanding, for a chosen date range.
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-4 mb-5">
                    <div>
                        <label class="cvr-form-label">Start Date *</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">End Date *</label>
                        <input v-model="endDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Account Type *</label>
                        <select v-model="accountTypeId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>Select account type</option>
                            <option v-for="type in accountTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Currency *</label>
                        <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>Select currency</option>
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ String(label).toUpperCase() }}</option>
                        </select>
                    </div>
                </div>

                <div class="max-w-xl mb-5">
                    <label class="cvr-form-label">Banks * <span class="cvr-text-muted font-normal">(pick one or more)</span></label>
                    <MultiSelectDropdown v-model="selectedBankIds" :options="bankOptions" placeholder="Select banks" />
                </div>

                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit }"
                >
                    View Statement
                </button>
                <ul v-if="!canSubmit" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-if="!startDate">— Start Date is not set.</li>
                    <li v-if="!endDate">— End Date is not set.</li>
                    <li v-if="!accountTypeId">— Account Type is not selected.</li>
                    <li v-if="!currency">— Currency is not selected.</li>
                    <li v-if="selectedBankIds.length === 0">— No bank is selected yet (open the Banks dropdown and pick at least one, or Select All).</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>