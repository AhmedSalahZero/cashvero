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
    lgTypes: Object,
    lgCategories: Object,
    commissionIntervals: Object,
    financialInstitutionBanks: Array, // [{id, name}]
    feesAccounts: Array,              // [{id, financial_institution_id, account_number, currency}]
    contracts: Array,
    purchaseOrders: Array,
    model: Object,
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
    lg_currency: props.model?.lg_currency ?? '',
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
    // Defaults to 100 — this source is "100% Cash Cover" by definition,
    // confirmed from the original blade's own default value.
    cash_cover_rate: props.model?.cash_cover_rate ?? 100,
    cash_cover_amount: props.model?.cash_cover_amount ?? 0,
    lg_commission_rate: props.model?.lg_commission_rate ?? 0,
    lg_commission_amount: props.model?.lg_commission_amount ?? 0,
    min_lg_commission_fees: props.model?.min_lg_commission_fees ?? 0,
    issuance_fees: props.model?.issuance_fees ?? 0,
    lg_commission_interval: props.model?.lg_commission_interval ?? 'quarterly',
    lg_fees_and_commission_account_id: props.model?.lg_fees_and_commission_account_id ?? '',
    user_comment: props.model?.user_comment ?? '',
});

const addingNewCustomer = ref(false);
const newCustomerName = ref('');

const contractsForCustomer = computed(() => props.contracts.filter(c => c.partner_id === Number(form.value.partner_id)));
const purchaseOrdersForContract = computed(() => props.purchaseOrders.filter(po => po.contract_id === Number(form.value.contract_id)));

/*
 * ⚠️ Confirmed by tracing store(): this source has no separate Cash
 * Cover account field at all — $cashCoverDeductedFromAccountId falls
 * back to the Fees & Commission account whenever none is submitted.
 * So there's genuinely only ONE account selector here, used for both.
 */
const feesAccountOptions = computed(() =>
    props.feesAccounts.filter(a =>
        a.financial_institution_id === Number(form.value.financial_institution_id) &&
        a.currency === form.value.lg_currency
    )
);
watch(() => [form.value.financial_institution_id, form.value.lg_currency], () => {
    const stillValid = feesAccountOptions.value.some(a => a.id === Number(form.value.lg_fees_and_commission_account_id));
    if (!stillValid) form.value.lg_fees_and_commission_account_id = '';
});

/* ── Live LG Type Outstanding Balance + customer list lookup ────── */
const customerOptions = ref([]);
const lookupLoading = ref(false);
async function runLookup() {
    if (!form.value.financial_institution_id) return;
    lookupLoading.value = true;
    try {
        const params = new URLSearchParams({
            financialInstitutionId: form.value.financial_institution_id,
            lgType: form.value.lg_type || '',
            source: props.source,
            lgCurrency: form.value.lg_currency || '',
        });
        if (props.model?.id) params.set('lgIssuanceId', props.model.id);
        const res = await fetch(`${props.lookupUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        form.value.lg_type_outstanding_balance = data.current_lg_type_outstanding_balance ?? 0;
        customerOptions.value = Object.entries(data.customers ?? {}).map(([name, id]) => ({ id: Number(id), name }));
        if (!isEdit) {
            form.value.min_lg_commission_fees = data.min_lg_commission_fees ?? 0;
            form.value.lg_commission_rate = data.lg_commission_rate || form.value.lg_commission_rate;
            form.value.issuance_fees = data.min_lg_issuance_fees_for_current_lg_type ?? form.value.issuance_fees;
        }
    } finally {
        lookupLoading.value = false;
    }
}
watch(() => [form.value.financial_institution_id, form.value.lg_currency, form.value.lg_type], runLookup, { immediate: true });

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
    if (addingNewCustomer.value) payload.new_customer_name = newCustomerName.value;
    // storeBasicForm() only converts a submitted value to NULL when it's
    // the literal string 'null' — an empty string is rejected by MySQL
    // as an invalid foreign key (same confirmed fix as the other 2 forms).
    if (!payload.contract_id) payload.contract_id = 'null';
    if (!payload.purchase_order_id) payload.purchase_order_id = 'null';
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
                {{ isEdit ? 'Edit' : 'Add' }} LG Issuance — 100% Cash Cover
            </h1>

            <div v-if="Object.keys(page.props.errors || {}).length" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
                Please fix the highlighted field(s) below before saving.
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Main Information -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Main Information</h2>
                    <div class="cvr-form-grid-7-3-2">
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

                <!-- Currency & LG Type -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
                        Currency &amp; LG Type
                        <span v-if="lookupLoading" class="text-xs font-normal cvr-text-muted normal-case">(updating...)</span>
                    </h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">LG Currency *</label>
                            <select v-model="form.lg_currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
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
                        <div>
                            <label class="cvr-form-label">Contract</label>
                            <select v-model="form.contract_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">None</option>
                                <option v-for="c in contractsForCustomer" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
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
                            <input v-model="form.min_lg_commission_fees" type="number" class="cvr-input w-full px-3 py-2 rounded" />
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

                <!-- Cash Cover & Fees Account -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Cash Cover &amp; Fees Account</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">Deducted From Account # (Cash Cover &amp; Fees) *</label>
                            <select v-model="form.lg_fees_and_commission_account_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="a in feesAccountOptions" :key="a.id" :value="a.id">{{ a.account_number }} ({{ a.currency?.toUpperCase() }})</option>
                            </select>
                            <p v-if="errorFor('lg_fees_and_commission_account_id')" class="text-xs mt-1 cvr-num-red">{{ errorFor('lg_fees_and_commission_account_id') }}</p>
                        </div>
                    </div>
                    <p class="text-xs cvr-text-muted mt-3">
                        This source uses one account for both the cash cover deposit and issuance/commission fees — confirmed from the original: no separate Cash Cover account field exists for 100% Cash Cover.
                    </p>
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
