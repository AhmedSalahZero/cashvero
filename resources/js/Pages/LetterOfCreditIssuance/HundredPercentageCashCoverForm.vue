<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import { todayDate } from '@/composables/today';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate();

const props = defineProps({
    mode: String, // 'create' | 'edit'
    company: Object, // { id, mainFunctionalCurrency }
    source: String,
    currencies: Object,
    lcTypes: Object,
    lcCategories: Object,
    financialInstitutionBanks: Array, // [{id, name}]
    beneficiaries: Array,             // [{id, name}] — suppliers only, confirmed server-side
    feesAccounts: Array,              // [{id, account_type_id, financial_institution_id, account_number, currency}]
    feesAccountTypes: Array,          // [{id, name}] — Current Account, in practice
    contracts: Array,
    purchaseOrders: Array,
    model: Object,
    lookupUrl: String,
    exchangeRateLookupUrl: String,
    balanceLookupUrlTemplate: String, // has __ACCOUNT_TYPE__ / __ACCOUNT_ID__ / __FI_ID__ placeholders
    submitUrl: String,
    backUrl: String,
    navUrls: Object,
});

const page = usePage();
const isEdit = props.mode === 'edit';

const form = ref({
    category_name: props.model?.category_name ?? '',
    transaction_name: props.model?.transaction_name ?? '',
    financial_institution_id: props.model?.financial_institution_id ?? '',
    lc_currency: props.model?.lc_currency ?? 'egp',
    lc_type: props.model?.lc_type ?? '',
    lc_type_outstanding_balance: 0,
    lc_code: props.model?.lc_code ?? '',
    partner_id: props.model?.partner_id ?? '',
    transaction_reference: props.model?.transaction_reference ?? '1',
    contract_id: props.model?.contract_id ?? -1,
    new_purchase_order_number: props.model?.new_purchase_order_number ?? '',
    purchase_order_id: props.model?.purchase_order_id ?? '',
    purchase_order_date: props.model?.purchase_order_date ?? '',
    transaction_date: props.model?.transaction_date ?? '',
    issuance_date: props.model?.issuance_date ?? '',
    lc_duration_days: props.model?.lc_duration_days ?? 1,
    due_date: props.model?.due_date ?? '',
    lc_amount: props.model?.lc_amount ?? 0,
    exchange_rate: props.model?.exchange_rate ?? 1,
    amount_in_main_currency: props.model?.amount_in_main_currency ?? 0,
    // Defaults to 100 — this source is "100% Cash Cover" by
    // definition, confirmed from the original blade's own default
    // value.
    cash_cover_rate: props.model?.cash_cover_rate ?? 100,
    cash_cover_amount: props.model?.cash_cover_amount ?? 0,
    lc_cash_cover_currency: props.model?.lc_cash_cover_currency ?? '',
    lc_commission_rate: props.model?.lc_commission_rate ?? 0,
    lc_commission_amount: props.model?.lc_commission_amount ?? 0,
    min_lc_commission_fees: props.model?.min_lc_commission_fees ?? 0,
    issuance_fees: props.model?.issuance_fees ?? 0,
    // ⚠️ Confirmed by tracing the original form: this source has no
    // separate Cash Cover account field at all — only one account
    // selector, used for both cash cover and fees/commission.
    cash_cover_deducted_from_account_type: props.model?.cash_cover_deducted_from_account_type ?? (props.feesAccountTypes[0]?.id ?? ''),
    lc_fees_and_commission_account_id: props.model?.lc_fees_and_commission_account_id ?? '',
    user_comment: props.model?.user_comment ?? '',
});

const contractsForCustomer = computed(() => props.contracts.filter(c => c.partner_id === Number(form.value.partner_id)));
const purchaseOrdersForContract = computed(() => props.purchaseOrders.filter(po => po.contract_id === Number(form.value.contract_id)));
const showNewPoInput = computed(() => Number(form.value.contract_id) === -1);
const showExistingPoDropdown = computed(() => Number(form.value.contract_id) === -2);

/*
 * ⚠️ Confirmed bug fix: the Account Number dropdown ("Deducted From
 * Account # (Cover & Commission)") must be filtered by LC CASH COVER
 * Currency, not LC Currency — traced from the original's account-
 * selection AJAX chain, which keys off the cash-cover-currency field
 * (`receiving-currency-class`), not the LC's own invoice currency.
 * Also now filtered by the Account Type selected just above it,
 * matching the original's two-step Account Type → Account Number
 * dependency (previously missing the Account Type field entirely).
 */
