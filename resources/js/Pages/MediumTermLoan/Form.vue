<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import ShareholderOwnershipFields from '@/Components/ShareholderOwnershipFields.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    mode: String, // 'create' | 'edit'
    company: Object,
    financialInstitution: Object,
    currencies: Object,
    installmentIntervals: Array, // [{value, title}]
    consumptionStatuses: Array, // [{value, title}]
    // True when the company is connected to Odoo at all — the Odoo Code
    // field is pointless noise otherwise.
    hasOdoo: { type: Boolean, default: false },
    isLocked: { type: Boolean, default: false },
    // Narrower than isLocked: true once a supplier has actually been paid
    // out of this loan, which freezes ONLY the New/Existing field.
    isConsumptionLocked: { type: Boolean, default: false },
    model: Object,
    // Shareholder ownership — docs/shareholder-accounts.md
    canManageShareholderAccounts: { type: Boolean, default: false },
    shareholders: { type: Array, default: () => [] },
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
    // ⚠️ Confirmed from the original: this is a plain free-text field,
    // not a linked bank account — account_number is a varchar column
    // on medium_term_loans, not a foreign key. Kept exactly as-is.
    account_number: props.model?.account_number ?? '',
    borrowing_rate: props.model?.borrowing_rate ?? 0,
    margin_rate: props.model?.margin_rate ?? 0,
    duration: props.model?.duration ?? 0,
    installment_payment_interval: props.model?.installment_payment_interval ?? '',
    // Onboarding fields — for a company that already had this MTL
    // running before joining CashVero. already_paid_amount /
    // remaining_installment_count / first_installment_date are
    // reference-only: they tell the user what Beginning Balance, row
    // count, and first date their schedule upload must match. They do
    // NOT feed the Outstanding/Paid figures shown on the dashboard —
    // those are always computed live from the schedule table itself.
    already_paid_amount: props.model?.already_paid_amount ?? 0,
    first_installment_date: props.model?.first_installment_date ?? '',
    remaining_installment_count: props.model?.remaining_installment_count ?? '',
    // 'existing' = the company already drew and spent this loan before
    // joining CashVero, so there is nothing left to pay suppliers with —
    // it only gets repaid. 'new' = not consumed yet, so the loan shows up
    // as a payable account on the Money Payment screen and supplier
    // invoices can be settled straight out of it.
    consumption_status: props.model?.consumption_status ?? 'existing',
    is_shareholder_account: props.model?.is_shareholder_account ?? false,
    shareholder_partner_id: props.model?.shareholder_partner_id ?? null,
    // Odoo Code — same field bank accounts have. On save it is looked up
    // in Odoo's chart of accounts and the loan's journal / payment-method
    // ids are filled in from there. Without it, an Odoo-connected company
    // cannot sync a payment made out of this loan at all.
    odoo_code: props.model?.odoo_code ?? '',
});

const isNewFacility = computed(() => form.value.consumption_status === 'new');

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
        <div class="px-6 pt-4">
            <Link v-if="instructionsUrl" :href="instructionsUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('📖 Instructions') }}
            </Link>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-3">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Medium Term Loan') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-3">
                {{ isEdit ? $t('Edit') : $t('Add') }} {{ $t('Medium Term Loan') }}
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ financialInstitution.name }}</p>

            <FormErrorSummary />

            <div v-if="isLocked" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm flex items-center justify-between gap-3 flex-wrap">
                <span>{{ $t('This loan has an uploaded schedule and can\'t be edited. Delete the schedule first if you need to make changes.') }}</span>
                <button v-if="can('medium_term_loan.manage_schedule')" type="button" :disabled="deletingSchedule" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm whitespace-nowrap" @click="deleteSchedule">
                    {{ deletingSchedule ? $t('Deleting...') : $t('Delete Schedule') }}
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <fieldset :disabled="isLocked">
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Loan Information') }}</h2>
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
                            <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                            <input v-model="form.account_number" type="text" class="cvr-input w-full px-3 py-2 rounded" />
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
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-1">{{ $t('Loan Status') }}</h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        {{ $t('Has this loan already been drawn and spent, or is the money still sitting with the bank waiting to be used?') }}
                    </p>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('New or Existing') }} *</label>
                            <select v-model="form.consumption_status" :disabled="isConsumptionLocked" class="cvr-input w-full px-3 py-2 rounded" :class="isConsumptionLocked ? 'opacity-70' : ''">
                                <option v-for="opt in consumptionStatuses" :key="opt.value" :value="opt.value">{{ opt.title }}</option>
                            </select>
                            <p v-if="isConsumptionLocked" class="text-xs cvr-text-muted mt-1">
                                {{ $t('Locked — supplier payments have already been made from this loan.') }}
                            </p>
                        </div>
                        <ShareholderOwnershipFields
                            :can-manage="canManageShareholderAccounts"
                            :shareholders="shareholders"
                            v-model:is-shareholder-account="form.is_shareholder_account"
                            v-model:shareholder-partner-id="form.shareholder_partner_id"
                            :owner-error="page.props.errors?.shareholder_partner_id ?? null"
                            :disabled="isLocked"
                            hint="An owner's loan is filtered like their other accounts — it appears under All accounts and Shareholders accounts, not under Company accounts."
                        />
                        <div v-if="hasOdoo && isNewFacility">
                            <label class="cvr-form-label">{{ $t('Odoo Code') }}</label>
                            <input v-model="form.odoo_code" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p class="text-xs cvr-text-muted mt-1">
                                {{ $t('Required to sync payments made from this loan to Odoo — it\'s looked up in Odoo\'s chart of accounts to find the loan\'s journal.') }}
                            </p>
                        </div>
                        <div v-if="isNewFacility">
                            <label class="cvr-form-label">{{ $t('Available Room') }}</label>
                            <input disabled :value="model?.available_room_formatted ?? netBalance" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('Limit minus what has been drawn so far.') }}</p>
                        </div>
                    </div>
                    <p v-if="isNewFacility" class="text-xs cvr-text-muted mt-3">
                        {{ $t('This loan will appear as a payable account on the Money Payment screen, so supplier invoices can be settled directly out of it — up to the Available Room above. When you later repay an installment, only its') }} <strong>{{ $t('principle') }}</strong> {{ $t('portion comes off the drawn balance; the interest portion never touches this account, because the installment already includes it. Repaying principle frees the room up again.') }}
                    </p>
                    <p v-else class="text-xs cvr-text-muted mt-3">
                        {{ $t('An existing loan is repayment-only — it won\'t be offered as a paying account, since the money was already drawn and spent before joining CashVero.') }}
                    </p>
                </div>

                <div class="cvr-card mt-4">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-1">{{ $t('Already Running Facility?') }}</h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        {{ $t('Fill this in only if the company already had this loan before joining CashVero. These values guide what to upload in the schedule Excel — they don\'t get used in any calculation themselves.') }}
                    </p>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Already Paid Amount') }}</label>
                            <input v-model="form.already_paid_amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('Paid to the bank before joining CashVero.') }}</p>
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
