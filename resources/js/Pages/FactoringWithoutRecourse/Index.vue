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

/* ── Mark As Settled modal ────────────────────────────────────── */
const settleTarget = ref(null);
const settleForm = reactive({ settlement_date: '' });
function openSettle(row) {
    settleTarget.value = row;
    settleForm.settlement_date = new Date().toISOString().slice(0, 10);
}
function submitSettle() {
    router.post(settleTarget.value.mark_as_settled_url, { ...settleForm }, { onSuccess: () => { settleTarget.value = null; } });
}

/* ── Revert Settlement confirmation ──────────────────────────── */
const revertSettleTarget = ref(null);
function submitRevertSettle() {
    router.post(revertSettleTarget.value.revert_settlement_url, {}, { onFinish: () => { revertSettleTarget.value = null; } });
}

/* ── Record Difference Received modal ────────────────────────── */
const differenceTarget = ref(null);
const differenceForm = reactive({ difference_received_date: '', financial_institution_id: '', account_type_id: '', account_number: '' });
const differenceAccountNumbers = ref([]);
// Same fix as FactoringWithRecourse/Index.vue's loadCollectAccountNumbers():
// invoice_currency wasn't a watched dependency, and opening the modal
// never forced a fresh fetch, so a row sharing account_type_id +
// financial_institution_id with whatever was previously loaded (but a
// different currency) silently kept the stale/wrong account list.
let differenceAccountNumbersRequestId = 0;
async function loadDifferenceAccountNumbers() {
    const requestId = ++differenceAccountNumbersRequestId;
    if (!differenceTarget.value || !differenceForm.account_type_id || !differenceForm.financial_institution_id) {
        differenceAccountNumbers.value = [];
        return;
    }
    const url = `${props.urls.getAccountNumbersForType}/${differenceForm.account_type_id}/${differenceTarget.value.invoice_currency}/${differenceForm.financial_institution_id}`;
    const result = await fetchJson(url);
    if (requestId !== differenceAccountNumbersRequestId) return;
    differenceAccountNumbers.value = mapAccountNumberOptions(result.data?.data);
}
function openDifference(row) {
    differenceTarget.value = row;
    differenceForm.difference_received_date = new Date().toISOString().slice(0, 10);
    differenceForm.financial_institution_id = row.financial_institution_id || '';
    differenceForm.account_type_id = row.account_type_id || '';
    differenceForm.account_number = row.account_number || '';
    differenceAccountNumbers.value = accountNumberOption(row.account_number);
    loadDifferenceAccountNumbers();
}
watch([() => differenceForm.account_type_id, () => differenceForm.financial_institution_id], () => {
    loadDifferenceAccountNumbers();
});
function submitDifference() {
    router.post(differenceTarget.value.mark_difference_received_url, { ...differenceForm }, { onSuccess: () => { differenceTarget.value = null; } });
}

/* ── Revert Difference Received confirmation ─────────────────── */
const revertDifferenceTarget = ref(null);
function submitRevertDifference() {
    router.post(revertDifferenceTarget.value.revert_difference_received_url, {}, { onFinish: () => { revertDifferenceTarget.value = null; } });
}