const feesAccountOptions = computed(() =>
    props.feesAccounts.filter(a =>
        a.financial_institution_id === Number(form.value.financial_institution_id) &&
        a.account_type_id === Number(form.value.cash_cover_deducted_from_account_type) &&
        a.currency === form.value.lc_cash_cover_currency
    )
);
watch(() => [form.value.financial_institution_id, form.value.cash_cover_deducted_from_account_type, form.value.lc_cash_cover_currency], () => {
    const stillValid = feesAccountOptions.value.some(a => a.id === Number(form.value.lc_fees_and_commission_account_id));
    if (!stillValid) form.value.lc_fees_and_commission_account_id = '';
});

/*
 * ⚠️ Confirmed missing business rule, traced from the original's
 * shared commonJs.blade.php: LC Cash Cover Currency is NOT a free
 * pick from every currency — it's restricted to just the company's
 * main functional currency, plus the selected LC Currency itself (if
 * different). If LC Currency equals the main functional currency,
 * Cash Cover Currency only has that one option and is auto-selected.
 * This is shared behavior with LcFacilityForm.vue (same original JS
 * handler covers both forms).
 */
const cashCoverCurrencyOptions = computed(() => {
    const opts = [props.company.mainFunctionalCurrency];
    if (form.value.lc_currency && form.value.lc_currency !== props.company.mainFunctionalCurrency) {
        opts.push(form.value.lc_currency);
    }
    return opts;
});
watch(() => form.value.lc_currency, () => {
    if (!cashCoverCurrencyOptions.value.includes(form.value.lc_cash_cover_currency)) {
        form.value.lc_cash_cover_currency = props.company.mainFunctionalCurrency;
    }
}, { immediate: true });

/**
 * Client-requested (2026-08-11): same auto-fill as LcFacilityForm.vue —
 * see that file for the full explanation. Manual override still works
 * since this only sets the field, never disables it.
 */
async function fetchExchangeRateForLcCurrency() {
    if (!form.value.lc_currency || form.value.lc_currency === props.company.mainFunctionalCurrency) {
        form.value.exchange_rate = 1;
        return;
    }
    const params = new URLSearchParams({
        fromCurrency: form.value.lc_currency,
        toCurrency: props.company.mainFunctionalCurrency,
        date: form.value.issuance_date || todayDate(),
    });
    try {
        const response = await fetch(`${props.exchangeRateLookupUrl}?${params.toString()}`);
        const data = await response.json();
        if (data?.exchange_rate) {
            form.value.exchange_rate = data.exchange_rate;
        }
    } catch (e) {
        // Silent fail — field stays editable regardless.
    }
}
watch(() => [form.value.lc_currency, form.value.issuance_date], fetchExchangeRateForLcCurrency);

/*
 * ⚠️ Previously missing entirely: Balance / Net Balance for the
 * selected account. Genuinely dynamic server-side computation (the
 * original's update.balance.and.net.balance.based.on.account.id.ajax
 * endpoint — Balance = as of today, Net Balance = the account's
 * latest posted statement regardless of date), can't be precomputed
 * client-side. balanceLookupUrlTemplate has placeholders substituted
 * before fetching, since the original route takes these as path
 * segments, not query params.
 */
const accountBalance = ref(0);
const accountBalanceDate = ref('');
const accountNetBalance = ref(0);
const accountNetBalanceDate = ref('');
const balanceLoading = ref(false);
let balanceRequestToken = 0;
async function runBalanceLookup() {
    if (!form.value.lc_fees_and_commission_account_id || !form.value.cash_cover_deducted_from_account_type || !form.value.financial_institution_id) {
        accountBalance.value = 0;
        accountBalanceDate.value = '';
        accountNetBalance.value = 0;
        accountNetBalanceDate.value = '';
        return;
    }
    const thisRequest = ++balanceRequestToken;
    balanceLoading.value = true;
    try {
        const url = props.balanceLookupUrlTemplate
            .replace('__ACCOUNT_TYPE__', form.value.cash_cover_deducted_from_account_type)
            .replace('__ACCOUNT_ID__', form.value.lc_fees_and_commission_account_id)
            .replace('__FI_ID__', form.value.financial_institution_id);
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (thisRequest !== balanceRequestToken) return;
        if (!res.ok) throw new Error(`Balance lookup failed (HTTP ${res.status})`);
        const data = await res.json();
        accountBalance.value = data.balance ?? 0;
        accountBalanceDate.value = data.balance_date ?? '';
        accountNetBalance.value = data.net_balance ?? 0;
        accountNetBalanceDate.value = data.net_balance_date ?? '';
    } catch (err) {
        if (thisRequest !== balanceRequestToken) return;
        console.error('Account balance lookup failed:', err);
    } finally {
        if (thisRequest === balanceRequestToken) balanceLoading.value = false;
    }
}
watch(() => [form.value.lc_fees_and_commission_account_id, form.value.cash_cover_deducted_from_account_type, form.value.financial_institution_id], runBalanceLookup);

