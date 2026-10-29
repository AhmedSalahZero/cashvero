<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';
import { usePermissions } from '@/composables/usePermissions';
import { todayDate } from '@/composables/today';

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    financialInstitution: Object,
    canCreate: Boolean,
    createUrl: String,
    rows: Array,
    canUpload: Boolean,
    canUpdate: Boolean,
    canDelete: Boolean,
    /* Early Settlement popup data. Accounts are keyed
       [currency][accountTypeId] => [{value, label}] — current accounts and
       overdrafts at this bank, in the loan's currency. */
    /* False in production: the "Early Settlement" button is hidden there.
       Edit / Delete of an existing early settlement are unaffected. */
    earlySettlementEnabled: Boolean,
    earlySettlementAccountTypes: Array,
    earlySettlementAccounts: Object,
    balanceLookupUrl: String,
    backUrl: String,
    navUrls: Object,
});

const { can } = usePermissions();
// Same key RoutePermissionMap enforces on the early-settlement routes,
// and on paying installments.
const canManageSchedule = () => can('medium_term_loan.manage_schedule');

const search = ref('');
const filteredRows = computed(() => {
    if (!search.value) return props.rows;
    const q = search.value.toLowerCase();
    return props.rows.filter(r => (r.name || '').toLowerCase().includes(q));
});

const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
const deletingRow = ref(false);
function destroyRow() {
    if (deletingRow.value) return;
    deletingRow.value = true;
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deletingRow.value = false; deleteTarget.value = null; } });
}

const fmt = (n) => Number(n || 0).toLocaleString('en-EG', { maximumFractionDigits: 2 });

/* ── Early Settlement popup ───────────────────────────────────────────
   The customer pays off the rest of the loan in one go: the bank sums the
   remaining principle (the amount's default) and adds an early-settlement
   fee. Saving closes every open installment without a cash movement of
   its own; the only cash movement is amount + fee from the chosen
   account. ────────────────────────────────────────────────────────── */
const maxDate = todayDate();
const earlySettlementRow = ref(null);
const isEditingEarlySettlement = computed(() => !!earlySettlementRow.value?.early_settlement);
const earlySettlementForm = ref({});
const earlySettlementSubmitting = ref(false);

function openEarlySettlement(row) {
    const existing = row.early_settlement;
    const firstAccountTypeId = props.earlySettlementAccountTypes[0]?.id ?? '';
    earlySettlementForm.value = {
        date: existing?.date ?? maxDate,
        amount: existing?.amount ?? row.early_settlement_preview?.remaining_principle ?? 0,
        fee_amount: existing?.fee_amount ?? 0,
        account_type_id: existing?.account_type_id ?? firstAccountTypeId,
        account_number: existing?.account_number ?? '',
    };
    earlySettlementRow.value = row;
    if (!existing) selectFirstAccount();
}
function closeEarlySettlement() { earlySettlementRow.value = null; }

const earlySettlementAccountOptions = computed(() => {
    const row = earlySettlementRow.value;
    if (!row) return [];
    return props.earlySettlementAccounts?.[row.currency]?.[earlySettlementForm.value.account_type_id] ?? [];
});
function selectFirstAccount() {
    earlySettlementForm.value.account_number = earlySettlementAccountOptions.value[0]?.value ?? '';
}
function onAccountTypeChange() { selectFirstAccount(); }

const earlySettlementTotal = computed(() =>
    Number(earlySettlementForm.value.amount || 0) + Number(earlySettlementForm.value.fee_amount || 0)
);

/* Live "Available Balance" — same endpoint as the installment payment
   page; for an overdraft it returns the room. */
