<script setup>
import { ref, reactive, watch, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';
import { todayDate } from '@/composables/today';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر. */
const maxDate = todayDate();
import Pagination from '@/Components/Pagination.vue';
import { mapAccountNumberOptions, accountNumberOption } from '@/composables/useAccountNumberOptions';

const props = defineProps({
    company: Object,
    transactions: Object,
    searchFields: Object,
    filters: Object,
    canCreate: Boolean,
    canUpdate: Boolean,
    canDelete: Boolean,
    financialInstitutionBanks: Array,
    accountTypes: Array,
    urls: Object,
});

const rows = computed(() => props.transactions?.data || []);
/* Row numbers continue across pages instead of restarting at 1 on each. */
const rowOffset = computed(() => ((props.transactions?.current_page || 1) - 1) * (props.transactions?.per_page || 0));

/* ── Search ───────────────────────────────────────────────────────
   Runs server-side, so it searches every transaction rather than the
   twenty currently rendered. Customer and Factoring Company match on
   the related record's name. */
const search = reactive({
    field: props.filters?.field || 'factoring_date',
    value: props.filters?.value || '',
    from: props.filters?.from || '',
    to: props.filters?.to || '',
});

function applySearch() {
    router.get(props.urls.index, { ...search, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
}

function resetSearch() {
    search.field = 'factoring_date';
    search.value = '';
    search.from = '';
    search.to = '';
    router.get(props.urls.index, {}, { preserveScroll: true, replace: true });
}

const page = usePage();
const errors = computed(() => page.props.errors || {});

async function fetchJson(url) {
    const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    const text = await res.text();
    let data = null;
    try { data = JSON.parse(text); } catch (e) { /* leave null */ }
    return { ok: res.ok, data };
}

const canAct = computed(() => props.canCreate || props.canUpdate);

/* ── Collect modal ────────────────────────────────────────────── */
const collectTarget = ref(null);
const collectForm = reactive({ collection_date: '', financial_institution_id: '', account_type_id: '', account_number: '' });
const collectAccountNumbers = ref([]);
/**
 * ⚠️ REAL BUG FIXED HERE (client-flagged): "Account Number sometimes
 * works, sometimes doesn't." Two compounding bugs in the old
 * watch-only approach:
 *   1. The fetch URL uses collectTarget.value.invoice_currency, but
 *      the watch() below only listened for account_type_id and
 *      financial_institution_id changing — currency was never a
 *      watched dependency. So opening a row that shares the same
 *      account type + bank as whatever was previously loaded (very
 *      common — most transactions go through the same account type/
 *      bank) but has a DIFFERENT currency never re-fired the watcher,
 *      silently leaving the account list for the wrong currency (or
 *      empty, if the modal had just been closed).
 *   2. openCollect() only set the form fields from the row's own
 *      values and relied entirely on the watcher noticing a change —
 *      it never forced a fresh fetch itself.
 * Fixed by always calling loadCollectAccountNumbers() directly when
 * the modal opens (so a fetch always happens, regardless of whether
 * the watched values changed), keeping the watch() for when the user
 * manually changes Bank/Account Type afterwards, and adding a
 * request-sequence guard so a slow, stale response can never
 * overwrite a newer one (a real race condition if a user reopens the
 * modal or switches dropdowns quickly).
 */
let collectAccountNumbersRequestId = 0;
async function loadCollectAccountNumbers() {
    const requestId = ++collectAccountNumbersRequestId;
    if (!collectTarget.value || !collectForm.account_type_id || !collectForm.financial_institution_id) {
        collectAccountNumbers.value = [];
        return;
    }
    const url = `${props.urls.getAccountNumbersForType}/${collectForm.account_type_id}/${collectTarget.value.invoice_currency}/${collectForm.financial_institution_id}`;
    const result = await fetchJson(url);
    if (requestId !== collectAccountNumbersRequestId) return; // a newer request has since started — discard this stale result
    collectAccountNumbers.value = mapAccountNumberOptions(result.data?.data);
}
function openCollect(row) {
    collectTarget.value = row;
    collectForm.collection_date = new Date().toISOString().slice(0, 10);
    collectForm.financial_institution_id = row.financial_institution_id || '';
    collectForm.account_type_id = row.account_type_id || '';
    collectForm.account_number = row.account_number || '';
    collectAccountNumbers.value = accountNumberOption(row.account_number);
    loadCollectAccountNumbers();
}
watch([() => collectForm.account_type_id, () => collectForm.financial_institution_id], () => {
    loadCollectAccountNumbers();
});
function submitCollect() {
    router.post(collectTarget.value.mark_collected_url, { ...collectForm }, { onSuccess: () => { collectTarget.value = null; } });
}

/* ── Reject modal ─────────────────────────────────────────────── */
const rejectTarget = ref(null);
const rejectForm = reactive({ rejection_date: '', uncollected_invoice_charges: 0, financial_institution_id: '', account_type_id: '', account_number: '' });
const rejectAccountNumbers = ref([]);
// Same fix as loadCollectAccountNumbers() above — see the comment there.
let rejectAccountNumbersRequestId = 0;
async function loadRejectAccountNumbers() {
    const requestId = ++rejectAccountNumbersRequestId;
    if (!rejectTarget.value || !rejectForm.account_type_id || !rejectForm.financial_institution_id) {
        rejectAccountNumbers.value = [];
        return;
    }
    const url = `${props.urls.getAccountNumbersForType}/${rejectForm.account_type_id}/${rejectTarget.value.invoice_currency}/${rejectForm.financial_institution_id}`;
    const result = await fetchJson(url);
    if (requestId !== rejectAccountNumbersRequestId) return;
    rejectAccountNumbers.value = mapAccountNumberOptions(result.data?.data);
}
function openReject(row) {
    rejectTarget.value = row;
    rejectForm.rejection_date = new Date().toISOString().slice(0, 10);
    rejectForm.uncollected_invoice_charges = 0;
    rejectForm.financial_institution_id = row.financial_institution_id || '';
    rejectForm.account_type_id = row.account_type_id || '';
    rejectForm.account_number = row.account_number || '';
    rejectAccountNumbers.value = accountNumberOption(row.account_number);
    loadRejectAccountNumbers();
}
watch([() => rejectForm.account_type_id, () => rejectForm.financial_institution_id], () => {
    loadRejectAccountNumbers();
});
function submitReject() {
    router.post(rejectTarget.value.mark_rejected_url, { ...rejectForm }, { onSuccess: () => { rejectTarget.value = null; } });
}

/* ── Revert / Delete confirmations ───────────────────────────── */
const revertCollectTarget = ref(null);
function submitRevertCollect() {
    router.post(revertCollectTarget.value.revert_collected_url, {}, { onFinish: () => { revertCollectTarget.value = null; } });
}
const revertRejectTarget = ref(null);
function submitRevertReject() {
    router.post(revertRejectTarget.value.revert_rejected_url, {}, { onFinish: () => { revertRejectTarget.value = null; } });
}
const deleteTarget = ref(null);
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Factoring With Recourse') }}</h1>
                    <p class="text-sm cvr-text-muted">{{ $t('Factoring Transactions') }}</p>
                </div>
                <Link v-if="canCreate" :href="urls.create" class="cvr-btn-primary px-4 py-2 rounded text-sm">
                    {{ $t('+ Create New') }}
                </Link>
            </div>

            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="cvr-form-label">{{ $t('Search By') }}</label>
                    <select v-model="search.field" class="cvr-input px-3 py-2 rounded">
                        <option v-for="(label, field) in searchFields" :key="field" :value="field">{{ label }}</option>
                    </select>
                </div>
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search.value" @keyup.enter="applySearch" type="text" :placeholder="$t('Search...')" class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('From') }}</label>
                    <input v-model="search.from" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('To') }}</label>
                    <input v-model="search.to" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <button @click="applySearch" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">{{ $t('Search') }}</button>
                <button @click="resetSearch" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">{{ $t('Reset') }}</button>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Factoring Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Factoring Company') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Customer') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Invoice Number') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Currency') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Factoring Amount') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Received Amount') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Status') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Bank') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Account Number') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Control') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3">{{ rowOffset + index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ row.factoring_date_formatted }}</td>
                            <td class="px-4 py-3">{{ row.factoring_company_name }}</td>
                            <td class="px-4 py-3">{{ row.customer_name }}</td>
                            <td class="px-4 py-3">{{ row.invoice_number }}</td>
                            <td class="px-4 py-3 uppercase">{{ row.invoice_currency }}</td>
                            <td class="px-4 py-3 cvr-num">{{ Number(row.factoring_amount).toLocaleString('en-EG') }}</td>
                            <td class="px-4 py-3 cvr-num">{{ Number(row.received_amount).toLocaleString('en-EG') }}</td>
                            <td class="px-4 py-3">
                                <template v-if="row.is_collected">
                                    <span class="cvr-badge cvr-badge-active">Collected</span>
                                    <div v-if="row.collection_date" class="text-xs cvr-text-muted mt-1">{{ row.collection_date }}</div>
                                </template>
                                <template v-else-if="row.is_rejected">
                                    <span class="cvr-badge cvr-badge-overdue">Rejected</span>
                                    <div v-if="row.rejection_date" class="text-xs cvr-text-muted mt-1">{{ row.rejection_date }}</div>
                                    <div v-if="row.uncollected_invoice_charges > 0" class="text-xs cvr-text-muted mt-1">
                                        Uncollected Invoices Charges: {{ Number(row.uncollected_invoice_charges).toLocaleString('en-EG') }}
                                    </div>
                                </template>
                                <span v-else class="cvr-badge cvr-badge-pending">{{ $t('Pending') }}</span>
                            </td>
                            <td class="px-4 py-3">{{ row.financial_institution_name }}</td>
                            <td class="px-4 py-3">{{ row.account_number_label || row.account_number }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <RecordLogButton subject="FactoringTransaction" :id="row.id" :company-id="company.id" />
                                    <Link v-if="canUpdate && row.is_pending" :href="row.edit_url" class="cvr-action-btn" :title="$t('Edit')">✏️</Link>
                                    <button v-if="canAct && row.is_pending" @click="openCollect(row)" class="cvr-action-btn" :title="$t('Collect')">✅</button>
                                    <button v-if="canAct && row.is_pending" @click="openReject(row)" class="cvr-action-btn-danger cvr-action-btn" :title="$t('Reject')">✖️</button>
                                    <button v-if="canAct && row.is_collected" @click="revertCollectTarget = row" class="cvr-action-btn" :title="$t('Revert Collection')">↩️</button>
                                    <button v-if="canAct && row.is_rejected" @click="revertRejectTarget = row" class="cvr-action-btn" :title="$t('Revert Rejection')">↩️</button>
                                    <button v-if="canDelete" @click="deleteTarget = row" class="cvr-action-btn-danger cvr-action-btn" :title="$t('Delete')">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="12" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No records found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="transactions" label="transactions" />

            <!-- Collect modal -->
            <div v-if="collectTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Collect') }}</h2>
                    <div v-if="collectTarget.difference_amount > 0" class="mb-4 px-4 py-3 rounded cvr-badge-pending text-sm">
                        <strong>{{ $t('Difference Amount:') }}</strong> {{ Number(collectTarget.difference_amount).toLocaleString('en-EG') }}
                        <span class="uppercase">{{ collectTarget.invoice_currency }}</span>
                        <div class="text-xs mt-1">{{ $t('Confirm that you have received this amount from the factoring company.') }}</div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Collection Date') }} *</label>
                            <input v-model="collectForm.collection_date" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.collection_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.collection_date }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Bank') }} *</label>
                            <select v-model="collectForm.financial_institution_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="b in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Type') }} *</label>
                            <select v-model="collectForm.account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="a in accountTypes" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                            <select v-model="collectForm.account_number" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="n in collectAccountNumbers" :key="n.value" :value="n.value">{{ n.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-5">
                        <button @click="collectTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitCollect" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm') }}</button>
                    </div>
                </div>
            </div>

            <!-- Reject modal -->
            <div v-if="rejectTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Rejected') }}</h2>
                    <div class="mb-4 px-4 py-3 rounded cvr-badge-pending text-sm">
                        <strong>{{ $t('Factoring Amount:') }}</strong> {{ Number(rejectTarget.factoring_amount).toLocaleString('en-EG') }}
                        <span class="uppercase">{{ rejectTarget.invoice_currency }}</span>
                        <div class="text-xs mt-1">{{ $t('Confirm payment to the factoring company because the customer did not pay.') }}</div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Uncollected Invoices Charges') }}</label>
                            <input v-model="rejectForm.uncollected_invoice_charges" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Date') }} *</label>
                            <input v-model="rejectForm.rejection_date" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.rejection_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.rejection_date }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Bank') }} *</label>
                            <select v-model="rejectForm.financial_institution_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="b in financialInstitutionBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Type') }} *</label>
                            <select v-model="rejectForm.account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="a in accountTypes" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                            <select v-model="rejectForm.account_number" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="n in rejectAccountNumbers" :key="n.value" :value="n.value">{{ n.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-5">
                        <button @click="rejectTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitReject" class="cvr-btn-danger px-3 py-1.5 rounded">{{ $t('Confirm') }}</button>
                    </div>
                </div>
            </div>

            <!-- Revert Collection confirmation -->
            <div v-if="revertCollectTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Revert Collection') }}</h2>
                    <p class="text-sm cvr-text-muted mb-4">{{ $t('Do you want to revert the collection, remove the settlement, and restore the invoice to pending?') }}</p>
                    <div class="flex justify-end gap-2">
                        <button @click="revertCollectTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitRevertCollect" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm Reset') }}</button>
                    </div>
                </div>
            </div>

            <!-- Revert Rejection confirmation -->
            <div v-if="revertRejectTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Revert Rejection') }}</h2>
                    <p class="text-sm cvr-text-muted mb-4">{{ $t('Do you want to revert the rejection and remove the related bank and factoring statement entries?') }}</p>
                    <div class="flex justify-end gap-2">
                        <button @click="revertRejectTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitRevertReject" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm Reset') }}</button>
                    </div>
                </div>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Do you want to delete this item?') }}</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