/* ── Delete confirmation ──────────────────────────────────────── */
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
                    <h1 class="text-xl font-semibold cvr-text-primary mb-1">Factoring Without Recourse</h1>
                    <p class="text-sm cvr-text-muted">Factoring Transactions</p>
                </div>
                <Link v-if="canCreate" :href="urls.create" class="cvr-btn-primary px-4 py-2 rounded text-sm">
                    + Create New
                </Link>
            </div>

            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="cvr-form-label">Search By</label>
                    <select v-model="search.field" class="cvr-input px-3 py-2 rounded">
                        <option v-for="(label, field) in searchFields" :key="field" :value="field">{{ label }}</option>
                    </select>
                </div>
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search.value" @keyup.enter="applySearch" type="text" placeholder="Search..." class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
                <div>
                    <label class="cvr-form-label">From</label>
                    <input v-model="search.from" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">To</label>
                    <input v-model="search.to" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <button @click="applySearch" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">Search</button>
                <button @click="resetSearch" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">Reset</button>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Factoring Date</th>
                            <th class="px-4 py-3 text-left">Factoring Company</th>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Invoice Number</th>
                            <th class="px-4 py-3 text-left">Currency</th>
                            <th class="px-4 py-3 text-left">Factoring Amount</th>
                            <th class="px-4 py-3 text-left">Received Amount</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Bank</th>
                            <th class="px-4 py-3 text-left">Account Number</th>
                            <th class="px-4 py-3 text-left">Control</th>
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
                            <td class="px-4 py-3 cvr-num">{{ Number(row.factoring_amount).toLocaleString() }}</td>
                            <td class="px-4 py-3 cvr-num">{{ Number(row.received_amount).toLocaleString() }}</td>
                            <td class="px-4 py-3">
                                <template v-if="row.is_settled">
                                    <span class="cvr-badge cvr-badge-active">Settled</span>
                                    <div v-if="row.settled_at" class="text-xs cvr-text-muted mt-1">{{ row.settled_at }}</div>
                                </template>
                                <span v-else class="cvr-badge cvr-badge-pending">Pending</span>
                                <div v-if="row.is_difference_received" class="text-xs cvr-text-muted mt-1">
                                    Difference Received: {{ Number(row.difference_received_amount).toLocaleString() }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ row.financial_institution_name }}</td>
                            <td class="px-4 py-3">{{ row.account_number }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <RecordLogButton subject="FactoringTransaction" :id="row.id" :company-id="company.id" />
                                    <Link v-if="canUpdate && !row.is_settled" :href="row.edit_url" class="cvr-action-btn" title="Edit">✏️</Link>
                                    <button v-if="canAct && !row.is_settled" @click="openSettle(row)" class="cvr-action-btn" title="Mark As Settled">✅</button>
                                    <button v-if="canAct && row.is_settled" @click="revertSettleTarget = row" class="cvr-action-btn" title="Reset Settlement">↩️</button>
                                    <button v-if="canAct && row.difference_amount > 0 && !row.is_difference_received" @click="openDifference(row)" class="cvr-action-btn" title="Record Difference Received">💰</button>
                                    <button v-if="canAct && row.is_difference_received" @click="revertDifferenceTarget = row" class="cvr-action-btn" title="Revert Difference Received">↩️</button>
                                    <button v-if="canDelete" @click="deleteTarget = row" class="cvr-action-btn-danger cvr-action-btn" title="Delete">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="12" class="px-4 py-8 text-center cvr-text-muted">No records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="transactions" label="transactions" />

            <!-- Mark As Settled modal -->
            <div v-if="settleTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Mark As Settled</h2>
                    <div>
                        <label class="cvr-form-label">Date *</label>
                        <input v-model="settleForm.settlement_date" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.settlement_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.settlement_date }}</p>
                    </div>
                    <div class="flex justify-end gap-2 mt-5">
                        <button @click="settleTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitSettle" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Revert Settlement confirmation -->
            <div v-if="revertSettleTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Reset Settlement</h2>
                    <p class="text-sm cvr-text-muted mb-4">Do you want to revert the settlement and remove the related factoring statement entry?</p>
                    <div class="flex justify-end gap-2">
                        <button @click="revertSettleTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitRevertSettle" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm Reset</button>
                    </div>
                </div>
            </div>

            <!-- Record Difference Received modal -->
            <div v-if="differenceTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Record Difference Received</h2>
                    <div class="mb-4 px-4 py-3 rounded cvr-badge-pending text-sm">
                        <strong>Difference Amount:</strong> {{ Number(differenceTarget.difference_amount).toLocaleString() }}
                        <span class="uppercase">{{ differenceTarget.invoice_currency }}</span>
                        <div class="text-xs mt-1">Confirm that you have received this amount.</div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="cvr-form-label">Date *</label>
                            <input v-model="differenceForm.difference_received_date" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.difference_received_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.difference_received_date }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Bank *</label>
                            <select v-model="differenceForm.financial_institution_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="b in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Account Type *</label>
                            <select v-model="differenceForm.account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="a in accountTypes" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Account Number *</label>
                            <select v-model="differenceForm.account_number" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="n in differenceAccountNumbers" :key="n.value" :value="n.value">{{ n.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-5">
                        <button @click="differenceTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitDifference" class="cvr-btn-copper px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- Revert Difference Received confirmation -->
            <div v-if="revertDifferenceTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Revert Difference Received</h2>
                    <p class="text-sm cvr-text-muted mb-2">Do you want to revert the difference received and remove the related bank movement?</p>
                    <p v-if="revertDifferenceTarget.difference_received_amount" class="text-sm">
                        <strong>Difference Amount:</strong> {{ Number(revertDifferenceTarget.difference_received_amount).toLocaleString() }}
                        <span class="uppercase">{{ revertDifferenceTarget.invoice_currency }}</span>
                    </p>
                    <div class="flex justify-end gap-2 mt-5">
                        <button @click="revertDifferenceTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitRevertDifference" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm Reset</button>
                    </div>
                </div>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to delete this item?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded">Confirm Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
