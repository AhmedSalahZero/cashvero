<script setup>
/**
 * Statements/LeasingContractStatement/Index.vue
 * ------------------------------------------------------------------
 * Served by LeasingContractStatementController@index. Filter form:
 * a date range, then Leasing Company → Currency → Contract, each
 * cascading from the previous pick — the same shape as its sibling
 * Factoring Statement.
 *
 * The period restricts which rows the statement LISTS, not how they
 * are calculated: balances stay continuous across it and the facility
 * figures are read as of the end date. See LeasingContractStatementData.
 */
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    leasingCompanies: Array, // [{ id, name }]
    urls: Object,            // { currencies, contracts, result }
    filters: Object,         // what the user came back with, if anything
    navUrls: Object,
});

/**
 * A contract runs for years into the future, so the default window
 * reaches forward as well as back — unlike a bank statement, whose
 * rows can only exist in the past. Clamping the end date to today
 * would hide every installment still to come.
 */
const defaultStart = new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().slice(0, 10);
const defaultEnd = new Date(new Date().setFullYear(new Date().getFullYear() + 5)).toISOString().slice(0, 10);

const startDate = ref(props.filters?.start_date || defaultStart);
const endDate = ref(props.filters?.end_date || defaultEnd);
const leasingCompanyId = ref(props.filters?.leasing_company_id || '');
const currency = ref(props.filters?.currency || '');
const leasingContractId = ref(props.filters?.leasing_contract_id || '');

const currencyOptions = ref({});
const loadingCurrencies = ref(false);
const contractOptions = ref([]);
const loadingContracts = ref(false);

/**
 * `keep` is set only while re-filling the form from the filters the
 * user came back with: the cascade normally clears whatever sits below
 * the field that changed, which would wipe the very selection being
 * restored.
 */
async function loadCurrencies({ keep = false } = {}) {
    if (!keep) {
        currency.value = '';
        leasingContractId.value = '';
        contractOptions.value = [];
    }
    currencyOptions.value = {};
    if (!leasingCompanyId.value) return;

    loadingCurrencies.value = true;
    try {
        const { data } = await window.axios.get(props.urls.currencies, {
            params: { leasing_company_id: leasingCompanyId.value },
        });
        currencyOptions.value = data?.currencies || {};
    } finally {
        loadingCurrencies.value = false;
    }
}

async function loadContracts({ keep = false } = {}) {
    if (!keep) {
        leasingContractId.value = '';
    }
    contractOptions.value = [];
    if (!leasingCompanyId.value || !currency.value) return;

    loadingContracts.value = true;
    try {
        const { data } = await window.axios.get(props.urls.contracts, {
            params: {
                leasing_company_id: leasingCompanyId.value,
                currency: currency.value,
                start_date: startDate.value,
                end_date: endDate.value,
            },
        });
        contractOptions.value = data?.contracts || [];
    } finally {
        loadingContracts.value = false;
    }
}

watch(leasingCompanyId, () => loadCurrencies());
watch(currency, () => loadContracts());
// The range narrows the list to contracts whose life overlaps it, so
// moving either end re-asks — keeping the current pick if it survives.
watch([startDate, endDate], async () => {
    if (!currency.value) return;
    const chosen = leasingContractId.value;
    await loadContracts({ keep: true });
    if (chosen && !contractOptions.value.some((c) => String(c.id) === String(chosen))) {
        leasingContractId.value = '';
    }
});

onMounted(async () => {
    if (!leasingCompanyId.value) return;
    await loadCurrencies({ keep: true });
    await loadContracts({ keep: true });
});

const canSubmit = computed(() => Boolean(
    startDate.value && endDate.value && leasingCompanyId.value && currency.value && leasingContractId.value,
));

const rangeIsBackwards = computed(() => Boolean(
    startDate.value && endDate.value && startDate.value > endDate.value,
));

function submit() {
    if (!canSubmit.value || rangeIsBackwards.value) return;
    router.get(props.urls.result, {
        start_date: startDate.value,
        end_date: endDate.value,
        leasing_company_id: leasingCompanyId.value,
        currency: currency.value,
        leasing_contract_id: leasingContractId.value,
    });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Leasing Contract Statement') }}</h1>
            <p class="text-sm cvr-text-muted mb-4">
                {{ $t('The installment breakdown of one leasing contract — interest and principle, due against paid — and the ledger of the supplier payments the leasing company made out of it.') }}
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-2 mb-5">
                    <div>
                        <label class="cvr-form-label">{{ $t('Start Date') }} *</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('End Date') }} *</label>
                        <input v-model="endDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        <p class="text-xs cvr-text-muted mt-1">
                            {{ $t('Future dates are allowed — installments still to come live there.') }}
                        </p>
                    </div>
                </div>

                <div class="cvr-form-grid-3">
                    <div>
                        <label class="cvr-form-label">{{ $t('Leasing Company') }} *</label>
                        <select v-model="leasingCompanyId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select') }}</option>
                            <option v-for="lc in leasingCompanies" :key="lc.id" :value="lc.id">{{ lc.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                        <select
                            v-model="currency"
                            class="cvr-input w-full px-3 py-2 rounded"
                            :disabled="loadingCurrencies || Object.keys(currencyOptions).length === 0"
                        >
                            <option value="" disabled>
                                {{ loadingCurrencies ? $t('Loading…') : (Object.keys(currencyOptions).length ? $t('Select') : $t('Select a leasing company first')) }}
                            </option>
                            <option v-for="(label, code) in currencyOptions" :key="code" :value="code">{{ String(label).toUpperCase() }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Contract Name') }} *</label>
                        <select
                            v-model="leasingContractId"
                            class="cvr-input w-full px-3 py-2 rounded"
                            :disabled="loadingContracts || contractOptions.length === 0"
                        >
                            <option value="" disabled>
                                {{ loadingContracts ? $t('Loading…') : (contractOptions.length ? $t('Select') : $t('Select a currency first')) }}
                            </option>
                            <option v-for="c in contractOptions" :key="c.id" :value="c.id">{{ c.label }}</option>
                        </select>
                        <p v-if="currency && !loadingContracts && contractOptions.length === 0" class="text-xs cvr-text-muted mt-1">
                            {{ $t('No contract in this currency runs within the selected dates.') }}
                        </p>
                    </div>
                </div>

                <button
                    @click="submit"
                    :disabled="!canSubmit || rangeIsBackwards"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-5"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit || rangeIsBackwards }"
                >
                    {{ $t('View Statement') }}
                </button>
                <ul v-if="!canSubmit || rangeIsBackwards" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-if="!startDate">{{ $t('— Start Date is not set.') }}</li>
                    <li v-if="!endDate">{{ $t('— End Date is not set.') }}</li>
                    <li v-if="rangeIsBackwards">{{ $t('— Start Date is after End Date.') }}</li>
                    <li v-if="!leasingCompanyId">{{ $t('— Leasing Company is not selected.') }}</li>
                    <li v-if="!currency">{{ $t('— Currency is not selected.') }}</li>
                    <li v-if="!leasingContractId">{{ $t('— Contract Name is not selected.') }}</li>
                </ul>
            </div>

            <div v-if="leasingCompanies.length === 0" class="cvr-card p-8 text-center cvr-text-muted mt-4">
                {{ $t('No leasing companies yet — add one under Financial Institutions first.') }}
            </div>
        </div>
    </AppLayout>
</template>
