<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';

const props = defineProps({
    mode: String, // 'create' | 'edit'
    company: Object,
    factoringCompany: Object,
    currencies: Object,
    recourseTypes: Object, // { with_recourse: 'With Recourse', without_recourse: 'Without Recourse' }
    model: Object, // null in create mode
    submitUrl: String,
    backUrl: String,
    navUrls: Object,
});

const page = usePage();
const isEdit = props.mode === 'edit';

const form = ref({
    contract_start_date: props.model?.contract_start_date ?? '',
    contract_end_date: props.model?.contract_end_date ?? '',
    recourse_type: props.model?.recourse_type ?? '',
    currency: props.model?.currency ?? 'egp',
    limit: props.model?.limit ?? 0,
    outstanding_balance: props.model?.outstanding_balance ?? 0,
    balance_date: props.model?.balance_date ?? '',
    // ⚠️ Confirmed from the original: these 4 rate fields are
    // CREATE-ONLY — the edit form never renders them at all, and
    // there's no separate "edit rate" feature for this model (unlike
    // some overdraft facilities elsewhere in the app). Left exactly
    // as the original: shown only in create mode, and never included
    // in the update payload.
    borrowing_rate: props.model?.borrowing_rate ?? 0,
    margin_rate: props.model?.margin_rate ?? 0,
    min_interest_rate: props.model?.min_interest_rate ?? 0,
    highest_debt_balance_rate: props.model?.highest_debt_balance_rate ?? 0,
    admin_fees_rate: props.model?.admin_fees_rate ?? 0,
    to_be_setteled_max_within_days: props.model?.to_be_setteled_max_within_days ?? 0,
});

// Interest Rate = Borrowing Rate + Margin Rate — always, read-only,
// create-only (same as the 4 rate fields above), matches the
// original's own client-side formula exactly.
const interestRate = ref(Number(form.value.borrowing_rate || 0) + Number(form.value.margin_rate || 0));
watch(() => [form.value.borrowing_rate, form.value.margin_rate], () => {
    interestRate.value = Number(form.value.borrowing_rate || 0) + Number(form.value.margin_rate || 0);
});

/*
 * Outstanding Breakdown — only shown/relevant when Outstanding
 * Balance > 0, matching the original's own show/hide toggle exactly.
 * Client-side validation mirrors App\Rules\OutstandingBreakdownRule:
 * the sum of every row's amount must equal Outstanding Balance, and
 * every settlement date must be >= Contract Start Date. The server
 * still enforces this independently — this is just the same helpful
 * up-front feedback the original gave via its own repeater +
 * required-field wiring.
 */
let breakdownIdCounter = 0;
function newBreakdownRow(amount = 0, settlementDate = '') {
    breakdownIdCounter += 1;
    return { key: breakdownIdCounter, amount, settlement_date: settlementDate };
}
const breakdowns = ref(
    props.model?.outstanding_breakdowns?.length
        ? props.model.outstanding_breakdowns.map(b => newBreakdownRow(b.amount, b.settlement_date))
        : [newBreakdownRow()]
);
const showBreakdown = computed(() => Number(form.value.outstanding_balance) > 0);

function addBreakdownRow() {
    breakdowns.value.push(newBreakdownRow());
}
function removeBreakdownRow(index) {
    if (breakdowns.value.length <= 1) return;
    if (!confirm('Are you sure you want to delete this element?')) return;
    breakdowns.value.splice(index, 1);
}

