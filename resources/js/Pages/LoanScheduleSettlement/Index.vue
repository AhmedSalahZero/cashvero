<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/composables/usePermissions';
import { todayDate } from '@/composables/today';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر. */
const maxDate = todayDate();

const props = defineProps({
    company: Object,
    loanSchedule: Object, // { id, medium_term_loan_name, date_formatted, currency, beginning_balance_formatted, schedule_payment_formatted, interest_amount_formatted, principle_amount_formatted, end_balance_formatted, settlement_default_date, remaining }
    currentAccounts: Array,      // [{value: account_number, label: shown text}, ...]
    currentAccountTypeId: Number,
    financialInstitutionId: Number,
    balanceLookupUrl: String,
    settlements: Array,          // [{id, date_formatted, account_number, amount_formatted, edit_url, delete_url, is_being_edited}]
    lastSettlementId: [Number, String, null],
    editingSettlement: Object,   // null unless editing
    submitUrl: String,
    backUrl: String,
    navUrls: Object,
});

const { can } = usePermissions();
// Settlements are schedule edits — gated by `medium_term_loan.manage_schedule`,
// the same key RoutePermissionMap enforces on the store/update/
// delete routes. This page previously had no gating at all.
const canManage = () => can('medium_term_loan.manage_schedule');

const isEdit = !!props.editingSettlement;

const form = ref({
    date: props.editingSettlement?.date ?? props.loanSchedule.settlement_default_date,
    amount: props.editingSettlement?.amount ?? props.loanSchedule.remaining,
    current_account_number: props.editingSettlement?.current_account_number ?? (props.currentAccounts[0]?.value ?? ''),
});

/* ── Live "Available Balance" lookup — genuinely dynamic server-side
   computation (as of the chosen settlement date), can't be
   precomputed client-side. Same endpoint already used elsewhere
   (update.balance.and.net.balance.based.on.account.number). ────────── */
const availableBalance = ref(null);
const balanceDateLabel = ref('');
const balanceLoading = ref(false);
let balanceRequestToken = 0;
async function refreshBalance() {
    if (!form.value.current_account_number || !form.value.date) {
        availableBalance.value = null;
        balanceDateLabel.value = '';
        return;
    }
    const thisRequest = ++balanceRequestToken;
    balanceLoading.value = true;
    try {
        const params = new URLSearchParams({
            accountNumber: form.value.current_account_number,
            accountType: props.currentAccountTypeId,
            financialInstitutionId: props.financialInstitutionId,
            balanceDate: form.value.date,
            modelType: 'LoanScheduleSettlement',
            modelId: props.editingSettlement?.id ?? 0,
        });
        const res = await fetch(`${props.balanceLookupUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
        if (thisRequest !== balanceRequestToken) return;
        if (!res.ok) throw new Error(`Balance lookup failed (HTTP ${res.status})`);
        const data = await res.json();
        availableBalance.value = data.balance ?? 0;
        balanceDateLabel.value = form.value.date;
    } catch (err) {
        if (thisRequest !== balanceRequestToken) return;
        console.error('Available balance lookup failed:', err);
        availableBalance.value = null;
    } finally {
        if (thisRequest === balanceRequestToken) balanceLoading.value = false;
    }
}
watch(() => [form.value.current_account_number, form.value.date], refreshBalance, { immediate: true });

const submitting = ref(false);
function submit() {
    submitting.value = true;
    if (isEdit) {
        router.patch(props.submitUrl, form.value, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.submitUrl, form.value, { onFinish: () => { submitting.value = false; } });
    }
}

const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

// Rows already exclude the one currently being edited (matches the
// original's @continue when $model->id == $settlement->id).
const visibleSettlements = computed(() =>
    props.settlements.filter(s => !(isEdit && s.id === props.editingSettlement.id))
);
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    ← Back To Loan Schedule
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">Loan Schedule Payment</h1>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Read-only installment info -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Loan Schedule Payment</h2>
                    <div class="cvr-form-grid-3 mb-3">
                        <div>
                            <label class="cvr-form-label">Loan Name</label>
                            <input disabled :value="loanSchedule.medium_term_loan_name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Installment Due Date</label>
                            <input disabled :value="loanSchedule.date_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Currency</label>
                            <input disabled :value="loanSchedule.currency" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Beginning Balance</label>
                            <input disabled :value="loanSchedule.beginning_balance_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Schedule Payment</label>
                            <input disabled :value="loanSchedule.schedule_payment_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Interest Amount</label>
                            <input disabled :value="loanSchedule.interest_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Principle Amount</label>
                            <input disabled :value="loanSchedule.principle_amount_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">End Balance</label>
                            <input disabled :value="loanSchedule.end_balance_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="cvr-form-grid-4 items-end">
                        <div>
                            <label class="cvr-form-label">Settlement Date *</label>
                            <input v-model="form.date" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Settlement Amount</label>
                            <input v-model="form.amount" type="number" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Current Account *</label>
                            <select v-model="form.current_account_number" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="acc in currentAccounts" :key="acc.value" :value="acc.value">{{ acc.label }}</option>
                            </select>
                            <p class="text-xs cvr-text-muted mt-1">
                                Available Balance <span v-if="balanceDateLabel">[ {{ balanceDateLabel }} ]</span>
                                <span v-if="balanceLoading">(updating...)</span>
                                <strong v-else-if="availableBalance !== null">: {{ availableBalance }}</strong>
                                <span v-else>: -</span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button v-if="canManage()" type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                                {{ submitting ? 'Saving...' : 'Save' }}
                            </button>
                            <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">Back To Loan Schedule</Link>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Settlement history -->
            <div class="cvr-card mt-6">
                <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Settlement History</h2>
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Account Number</th>
                            <th class="px-3 py-2 text-left">Amount</th>
                            <th class="px-3 py-2 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(s, i) in visibleSettlements" :key="s.id" class="cvr-table-row">
                            <td class="px-3 py-2 cvr-text-secondary">{{ i + 1 }}</td>
                            <td class="px-3 py-2 whitespace-nowrap cvr-text-secondary">{{ s.date_formatted }}</td>
                            <td class="px-3 py-2 cvr-text-secondary">{{ s.account_number }}</td>
                            <td class="px-3 py-2 cvr-num">{{ s.amount_formatted }}</td>
                            <td class="px-3 py-2">
                                <!-- Only the LAST settlement is editable/deletable —
                                     confirmed from the original's @if($loop->last) guard. -->
                                <div v-if="s.id === lastSettlementId" class="flex items-center gap-2">
                                    <Link v-if="canManage()" :href="s.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">Edit</Link>
                                    <button v-if="canManage()" @click="confirmDelete(s)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="visibleSettlements.length === 0">
                            <td colspan="5" class="px-3 py-6 text-center cvr-text-muted">No settlements yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Delete this settlement?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
