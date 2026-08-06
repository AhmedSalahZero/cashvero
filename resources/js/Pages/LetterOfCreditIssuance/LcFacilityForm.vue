<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { todayDate } from '@/composables/today';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate();

const props = defineProps({
    mode: String, // 'create' | 'edit'
    company: Object,
    source: String,
    currencies: Object,
    lcTypes: Object,
    lcCategories: Object,
    financialInstitutionBanks: Array, // [{id, name, lc_facilities: [{id, name}]}]
    beneficiaries: Array,              // [{id, name}] — supplier partners
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
    lc_facility_id: props.model?.lc_facility_id ?? '',
    limit: 0,
    total_lc_outstanding_balance: 0,
    total_lc_room: 0,
    lc_type: props.model?.lc_type ?? '',
    lc_type_outstanding_balance: 0,
    lc_code: props.model?.lc_code ?? '',
    partner_id: props.model?.partner_id ?? '',
    transaction_reference: props.model?.transaction_reference ?? '1',
    // Contract picker: -1 = "New PO" (type new PO number in free text),
    // -2 = "Existing PO" (pick from the Purchase Order dropdown instead
    // of a contract) — confirmed sentinel values read directly from
    // store()'s handling of contract_id. A real contract_id otherwise.
    contract_id: props.model?.contract_id ?? -1,
    new_purchase_order_number: props.model?.new_purchase_order_number ?? '',
    purchase_order_id: props.model?.purchase_order_id ?? '',
    purchase_order_date: props.model?.purchase_order_date ?? '',
    transaction_date: props.model?.transaction_date ?? '',
    issuance_date: props.model?.issuance_date ?? '',
    lc_duration_days: props.model?.lc_duration_days ?? 1,
    due_date: props.model?.due_date ?? '',
    lc_amount: props.model?.lc_amount ?? 0,
    lc_currency: props.model?.lc_currency ?? 'usd',
    exchange_rate: props.model?.exchange_rate ?? 1,
    amount_in_main_currency: props.model?.amount_in_main_currency ?? 0,
    cash_cover_rate: props.model?.cash_cover_rate ?? 0,
    cash_cover_amount: props.model?.cash_cover_amount ?? 0,
    lc_cash_cover_currency: props.model?.lc_cash_cover_currency ?? '',
    lc_commission_rate: props.model?.lc_commission_rate ?? 0,
    lc_commission_amount: props.model?.lc_commission_amount ?? 0,
    min_lc_commission_fees: props.model?.min_lc_commission_fees ?? 0,
    issuance_fees: props.model?.issuance_fees ?? 0,
    cash_cover_deducted_from_account_type: props.model?.cash_cover_deducted_from_account_type ?? (props.cashCoverAccountTypes[0]?.id ?? ''),
    cash_cover_deducted_from_account_id: props.model?.cash_cover_deducted_from_account_id ?? '',
    lc_fees_and_commission_account_type: props.model?.lc_fees_and_commission_account_type ?? (props.feesAccountTypes[0]?.id ?? ''),
    lc_fees_and_commission_account_id: props.model?.lc_fees_and_commission_account_id ?? '',
    financed_by_bank_or_self: props.model?.financed_by_bank_or_self ?? 'bank',
    financing_duration: props.model?.financing_duration ?? 0,
    user_comment: props.model?.user_comment ?? '',
});

/*
 * ⚠️ Confirmed dead code in the original, not ported: a "show only
 * Bid Bond" / "hide only Bid Bond" toggle exists in the Blade form,
 * but only ever fires for lc_type === 'bid-bond' — a value that
 * doesn't exist in App\Enums\LcTypes (LC only has Sight LC / Deferred
 * / Cash Against Document). So the fields it would hide (Contract/PO)
 * are always shown, and the fields it would show (an inline "Add New
 * Customer" button) are never reachable. Left out entirely here —
 * the Contract/PO section below is simply always shown, matching
 * actual runtime behavior.
 */
const addingNewCustomer = ref(false);
const newCustomerName = ref('');

const selectedBank = computed(() => props.financialInstitutionBanks.find(b => b.id === Number(form.value.financial_institution_id)));
const lcFacilitiesForBank = computed(() => selectedBank.value?.lc_facilities ?? []);