/* ── Live LC Type Outstanding Balance lookup ──────────────────────── */
const lookupLoading = ref(false);
const lookupError = ref(null);
let lookupRequestToken = 0;
async function runLookup() {
    if (!form.value.financial_institution_id) return;
    const thisRequest = ++lookupRequestToken;
    lookupLoading.value = true;
    lookupError.value = null;
    try {
        const params = new URLSearchParams({
            financialInstitutionId: form.value.financial_institution_id,
            lcType: form.value.lc_type || '',
            source: props.source,
            lcCurrency: form.value.lc_currency || '',
        });
        if (props.model?.id) params.set('lcIssuanceId', props.model.id);
        const res = await fetch(`${props.lookupUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
        if (thisRequest !== lookupRequestToken) return;
        if (!res.ok) {
            throw new Error(`Lookup failed (HTTP ${res.status})`);
        }
        const data = await res.json();
        form.value.lc_type_outstanding_balance = data.current_lc_type_outstanding_balance ?? 0;
        if (!isEdit) {
            form.value.min_lc_commission_fees = data.min_lc_commission_rate ?? 0;
            form.value.lc_commission_rate = data.lc_commission_rate || form.value.lc_commission_rate;
            form.value.issuance_fees = data.min_lc_issuance_fees_for_current_lc_type ?? form.value.issuance_fees;
        }
    } catch (err) {
        if (thisRequest !== lookupRequestToken) return;
        console.error('LC Type Outstanding Balance lookup failed:', err);
        lookupError.value = 'Could not load LC Type Outstanding Balance — check your connection and try reselecting the bank/currency/type.';
    } finally {
        if (thisRequest === lookupRequestToken) lookupLoading.value = false;
    }
}
watch(() => [form.value.financial_institution_id, form.value.lc_currency, form.value.lc_type], runLookup, { immediate: true });

function addDaysIso(dateIso, days) {
    if (!dateIso) return '';
    const d = new Date(dateIso);
    d.setDate(d.getDate() + Number(days || 0));
    return d.toISOString().split('T')[0];
}
watch(() => [form.value.issuance_date, form.value.lc_duration_days], () => {
    form.value.due_date = addDaysIso(form.value.issuance_date, form.value.lc_duration_days);
}, { immediate: true });

watch(() => [form.value.lc_amount, form.value.exchange_rate], () => {
    form.value.amount_in_main_currency = Math.round((Number(form.value.lc_amount || 0) * Number(form.value.exchange_rate || 0)) * 100) / 100;
});
watch(() => [form.value.amount_in_main_currency, form.value.cash_cover_rate], () => {
    form.value.cash_cover_amount = Math.round((Number(form.value.amount_in_main_currency || 0) * Number(form.value.cash_cover_rate || 0) / 100) * 100) / 100;
});
watch(() => [form.value.lc_amount, form.value.lc_commission_rate], () => {
    form.value.lc_commission_amount = Math.round((Number(form.value.lc_amount || 0) * Number(form.value.lc_commission_rate || 0) / 100) * 100) / 100;
});

function currencyLabel(code) {
    return props.currencies[code] ?? code?.toUpperCase();
}

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = { ...form.value };
    // Defense in depth: see LcFacilityForm.vue — same confirmed FK
    // violation root cause, same fix.
    if (!payload.contract_id && payload.contract_id !== 0) payload.contract_id = -1;
    if (!showNewPoInput.value) payload.new_purchase_order_number = '';
    if (!showExistingPoDropdown.value && !showNewPoInput.value) payload.purchase_order_id = payload.purchase_order_id || 'null';
    if (isEdit) {
        router.put(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    }
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to LC Issuance') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? $t('Edit') : $t('Add') }} {{ $t('LC Issuance — 100% Cash Cover') }}
            </h1>

            <FormErrorSummary />

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Letter Of Credit Type -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Letter Of Credit Type') }}</h2>
                    <div class="cvr-form-grid-3 mb-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Issuance Type') }} *</label>
                            <select v-model="form.category_name" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select') }}</option>
                                <option v-for="(label, code) in lcCategories" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Transaction Name') }} *</label>
                            <input v-model="form.transaction_name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('transaction_name')" class="text-xs mt-1 cvr-num-red">{{ errorFor('transaction_name') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Bank') }} *</label>
                            <select v-model="form.financial_institution_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select') }}</option>
                                <option v-for="b in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('LC Currency') }} *</label>
                            <select v-model="form.lc_currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select') }}</option>
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('LC Type') }} *</label>
                            <select v-model="form.lc_type" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select') }}</option>
                                <option v-for="(label, code) in lcTypes" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">
                                {{ $t('LC Type Outstanding Balance') }}
                                <span v-if="lookupLoading" class="font-normal normal-case">{{ $t('(updating...)') }}</span>
                            </label>
                            <input disabled :value="form.lc_type_outstanding_balance" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <p v-if="lookupError" class="text-xs cvr-num-red mt-1">{{ lookupError }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('LC Code') }} *</label>
                            <input v-model="form.lc_code" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Beneficiary & Reference -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Beneficiary & Reference') }}</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Beneficiary Name') }} *</label>
                            <select v-model="form.partner_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select') }}</option>
                                <option v-for="b in beneficiaries" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Contract Reference') }} *</label>
                            <select v-model="form.contract_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="-1">{{ $t('New PO') }}</option>
                                <option value="-2">{{ $t('Existing PO') }}</option>
                                <option v-for="c in contractsForCustomer" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div v-if="showExistingPoDropdown">
                            <label class="cvr-form-label">{{ $t('Purchase Order') }} *</label>
                            <select v-model="form.purchase_order_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('None') }}</option>
                                <option v-for="po in purchaseOrders" :key="po.id" :value="po.id">{{ po.po_number }}</option>
                            </select>
                        </div>
                        <div v-else-if="showNewPoInput">
                            <label class="cvr-form-label">{{ $t('New PO') }} *</label>
                            <input v-model="form.new_purchase_order_number" type="text" :placeholder="$t('New PO')" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div v-else>
                            <label class="cvr-form-label">{{ $t('Purchase Order') }}</label>
                            <select v-model="form.purchase_order_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('None') }}</option>
                                <option v-for="po in purchaseOrdersForContract" :key="po.id" :value="po.id">{{ po.po_number }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Purchase Order Date') }} *</label>
                            <input v-model="form.purchase_order_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Letter Of Credit Information -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Letter Of Credit Information') }}</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Issuance Date') }} *</label>
                            <input v-model="form.issuance_date" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('LC Duration (Days)') }} *</label>
                            <input v-model="form.lc_duration_days" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Due Date') }}</label>
                            <input disabled :value="form.due_date" type="date" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('Issuance Date + LC Duration') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('LC Amount') }} *</label>
                            <input v-model="form.lc_amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('lc_amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor('lc_amount') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Exchange Rate') }} *</label>
                            <input v-model="form.exchange_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Amount In Payment Currency') }}</label>
                            <input disabled :value="form.amount_in_main_currency" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Cash Cover Rate (%)') }}</label>
                            <input v-model="form.cash_cover_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('LC Cash Cover Currency') }} *</label>
                            <select v-model="form.lc_cash_cover_currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="code in cashCoverCurrencyOptions" :key="code" :value="code">{{ currencyLabel(code) }}</option>
                            </select>
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('Limited to the company\'s main functional currency and the LC Currency above') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Cash Cover Amount') }}</label>
                            <input disabled :value="form.cash_cover_amount" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('LC Commission Rate (%)') }} *</label>
                            <input v-model="form.lc_commission_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('LC Commission Amount') }}</label>
                            <input disabled :value="form.lc_commission_amount" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Min LC Commission Fees') }}</label>
                            <input v-model="form.min_lc_commission_fees" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Issuance Fees') }}</label>
                            <input v-model="form.issuance_fees" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Type') }} *</label>
                            <select v-model="form.cash_cover_deducted_from_account_type" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="t in feesAccountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Deducted From Account # (Cover & Commission)') }} *</label>
                            <select v-model="form.lc_fees_and_commission_account_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select') }}</option>
                                <option v-for="a in feesAccountOptions" :key="a.id" :value="a.id">{{ a.account_number }} ({{ a.currency?.toUpperCase() }})</option>
                            </select>
                            <p v-if="errorFor('lc_fees_and_commission_account_id')" class="text-xs mt-1 cvr-num-red">{{ errorFor($t('lc_fees_and_commission_account_id')) }}</p>
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('Filtered by Account Type and LC Cash Cover Currency above') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">
                                {{ $t('Balance') }} <span v-if="accountBalanceDate">[ {{ accountBalanceDate }} ]</span>
                                <span v-if="balanceLoading" class="font-normal normal-case">{{ $t('(updating...)') }}</span>
                            </label>
                            <input disabled :value="accountBalance" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">
                                {{ $t('Net Balance') }} <span v-if="accountNetBalanceDate">[ {{ accountNetBalanceDate }} ]</span>
                            </label>
                            <input disabled :value="accountNetBalance" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                    </div>
                    <p class="text-xs cvr-text-muted mt-3">
                        {{ $t('This source uses one account for both the cash cover deposit and issuance/commission fees — confirmed from the original: no separate Cash Cover account field exists for 100% Cash Cover.') }}
                    </p>
                </div>

                <!-- User Comment -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('User Comment') }}</h2>
                    <textarea v-model="form.user_comment" rows="3" class="cvr-input w-full px-3 py-2 rounded" :placeholder="$t('Comment')"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Cancel') }}</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? $t('Saving...') : $t('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
