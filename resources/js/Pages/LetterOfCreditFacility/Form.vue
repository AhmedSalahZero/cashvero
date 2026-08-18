<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';

const props = defineProps({
    mode: String, // 'create' | 'edit'
    company: Object,
    financialInstitution: Object,
    currencies: Object,
    lcTypes: Object,       // { 'sight-lc': 'Sight LC', ... }
    facilityTypes: Object, // { 'unsecured': 'Unsecured', 'fully-secured': 'Fully Secured' }
    cdOrTdAccountTypes: Array, // [{id, name}]
    cdOrTdAccounts: Array,     // [{id, account_type_id, account_number, currency, amount, interest_rate}]
    model: Object,          // null in create mode
    submitUrl: String,
    backUrl: String,
    navUrls: Object,
});

const page = usePage();
const isEdit = props.mode === 'edit';

const form = ref({
    name: props.model?.name ?? '',
    contract_start_date: props.model?.contract_start_date ?? '',
    contract_end_date: props.model?.contract_end_date ?? '',
    type: props.model?.type ?? 'unsecured',
    currency: props.model?.currency ?? '',
    limit: props.model?.limit ?? 0,
    // CD/TD fields (only meaningful when type === 'fully-secured')
    cd_or_td_currency: props.model?.cd_or_td_currency ?? '',
    cd_or_td_account_type_id: props.model?.cd_or_td_account_type_id ?? (props.cdOrTdAccountTypes[0]?.id ?? ''),
    cd_or_td_id: props.model?.cd_or_td_id ?? '',
    cd_or_td_amount: props.model?.cd_or_td_amount ?? 0,
    cd_or_td_interest: props.model?.cd_or_td_interest ?? 0,
    cd_or_td_lending_percentage: props.model?.cd_or_td_lending_percentage ?? 0,
    // Financing Terms & Conditions
    borrowing_rate: props.model?.borrowing_rate ?? 0,
    bank_margin_rate: props.model?.bank_margin_rate ?? 0,
    interest_rate: props.model?.interest_rate ?? 0,
    min_interest_rate: props.model?.min_interest_rate ?? 0,
    highest_debt_balance_rate: props.model?.highest_debt_balance_rate ?? 0,
});

const isFullySecured = computed(() => form.value.type === 'fully-secured');

/*
 * Term & Conditions is one row per LC type (Sight LC, Deferred, Cash
 * Against Document — see App\Enums\LcTypes), same fixed-matrix concept
 * as LG Facility, just 3 rows instead of 4. Pre-filled from the model
 * in edit mode, or blank defaults otherwise. Note: the original never
 * exposed a "Commission Interval" field on this particular form (the
 * column exists on the table but this page never wrote to it) —
 * matched here exactly, no field added for it.
 */
const termAndConditions = ref(
    props.model?.term_and_conditions ?? Object.keys(props.lcTypes).map(lcType => ({
        lc_type: lcType,
        cash_cover_rate: 0,
        commission_rate: 0,
        min_commission_fees: 0,
        issuance_fees: 0,
    }))
);

// When Fully Secured, Cash Cover Rate is forced to 0 and locked on
// every row — matches the original's `.cash-cover-class` readonly +
// value-reset behavior triggered by the Type dropdown.
watch(isFullySecured, (fullySecured) => {
    if (fullySecured) {
        termAndConditions.value.forEach(row => { row.cash_cover_rate = 0; });
    }
});

/* ── CD/TD account selection (Fully Secured only) ─────────────────
   Only accounts matching the selected account TYPE *and* the
   selected CD/TD currency are eligible. Selecting one auto-fills its
   amount/interest rate, and — matching the original's AJAX callback
   exactly — also sets Borrowing Rate to that account's interest
   rate. Done client-side against pre-loaded accounts, same approach
   already used on FullySecuredOverdraft/Form.vue. ──────────────── */
const accountsForType = computed(() =>
    props.cdOrTdAccounts.filter(a =>
        a.account_type_id === Number(form.value.cd_or_td_account_type_id) &&
        a.currency === form.value.cd_or_td_currency
    )
);

function onAccountSelected() {
    const account = props.cdOrTdAccounts.find(a => a.id === Number(form.value.cd_or_td_id));
    if (account) {
        form.value.cd_or_td_amount = account.amount;
        form.value.cd_or_td_interest = account.interest_rate;
        form.value.borrowing_rate = account.interest_rate;
    }
}

watch(() => [form.value.cd_or_td_currency, form.value.cd_or_td_account_type_id], () => {
    const stillValid = accountsForType.value.some(a => a.id === Number(form.value.cd_or_td_id));
    if (!stillValid) {
        form.value.cd_or_td_id = '';
        form.value.cd_or_td_amount = 0;
        form.value.cd_or_td_interest = 0;
    }
});

