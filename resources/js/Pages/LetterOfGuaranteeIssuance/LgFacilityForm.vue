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
    company: Object,
    source: String,
    currencies: Object,
    lgTypes: Object,
    lgCategories: Object,
    commissionIntervals: Object,
    financialInstitutionBanks: Array, // [{id, name, lg_facilities: [{id, name}]}]
    accounts: Array,                  // [{id, account_type_id, financial_institution_id, account_number, currency, amount}]
    cashCoverAccountTypes: Array,     // [{id, name}] — Current Account / TD / CD
    feesAccountTypes: Array,          // [{id, name}] — Current Account only, in practice
    contracts: Array,                 // [{id, partner_id, name}]
    purchaseOrders: Array,            // [{id, contract_id, po_number}]
    model: Object,                    // null in create mode
    lookupUrl: String,
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
    lg_facility_id: props.model?.lg_facility_id ?? '',
    limit: 0,
    total_lg_outstanding_balance: 0,
    total_lg_room: 0,
    lg_type: props.model?.lg_type ?? '',
    lg_type_outstanding_balance: 0,
    lg_code: props.model?.lg_code ?? '',
    partner_id: props.model?.partner_id ?? '',
    transaction_reference: props.model?.transaction_reference ?? '1',
    contract_id: props.model?.contract_id ?? '',
    purchase_order_id: props.model?.purchase_order_id ?? '',
    purchase_order_date: props.model?.purchase_order_date ?? '',
    transaction_date: props.model?.transaction_date ?? '',
    issuance_date: props.model?.issuance_date ?? '',
    lg_duration_months: props.model?.lg_duration_months ?? 1,
    renewal_date: props.model?.renewal_date ?? '',
    lg_amount: props.model?.lg_amount ?? 0,
    lg_currency: '',
    cash_cover_rate: props.model?.cash_cover_rate ?? 0,
    cash_cover_amount: props.model?.cash_cover_amount ?? 0,
    lg_commission_rate: props.model?.lg_commission_rate ?? 0,
    lg_commission_amount: props.model?.lg_commission_amount ?? 0,
    min_lg_commission_fees: props.model?.min_lg_commission_fees ?? 0,
    issuance_fees: props.model?.issuance_fees ?? 0,
    lg_commission_interval: props.model?.lg_commission_interval ?? 'quarterly',
    cash_cover_deducted_from_account_type: props.model?.cash_cover_deducted_from_account_type ?? (props.cashCoverAccountTypes[0]?.id ?? ''),
    cash_cover_deducted_from_account_id: props.model?.cash_cover_deducted_from_account_id ?? '',
    lg_fees_and_commission_account_type: props.model?.lg_fees_and_commission_account_type ?? (props.feesAccountTypes[0]?.id ?? ''),
    lg_fees_and_commission_account_id: props.model?.lg_fees_and_commission_account_id ?? '',
    user_comment: props.model?.user_comment ?? '',
});

/* ── New customer toggle (the original form lets you type a brand new
   beneficiary name instead of picking an existing partner) ───────── */
const addingNewCustomer = ref(false);
const newCustomerName = ref('');

/* ── Cascading selects: FI -> LG Facilities; Customer -> Contracts ->
   Purchase Orders. All fetched up front server-side (same pattern
   already used for CD/TD/Contract selection elsewhere), filtered
   client-side — the original relied on client-side AJAX endpoints
   with no traceable server route in this codebase. ────────────────── */
const selectedBank = computed(() => props.financialInstitutionBanks.find(b => b.id === Number(form.value.financial_institution_id)));
const lgFacilitiesForBank = computed(() => selectedBank.value?.lg_facilities ?? []);

/*
 * ⚠️ Real fix here: the Customer/Beneficiary list is NOT simply
 * derived from contracts client-side — the original already
 * implements a specific rule server-side (confirmed by reading
 * updateOutstandingBalanceAndLimits()): Bid Bond shows every
 * customer (with or without a contract) + other partners; any other
 * LG type shows only customers WITH a contract + other partners.
 * This list comes from the same live lookup call already needed for
 * Limit/Outstanding/Room, not computed here.
 */
const customerOptions = ref([]);

const contractsForCustomer = computed(() =>
    props.contracts.filter(c => c.partner_id === Number(form.value.partner_id))
);
const purchaseOrdersForContract = computed(() =>
    props.purchaseOrders.filter(po => Number(po.contract_id) === Number(form.value.contract_id))
);

/* ── Bid Bond doesn't link to a Contract/SO — hide those fields and
   clear out any values so a stale selection never gets submitted. ── */
