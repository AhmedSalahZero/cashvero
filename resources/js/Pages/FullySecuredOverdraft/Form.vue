<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';

const props = defineProps({
    mode: String, // 'create' | 'edit'
    hasRenewals: Boolean, // edit mode only — true once the facility has a renewal
    company: Object,
    financialInstitution: Object,
    currencies: Object,
    hasOdooIntegration: Boolean,
    cdOrTdAccountTypes: Array,   // [{id, name}]
    cdOrTdAccounts: Array,       // [{id, account_type_id, account_number, currency, amount, interest_rate}]
    linkedCdOrTdAmount: { type: Number, default: 0 }, // edit mode, locked-by-renewal: the already-linked account's own amount
    model: Object,               // null in create mode
    submitUrl: String,
    backUrl: String,
    navUrls: Object,
});

const page = usePage();
const isEdit = props.mode === 'edit';
const isLockedByRenewal = isEdit && props.hasRenewals;

/* ── Form state ───────────────────────────────────────────────── */
const form = ref({
    contract_start_date: props.model?.contract_start_date ?? '',
    contract_end_date: props.model?.contract_end_date ?? '',
    account_number: props.model?.account_number ?? '',
    odoo_code: props.model?.odoo_code ?? '',
    currency: props.model?.currency ?? '',
    cd_or_td_account_type_id: props.model?.cd_or_td_account_type_id ?? (props.cdOrTdAccountTypes[0]?.id ?? ''),
    cd_or_td_id: props.model?.cd_or_td_id ?? '',
    cd_or_td_amount: 0,
    cd_or_td_interest: 0,
    cd_or_td_lending_percentage: props.model?.cd_or_td_lending_percentage ?? 0,
    limit: props.model?.limit ?? 0,
    outstanding_balance: props.model?.outstanding_balance ?? 0,
    balance_date: props.model?.balance_date ?? '',
    borrowing_rate: 0,
    margin_rate: 0,
    interest_rate: 0,
    highest_debt_balance_rate: props.model?.highest_debt_balance_rate ?? 0,
    admin_fees_rate: props.model?.admin_fees_rate ?? 0,
    to_be_setteled_max_within_days: props.model?.to_be_setteled_max_within_days ?? 0,
});

/* ── CD/TD account selection ─────────────────────────────────────
   Only accounts matching the selected account TYPE *and* the
   selected currency are eligible — selecting EGP should only show
   EGP-denominated TD/CD accounts of the currently-selected type.
   Selecting one auto-fills its amount/interest rate — mirrors the
   original's AJAX lookup, done client-side instead since all
   candidate accounts are already loaded. ────────────────────────── */
const accountsForType = computed(() =>
    props.cdOrTdAccounts.filter(a =>
        a.account_type_id === Number(form.value.cd_or_td_account_type_id) &&
        a.currency === form.value.currency
    )
);

function onAccountSelected() {
    const account = props.cdOrTdAccounts.find(a => a.id === Number(form.value.cd_or_td_id));
    if (account) {
        form.value.cd_or_td_amount = account.amount;
        form.value.cd_or_td_interest = account.interest_rate;
        if (!isEdit) {
            form.value.borrowing_rate = account.interest_rate;
        }
    }
}

// If the currency or account type changes and the currently-selected
// account no longer matches the new filter, clear the selection
// instead of silently leaving a stale, now-invalid choice in place.
watch(() => [form.value.currency, form.value.cd_or_td_account_type_id], () => {
    const stillValid = accountsForType.value.some(a => a.id === Number(form.value.cd_or_td_id));
    if (!stillValid) {
        form.value.cd_or_td_id = '';
        form.value.cd_or_td_amount = 0;
        form.value.cd_or_td_interest = 0;
    }
});

// If editing, pre-fill the amount/interest display for the already-linked account
if (isEdit && form.value.cd_or_td_id) {
    onAccountSelected();
}

/* ── Limit auto-calculation: CD/TD amount × lending percentage.
   While locked by a renewal, the CD/TD account can't be changed (see
   template), but the percentage still can — the amount used is the
   already-linked account's own (linkedCdOrTdAmount), not
   cd_or_td_amount (which is only populated when the picker is shown). */
function recalculateLimit() {
    const amount = isLockedByRenewal ? props.linkedCdOrTdAmount : (Number(form.value.cd_or_td_amount) || 0);
    const percentage = Number(form.value.cd_or_td_lending_percentage) || 0;
    form.value.limit = Math.round((amount * percentage / 100) * 100) / 100;
}
watch(() => [form.value.cd_or_td_amount, form.value.cd_or_td_lending_percentage], recalculateLimit);
if (isLockedByRenewal) recalculateLimit();