const contractsForCustomer = computed(() =>
    props.contracts.filter(c => c.partner_id === Number(form.value.partner_id))
);
const purchaseOrdersForContract = computed(() =>
    props.purchaseOrders.filter(po => po.contract_id === Number(form.value.contract_id))
);
// The two sentinel contract options mean "no contract" — the PO
// dropdown / new-PO text field take over instead.
const showNewPoInput = computed(() => Number(form.value.contract_id) === -1);
const showExistingPoDropdown = computed(() => Number(form.value.contract_id) === -2);

const accountsForBank = computed(() =>
    props.accounts.filter(a => a.financial_institution_id === Number(form.value.financial_institution_id))
);
const cashCoverAccountOptions = computed(() =>
    accountsForBank.value.filter(a =>
        a.account_type_id === Number(form.value.cash_cover_deducted_from_account_type) &&
        a.currency === form.value.lc_cash_cover_currency
    )
);
const feesAccountOptions = computed(() =>
    accountsForBank.value.filter(a =>
        a.account_type_id === Number(form.value.lc_fees_and_commission_account_type) &&
        a.currency === form.value.lc_currency
    )
);
watch(() => [form.value.cash_cover_deducted_from_account_type, form.value.lc_cash_cover_currency], () => {
    const stillValid = cashCoverAccountOptions.value.some(a => a.id === Number(form.value.cash_cover_deducted_from_account_id));
    if (!stillValid) form.value.cash_cover_deducted_from_account_id = '';
});
watch(() => [form.value.lc_fees_and_commission_account_type, form.value.lc_currency], () => {
    const stillValid = feesAccountOptions.value.some(a => a.id === Number(form.value.lc_fees_and_commission_account_id));
    if (!stillValid) form.value.lc_fees_and_commission_account_id = '';
});