const isBidBond = computed(() => form.value.lg_type === 'bid-bond');
watch(isBidBond, (bidBond) => {
    if (bidBond) {
        form.value.contract_id = '';
        form.value.purchase_order_id = '';
        form.value.purchase_order_date = '';
    }
});

/* ── Picking a specific SO auto-fills its date (and locks the field);
   picking "All SOs" clears it so the user fills it in themselves. ── */
const isSpecificSoSelected = computed(() => !!form.value.purchase_order_id && form.value.purchase_order_id !== 'all');
watch(() => form.value.purchase_order_id, (soId) => {
    if (soId && soId !== 'all') {
        const so = props.purchaseOrders.find(p => p.id === Number(soId));
        form.value.purchase_order_date = so?.so_date ?? '';
    } else if (soId === 'all') {
        form.value.purchase_order_date = '';
    }
});

/* Cash cover / fees accounts — filtered by selected bank + selected
   account TYPE (Current Account / TD / CD for cash cover; Current
   Account only for fees) + currency. */
const accountsForBank = computed(() =>
    props.accounts.filter(a => a.financial_institution_id === Number(form.value.financial_institution_id))
);
const cashCoverAccountOptions = computed(() =>
    accountsForBank.value.filter(a =>
        a.account_type_id === Number(form.value.cash_cover_deducted_from_account_type) &&
        a.currency === form.value.lg_currency
    )
);
const selectedCashCoverAccount = computed(() =>
    accountsForBank.value.find(a => a.id === Number(form.value.cash_cover_deducted_from_account_id))
);
const feesAccountOptions = computed(() =>
    accountsForBank.value.filter(a =>
        a.account_type_id === Number(form.value.lg_fees_and_commission_account_type) &&
        a.currency === form.value.lg_currency
    )
);

/* ── Live limit / outstanding-balance / commission-rate lookup —
   genuinely dynamic server-side calculation (UNCHANGED,
   untouched updateOutstandingBalanceAndLimits() endpoint), can't be
   precomputed client-side. Calls automatically whenever the relevant
   selections change. ─────────────────────────────────────────────── */