const breakdownTotal = computed(() =>
    breakdowns.value.reduce((sum, row) => sum + (Number(row.amount) || 0), 0)
);
const breakdownTotalMismatch = computed(() =>
    showBreakdown.value && Math.round(breakdownTotal.value * 100) !== Math.round(Number(form.value.outstanding_balance) * 100)
);
const breakdownDateTooEarly = computed(() => {
    if (!showBreakdown.value || !form.value.contract_start_date) return false;
    return breakdowns.value.some(row => row.settlement_date && row.settlement_date < form.value.contract_start_date);
});

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        contract_start_date: form.value.contract_start_date,
        contract_end_date: form.value.contract_end_date,
        recourse_type: form.value.recourse_type,
        currency: form.value.currency,
        limit: form.value.limit,
        outstanding_balance: form.value.outstanding_balance,
        balance_date: form.value.balance_date,
        highest_debt_balance_rate: form.value.highest_debt_balance_rate,
        admin_fees_rate: form.value.admin_fees_rate,
        to_be_setteled_max_within_days: form.value.to_be_setteled_max_within_days,
        outstanding_breakdowns: showBreakdown.value
            ? breakdowns.value.map(row => ({ amount: row.amount, settlement_date: row.settlement_date }))
            : [],
    };
    if (!isEdit) {
        payload.borrowing_rate = form.value.borrowing_rate;
        payload.margin_rate = form.value.margin_rate;
        payload.interest_rate = interestRate.value;
        payload.min_interest_rate = form.value.min_interest_rate;
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
                    ← Back to Factoring Contracts
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? 'Edit' : 'Add' }} Factoring Contract
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ factoringCompany.name }}</p>

            <FormErrorSummary />

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Contract Main Information -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Contract Main Information</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">Factoring Company Name</label>
                            <input disabled :value="factoringCompany.name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Contract Start Date *</label>
                            <input v-model="form.contract_start_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('contract_start_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('contract_start_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Contract End Date *</label>
                            <input v-model="form.contract_end_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('contract_end_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('contract_end_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Recourse Type *</label>
                            <select v-model="form.recourse_type" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="(label, code) in recourseTypes" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Select Currency *</label>
                            <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Terms & Conditions -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Terms &amp; Conditions</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">Limit *</label>
                            <input v-model="form.limit" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('limit')" class="text-xs mt-1 cvr-num-red">{{ errorFor('limit') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Outstanding Balance *</label>
                            <input v-model="form.outstanding_balance" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Balance Date *</label>
                            <input v-model="form.balance_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>

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
                                <label class="cvr-form-label">Interest Rate (%)</label>
                                <input disabled :value="interestRate" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Min Interest Rate (%) *</label>
                                <input v-model="form.min_interest_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                        </template>
                        <p v-else class="text-xs cvr-text-muted col-span-3">
                            Borrowing/Margin/Interest/Min Interest Rate can only be set when the contract is created — matches the original (no edit-rate feature for this model).
                        </p>

                        <div>
                            <label class="cvr-form-label">Highest Debt Balance Rate (%) *</label>
                            <input v-model="form.highest_debt_balance_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Admin Fees Rate (%) *</label>
                            <input v-model="form.admin_fees_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Settled Max Within (Days) *</label>
                            <input v-model="form.to_be_setteled_max_within_days" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Outstanding Breakdown -->
                <div v-if="showBreakdown" class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Outstanding Breakdown</h2>
                    <p class="text-xs cvr-text-muted mb-3">
                        The amounts below must add up to the Outstanding Balance above, and each settlement date must be on or after the Contract Start Date.
                    </p>
                    <div v-for="(row, index) in breakdowns" :key="row.key" class="flex items-end gap-3 mb-3">
                        <div class="w-40">
                            <label class="cvr-form-label">Amount</label>
                            <input v-model="row.amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div class="w-48">
                            <label class="cvr-form-label">Settlement Date</label>
                            <input v-model="row.settlement_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <button type="button" @click="removeBreakdownRow(index)" class="cvr-btn-danger px-2 py-2 rounded border text-xs">✕</button>
                    </div>
                    <button type="button" @click="addBreakdownRow" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm mb-3">+ Add</button>

                    <p class="text-sm">
                        Total: <strong :class="breakdownTotalMismatch ? 'cvr-num-red' : 'cvr-num'">{{ breakdownTotal }}</strong>
                        <span class="cvr-text-muted"> / {{ form.outstanding_balance }}</span>
                    </p>
                    <p v-if="breakdownTotalMismatch" class="text-xs cvr-num-red mt-1">
                        Repeater Outstanding Balance Must Be Equal To Total Outstanding Balance
                    </p>
                    <p v-if="breakdownDateTooEarly" class="text-xs cvr-num-red mt-1">
                        Settlement Dates Must Be Greater Than Or Equal Contract Start Date
                    </p>
                    <p v-if="errorFor('outstanding_breakdowns')" class="text-xs cvr-num-red mt-1">{{ errorFor('outstanding_breakdowns') }}</p>
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