const availableBalance = ref(null);
const balanceLoading = ref(false);
let balanceRequestToken = 0;
async function refreshBalance() {
    const row = earlySettlementRow.value;
    const form = earlySettlementForm.value;
    if (!row || !form.account_number || !form.account_type_id || !form.date) {
        availableBalance.value = null;
        return;
    }
    const thisRequest = ++balanceRequestToken;
    balanceLoading.value = true;
    try {
        const params = new URLSearchParams({
            accountNumber: form.account_number,
            accountType: form.account_type_id,
            financialInstitutionId: props.financialInstitution.id,
            balanceDate: form.date,
        });
        if (row.early_settlement) {
            params.append('modelType', 'MediumTermLoanEarlySettlement');
            params.append('modelId', row.early_settlement.id);
        }
        const res = await fetch(`${props.balanceLookupUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
        if (thisRequest !== balanceRequestToken) return;
        if (!res.ok) throw new Error(`Balance lookup failed (HTTP ${res.status})`);
        const data = await res.json();
        availableBalance.value = data.balance ?? 0;
    } catch (err) {
        if (thisRequest !== balanceRequestToken) return;
        console.error('Available balance lookup failed:', err);
        availableBalance.value = null;
    } finally {
        if (thisRequest === balanceRequestToken) balanceLoading.value = false;
    }
}
watch(
    () => [earlySettlementRow.value?.id, earlySettlementForm.value.account_type_id, earlySettlementForm.value.account_number, earlySettlementForm.value.date],
    refreshBalance
);

function submitEarlySettlement() {
    const row = earlySettlementRow.value;
    earlySettlementSubmitting.value = true;
    const options = {
        preserveScroll: true,
        onSuccess: () => { earlySettlementRow.value = null; },
        onFinish: () => { earlySettlementSubmitting.value = false; },
    };
    if (row.early_settlement) {
        router.patch(row.early_settlement.update_url, earlySettlementForm.value, options);
    } else {
        router.post(row.early_settlement_url, earlySettlementForm.value, options);
    }
}

const earlySettlementDeleteTarget = ref(null);
const deletingEarlySettlement = ref(false);
function destroyEarlySettlement() {
    if (deletingEarlySettlement.value) return;
    deletingEarlySettlement.value = true;
    router.delete(earlySettlementDeleteTarget.value.early_settlement.delete_url, {
        preserveScroll: true,
        onFinish: () => { deletingEarlySettlement.value = false; earlySettlementDeleteTarget.value = null; },
    });
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
                    {{ $t('← Back to Banks') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Medium Term Loan') }}</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ financialInstitution.name }}</p>

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" :placeholder="$t('Search by name...')" class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                    {{ $t('+ New Loan') }}
                </Link>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-start">#</th>
                            <th class="px-3 py-3 text-start">{{ $t('Name') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Status') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Start Date') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('End Date') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Currency') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Limit') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Account Number') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Borrowing Rate') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Margin Rate') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Duration') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Installment Interval') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-3 py-3 cvr-text-primary whitespace-nowrap">{{ row.name }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span :class="row.is_early_settled ? 'cvr-badge cvr-badge-info' : 'cvr-badge cvr-badge-active'">{{ row.status_formatted }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.start_date_formatted }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.end_date_formatted }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.currency_formatted }}</td>
                            <td class="px-3 py-3 cvr-num">{{ row.limit_formatted }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.account_number }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.borrowing_rate_formatted }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.margin_rate_formatted }}</td>
                            <td class="px-3 py-3 uppercase cvr-text-secondary">{{ row.duration_formatted }}</td>
                            <td class="px-3 py-3 capitalize cvr-text-secondary">{{ row.installment_interval_formatted }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <RecordLogButton subject="MediumTermLoan" :id="row.id" :company-id="company.id" />
                                    <a v-if="canUpload" :href="row.upload_schedule_url" class="cvr-action-btn" :title="$t('Upload Loan Schedule & Apply Payments')">📤💵</a>
                                    <Link :href="row.statement_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs" :title="$t('Interest & principle: due vs paid, plus the drawdown ledger')">{{ $t('Statement') }}</Link>
                                    <template v-if="canManageSchedule()">
                                        <button v-if="earlySettlementEnabled && !row.is_early_settled && row.early_settlement_preview" @click="openEarlySettlement(row)" class="cvr-btn-copper inline-flex items-center px-2 py-1 rounded text-xs">{{ $t('Early Settlement') }}</button>
                                        <template v-if="row.early_settlement">
                                            <button @click="openEarlySettlement(row)" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">{{ $t('Edit Early Settlement') }}</button>
                                            <button @click="earlySettlementDeleteTarget = row" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">{{ $t('Delete Early Settlement') }}</button>
                                        </template>
                                    </template>
                                    <Link v-if="canUpdate" :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">{{ $t('Edit') }}</Link>
                                    <button v-if="canDelete" @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">{{ $t('Delete') }}</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="13" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No Medium Term Loan records found.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Do you want to delete this item?') }}</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" :disabled="deletingRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ deletingRow ? $t('Deleting...') : $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>

            <!-- Early Settlement popup (create / edit) -->
            <div v-if="earlySettlementRow" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <form @submit.prevent="submitEarlySettlement" class="cvr-modal rounded-lg p-6 w-full max-w-xl">
                    <h2 class="text-lg font-medium cvr-text-primary mb-1">
                        {{ isEditingEarlySettlement ? $t('Edit Early Settlement') : $t('Early Settlement') }}
                    </h2>
                    <p class="text-sm cvr-text-blue mb-4">{{ earlySettlementRow.name }} · {{ earlySettlementRow.currency_formatted }}</p>

                    <!-- What gets closed: every open installment from the first unpaid one -->
                    <div v-if="earlySettlementRow.early_settlement_preview" class="cvr-card mb-4 text-sm space-y-1">
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('First Open Installment') }}</span>
                            <span>#{{ earlySettlementRow.early_settlement_preview.first_installment_number }} · {{ earlySettlementRow.early_settlement_preview.first_installment_date_formatted }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Open Installments') }}</span>
                            <span>{{ earlySettlementRow.early_settlement_preview.open_installments_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Remaining Principle') }}</span>
                            <span class="cvr-num">{{ fmt(earlySettlementRow.early_settlement_preview.remaining_principle) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Remaining Interest') }}</span>
                            <span class="cvr-num">{{ fmt(earlySettlementRow.early_settlement_preview.remaining_interest) }}</span>
                        </div>
                    </div>
                    <div v-else-if="earlySettlementRow.early_settlement" class="cvr-card mb-4 text-sm space-y-1">
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Remaining Principle Settled') }}</span>
                            <span class="cvr-num">{{ fmt(earlySettlementRow.early_settlement.principle_settled) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Interest Waived') }}</span>
                            <span class="cvr-num">{{ fmt(earlySettlementRow.early_settlement.interest_waived) }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Date') }} *</label>
                            <input v-model="earlySettlementForm.date" type="date" :max="maxDate" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Early Settlement Amount') }} *</label>
                            <input v-model="earlySettlementForm.amount" type="number" step="0.01" min="0" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="cvr-form-label">{{ $t('Early Settlement Fees') }}</label>
                            <input v-model="earlySettlementForm.fee_amount" type="number" step="0.01" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Type') }} *</label>
                            <select v-model="earlySettlementForm.account_type_id" @change="onAccountTypeChange" required class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="type in earlySettlementAccountTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                            <select v-model="earlySettlementForm.account_number" required class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="acc in earlySettlementAccountOptions" :key="acc.value" :value="acc.value">{{ acc.label }}</option>
                            </select>
                            <p v-if="!earlySettlementAccountOptions.length" class="text-xs cvr-text-muted mt-1">{{ $t('No accounts of this type at this bank in the loan currency.') }}</p>
                            <p v-else class="text-xs cvr-text-muted mt-1">
                                {{ $t('Available Balance') }}
                                <span v-if="balanceLoading">{{ $t('(updating...)') }}</span>
                                <strong v-else-if="availableBalance !== null">: {{ fmt(availableBalance) }}</strong>
                                <span v-else>: -</span>
                            </p>
                        </div>
                    </div>

                    <p class="text-sm cvr-text-secondary mt-4">
                        {{ $t('Total Paid (Amount + Fees)') }}: <strong class="cvr-num">{{ fmt(earlySettlementTotal) }}</strong>
                    </p>

                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" @click="closeEarlySettlement" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button type="submit" :disabled="earlySettlementSubmitting" class="cvr-btn-primary px-3 py-1.5 rounded">
                            {{ earlySettlementSubmitting ? $t('Saving...') : $t('Save') }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="earlySettlementDeleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-2">{{ $t('Delete Early Settlement') }}</h2>
                    <p class="text-sm cvr-text-secondary mb-4">{{ $t('Its bank movements will be deleted and every installment it closed will go back to how it was before.') }}</p>
                    <div class="flex justify-end gap-2">
                        <button @click="earlySettlementDeleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyEarlySettlement" :disabled="deletingEarlySettlement" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ deletingEarlySettlement ? $t('Deleting...') : $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
