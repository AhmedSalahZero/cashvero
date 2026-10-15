<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import ShareholderOwnershipFields from '@/Components/ShareholderOwnershipFields.vue';

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    mode: String, // 'create' | 'edit'
    company: Object,
    financialInstitution: Object,
    accounts: Array,
    currencies: Object,
    hasOdooIntegration: Boolean,
    model: Object, // null in create mode
    submitUrl: String,
    backUrl: String,
    navUrls: Object,
    // Shareholder ownership — docs/shareholder-accounts.md
    canManageShareholderAccounts: { type: Boolean, default: false },
    shareholders: { type: Array, default: () => [] },
});

const page = usePage();
const isEdit = props.mode === 'edit';

/* ── Form state ───────────────────────────────────────────────── */
const form = ref({
    account_number: props.model?.account_number ?? '',
    currency: props.model?.currency ?? '',
    odoo_code: props.model?.odoo_code ?? '',
    deducted_from_account_id: props.model?.deducted_from_account_id ?? 0,
    maturity_amount_added_to_account_id: props.model?.maturity_amount_added_to_account_id ?? '',
    start_date: props.model?.start_date ?? '',
    end_date: props.model?.end_date ?? '',
    amount: props.model?.amount ?? 0,
    interest_rate: props.model?.interest_rate ?? 0,
    interest_amount: props.model?.interest_amount ?? 0,
    is_at_maturity: props.model?.is_at_maturity ?? true,
    is_shareholder_account: props.model?.is_shareholder_account ?? false,
    shareholder_partner_id: props.model?.shareholder_partner_id ?? null,
});

/*
 * Only accounts matching the selected currency are eligible, plus the
 * account currently selected even if it's since been locked/deactivated
 * — same rule as the Time Of Deposit form (mirrors
 * UpdateCurrentAccountBasedOnCurrencyController@index).
 */
function accountsForCurrency(currentlySelectedId) {
    return props.accounts.filter(a =>
        a.currency === form.value.currency &&
        (a.is_active || a.id === Number(currentlySelectedId))
    );
}
const deductedFromOptions = computed(() => accountsForCurrency(form.value.deducted_from_account_id));
const maturityAccountOptions = computed(() => accountsForCurrency(form.value.maturity_amount_added_to_account_id));

/* ── Auto-calculate Interest Amount [At Maturity] ────────────────
   Same formula as Time Of Deposit: rate/100/365 * days * amount */
function recalculateInterest() {
    if (!form.value.start_date || !form.value.end_date || !form.value.amount || !form.value.interest_rate) return;
    const start = new Date(form.value.start_date);
    const end = new Date(form.value.end_date);
    const days = Math.round((end - start) / (1000 * 60 * 60 * 24));
    if (days > 0) {
        form.value.interest_amount = Math.round(
            (form.value.interest_rate / 100 / 365) * days * Number(form.value.amount) * 100
        ) / 100;
    }
}
watch(() => [form.value.start_date, form.value.end_date, form.value.amount, form.value.interest_rate], recalculateInterest);

/* ── Error display ────────────────────────────────────────────── */
function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

/* ── Submit ───────────────────────────────────────────────────── */
const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = { ...form.value, type: 'running' };
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
                    {{ $t('← Back to Certificates Of Deposit') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? $t('Edit') : $t('Add') }} {{ $t('Certificate Of Deposit') }}
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ financialInstitution.name }}</p>

            <FormErrorSummary />

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Main Information') }}</h2>

                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Financial Institution Name') }}</label>
                            <input disabled :value="financialInstitution.name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                            <input v-model="form.account_number" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('account_number')" class="text-xs mt-1 cvr-num-red">{{ errorFor('account_number') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency') }}</label>
                            <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>

                        <!-- Odoo Code is genuinely OPTIONAL for Certificates Of
                             Deposit, unlike Time Of Deposit where it's required
                             — matches the original blade exactly (no `required`
                             attribute, no asterisk there). -->
                        <div v-if="hasOdooIntegration">
                            <label class="cvr-form-label">{{ $t('Odoo Code') }}</label>
                            <input v-model="form.odoo_code" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Deducted From Account #') }}</label>
                            <select v-model="form.deducted_from_account_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option :value="0">{{ $t('Opening Balance') }}</option>
                                <option v-for="a in deductedFromOptions" :key="a.id" :value="a.id">{{ a.account_number }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Start Date') }} *</label>
                            <input v-model="form.start_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('End Date') }} *</label>
                            <input v-model="form.end_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('end_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('end_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Amount') }} *</label>
                            <input v-model="form.amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Interest Rate (%)') }} *</label>
                            <input v-model="form.interest_rate" type="number" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Interest Amount [At Maturity]') }}</label>
                            <input :value="form.interest_amount" readonly class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Add Maturity Amount To Account') }} *</label>
                            <select v-model="form.maturity_amount_added_to_account_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select') }}</option>
                                <option v-for="a in maturityAccountOptions" :key="a.id" :value="a.id">{{ a.account_number }}</option>
                            </select>
                        </div>
                        <ShareholderOwnershipFields
                            :can-manage="canManageShareholderAccounts"
                            :shareholders="shareholders"
                            v-model:is-shareholder-account="form.is_shareholder_account"
                            v-model:shareholder-partner-id="form.shareholder_partner_id"
                            :owner-error="page.props.errors?.shareholder_partner_id ?? null"
                        />
                    </div>

                    <div class="mt-4">
                        <label class="cvr-form-label mb-2 block">{{ $t('Interest Amount Interval') }}</label>
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 cvr-text-primary text-sm">
                                <input type="radio" :value="true" v-model="form.is_at_maturity" />
                                {{ $t('At Maturity') }}
                            </label>
                            <label class="flex items-center gap-2 cvr-text-primary text-sm">
                                <input type="radio" :value="false" v-model="form.is_at_maturity" />
                                {{ $t('Periodically (biweekly / monthly / quarterly / etc.)') }}
                            </label>
                        </div>
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
