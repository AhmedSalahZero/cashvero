<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    financialInstitution: Object,
    canCreate: Boolean,
    createUrl: String,
    rows: Array,
    backUrl: String,
    navUrls: Object,
    lcTypes: Object,
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
   history expandable) — same pattern as the ODA/LG facilities. ──── */
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

/* ── Facility Renewal — Phase 6 (LC Facility). A hybrid form: the
   flat Financing Terms & Conditions fields (like Fully Secured
   Overdraft's renewal) PLUS the required 3-row LC-type rate matrix
   (like LG Facility's renewal). ─────────────────────────────────── */
const lcTypes = props.lcTypes;
function blankRenewTermAndConditions() {
    return Object.keys(lcTypes).map(lcType => ({
        lc_type: lcType,
        cash_cover_rate: 0,
        commission_rate: 0,
        min_commission_fees: 0,
        issuance_fees: 0,
    }));
}
const renewTarget = ref(null);
const renewForm = ref({
    effective_date: '',
    contract_end_date: '',
    limit: '',
    cd_or_td_lending_percentage: '',
    borrowing_rate: '',
    bank_margin_rate: '',
    min_interest_rate: '',
    highest_debt_balance_rate: '',
    admin_fees_rate: '',
    notes: '',
});
const renewTermAndConditions = ref(blankRenewTermAndConditions());
const renewErrors = ref({});
function openRenew(row) {
    renewTarget.value = row;
    renewErrors.value = {};
    renewForm.value = {
        effective_date: '',
        contract_end_date: '',
        limit: '',
        cd_or_td_lending_percentage: '',
        borrowing_rate: '',
        bank_margin_rate: '',
        min_interest_rate: '',
        highest_debt_balance_rate: '',
        admin_fees_rate: '',
        notes: '',
    };
    renewTermAndConditions.value = blankRenewTermAndConditions();
}
function cancelRenew() { renewTarget.value = null; }
const isFullySecuredRenewTarget = computed(() => renewTarget.value?.type === 'fully-secured');
// New Interest Rate preview = Borrowing Rate + Bank Margin Rate,
// falling back to the facility's current rates when a field is left
// blank (matching the server's "blank = carry forward" rule) — for
// display only, the authoritative value is always computed server-side.
const renewInterestRatePreview = computed(() => {
    const borrowing = renewForm.value.borrowing_rate !== '' ? Number(renewForm.value.borrowing_rate) : Number(renewTarget.value?.borrowing_rate_formatted || 0);
    const margin = renewForm.value.bank_margin_rate !== '' ? Number(renewForm.value.bank_margin_rate) : Number(renewTarget.value?.bank_margin_rate_formatted || 0);
    return (borrowing + margin).toFixed(2);
});
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
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('LC Facility') }}</h1>
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
                >{{ $t('Archived Facilities (') }}{{ archivedRows.length }})</button>
            </div>

            <div v-if="activeMainTab === 'facilities'">
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Type') }}</th>
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
                            <td class="px-4 py-3">
                                <span :class="['cvr-badge', row.type === 'fully-secured' ? 'cvr-badge-deposit' : 'cvr-badge-current']">
                                    {{ row.type_formatted }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.contract_start_date_formatted }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.contract_end_date_formatted }}</td>
                            <td class="px-4 py-3 uppercase cvr-text-secondary">{{ row.currency }}</td>
                            <td class="px-4 py-3 cvr-num">{{ row.limit_formatted }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <RecordLogButton subject="LetterOfCreditFacility" :id="row.id" :company-id="company.id" />
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
                            <td colspan="8" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No LC Facility records found.') }}
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
                                                — {{ chapter.effective_date_formatted }} {{ $t('to') }} {{ chapter.contract_end_date_formatted }}
                                                {{ $t('— Limit:') }} {{ chapter.limit_formatted }}
                                                {{ $t('— Borrowing') }} {{ chapter.borrowing_rate }}{{ $t('% + Margin') }} {{ chapter.bank_margin_rate }}{{ $t('% = Interest') }} {{ chapter.interest_rate }}%
                                            </p>
                                            <button
                                                v-if="cIndex === row.terms_history.length - 1 && !chapter.is_original"
                                                @click.stop="confirmDeleteRenewal(row)"
                                                class="cvr-btn-danger px-2 py-1 rounded border text-xs"
                                                :title="$t('Undo this renewal — reverts the facility to its previous chapter\'s terms')"
                                            >{{ $t('Delete This Renewal') }}</button>
                                        </div>
                                        <table class="w-full text-xs border rounded overflow-hidden">
                                            <thead class="cvr-table-head">
                                                <tr>
                                                    <th class="px-3 py-2 text-start">{{ $t('LC Type') }}</th>
                                                    <th class="px-3 py-2 text-start">{{ $t('Cash Cover Rate') }}</th>
                                                    <th class="px-3 py-2 text-start">{{ $t('Commission Rate') }}</th>
                                                    <th class="px-3 py-2 text-start">{{ $t('Min Commission Fees') }}</th>
                                                    <th class="px-3 py-2 text-start">{{ $t('Issuance Fees') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(tc, i) in chapter.term_and_conditions" :key="i" class="cvr-table-row">
                                                    <td class="px-3 py-2 cvr-text-primary">{{ tc.lc_type_formatted }}</td>
                                                    <td class="px-3 py-2 text-center cvr-num-blue">{{ tc.cash_cover_rate_formatted }}</td>
                                                    <td class="px-3 py-2 text-center cvr-num-blue">{{ tc.commission_rate_formatted }}</td>
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
                        {{ $t('The facility will revert to its previous chapter\'s terms. This is blocked if any LC has already been issued on or after the renewal\'s effective date.') }}
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
                    <h2 class="text-lg font-medium cvr-text-primary mb-1">{{ $t('Renew Facility —') }} {{ renewTarget.name }}</h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        {{ $t('Leave a field blank to keep it unchanged from the current terms. Nothing dated before the effective date is ever recalculated — past LCs keep whatever rate was in force when they were issued. Provide the full new Term & Conditions matrix below; it applies to any LC issued from the effective date onward.') }}
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
                    </div>

                    <h3 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-2">{{ $t('Financing Terms & Conditions') }}</h3>
                    <div class="cvr-form-grid-2 mb-4">
                        <div v-if="isFullySecuredRenewTarget">
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New CD/TD Lending Percentage (%)') }}</label>
                            <input type="number" step="any" v-model="renewForm.cd_or_td_lending_percentage" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                            <p class="text-xs cvr-text-muted mt-1">{{ $t('This facility is Fully Secured — the Limit is recalculated automatically from the linked CD/TD amount × this percentage.') }}</p>
                            <p v-if="renewErrors.cd_or_td_lending_percentage" class="text-xs text-red-600 mt-1">{{ renewErrors.cd_or_td_lending_percentage }}</p>
                        </div>
                        <div v-else>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Limit') }}</label>
                            <input type="number" step="any" v-model="renewForm.limit" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                            <p v-if="renewErrors.limit" class="text-xs text-red-600 mt-1">{{ renewErrors.limit }}</p>
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Borrowing Rate (%)') }}</label>
                            <input type="number" step="any" v-model="renewForm.borrowing_rate" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                            <p v-if="renewErrors.borrowing_rate" class="text-xs text-red-600 mt-1">{{ renewErrors.borrowing_rate }}</p>
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Bank Margin Rate (%)') }}</label>
                            <input type="number" step="any" v-model="renewForm.bank_margin_rate" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                            <p v-if="renewErrors.bank_margin_rate" class="text-xs text-red-600 mt-1">{{ renewErrors.bank_margin_rate }}</p>
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('Interest Rate (%) — Borrowing + Margin') }}</label>
                            <input type="text" :value="renewInterestRatePreview" disabled class="cvr-input w-full opacity-70" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Min Interest Rate (%)') }}</label>
                            <input type="number" step="any" v-model="renewForm.min_interest_rate" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Highest-Debt-Balance Rate (%)') }}</label>
                            <input type="number" step="any" v-model="renewForm.highest_debt_balance_rate" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                        </div>
                        <div>
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('New Admin Fees Rate (%)') }}</label>
                            <input type="number" step="any" v-model="renewForm.admin_fees_rate" class="cvr-input w-full" :placeholder="$t('Unchanged')" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs cvr-text-secondary mb-1">{{ $t('Notes') }}</label>
                            <textarea v-model="renewForm.notes" class="cvr-input w-full" rows="2" :placeholder="$t('e.g. Bank renewal letter reference')"></textarea>
                        </div>
                    </div>

                    <h3 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-2">{{ $t('New Term & Conditions — by LC Type') }} *</h3>
                    <div class="overflow-x-auto mb-2">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-start">{{ $t('LC Type') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Cash Cover Rate (%)') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Commission Rate (%)') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Min Commission Fees') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Issuance Fees') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in renewTermAndConditions" :key="row.lc_type" class="cvr-table-row">
                                    <td class="px-3 py-2 whitespace-nowrap cvr-text-primary font-medium">{{ lcTypes[row.lc_type] }}</td>
                                    <td class="px-3 py-2"><input v-model="row.cash_cover_rate" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-24" /></td>
                                    <td class="px-3 py-2"><input v-model="row.commission_rate" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-24" /></td>
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

            <!-- LCs Terms And Conditions — read-only reference view,
                 matching the original's "Click Here" popup exactly -->
            <div v-if="termsTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-6xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium cvr-text-primary">{{ $t('LCs Terms And Conditions') }}</h2>
                        <button @click="termsTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ $t('LC Type') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Cash Cover Rate') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Commission Rate') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Min Commission Fees') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Issuance Fees') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(tc, i) in termsTarget.term_and_conditions" :key="i" class="cvr-table-row">
                                <td class="px-3 py-2 cvr-text-primary">{{ tc.lc_type_formatted }}</td>
                                <td class="px-3 py-2 text-center cvr-num-blue">{{ tc.cash_cover_rate_formatted }}</td>
                                <td class="px-3 py-2 text-center cvr-num-blue">{{ tc.commission_rate_formatted }}</td>
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
