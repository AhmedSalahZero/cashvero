<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/composables/usePermissions';
import { todayDate } from '@/composables/today';
/* Payment date window — same rule as a supplier payable cheque:
   not before the due date, not after today. The server enforces it too. */
const maxDate = todayDate();

const props = defineProps({
    company: Object,
    contractLoanSchedule: Object, // { id, leasing_contract_name, date, date_formatted, currency, beginning_balance_formatted, cheque_amount_formatted, amount_to_pay_formatted, cheque_number, drawee_bank_name, account_number, interest_amount_formatted, principle_amount_formatted, end_balance_formatted, settlement_default_date, is_paid, actual_payment_date_formatted }
    settlements: Array,          // older partial settlements: [{id, date_formatted, account_number, amount_formatted, delete_url}]
    markAsPaidUrl: String,
    unmarkAsPaidUrl: String,
    backUrl: String,
    navUrls: Object,
});

const { can } = usePermissions();
// Paying a cheque is a schedule edit — gated by `leasing_contract.manage_schedule`,
// same key RoutePermissionMap enforces on the mark/unmark routes.
const canManage = () => can('leasing_contract.manage_schedule');

/* Leasing cheques are post-dated, so "today" is often before the due date.
   Default to the due date when it has passed, otherwise leave it for the
   user — a pre-filled date the server would reject helps nobody. */
const dueDate = props.contractLoanSchedule.date;
const form = ref({
    actual_payment_date: dueDate && dueDate <= maxDate ? props.contractLoanSchedule.settlement_default_date : '',
});

const submitting = ref(false);
function markAsPaid() {
    submitting.value = true;
    router.post(props.markAsPaidUrl, form.value, { onFinish: () => { submitting.value = false; } });
}

const confirmingUnmark = ref(false);
function unmarkAsPaid() {
    submitting.value = true;
    router.post(props.unmarkAsPaidUrl, {}, {
        onFinish: () => { submitting.value = false; confirmingUnmark.value = false; },
    });
}

const deleteTarget = ref(null);
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back To Contract Schedule') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mt-3 mb-6">{{ $t('Leasing Cheque') }}</h1>

            <div class="cvr-card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide">{{ $t('Contract Schedule Payment') }}</h2>
                    <span
                        class="text-xs font-semibold px-2 py-1 rounded"
                        :class="contractLoanSchedule.is_paid ? 'cvr-badge-active' : 'cvr-badge-pending'"
                    >
                        {{ contractLoanSchedule.is_paid ? $t('Paid') : $t('Pending') }}
                    </span>
                </div>
                <div class="cvr-form-grid-3 mb-3">
                    <div>
                        <label class="cvr-form-label">{{ $t('Contract Name') }}</label>
                        <input disabled :value="contractLoanSchedule.leasing_contract_name" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Due Date') }}</label>
                        <input disabled :value="contractLoanSchedule.date_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Currency') }}</label>
                        <input disabled :value="contractLoanSchedule.currency" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Cheque Number') }}</label>
                        <input disabled :value="contractLoanSchedule.cheque_number" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Drawee Bank') }}</label>
                        <input disabled :value="contractLoanSchedule.drawee_bank_name ?? '-'" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Account Number') }}</label>
                        <input disabled :value="contractLoanSchedule.account_number ?? '-'" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Cheque Amount') }}</label>
                        <input disabled :value="contractLoanSchedule.cheque_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Interest Amount') }}</label>
                        <input disabled :value="contractLoanSchedule.interest_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Principle Amount') }}</label>
                        <input disabled :value="contractLoanSchedule.principle_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Beginning Balance') }}</label>
                        <input disabled :value="contractLoanSchedule.beginning_balance_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('End Balance') }}</label>
                        <input disabled :value="contractLoanSchedule.end_balance_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>

                <!-- Pending: mark as paid -->
                <form v-if="!contractLoanSchedule.is_paid" @submit.prevent="markAsPaid" class="cvr-form-grid-4 items-end mt-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Actual Payment Date') }} *</label>
                        <input
                            v-model="form.actual_payment_date"
                            type="date"
                            :min="contractLoanSchedule.date"
                            :max="maxDate"
                            required
                            class="cvr-input w-full px-3 py-2 rounded"
                        />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Amount To Pay') }}</label>
                        <input disabled :value="contractLoanSchedule.amount_to_pay_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div class="flex gap-2">
                        <button v-if="canManage()" type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                            {{ submitting ? $t('Saving...') : $t('Mark As Paid') }}
                        </button>
                    </div>
                </form>

                <!-- Paid: show the date, allow returning it to unpaid -->
                <div v-else class="cvr-form-grid-4 items-end mt-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Actual Payment Date') }}</label>
                        <input disabled :value="contractLoanSchedule.actual_payment_date_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div class="flex gap-2">
                        <button v-if="canManage()" type="button" :disabled="submitting" @click="confirmingUnmark = true" class="cvr-btn-danger px-4 py-2 rounded border">
                            {{ $t('Unmark As Paid') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Partial settlements recorded before leasing cheques were pre-posted -->
            <div v-if="settlements.length" class="cvr-card mt-6">
                <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Earlier Partial Settlements') }}</h2>
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-2 text-start">#</th>
                            <th class="px-3 py-2 text-start">{{ $t('Date') }}</th>
                            <th class="px-3 py-2 text-start">{{ $t('Account Number') }}</th>
                            <th class="px-3 py-2 text-start">{{ $t('Amount') }}</th>
                            <th class="px-3 py-2 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(s, i) in settlements" :key="s.id" class="cvr-table-row">
                            <td class="px-3 py-2 cvr-text-secondary">{{ i + 1 }}</td>
                            <td class="px-3 py-2 whitespace-nowrap cvr-text-secondary">{{ s.date_formatted }}</td>
                            <td class="px-3 py-2 cvr-text-secondary">{{ s.account_number }}</td>
                            <td class="px-3 py-2 cvr-num">{{ s.amount_formatted }}</td>
                            <td class="px-3 py-2">
                                <button v-if="s.delete_url && canManage()" @click="deleteTarget = s" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">{{ $t('Delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="confirmingUnmark" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-2">{{ $t('Return this cheque to unpaid?') }}</h2>
                    <p class="text-sm cvr-text-secondary mb-4">{{ $t('The cheque movement goes back to its due date in the bank statement.') }}</p>
                    <div class="flex justify-end gap-2">
                        <button @click="confirmingUnmark = false" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="unmarkAsPaid" :disabled="submitting" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Unmark As Paid') }}</button>
                    </div>
                </div>
            </div>

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Delete this settlement?') }}</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
