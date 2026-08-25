<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';

const props = defineProps({
    company: Object,
    financialInstitution: Object,
    canCreate: Boolean,
    createUrl: String,
    rows: Array,
    backUrl: String,
    navUrls: Object,
    lgTypes: Object,
    commissionIntervals: Object,
});

const page = usePage();
const flashErrors = computed(() => page.props.errors || {});

const search = ref('');
const filteredRows = computed(() => {
    if (!search.value) return props.rows;
    const q = search.value.toLowerCase();
    return props.rows.filter(r => (r.name || '').toLowerCase().includes(q));
});

const termsTarget = ref(null);
function openTerms(row) { termsTarget.value = row; }

const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

/* ── Tabs: Facilities vs Archived Facilities (any facility that has
   ever been renewed, grouped one row per facility with its full
   history expandable) — same pattern as the ODA facilities. ─────── */
const activeMainTab = ref('facilities');
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

/* ── Facility Renewal — Phase 5 (final facility type). Requires the
   full 4-row Term & Conditions matrix, same as the original Create/
   Edit form — never optional, since an LG issued after the renewal
   with no matching rate row would silently get nothing. ──────────── */
const lgTypes = props.lgTypes;
function blankRenewTermAndConditions() {
    return Object.keys(lgTypes).map(lgType => ({
        lg_type: lgType,
        cash_cover_rate: 0,
        commission_rate: 0,
        commission_interval: 'quarterly',
        min_commission_fees: 0,
        issuance_fees: 0,
    }));
}
const renewTarget = ref(null);
const renewForm = ref({ effective_date: '', limit: '', contract_end_date: '', notes: '' });
const renewTermAndConditions = ref(blankRenewTermAndConditions());
const renewErrors = ref({});
function openRenew(row) {
    renewTarget.value = row;
    renewErrors.value = {};
    renewForm.value = { effective_date: '', limit: '', contract_end_date: '', notes: '' };
    // Pre-fill labels from the row's own current matrix if available,
    // so the person only has to type new numbers, not remember the
    // LG type order.
    if (row.term_and_conditions?.length) {
        renewTermAndConditions.value = row.term_and_conditions.map(tc => ({
            lg_type: Object.keys(lgTypes).find(k => lgTypes[k] === tc.lg_type_formatted) || '',
            cash_cover_rate: 0,
            commission_rate: 0,
            commission_interval: 'quarterly',
            min_commission_fees: 0,
            issuance_fees: 0,
        }));
    } else {
        renewTermAndConditions.value = blankRenewTermAndConditions();
    }
}
function cancelRenew() { renewTarget.value = null; }
const currentContractEndDate = computed(() => {
    if (!renewTarget.value) return '';
    const history = renewTarget.value.terms_history || [];
    const latest = history[history.length - 1];
    return (latest && latest.contract_end_date_formatted) || renewTarget.value.contract_end_date_formatted || 'N/A';
});
function submitRenew() {
    const payload = { ...renewForm.value, termAndConditions: renewTermAndConditions.value };
    router.post(renewTarget.value.renew_url, payload, {
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
                    {{ $t('← Back to Banks') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('LG Facility') }}</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ financialInstitution.name }}</p>

            <div v-if="Object.keys(flashErrors).length" class="mb-4 rounded border border-red-400 bg-red-50 text-red-700 text-sm px-4 py-3">
                <p v-for="(msg, key) in flashErrors" :key="key">{{ msg }}</p>
            </div>

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" :placeholder="$t('Search by name...')" class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                    {{ $t('+ New Record') }}
                </Link>
            </div>

            <div class="flex items-center gap-2 mb-4 border-b cvr-border">
                <button
                    @click="activeMainTab = 'facilities'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    :class="activeMainTab === 'facilities' ? 'border-current cvr-text-primary' : 'border-transparent cvr-text-muted'"
                >{{ $t('Facilities') }}</button>
                <button
                    @click="activeMainTab = 'archived'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    :class="activeMainTab === 'archived' ? 'border-current cvr-text-primary' : 'border-transparent cvr-text-muted'"
                >Archived Facilities ({{ archivedRows.length }})</button>
            </div>

            <div v-if="activeMainTab === 'facilities'">
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Start Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('End Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Currency') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Limit') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-4 py-3 cvr-text-primary">{{ row.name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.contract_start_date_formatted }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.contract_end_date_formatted }}</td>
                            <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency }}</td>
                            <td class="px-4 py-3 cvr-num">{{ row.limit_formatted }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <RecordLogButton subject="LetterOfGuaranteeFacility" :id="row.id" :company-id="company.id" />
                                    <button @click="openTerms(row)" class="cvr-btn-secondary inline-flex items-center gap-1 px-2 py-1 rounded border text-xs">
                                        {{ $t('🏷️ Click Here') }}
                                    </button>
                                    <button @click="openRenew(row)" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs" :title="$t('Renew this facility with new terms')">
                                        {{ $t('Renew') }}
                                    </button>
                                    <Link :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">
                                        {{ $t('Edit') }}
                                    </Link>
                                    <button @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">
                                        {{ $t('Delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No LG Facility records found.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>

            <!-- Archived Facilities tab -->
            <div v-if="activeMainTab === 'archived'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Name') }}</th>
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
                                <td class="px-4 py-3 cvr-text-primary">{{ row.name }}</td>
                                <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.limit_formatted }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.terms_history.length - 1 }}</td>
                                <td class="px-4 py-3 cvr-text-muted">{{ expandedArchiveId === row.id ? '▲ Hide History' : '▼ Show History' }}</td>
                            </tr>
                            <tr v-if="expandedArchiveId === row.id">
                                <td colspan="6" class="px-4 py-3 cvr-card-bg">
                                    <div v-for="(chapter, cIndex) in row.terms_history" :key="chapter.id" class="mb-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="text-xs cvr-text-muted">
                                                <strong>{{ chapter.is_original ? 'Original' : 'Renewal' }}</strong>
                                                — {{ chapter.effective_date_formatted }} to {{ chapter.contract_end_date_formatted }}
                                                — Limit: {{ chapter.limit_formatted }}
                                            </p>
                                            <button
                                                v-if="cIndex === row.terms_history.length - 1 && !chapter.is_original"
                                                @click.stop="confirmDeleteRenewal(row)"
                                                class="cvr-btn-danger px-2 py-1 rounded border text-xs"
                                                title="Undo this renewal — reverts the facility to its previous chapter's terms"
                                            >Delete This Renewal</button>
                                        </div>
                                        <table class="w-full text-xs border rounded overflow-hidden">
                                            <thead class="cvr-table-head">
                                                <tr>
                                                    <th class="px-3 py-2 text-start">LG Type</th>
                                                    <th class="px-3 py-2 text-start">Cash Cover Rate</th>
                                                    <th class="px-3 py-2 text-start">Commission Rate</th>
                                                    <th class="px-3 py-2 text-start">Commission Interval</th>
                                                    <th class="px-3 py-2 text-start">Min Commission Fees</th>
                                                    <th class="px-3 py-2 text-start">Issuance Fees</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(tc, i) in chapter.term_and_conditions" :key="i" class="cvr-table-row">
                                                    <td class="px-3 py-2 cvr-text-primary">{{ tc.lg_type_formatted }}</td>
                                                    <td class="px-3 py-2 text-center cvr-num-blue">{{ tc.cash_cover_rate_formatted }}</td>
                                                    <td class="px-3 py-2 text-center cvr-num-blue">{{ tc.commission_rate_formatted }}</td>
                                                    <td class="px-3 py-2 text-center capitalize cvr-text-secondary">{{ tc.commission_interval }}</td>
                                                    <td class="px-3 py-2 text-center cvr-num">{{ tc.min_commission_fees_formatted }}</td>
                                                    <td class="px-3 py-2 text-center cvr-num">{{ tc.issuance_fees_formatted }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="archivedRows.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No facility has been renewed yet.') }}
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
                        {{ $t('The facility will revert to its previous chapter\'s terms. This is blocked if any LG has already been issued on or after the renewal\'s effective date.') }}
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

            <!-- Renew Facility modal -->
            <div v-if="renewTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                    <h2 class="text-lg font-medium cvr-text-primary mb-1">Renew Facility — {{ renewTarget.name }}</h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        {{ $t('Nothing dated before the effective date is ever recalculated — past LGs keep whatever rate was in force when they were issued. Provide the full new Term & Conditions matrix below; it applies to any LG issued from the effective date onward.') }}
                    </p>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('Renewal Effective Date') }} *</label>
                            <input type="date" v-model="renewForm.effective_date" class="cvr-input w-full" />
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('Current contract end date:') }} <strong>{{ currentContractEndDate }}</strong> {{ $t('— the renewal date must be after this.') }}</p>
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
                        <div class="col-span-2">
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('Notes') }}</label>
                            <textarea v-model="renewForm.notes" class="cvr-input w-full" rows="2" :placeholder="$t('e.g. Bank renewal letter reference')"></textarea>
                        </div>
                    </div>

                    <h3 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-2">{{ $t('New Term & Conditions — by LG Type') }} *</h3>
                    <div class="overflow-x-auto mb-2">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-start">{{ $t('LG Type') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Cash Cover Rate (%)') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Commission Rate (%)') }}</th>
                                    <th class="px-3 py-2 text-start min-w-[11rem]">{{ $t('Commission Interval') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Min Commission Fees') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Issuance Fees') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in renewTermAndConditions" :key="row.lg_type" class="cvr-table-row">
                                    <td class="px-3 py-2 whitespace-nowrap cvr-text-primary font-medium">{{ lgTypes[row.lg_type] }}</td>
                                    <td class="px-3 py-2"><input v-model="row.cash_cover_rate" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-24" /></td>
                                    <td class="px-3 py-2"><input v-model="row.commission_rate" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-24" /></td>
                                    <td class="px-3 py-2">
                                        <select v-model="row.commission_interval" class="cvr-input px-2 py-1.5 rounded w-full min-w-[11rem]">
                                            <option v-for="(label, code) in commissionIntervals" :key="code" :value="code">{{ label }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2"><input v-model="row.min_commission_fees" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-28" /></td>
                                    <td class="px-3 py-2"><input v-model="row.issuance_fees" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-28" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="renewErrors.termAndConditions" class="text-xs text-red-600 mb-2">{{ renewErrors.termAndConditions }}</p>

                    <div class="flex justify-end gap-2">
                        <button @click="cancelRenew" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitRenew" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm Renewal') }}</button>
                    </div>
                </div>
            </div>

            <!-- LGs Terms And Conditions — read-only reference view,
                 matching the original's "Click Here" popup exactly -->
            <div v-if="termsTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-6xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium cvr-text-primary">{{ $t('LGs Terms And Conditions') }}</h2>
                        <button @click="termsTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ $t('LG Type') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Cash Cover Rate') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Commission Rate') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Commission Interval') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Min Commission Fees') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Issuance Fees') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(tc, i) in termsTarget.term_and_conditions" :key="i" class="cvr-table-row">
                                <td class="px-3 py-2 cvr-text-primary">{{ tc.lg_type_formatted }}</td>
                                <td class="px-3 py-2 text-center cvr-num-blue">{{ tc.cash_cover_rate_formatted }}</td>
                                <td class="px-3 py-2 text-center cvr-num-blue">{{ tc.commission_rate_formatted }}</td>
                                <td class="px-3 py-2 text-center capitalize cvr-text-secondary">{{ tc.commission_interval }}</td>
                                <td class="px-3 py-2 text-center cvr-num">{{ tc.min_commission_fees_formatted }}</td>
                                <td class="px-3 py-2 text-center cvr-num">{{ tc.issuance_fees_formatted }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>