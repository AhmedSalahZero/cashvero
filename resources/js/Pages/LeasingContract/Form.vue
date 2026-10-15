<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const props = defineProps({
    mode: String, // 'create' | 'edit'
    company: Object,
    leasingCompany: Object,
    currencies: Object,
    installmentIntervals: Array, // [{value, title}]
    isLocked: { type: Boolean, default: false },
    model: Object,
    submitUrl: String,
    deleteScheduleUrl: String,
    backUrl: String,
    navUrls: Object,
});

const { can } = usePermissions();

const page = usePage();
const isEdit = props.mode === 'edit';

const form = ref({
    name: props.model?.name ?? '',
    start_date: props.model?.start_date ?? '',
    end_date: props.model?.end_date ?? '',
    currency: props.model?.currency ?? 'egp',
    limit: props.model?.limit ?? 0,
    borrowing_rate: props.model?.borrowing_rate ?? 0,
    margin_rate: props.model?.margin_rate ?? 0,
    duration: props.model?.duration ?? 0,
    installment_payment_interval: props.model?.installment_payment_interval ?? '',
    // Onboarding fields — for a company that already had this leasing
    // contract running before joining CashVero. Reference-only, same
    // as Medium Term Loan: they guide what to upload in the schedule
    // Excel (Beginning Balance, row count, first date) and don't feed
    // the Outstanding/Paid figures on the dashboard — those are always
    // computed live from the schedule table itself.
    already_paid_amount: props.model?.already_paid_amount ?? 0,
    first_installment_date: props.model?.first_installment_date ?? '',
    remaining_installment_count: props.model?.remaining_installment_count ?? '',
});

// Interest Rate = Borrowing Rate + Margin Rate — always, read-only,
// matches the original's own client-side formula exactly.
const interestRate = ref(Number(form.value.borrowing_rate || 0) + Number(form.value.margin_rate || 0));
watch(() => [form.value.borrowing_rate, form.value.margin_rate], () => {
    interestRate.value = Number(form.value.borrowing_rate || 0) + Number(form.value.margin_rate || 0);
});

// Net Balance = Limit − Already Paid Amount — read-only, live. This is
// the number the user should put as the Beginning Balance on row 1 of
// their schedule Excel.
const netBalance = computed(() => Number(form.value.limit || 0) - Number(form.value.already_paid_amount || 0));

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    if (props.isLocked) return;
    submitting.value = true;
    // ⚠️ Same confirmed bug fix as Medium Term Loan: storeBasicForm()
    // only assigns fields it actually receives in the request, so
    // company_id and leasing_company_id must be explicitly included
    // here — the original form sends these as hidden inputs.
    const payload = { ...form.value, company_id: props.company.id, leasing_company_id: props.leasingCompany.id };
    if (isEdit) {
        router.put(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    }
}

const deletingSchedule = ref(false);
function deleteSchedule() {
    if (!confirm(t('This will delete every installment, every payment recorded against them, and reverse their effect on your bank statements. This can\'t be undone. Continue?'))) {
        return;
    }
    deletingSchedule.value = true;
    router.delete(props.deleteScheduleUrl, { onFinish: () => { deletingSchedule.value = false; } });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Leasing Contracts') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? $t('Edit') : $t('Add') }} {{ $t('Leasing Contract') }}
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ leasingCompany.name }}</p>

            <FormErrorSummary />

            <div v-if="isLocked" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm flex items-center justify-between gap-3 flex-wrap">
                <span>{{ $t('This contract has an uploaded schedule and can\'t be edited. Delete the schedule first if you need to make changes.') }}</span>
                <button v-if="can('leasing_contract.manage_schedule')" type="button" :disabled="deletingSchedule" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm whitespace-nowrap" @click="deleteSchedule">
                    {{ deletingSchedule ? $t('Deleting...') : $t('Delete Schedule') }}
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <fieldset :disabled="isLocked">
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Contract Information') }}</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Name') }} *</label>
                            <input v-model="form.name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('name')" class="text-xs mt-1 cvr-num-red">{{ errorFor('name') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Start Date') }} *</label>
                            <input v-model="form.start_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('End Date') }} *</label>
                            <input v-model="form.end_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                            <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select') }}</option>
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Limit') }} *</label>
                            <input v-model="form.limit" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Borrowing Rate (%)') }} *</label>
                            <input v-model="form.borrowing_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Margin Rate (%)') }} *</label>
                            <input v-model="form.margin_rate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Interest Rate (%)') }}</label>
                            <input disabled :value="interestRate" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Duration (Months)') }} *</label>
                            <input v-model="form.duration" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Installment Payment Interval') }} *</label>
                            <select v-model="form.installment_payment_interval" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select') }}</option>
                                <option v-for="opt in installmentIntervals" :key="opt.value" :value="opt.value">{{ opt.title }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="cvr-card mt-4">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-1">{{ $t('Already Running Facility?') }}</h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        {{ $t('Fill this in only if the company already had this contract before joining CashVero. These values guide what to upload in the schedule Excel — they don\'t get used in any calculation themselves.') }}
                    </p>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Already Paid Amount') }}</label>
                            <input v-model="form.already_paid_amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('Paid to the leasing company before joining CashVero.') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Net Balance') }}</label>
                            <input disabled :value="netBalance" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('Use this as row 1\'s Beginning Balance in the Excel.') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Remaining Installment Count') }}</label>
                            <input v-model="form.remaining_installment_count" type="number" class="cvr-input w-full px-3 py-2 rounded" />
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('Number of rows the schedule Excel must have.') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('First Installment Date') }}</label>
                            <input v-model="form.first_installment_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('Must match row 1\'s date in the Excel.') }}</p>
                        </div>
                    </div>
                </div>
                </fieldset>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Cancel') }}</Link>
                    <button type="submit" :disabled="submitting || isLocked" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? $t('Saving...') : $t('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