/* ── Limit auto-calculation when Fully Secured: CD/TD amount ×
   lending percentage. When Unsecured, Limit is entered directly. ── */
function recalculateLimit() {
    if (!isFullySecured.value) return;
    const amount = Number(form.value.cd_or_td_amount) || 0;
    const percentage = Number(form.value.cd_or_td_lending_percentage) || 0;
    form.value.limit = Math.round((amount * percentage / 100) * 100) / 100;
}
watch(() => [form.value.cd_or_td_amount, form.value.cd_or_td_lending_percentage, form.value.type], recalculateLimit);

/* ── Interest rate = borrowing rate + bank margin rate (always) ─── */
function recalculateInterest() {
    form.value.interest_rate = (Number(form.value.borrowing_rate || 0) + Number(form.value.bank_margin_rate || 0));
}
watch(() => [form.value.borrowing_rate, form.value.bank_margin_rate], recalculateInterest);

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        ...form.value,
        financial_institution_id: props.financialInstitution.id,
        cd_or_td_limit: isFullySecured.value ? form.value.limit : 0,
        termAndConditions: termAndConditions.value,
    };
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
                    ← Back to LC Facility
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? 'Edit' : 'Add' }} LC Facility
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ financialInstitution.name }}</p>

            <FormErrorSummary />

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Contract Main Information -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Contract Main Information</h2>
                    <div class="cvr-form-grid-8-4 mb-3">
                        <div>
                            <label class="cvr-form-label">Bank Name</label>
                            <input disabled :value="financialInstitution.name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Name *</label>
                            <input v-model="form.name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('name')" class="text-xs mt-1 cvr-num-red">{{ errorFor('name') }}</p>
                        </div>
                    </div>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Contract Start Date *</label>
                            <input v-model="form.contract_start_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Contract End Date *</label>
                            <input v-model="form.contract_end_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Type *</label>
                            <select v-model="form.type" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, code) in facilityTypes" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div v-if="!isFullySecured">
                            <label class="cvr-form-label">Limit *</label>
                            <input v-model="form.limit" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Select Currency *</label>
                            <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- CD Or TD Information — only when Fully Secured -->
                <div v-if="isFullySecured" class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">CD Or TD Information</h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Select Currency *</label>
                            <select v-model="form.cd_or_td_currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Account Type</label>
                            <select v-model="form.cd_or_td_account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="t in cdOrTdAccountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Account Number</label>
                            <select v-model="form.cd_or_td_id" @change="onAccountSelected" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="a in accountsForType" :key="a.id" :value="a.id">{{ a.account_number }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount</label>
                            <input disabled :value="form.cd_or_td_amount" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">CD Or TD Interest Rate</label>
                            <input disabled :value="form.cd_or_td_interest" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">CD Or TD Lending Percentage (%) *</label>
                            <input v-model="form.cd_or_td_lending_percentage" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Limit</label>
                            <input disabled :value="form.limit" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <p class="text-xs cvr-text-muted mt-1">CD/TD amount × lending percentage</p>
                        </div>
                    </div>
                </div>

                <!-- Term & Conditions -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Term &amp; Conditions — by LC Type</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">LC Type</th>
                                    <th class="px-3 py-2 text-left">Cash Cover Rate (%)</th>
                                    <th class="px-3 py-2 text-left">Commission Rate (%)</th>
                                    <th class="px-3 py-2 text-left">Min Commission Fees</th>
                                    <th class="px-3 py-2 text-left">Issuance Fees</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in termAndConditions" :key="row.lc_type" class="cvr-table-row">
                                    <td class="px-3 py-2 whitespace-nowrap cvr-text-primary font-medium">{{ lcTypes[row.lc_type] }}</td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="row.cash_cover_rate"
                                            type="number" step="any"
                                            :disabled="isFullySecured"
                                            class="cvr-input px-2 py-1.5 rounded w-24"
                                            :class="{ 'opacity-70': isFullySecured }"
                                        />
                                    </td>
                                    <td class="px-3 py-2"><input v-model="row.commission_rate" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-24" /></td>
                                    <td class="px-3 py-2"><input v-model="row.min_commission_fees" type="number" class="cvr-input px-2 py-1.5 rounded w-28" /></td>
                                    <td class="px-3 py-2"><input v-model="row.issuance_fees" type="number" class="cvr-input px-2 py-1.5 rounded w-28" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Financing Terms & Conditions -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Financing Terms &amp; Conditions</h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Borrowing Rate (%) *</label>
                            <input v-model="form.borrowing_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Bank Margin Rate (%) *</label>
                            <input v-model="form.bank_margin_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Interest Rate (%) *</label>
                            <input disabled :value="form.interest_rate" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Min Interest Rate (%) *</label>
                            <input v-model="form.min_interest_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Highest Debt Balance Rate (%) *</label>
                            <input v-model="form.highest_debt_balance_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
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