const lookupLoading = ref(false);
async function runLookup() {
    if (!form.value.financial_institution_id) return;
    lookupLoading.value = true;
    try {
        const params = new URLSearchParams({
            financialInstitutionId: form.value.financial_institution_id,
            lgType: form.value.lg_type || '',
            source: props.source,
            letterOfGuaranteeFacilityId: form.value.lg_facility_id || '',
        });
        if (props.model?.id) params.set('lgIssuanceId', props.model.id);
        const res = await fetch(`${props.lookupUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        form.value.limit = data.limit ?? 0;
        form.value.total_lg_outstanding_balance = data.total_lg_outstanding_balance ?? 0;
        form.value.total_lg_room = data.total_room ?? 0;
        form.value.lg_type_outstanding_balance = data.current_lg_type_outstanding_balance ?? 0;
        form.value.lg_currency = data.currency_name ?? form.value.lg_currency;
        customerOptions.value = Object.entries(data.customers ?? {}).map(([name, id]) => ({ id: Number(id), name }));
        if (!isEdit) {
            form.value.min_lg_commission_fees = data.min_lg_commission_fees ?? 0;
            form.value.lg_commission_rate = data.lg_commission_rate || form.value.lg_commission_rate;
            form.value.cash_cover_rate = data.min_lg_cash_cover_rate_for_current_lg_type || form.value.cash_cover_rate;
            form.value.issuance_fees = data.min_lg_issuance_fees_for_current_lg_type ?? form.value.issuance_fees;
        }
    } finally {
        lookupLoading.value = false;
    }
}
watch(() => [form.value.financial_institution_id, form.value.lg_facility_id, form.value.lg_type], runLookup, { immediate: true });

// If the selected cash-cover account type or currency changes and the
// currently-picked account no longer matches, clear it rather than
// silently leaving a stale, now-invalid selection in place.
watch(() => [form.value.cash_cover_deducted_from_account_type, form.value.lg_currency], () => {
    const stillValid = cashCoverAccountOptions.value.some(a => a.id === Number(form.value.cash_cover_deducted_from_account_id));
    if (!stillValid) form.value.cash_cover_deducted_from_account_id = '';
});
watch(() => [form.value.lg_fees_and_commission_account_type, form.value.lg_currency], () => {
    const stillValid = feesAccountOptions.value.some(a => a.id === Number(form.value.lg_fees_and_commission_account_id));
    if (!stillValid) form.value.lg_fees_and_commission_account_id = '';
});

/* ── Auto-calculated fields — replicated from the original's own
   formulas (confirmed from store()'s $maxLgCommissionAmount = max(...)
   and the cash-cover-rate × LG-amount relationship): ─────────────── */
/*
 * Reversed on request: the user now picks Issuance Date AND Renewal
 * Date directly; LG Duration (Months) becomes the read-only,
 * auto-calculated field — rounded UP (ceiling), since it feeds the
 * commission calculations downstream (matches the ceiling rounding
 * already used elsewhere in this same feature for quarterly
 * commission iterations). This calculation itself is new — the
 * original never auto-computed a duration from two dates, the user
 * just typed the month count directly.
 */
function monthsBetweenCeil(startIso, endIso) {
    const start = new Date(startIso);
    const end = new Date(endIso);
    let months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
    if (end.getDate() > start.getDate()) months += 1;
    return Math.max(1, months);
}
watch(() => [form.value.issuance_date, form.value.renewal_date], () => {
    if (!form.value.issuance_date || !form.value.renewal_date) return;
    form.value.lg_duration_months = monthsBetweenCeil(form.value.issuance_date, form.value.renewal_date);
});
watch(() => [form.value.lg_amount, form.value.cash_cover_rate], () => {
    form.value.cash_cover_amount = Math.round((Number(form.value.lg_amount || 0) * Number(form.value.cash_cover_rate || 0) / 100) * 100) / 100;
});
watch(() => [form.value.lg_amount, form.value.lg_commission_rate], () => {
    form.value.lg_commission_amount = Math.round((Number(form.value.lg_amount || 0) * Number(form.value.lg_commission_rate || 0) / 100) * 100) / 100;
});

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = { ...form.value };
    if (addingNewCustomer.value) {
        payload.new_customer_name = newCustomerName.value;
    }
    // storeBasicForm() only converts a submitted value to NULL when it's
    // the literal string 'null' — an empty string sails through as-is
    // and MySQL rejects it as an invalid foreign key (confirmed: this
    // caused a real "Cannot add or update a child row" error on
    // contract_id when no contract was selected).
    if (!payload.contract_id) payload.contract_id = 'null';
    // 'all' is the "All SOs" choice — it means "no specific SO",
    // same as leaving the field empty, so it's stored as null too.
    if (!payload.purchase_order_id || payload.purchase_order_id === 'all') payload.purchase_order_id = 'null';
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
                    ← Back to LG Issuance
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? 'Edit' : 'Add' }} LG Issuance — Via LG Facility
            </h1>

            <FormErrorSummary />
            <p v-if="errorFor('cash_cover_amount')" class="mb-4 text-sm cvr-num-red">{{ errorFor('cash_cover_amount') }}</p>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Main Information -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Main Information</h2>
                    <div class="cvr-form-grid-6-4-2">
                        <div>
                            <label class="cvr-form-label">Bank Name *</label>
                            <select v-model="form.financial_institution_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="b in financialInstitutionBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Transaction Name *</label>
                            <input v-model="form.transaction_name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('transaction_name')" class="text-xs mt-1 cvr-num-red">{{ errorFor('transaction_name') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Issuance Type *</label>
                            <select v-model="form.category_name" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="(label, code) in lgCategories" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Facility & Limits -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
                        LG Facility &amp; Limits
                        <span v-if="lookupLoading" class="text-xs font-normal cvr-text-muted normal-case">(updating...)</span>
                    </h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">LG Facility *</label>
                            <select v-model="form.lg_facility_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="f in lgFacilitiesForBank" :key="f.id" :value="f.id">{{ f.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Limit</label>
                            <input disabled :value="form.limit" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Total LGs Outstanding Balance</label>
                            <input disabled :value="form.total_lg_outstanding_balance" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Total LGs Room</label>
                            <input disabled :value="form.total_lg_room" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Type *</label>
                            <select v-model="form.lg_type" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="(label, code) in lgTypes" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Type Outstanding Balance</label>
                            <input disabled :value="form.lg_type_outstanding_balance" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Code *</label>
                            <input v-model="form.lg_code" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Beneficiary & Reference -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Beneficiary &amp; Reference</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="cvr-form-label">Customer / Beneficiary *</label>
                                <button type="button" @click="addingNewCustomer = !addingNewCustomer" class="text-xs cvr-text-blue">
                                    {{ addingNewCustomer ? 'Pick existing' : '+ New' }}
                                </button>
                            </div>
                            <select v-if="!addingNewCustomer" v-model="form.partner_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="c in customerOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <input v-else v-model="newCustomerName" type="text" placeholder="Enter new customer name" class="cvr-input w-full px-3 py-2 rounded" />
                            <p class="text-xs cvr-text-muted mt-1">
                                {{ form.lg_type === 'bid-bond' ? 'Bid Bond: showing all customers' : 'Showing only customers with an active contract' }}
                            </p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Transaction Reference *</label>
                            <input v-model="form.transaction_reference" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div v-if="!isBidBond">
                            <label class="cvr-form-label">Contract *</label>
                            <select v-model="form.contract_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="c in contractsForCustomer" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="errorFor('contract_id')" class="text-xs mt-1 cvr-num-red">{{ errorFor('contract_id') }}</p>
                        </div>
                        <div v-if="!isBidBond">
                            <label class="cvr-form-label">SO *</label>
                            <select v-model="form.purchase_order_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option value="all">All SOs</option>
                                <option v-for="po in purchaseOrdersForContract" :key="po.id" :value="po.id">{{ po.po_number }}</option>
                            </select>
                        </div>
                        <div v-if="!isBidBond">
                            <label class="cvr-form-label">Sales Order Date *</label>
                            <input v-model="form.purchase_order_date" type="date" :disabled="isSpecificSoSelected" class="cvr-input w-full px-3 py-2 rounded" :class="{ 'opacity-70': isSpecificSoSelected }" />
                            <p class="text-xs cvr-text-muted mt-1">
                                {{ isSpecificSoSelected ? 'Auto-filled from the selected SO' : 'Pick a date for this contract-wide LG' }}
                            </p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Transaction Date *</label>
                            <input v-model="form.transaction_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Dates & Amount -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Dates &amp; Amount</h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Issuance Date *</label>
                            <input v-model="form.issuance_date" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Renewal Date *</label>
                            <input v-model="form.renewal_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Duration (Months)</label>
                            <input disabled :value="form.lg_duration_months" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <p class="text-xs cvr-text-muted mt-1">Rounded up — feeds commission calculations</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Amount *</label>
                            <input v-model="form.lg_amount" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('lg_amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor('lg_amount') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Currency</label>
                            <input disabled :value="form.lg_currency?.toUpperCase()" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <p class="text-xs cvr-text-muted mt-1">Set automatically from the selected LG Facility's currency</p>
                        </div>
                    </div>
                </div>

                <!-- Cash Cover & Commission -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Cash Cover &amp; Commission</h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Cash Cover Rate (%) *</label>
                            <input v-model="form.cash_cover_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Cash Cover Amount</label>
                            <input disabled :value="form.cash_cover_amount" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Commission Rate (%) *</label>
                            <input v-model="form.lg_commission_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Commission Amount</label>
                            <input disabled :value="form.lg_commission_amount" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Min LG Commission Fees</label>
                            <input disabled :value="form.min_lg_commission_fees" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Issuance Fees *</label>
                            <input v-model="form.issuance_fees" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Commission Interval *</label>
                            <select v-model="form.lg_commission_interval" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, code) in commissionIntervals" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Accounts -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Accounts</h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Cash Cover From Account Type *</label>
                            <select v-model="form.cash_cover_deducted_from_account_type" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="t in cashCoverAccountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Cash Cover Deducted From Account #</label>
                            <select v-model="form.cash_cover_deducted_from_account_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="a in cashCoverAccountOptions" :key="a.id" :value="a.id">{{ a.account_number }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount</label>
                            <input disabled :value="selectedCashCoverAccount?.amount ?? 0" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <p class="text-xs cvr-text-muted mt-1">TD/CD deposit amount, or the current account's last known balance</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Fees &amp; Commission Account Type *</label>
                            <select v-model="form.lg_fees_and_commission_account_type" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="t in feesAccountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Deducted From Account # (Fees &amp; Commission) *</label>
                            <select v-model="form.lg_fees_and_commission_account_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="a in feesAccountOptions" :key="a.id" :value="a.id">{{ a.account_number }} ({{ a.currency?.toUpperCase() }})</option>
                            </select>
                            <p v-if="errorFor('lg_fees_and_commission_account_id')" class="text-xs mt-1 cvr-num-red">{{ errorFor('lg_fees_and_commission_account_id') }}</p>
                        </div>
                    </div>
                    <p class="text-xs cvr-text-muted mt-3">Both account dropdowns only show accounts belonging to the selected Bank Name above.</p>
                </div>

                <!-- User Comment -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">User Comment</h2>
                    <textarea v-model="form.user_comment" rows="3" class="cvr-input w-full px-3 py-2 rounded" placeholder="Comment"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">Cancel</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
