<script setup>
import { ref, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    mode: String, // 'create' | 'edit'
    hasRenewals: Boolean, // edit mode only — true once the facility has a renewal
    company: Object,
    financialInstitution: Object,
    currencies: Object,
    hasOdooIntegration: Boolean,
    model: Object, // null in create mode
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
    limit: props.model?.limit ?? 0,
    outstanding_balance: props.model?.outstanding_balance ?? 0,
    balance_date: props.model?.balance_date ?? '',
    borrowing_rate: 0,
    margin_rate: 0,
    interest_rate: 0,
    min_interest_rate: 0,
    highest_debt_balance_rate: props.model?.highest_debt_balance_rate ?? 0,
    admin_fees_rate: props.model?.admin_fees_rate ?? 0,
    to_be_setteled_max_within_days: props.model?.to_be_setteled_max_within_days ?? 0,
});

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
    // omit them entirely when editing (matches the original exactly;
    // rate changes after creation go through the Rates modal instead).
    if (isEdit) {
        delete payload.borrowing_rate;
        delete payload.margin_rate;
        delete payload.interest_rate;
        delete payload.min_interest_rate;
    }
    // Client-directed rework (2026-08-10): once a renewal exists, these
    // fields are onboarding-only data that must never be resubmitted —
    // dropping the keys entirely (rather than sending blank/zero values)
    // is what makes the backend leave them completely untouched.
    if (isLockedByRenewal) {
        delete payload.contract_start_date;
        delete payload.account_number;
        delete payload.odoo_code;
        delete payload.currency;
        delete payload.outstanding_balance;
        delete payload.balance_date;
        delete payload.outstanding_breakdowns;
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
        <div class="px-6 pt-4">
            <Link v-if="instructionsUrl" :href="instructionsUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('📖 Instructions') }}
            </Link>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Clean Overdraft') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? $t('Edit') : $t('Add') }} {{ $t('Clean Overdraft') }}
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ financialInstitution.name }}</p>

            <FormErrorSummary />

            <div v-if="isLockedByRenewal" class="mb-4 px-4 py-3 rounded border border-blue-400 bg-blue-50 text-blue-800 text-sm">
                {{ $t('This facility has an active renewal, so this edits the') }} <strong>{{ $t('current chapter\'s') }}</strong> {{ $t('terms only. Account details and onboarding data (Outstanding Balance / Balance Date / Outstanding Breakdown) belong to the original setup and can\'t be changed here. To change the renewal\'s own start date, delete and re-do the renewal from the Archived Facilities tab instead.') }}
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

                <!-- Terms & Conditions -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Terms & Conditions') }}</h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                            <select v-model="form.currency" :disabled="isLockedByRenewal" class="cvr-input w-full px-3 py-2 rounded" :class="{ 'opacity-60': isLockedByRenewal }">
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Limit') }} *</label>
                            <input v-model="form.limit" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <template v-if="!isLockedByRenewal">
                            <div>
                                <label class="cvr-form-label">{{ $t('Outstanding Balance *') }}</label>
                                <input v-model="form.outstanding_balance" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Balance Date *') }}</label>
                                <input v-model="form.balance_date" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                                <p v-if="errorFor('balance_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('balance_date') }}</p>
                            </div>
                        </template>

                        <!-- Rate fields only apply at creation — after that,
                             rate changes go through the Rates modal on the
                             list page instead, exactly like the original. -->
                        <template v-if="!isEdit">
                            <div>
                                <label class="cvr-form-label">{{ $t('Borrowing Rate (%) *') }}</label>
                                <input v-model="form.borrowing_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Bank Margin Rate (%) *') }}</label>
                                <input v-model="form.margin_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Interest Rate (%) *') }}</label>
                                <input disabled :value="form.interest_rate" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Min Interest Rate (%) *') }}</label>
                                <input v-model="form.min_interest_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
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
