<script setup>
import { ref, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    mode: String, // 'create' | 'edit'
    company: Object,
    financialInstitution: Object,
    currencies: Object,
    installmentIntervals: Array, // [{value, title}]
    model: Object,
    submitUrl: String,
    backUrl: String,
    navUrls: Object,
});

const page = usePage();
const isEdit = props.mode === 'edit';

const form = ref({
    name: props.model?.name ?? '',
    start_date: props.model?.start_date ?? '',
    end_date: props.model?.end_date ?? '',
    currency: props.model?.currency ?? 'egp',
    limit: props.model?.limit ?? 0,
    // ⚠️ Confirmed from the original: this is a plain free-text field,
    // not a linked bank account — account_number is a varchar column
    // on medium_term_loans, not a foreign key. Kept exactly as-is.
    account_number: props.model?.account_number ?? '',
    borrowing_rate: props.model?.borrowing_rate ?? 0,
    margin_rate: props.model?.margin_rate ?? 0,
    duration: props.model?.duration ?? 0,
    installment_payment_interval: props.model?.installment_payment_interval ?? '',
});

// Interest Rate = Borrowing Rate + Margin Rate — always, read-only,
// matches the original's own client-side formula exactly.
const interestRate = ref(Number(form.value.borrowing_rate || 0) + Number(form.value.margin_rate || 0));
watch(() => [form.value.borrowing_rate, form.value.margin_rate], () => {
    interestRate.value = Number(form.value.borrowing_rate || 0) + Number(form.value.margin_rate || 0);
});

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    // ⚠️ Confirmed bug fix: the original form sends company_id and
    // financial_institution_id as hidden inputs — storeBasicForm()
    // only assigns fields it actually receives in the request, so
    // without these explicitly included here, both were left unset
    // on insert. Fixed by adding them directly to the payload.
    const payload = { ...form.value, company_id: props.company.id, financial_institution_id: props.financialInstitution.id };
    if (isEdit) {
        router.put(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    }
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6 max-w-5xl">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    ← Back to Medium Term Loan
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? 'Edit' : 'Add' }} Medium Term Loan
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ financialInstitution.name }}</p>

            <div v-if="Object.keys(page.props.errors || {}).length" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
                Please fix the highlighted field(s) below before saving.
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Loan Information</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">Name *</label>
                            <input v-model="form.name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('name')" class="text-xs mt-1 cvr-num-red">{{ errorFor('name') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Start Date *</label>
                            <input v-model="form.start_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">End Date *</label>
                            <input v-model="form.end_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Currency *</label>
                            <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Limit *</label>
                            <input v-model="form.limit" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Account Number *</label>
                            <input v-model="form.account_number" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Borrowing Rate (%) *</label>
                            <input v-model="form.borrowing_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Margin Rate (%) *</label>
                            <input v-model="form.margin_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Interest Rate (%)</label>
                            <input disabled :value="interestRate" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Duration (Months) *</label>
                            <input v-model="form.duration" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Installment Payment Interval *</label>
                            <select v-model="form.installment_payment_interval" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="opt in installmentIntervals" :key="opt.value" :value="opt.value">{{ opt.title }}</option>
                            </select>
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
