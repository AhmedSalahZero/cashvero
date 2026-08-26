<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';

const page = usePage();
const flashErrors = computed(() => page.props.errors || {});

const props = defineProps({
    company: Object,
    factoringCompany: Object,
    canCreate: Boolean,
    createUrl: String,
    rows: Array,
    canUpdate: Boolean,
    canDelete: Boolean,
    backUrl: String,
    navUrls: Object,
});

/* ── Tabs: Contracts (running, current terms) vs Archived Contracts
   (any contract that has ever been renewed, grouped one row per
   contract with its full history expandable) — same pattern as the
   Overdraft/LG/LC facilities. ─────────────────────────────────────── */
const activeMainTab = ref('contracts');
const archivedRows = computed(() => props.rows.filter(r => r.has_renewals));
const expandedArchiveId = ref(null);
function toggleArchiveExpand(row) {
    expandedArchiveId.value = expandedArchiveId.value === row.id ? null : row.id;
}
const deleteRenewalTarget = ref(null);
function confirmDeleteRenewal(row) { deleteRenewalTarget.value = row; }
function cancelDeleteRenewal() { deleteRenewalTarget.value = null; }
function destroyRenewal() {
    router.delete(deleteRenewalTarget.value.delete_renewal_url, {
        onFinish: () => { deleteRenewalTarget.value = null; },
    });
}

const search = ref('');
const filteredRows = computed(() => {
    if (!search.value) return props.rows;
    const q = search.value.toLowerCase();
    return props.rows.filter(r => (r.recourse_type_label || '').toLowerCase().includes(q) || (r.currency || '').toLowerCase().includes(q));
});

const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

/* ── Facility Renewal — Phase 7 (final facility type). Same shape as
   Clean Overdraft's renewal: only limit, Borrowing Rate, Margin Rate,
   Min Interest Rate, Highest-Debt-Balance rate, Admin fees rate, and
   settlement days — never recourse_type/currency, since a renewal
   continues the SAME contract rather than creating a new one. ────── */