/*
 * ⚠️ Confirmed missing business rule, traced from the original's
 * shared commonJs.blade.php `select.lc-currency` handler: LC Cash
 * Cover Currency is NOT a free pick from every currency — it's
 * restricted to just the company's main functional currency, plus
 * the selected LC Currency itself (if different). If LC Currency
 * equals the main functional currency, Cash Cover Currency only has
 * that one option and is auto-selected. Shared behavior with
 * HundredPercentageCashCoverForm.vue (same original JS handler covers
 * both forms).
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

function currencyLabel(code) {
    return props.currencies[code] ?? code?.toUpperCase();
}

/* ── Live limit / outstanding-balance / commission-rate lookup —
   genuinely dynamic server-side calculation (UNCHANGED,
   untouched updateOutstandingBalanceAndLimits() endpoint). ────────── */
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
            letterOfCreditFacilityId: form.value.lc_facility_id || '',
        });
        if (props.model?.id) params.set('lcIssuanceId', props.model.id);
        const res = await fetch(`${props.lookupUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
        // Ignore this response if a newer lookup has already started —
        // prevents a slow, stale request from overwriting a more
        // recent selection's correct values.
        if (thisRequest !== lookupRequestToken) return;
        if (!res.ok) {
            throw new Error(`Lookup failed (HTTP ${res.status})`);
        }
        const data = await res.json();
        form.value.limit = data.limit ?? 0;
        form.value.total_lc_outstanding_balance = data.total_lc_outstanding_balance ?? 0;
        form.value.total_lc_room = data.total_room ?? 0;
        form.value.lc_type_outstanding_balance = data.current_lc_type_outstanding_balance ?? 0;
        if (!isEdit) {
            form.value.min_lc_commission_fees = data.min_lc_commission_rate ?? 0;
            form.value.lc_commission_rate = data.lc_commission_rate || form.value.lc_commission_rate;
            form.value.cash_cover_rate = data.min_lc_cash_cover_rate_for_current_lc_type || form.value.cash_cover_rate;
            form.value.issuance_fees = data.min_lc_issuance_fees_for_current_lc_type ?? form.value.issuance_fees;
        }
    } catch (err) {
        if (thisRequest !== lookupRequestToken) return;
        console.error('LC Facility & Limits lookup failed:', err);
        lookupError.value = 'Could not load LC Facility limit/balance data — check your connection and try reselecting the bank/facility.';
    } finally {
        if (thisRequest === lookupRequestToken) lookupLoading.value = false;
    }
}
watch(() => [form.value.financial_institution_id, form.value.lc_facility_id, form.value.lc_type], runLookup, { immediate: true });

/* ── Due Date = Issuance Date + LC Duration (Days) — read-only,
   auto-calculated, matches the original's own client-side formula
   exactly (`issuanceDate.addDays(duration)`). ─────────────────────── */
function addDaysIso(dateIso, days) {
    if (!dateIso) return '';
    const d = new Date(dateIso);
    d.setDate(d.getDate() + Number(days || 0));
    return d.toISOString().split('T')[0];
}
watch(() => [form.value.issuance_date, form.value.lc_duration_days], () => {
    form.value.due_date = addDaysIso(form.value.issuance_date, form.value.lc_duration_days);
}, { immediate: true });

/* ── Auto-calculated amounts — replicated from the original's own
   formulas (amount_in_main_currency = LC amount × exchange rate; cash
   cover / commission amounts are rate × that main-currency amount /
   LC amount respectively, matching the original's exact field
   references). ────────────────────────────────────────────────────── */
watch(() => [form.value.lc_amount, form.value.exchange_rate], () => {
    form.value.amount_in_main_currency = Math.round((Number(form.value.lc_amount || 0) * Number(form.value.exchange_rate || 0)) * 100) / 100;
});
watch(() => [form.value.amount_in_main_currency, form.value.cash_cover_rate], () => {
    form.value.cash_cover_amount = Math.round((Number(form.value.amount_in_main_currency || 0) * Number(form.value.cash_cover_rate || 0) / 100) * 100) / 100;
});
watch(() => [form.value.lc_amount, form.value.lc_commission_rate], () => {
    form.value.lc_commission_amount = Math.round((Number(form.value.lc_amount || 0) * Number(form.value.lc_commission_rate || 0) / 100) * 100) / 100;
});

// Financing Duration only applies when financed by the bank —
// matches the original's show/hide + reset-to-0 behavior exactly.
watch(() => form.value.financed_by_bank_or_self, (val) => {
    if (val !== 'bank') form.value.financing_duration = 0;
});

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = { ...form.value };
    if (addingNewCustomer.value) payload.new_customer_name = newCustomerName.value;
    // Defense in depth: contract_id should always be -1, -2, or a real
    // contract id (the <select> always has -1/-2 as real options and
    // now defaults to -1), but if it were ever empty, storeBasicForm()
    // would only null it out for the literal string 'null' — an empty
    // value otherwise sails through and MySQL coerces it toward 0,
    // which isn't a real contract, causing a foreign key violation on
    // insert. Confirmed root cause of a real bug; guarded here.
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
                    ← Back to LC Issuance
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? 'Edit' : 'Add' }} LC Issuance — Via LC Facility
            </h1>

            <div v-if="Object.keys(page.props.errors || {}).length" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
                Please fix the highlighted field(s) below before saving.
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Letter Of Credit Type -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Letter Of Credit Type</h2>
                    <div class="cvr-form-grid-3 mb-3">
                        <div>
                            <label class="cvr-form-label">Issuance Type *</label>
                            <select v-model="form.category_name" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="(label, code) in lcCategories" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Transaction Name *</label>
                            <input v-model="form.transaction_name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('transaction_name')" class="text-xs mt-1 cvr-num-red">{{ errorFor('transaction_name') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Bank *</label>
                            <select v-model="form.financial_institution_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="b in financialInstitutionBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>
                    <h3 class="text-xs font-semibold cvr-text-muted uppercase tracking-wide mb-3 mt-2">
                        Facility &amp; Limits
                        <span v-if="lookupLoading" class="font-normal normal-case">(updating...)</span>
                    </h3>
                    <p v-if="lookupError" class="text-xs cvr-num-red mb-3">{{ lookupError }}</p>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">LC Facility *</label>
                            <select v-model="form.lc_facility_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="f in lcFacilitiesForBank" :key="f.id" :value="f.id">{{ f.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Limit</label>
                            <input disabled :value="form.limit" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Total LCs Outstanding Balance</label>
                            <input disabled :value="form.total_lc_outstanding_balance" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Total LCs Room</label>
                            <input disabled :value="form.total_lc_room" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Type *</label>
                            <select v-model="form.lc_type" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="(label, code) in lcTypes" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Type Outstanding Balance</label>
                            <input disabled :value="form.lc_type_outstanding_balance" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Code *</label>
                            <input v-model="form.lc_code" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Beneficiary & Reference -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Beneficiary &amp; Reference</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">Beneficiary Name *</label>
                            <select v-model="form.partner_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="b in beneficiaries" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Contract Reference *</label>
                            <select v-model="form.contract_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="-1">New PO</option>
                                <option value="-2">Existing PO</option>
                                <option v-for="c in contractsForCustomer" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div v-if="showExistingPoDropdown">
                            <label class="cvr-form-label">Purchase Order *</label>
                            <select v-model="form.purchase_order_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">None</option>
                                <option v-for="po in purchaseOrders" :key="po.id" :value="po.id">{{ po.po_number }}</option>
                            </select>
                        </div>
                        <div v-else-if="showNewPoInput">
                            <label class="cvr-form-label">New PO *</label>
                            <input v-model="form.new_purchase_order_number" type="text" placeholder="New PO" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div v-else>
                            <label class="cvr-form-label">Purchase Order</label>
                            <select v-model="form.purchase_order_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">None</option>
                                <option v-for="po in purchaseOrdersForContract" :key="po.id" :value="po.id">{{ po.po_number }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Purchase Order Date *</label>
                            <input v-model="form.purchase_order_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Letter Of Credit Information -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Letter Of Credit Information</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">Issuance Date *</label>
                            <input v-model="form.issuance_date" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Duration (Days) *</label>
                            <input v-model="form.lc_duration_days" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Due Date</label>
                            <input disabled :value="form.due_date" type="date" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <p class="text-xs cvr-text-muted mt-1">Issuance Date + LC Duration</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Amount *</label>
                            <input v-model="form.lc_amount" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('lc_amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor('lc_amount') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Currency *</label>
                            <select v-model="form.lc_currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Exchange Rate *</label>
                            <input v-model="form.exchange_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount In Payment Currency</label>
                            <input disabled :value="form.amount_in_main_currency" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Cash Cover Rate (%) *</label>
                            <input v-model="form.cash_cover_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Cash Cover Currency *</label>
                            <select v-model="form.lc_cash_cover_currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="code in cashCoverCurrencyOptions" :key="code" :value="code">{{ currencyLabel(code) }}</option>
                            </select>
                            <p class="text-xs cvr-text-muted mt-1">Limited to the company's main functional currency and the LC Currency above</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Cash Cover Amount</label>
                            <input disabled :value="form.cash_cover_amount" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Commission Rate (%) *</label>
                            <input v-model="form.lc_commission_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LC Commission Amount</label>
                            <input disabled :value="form.lc_commission_amount" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Min LC Commission Fees</label>
                            <input disabled :value="form.min_lc_commission_fees" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Issuance Fees</label>
                            <input disabled :value="form.issuance_fees" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
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
                            <label class="cvr-form-label">Cash Cover Account #</label>
                            <select v-model="form.cash_cover_deducted_from_account_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="a in cashCoverAccountOptions" :key="a.id" :value="a.id">{{ a.account_number }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Fees &amp; Commission Account Type *</label>
                            <select v-model="form.lc_fees_and_commission_account_type" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="t in feesAccountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Deducted From Account # (Fees &amp; Commission) *</label>
                            <select v-model="form.lc_fees_and_commission_account_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="a in feesAccountOptions" :key="a.id" :value="a.id">{{ a.account_number }} ({{ a.currency?.toUpperCase() }})</option>
                            </select>
                            <p v-if="errorFor('lc_fees_and_commission_account_id')" class="text-xs mt-1 cvr-num-red">{{ errorFor('lc_fees_and_commission_account_id') }}</p>
                        </div>
                    </div>
                    <p class="text-xs cvr-text-muted mt-3">Both account dropdowns only show accounts belonging to the selected Bank above.</p>
                </div>

                <!-- Financing -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Financing</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">Self Financed Or By Bank *</label>
                            <select v-model="form.financed_by_bank_or_self" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="bank">By Bank</option>
                                <option value="self">Self</option>
                            </select>
                        </div>
                        <div v-if="form.financed_by_bank_or_self === 'bank'">
                            <label class="cvr-form-label">Financing Duration (Days) *</label>
                            <input v-model="form.financing_duration" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
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