/* ── Interest rate = borrowing rate + margin rate (create mode only,
   rate changes after creation happen via the Rates modal instead) ─ */
function recalculateInterest() {
    form.value.interest_rate = (Number(form.value.borrowing_rate || 0) + Number(form.value.margin_rate || 0));
}
watch(() => [form.value.borrowing_rate, form.value.margin_rate], recalculateInterest);

/* ── Outstanding Breakdown repeater ───────────────────────────── */
let nextRowId = 1;
function blankBreakdownRow() {
    return { _rowId: nextRowId++, settlement_date: '', amount: 0 };
}
const outstandingBreakdowns = ref(
    props.model?.outstanding_breakdowns?.length
        ? props.model.outstanding_breakdowns.map(b => ({ _rowId: nextRowId++, ...b }))
        : [blankBreakdownRow()]
);
function addBreakdownRow() {
    outstandingBreakdowns.value.push(blankBreakdownRow());
}
function removeBreakdownRow(rowId) {
    if (outstandingBreakdowns.value.length <= 1) return;
    outstandingBreakdowns.value = outstandingBreakdowns.value.filter(r => r._rowId !== rowId);
}

/* ── Error display ────────────────────────────────────────────── */
function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

/* ── Submit ───────────────────────────────────────────────────── */
const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        ...form.value,
        outstanding_breakdowns: outstandingBreakdowns.value.map(({ _rowId, ...rest }) => rest),
    };
    // Rate fields only exist on the create-mode form in the original —
    // omit them entirely when editing, so they're not sent at all
    // (matches the original: these inputs simply don't exist in the
    // edit-mode HTML, so the request never contained them either).
    if (isEdit) {
        delete payload.borrowing_rate;
        delete payload.margin_rate;
        delete payload.interest_rate;
    }
    // Client-directed rework (2026-08-11), applied from the start here:
    // once a renewal exists, onboarding/identity data is never
    // resubmitted — dropping the keys entirely (rather than sending
    // blank/zero values) is what makes the backend leave them
    // completely untouched. CD/TD linkage fields are dropped too since
    // they're hidden in this state (see the template) — Limit becomes
    // a direct input instead of being derived from them.
    if (isLockedByRenewal) {
        delete payload.contract_start_date;
        delete payload.account_number;
        delete payload.odoo_code;
        delete payload.currency;
        delete payload.outstanding_balance;
        delete payload.balance_date;
        delete payload.outstanding_breakdowns;
        delete payload.cd_or_td_account_type_id;
        delete payload.cd_or_td_id;
        delete payload.cd_or_td_amount;
        delete payload.cd_or_td_interest;
        // cd_or_td_lending_percentage is kept — it's still editable
        // while locked, and drives the limit recalculation server-side.
    }
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
                    {{ $t('← Back to Fully Secured Overdraft') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? $t('Edit') : $t('Add') }} Fully Secured Overdraft
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ financialInstitution.name }}</p>

            <FormErrorSummary />

            <div v-if="isLockedByRenewal" class="mb-4 px-4 py-3 rounded border border-blue-400 bg-blue-50 text-blue-800 text-sm">
                {{ $t('This facility has an active renewal, so this edits the') }} <strong>{{ $t('current chapter\'s') }}</strong> {{ $t('terms only. Account details and onboarding data (Outstanding Balance / Balance Date / Outstanding Breakdown) belong to the original setup and can\'t be changed here. The linked CD/TD account itself can\'t be swapped here either — only its lending percentage, which recalculates the limit the same way it always has. To change the renewal\'s own start date, delete and re-do the renewal from the Archived Facilities tab instead.') }}
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Main Information -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Main Information') }}</h2>
                    <div class="cvr-form-grid-6-2-2-2">
                        <div>
                            <label class="cvr-form-label">{{ $t('Bank Name') }}</label>
                            <input disabled :value="financialInstitution.name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ isLockedByRenewal ? $t('Current Chapter Start Date') : $t('Contract Start Date *') }}</label>
                            <input v-model="form.contract_start_date" type="date" :disabled="isLockedByRenewal" class="cvr-input w-full px-3 py-2 rounded" :class="{ 'opacity-60': isLockedByRenewal }" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Contract End Date') }} *</label>
                            <input v-model="form.contract_end_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('contract_end_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('contract_end_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                            <input v-model="form.account_number" type="text" :disabled="isLockedByRenewal" class="cvr-input w-full px-3 py-2 rounded" :class="{ 'opacity-60': isLockedByRenewal }" />
                            <p v-if="errorFor('account_number')" class="text-xs mt-1 cvr-num-red">{{ errorFor('account_number') }}</p>
                        </div>
                    </div>
                    <div v-if="hasOdooIntegration && !isLockedByRenewal" class="cvr-form-grid-4 mt-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Odoo Code') }}</label>
                            <input v-model="form.odoo_code" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- CD Or TD Information — hidden once locked by a renewal;
                     see the Limit field note in Terms & Conditions below
                     for why. -->
                <div v-if="!isLockedByRenewal" class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('CD Or TD Information') }}</h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Select Currency') }} *</label>
                            <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Type') }}</label>
                            <select v-model="form.cd_or_td_account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="t in cdOrTdAccountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Number') }}</label>
                            <select v-model="form.cd_or_td_id" @change="onAccountSelected" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select') }}</option>
                                <option v-for="a in accountsForType" :key="a.id" :value="a.id">{{ a.account_number }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Amount') }}</label>
                            <input disabled :value="form.cd_or_td_amount" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('CD Or TD Interest Rate') }}</label>
                            <input disabled :value="form.cd_or_td_interest" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('CD Or TD Lending Percentage (%)') }} *</label>
                            <input v-model="form.cd_or_td_lending_percentage" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Terms & Conditions -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Terms & Conditions') }}</h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Limit') }}</label>
                            <input disabled :value="form.limit" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('CD/TD amount × lending percentage') }}</p>
                        </div>
                        <div v-if="isLockedByRenewal">
                            <label class="cvr-form-label">{{ $t('CD Or TD Lending Percentage (%)') }} *</label>
                            <input v-model="form.cd_or_td_lending_percentage" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('cd_or_td_lending_percentage')" class="text-xs mt-1 cvr-num-red">{{ errorFor($t('cd_or_td_lending_percentage')) }}</p>
                        </div>
                        <template v-if="!isLockedByRenewal">
                            <div>
                                <label class="cvr-form-label">Outstanding Balance *</label>
                                <input v-model="form.outstanding_balance" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Balance Date *</label>
                                <input v-model="form.balance_date" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                                <p v-if="errorFor('balance_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('balance_date') }}</p>
                            </div>
                        </template>

                        <!-- Rate fields only apply at creation — after that,
                             rate changes go through the Rates modal on the
                             list page instead, exactly like the original. -->
                        <template v-if="!isEdit">
                            <div>
                                <label class="cvr-form-label">Borrowing Rate (%) *</label>
                                <input v-model="form.borrowing_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Bank Margin Rate (%) *</label>
                                <input v-model="form.margin_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Interest Rate (%) *</label>
                                <input disabled :value="form.interest_rate" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            </div>
                        </template>

                        <div>
                            <label class="cvr-form-label">{{ $t('Highest Debt Balance Rate (%)') }} *</label>
                            <input v-model="form.highest_debt_balance_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Admin Fees Rate (%)') }}</label>
                            <input v-model="form.admin_fees_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Settled Max Within (Days)') }} *</label>
                            <input v-model="form.to_be_setteled_max_within_days" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Outstanding Breakdown — onboarding-only, so it's
                     dropped entirely once a renewal exists (see banner
                     above). -->
                <div v-if="!isLockedByRenewal" class="cvr-card">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide">{{ $t('Outstanding Breakdown') }}</h2>
                        <button type="button" @click="addBreakdownRow" class="cvr-btn-primary px-3 py-1.5 rounded text-sm">
                            {{ $t('+ Add Row') }}
                        </button>
                    </div>
                    <p class="text-xs cvr-text-muted mb-4">
                        {{ $t('For balances brought in from before joining CashVero — break the outstanding balance down by settlement date.') }}
                    </p>
                    <div v-for="row in outstandingBreakdowns" :key="row._rowId" class="cvr-form-grid-3 mb-2 items-end">
                        <div>
                            <label class="cvr-form-label">{{ $t('Settlement Date') }}</label>
                            <input v-model="row.settlement_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Amount') }}</label>
                            <input v-model="row.amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <button
                            type="button"
                            @click="removeBreakdownRow(row._rowId)"
                            class="cvr-btn-remove-row justify-self-start w-auto"
                        >{{ $t('🗑 Remove Row') }}</button>
                    </div>
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