const renewTarget = ref(null);
const renewForm = ref({
    effective_date: '',
    contract_end_date: '',
    limit: '',
    borrowing_rate: '',
    margin_rate: '',
    min_interest_rate: '',
    highest_debt_balance_rate: '',
    admin_fees_rate: '',
    to_be_setteled_max_within_days: '',
    notes: '',
});
const renewErrors = ref({});
function openRenew(row) {
    renewTarget.value = row;
    renewErrors.value = {};
    renewForm.value = {
        effective_date: '',
        contract_end_date: '',
        limit: '',
        borrowing_rate: '',
        margin_rate: '',
        min_interest_rate: '',
        highest_debt_balance_rate: '',
        admin_fees_rate: '',
        to_be_setteled_max_within_days: '',
        notes: '',
    };
}
function cancelRenew() { renewTarget.value = null; }
const renewInterestRatePreview = computed(() => {
    const borrowing = renewForm.value.borrowing_rate !== '' ? Number(renewForm.value.borrowing_rate) : Number(renewTarget.value?.borrowing_rate_formatted || 0);
    const margin = renewForm.value.margin_rate !== '' ? Number(renewForm.value.margin_rate) : Number(renewTarget.value?.margin_rate_formatted || 0);
    return (borrowing + margin).toFixed(2);
});
const currentContractEndDate = computed(() => {
    if (!renewTarget.value) return '';
    const history = renewTarget.value.terms_history || [];
    const latest = history[history.length - 1];
    return (latest && latest.contract_end_date_formatted) || renewTarget.value.end_date_formatted || 'N/A';
});
function submitRenew() {
    router.post(renewTarget.value.renew_url, renewForm.value, {
        onError: (errors) => { renewErrors.value = errors; },
        onSuccess: () => { renewTarget.value = null; },
    });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Factoring Companies') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Factoring Contracts') }}</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ factoringCompany.name }}</p>

            <div v-if="Object.keys(flashErrors).length" class="mb-4 rounded border border-red-400 bg-red-50 text-red-700 text-sm px-4 py-3">
                <p v-for="(msg, key) in flashErrors" :key="key">{{ msg }}</p>
            </div>

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" :placeholder="$t('Search by recourse type or currency...')" class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                    {{ $t('+ New Contract') }}
                </Link>
            </div>

            <div class="flex items-center gap-2 mb-4 border-b cvr-border">
                <button
                    @click="activeMainTab = 'contracts'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    :class="activeMainTab === 'contracts' ? 'border-current cvr-text-primary' : 'border-transparent cvr-text-muted'"
                >{{ $t('Contracts') }}</button>
                <button
                    @click="activeMainTab = 'archived'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    :class="activeMainTab === 'archived' ? 'border-current cvr-text-primary' : 'border-transparent cvr-text-muted'"
                >{{ $t('Archived Contracts (') }}{{ archivedRows.length }})</button>
            </div>

            <div v-if="activeMainTab === 'contracts'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-start">#</th>
                            <th class="px-3 py-3 text-start">{{ $t('Start Date') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('End Date') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Recourse Type') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Currency') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Limit') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Borrowing Rate %') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Margin Rate %') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Interest Rate %') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.start_date_formatted }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.end_date_formatted }}</td>
                            <td class="px-3 py-3 cvr-text-primary">{{ row.recourse_type_label }}</td>
                            <td class="px-3 py-3 uppercase cvr-text-secondary">{{ row.currency }}</td>
                            <td class="px-3 py-3 cvr-num">{{ row.limit_formatted }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.borrowing_rate_formatted }} %</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.margin_rate_formatted }} %</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.interest_rate_formatted }} %</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <RecordLogButton subject="FactoringContract" :id="row.id" :company-id="company.id" />
                                    <button
                                        v-if="canUpdate"
                                        @click="openRenew(row)"
                                        class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs"
                                        :title="$t('Record a renewal — new limit/terms from a chosen date, without losing history')"
                                    >{{ $t('Renew') }}</button>
                                    <Link v-if="canUpdate" :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">{{ $t('Edit') }}</Link>
                                    <button v-if="canDelete" @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">{{ $t('Delete') }}</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="10" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No Factoring Contract records found.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Archived Contracts tab -->
            <div v-if="activeMainTab === 'archived'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Recourse Type') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Currency') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Current Limit') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Renewals') }}</th>
                            <th class="px-4 py-3 text-start"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(row, index) in archivedRows" :key="row.id">
                            <tr class="cvr-table-row cursor-pointer" @click="toggleArchiveExpand(row)">
                                <td class="px-4 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                                <td class="px-4 py-3 cvr-text-primary">{{ row.recourse_type_label }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.limit_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.terms_history.length - 1 }}</td>
                                <td class="px-4 py-3 cvr-text-muted">{{ expandedArchiveId === row.id ? '▲ Hide History' : '▼ Show History' }}</td>
                            </tr>
                            <tr v-if="expandedArchiveId === row.id">
                                <td colspan="6" class="px-4 py-3 cvr-card-bg">
                                    <table class="w-full text-xs border rounded overflow-hidden">
                                        <thead class="cvr-table-head">
                                            <tr>
                                                <th class="px-3 py-2 text-start">{{ $t('Effective Date') }}</th>
                                                <th class="px-3 py-2 text-start">{{ $t('Contract End Date') }}</th>
                                                <th class="px-3 py-2 text-start">{{ $t('Limit') }}</th>
                                                <th class="px-3 py-2 text-start">{{ $t('Borrowing Rate') }}</th>
                                                <th class="px-3 py-2 text-start">{{ $t('Margin Rate') }}</th>
                                                <th class="px-3 py-2 text-start">{{ $t('Interest Rate') }}</th>
                                                <th class="px-3 py-2 text-start">{{ $t('Settlement Days') }}</th>
                                                <th class="px-3 py-2 text-start">{{ $t('Chapter') }}</th>
                                                <th class="px-3 py-2 text-start"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(term, tIndex) in row.terms_history" :key="term.id" class="cvr-table-row">
                                                <td class="px-3 py-2 whitespace-nowrap">{{ term.effective_date_formatted }}</td>
                                                <td class="px-3 py-2 whitespace-nowrap">{{ term.contract_end_date_formatted }}</td>
                                                <td class="px-3 py-2 cvr-num">{{ term.limit_formatted }}</td>
                                                <td class="px-3 py-2">{{ term.borrowing_rate }} %</td>
                                                <td class="px-3 py-2">{{ term.margin_rate }} %</td>
                                                <td class="px-3 py-2">{{ term.interest_rate }} %</td>
                                                <td class="px-3 py-2">{{ term.to_be_setteled_max_within_days }}</td>
                                                <td class="px-3 py-2 cvr-text-muted">{{ term.is_original ? 'Original' : 'Renewal' }}</td>
                                                <td class="px-3 py-2">
                                                    <button
                                                        v-if="canUpdate && tIndex === row.terms_history.length - 1 && !term.is_original"
                                                        @click.stop="confirmDeleteRenewal(row)"
                                                        class="cvr-btn-danger px-2 py-1 rounded border text-xs"
                                                        :title="$t('Undo this renewal — reverts the contract to its previous chapter\'s terms')"
                                                    >{{ $t('Delete This Renewal') }}</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="archivedRows.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No contract has been renewed yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Delete renewal confirmation -->
            <div v-if="deleteRenewalTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-2">{{ $t('Delete this renewal?') }}</h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        {{ $t('The contract will revert to its previous chapter\'s terms. This is blocked if any transactions are already dated on or after the renewal\'s effective date.') }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDeleteRenewal" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRenewal" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Do you want to delete this item?') }}</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>

            <!-- Contract Renewal modal -->
            <div v-if="renewTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <h2 class="text-lg font-medium cvr-text-primary mb-1">{{ $t('Renew Contract —') }} {{ renewTarget.recourse_type_label }}</h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        {{ $t('Leave a field blank to keep it unchanged from the current terms. Nothing dated before the effective date is ever recalculated — past interest, fees, and due dates stay exactly as they were.') }}
                    </p>

                    <!-- Read-only terms history -->
                    <div v-if="renewTarget.terms_history && renewTarget.terms_history.length" class="mb-4 border rounded overflow-hidden">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="cvr-table-head">
                                    <th class="px-3 py-2 text-start">{{ $t('Effective Date') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Contract End Date') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Limit') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Borrowing Rate') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Margin Rate') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Chapter') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="term in renewTarget.terms_history" :key="term.id" class="cvr-table-row">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ term.effective_date_formatted }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ term.contract_end_date_formatted }}</td>
                                    <td class="px-3 py-2 cvr-num">{{ term.limit_formatted }}</td>
                                    <td class="px-3 py-2">{{ term.borrowing_rate }} %</td>
                                    <td class="px-3 py-2">{{ term.margin_rate }} %</td>
                                    <td class="px-3 py-2 cvr-text-muted">{{ term.is_original ? $t('Original') : $t('Renewal') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('Renewal Effective Date') }} *</label>
                            <input type="date" v-model="renewForm.effective_date" class="cvr-input w-full" />
                            <p class="text-xs cvr-text-muted mt-1">
                                {{ $t('Current contract end date:') }} <strong>{{ currentContractEndDate }}</strong> {{ $t('— the renewal date must be after this.') }}
                            </p>
                            <p v-if="renewErrors.effective_date" class="text-xs text-red-600 mt-1">{{ renewErrors.effective_date }}</p>
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Contract End Date') }} *</label>
                            <input type="date" v-model="renewForm.contract_end_date" class="cvr-input w-full" />
                            <p v-if="renewErrors.contract_end_date" class="text-xs text-red-600 mt-1">{{ renewErrors.contract_end_date }}</p>
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Limit') }}</label>
                            <input type="number" step="any" v-model="renewForm.limit" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                            <p v-if="renewErrors.limit" class="text-xs text-red-600 mt-1">{{ renewErrors.limit }}</p>
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Settlement Days') }}</label>
                            <input type="number" v-model="renewForm.to_be_setteled_max_within_days" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Borrowing Rate %') }}</label>
                            <input type="number" step="any" v-model="renewForm.borrowing_rate" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Margin Rate %') }}</label>
                            <input type="number" step="any" v-model="renewForm.margin_rate" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('Interest Rate % — Borrowing + Margin') }}</label>
                            <input type="text" :value="renewInterestRatePreview" disabled class="cvr-input w-full opacity-70" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Min Interest Rate %') }}</label>
                            <input type="number" step="any" v-model="renewForm.min_interest_rate" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Highest-Debt-Balance Rate %') }}</label>
                            <input type="number" step="any" v-model="renewForm.highest_debt_balance_rate" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Admin Fees Rate %') }}</label>
                            <input type="number" step="any" v-model="renewForm.admin_fees_rate" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('Notes') }}</label>
                            <textarea v-model="renewForm.notes" class="cvr-input w-full" rows="2" :placeholder="$t('e.g. Bank renewal letter reference')"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button @click="cancelRenew" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitRenew" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm Renewal') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
