<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dropdown from '@/Components/Dropdown.vue';

const props = defineProps({
    activeTab: String,
    company: Object,
    permissions: Object,
    banks: Array,
    leasingCompanies: Array,
    factoringCompanies: Array,
    mortgageCompanies: Array,
    createUrls: Object,
    leasingCompanyStoreUrl: String,
    factoringCompanyStoreUrl: String,
    tabUrls: Object,
    navUrls: Object,
});

/* ── Tabs ─────────────────────────────────────────────────────── */
const tabs = [
    { key: 'bank', label: 'Banks Table' },
    { key: 'leasing_companies', label: 'Leasing Companies' },
    { key: 'factoring_companies', label: 'Factoring Companies' },
    // { key: 'mortgage_companies', label: 'Mortgage Companies' }, / The Application still does not have Mortgage Companies /
];

function goToTab(tabKey) {
    router.get(props.tabUrls[tabKey]);
}

const currentRows = computed(() => {
    switch (props.activeTab) {
        case 'leasing_companies': return props.leasingCompanies;
        case 'factoring_companies': return props.factoringCompanies;
        case 'mortgage_companies': return props.mortgageCompanies;
        default: return props.banks;
    }
});

/* ── Search (client-side, simple name/branch match) ──────────── */
const search = ref('');
const filteredRows = computed(() => {
    if (!search.value) return currentRows.value;
    const q = search.value.toLowerCase();
    return currentRows.value.filter(row => {
        const haystack = [row.bank_name, row.name, row.branch_name, row.company_account_number]
            .filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(q);
    });
});

/* ── Leasing / Factoring Company inline create/edit modal ─────────
   Per the project owner's decision: both are just a "name" field, so
   this is one lightweight modal shared by both tabs, right on this
   page, instead of separate Create/Edit pages. Submits to
   LeasingCompanyController's / FactoringCompanyController's unchanged
   store()/update() endpoints, which already redirect back here. ──── */
const companyModal = ref(null); // null closed, { kind: 'leasing'|'factoring', mode: 'create'|'edit', name, url }
const companyModalLabel = computed(() => companyModal.value?.kind === 'factoring' ? 'Factoring Company' : 'Leasing Company');
function openCreateCompanyModal(kind) {
    const storeUrl = kind === 'factoring' ? props.factoringCompanyStoreUrl : props.leasingCompanyStoreUrl;
    companyModal.value = { kind, mode: 'create', name: '', url: storeUrl };
}
function openEditCompanyModal(kind, row) {
    companyModal.value = { kind, mode: 'edit', name: row.name, url: row.update_url };
}
const companyModalSubmitting = ref(false);
function submitCompanyModal() {
    companyModalSubmitting.value = true;
    const payload = { name: companyModal.value.name };
    const onFinish = () => { companyModalSubmitting.value = false; companyModal.value = null; };
    if (companyModal.value.mode === 'edit') {
        router.put(companyModal.value.url, payload, { onFinish });
    } else {
        router.post(companyModal.value.url, payload, { onFinish });
    }
}

/* ── Delete confirmation ──────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, {
        onFinish: () => { deleteTarget.value = null; },
    });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Financial Institutions') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">{{ $t('Banks, leasing, factoring & mortgage relationships') }}</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">🏦</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Banks') }}</p>
                        <p class="cvr-kpi-value">{{ banks.length }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">📋</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Leasing Companies') }}</p>
                        <p class="cvr-kpi-value">{{ leasingCompanies.length }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">🤝</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Factoring Companies') }}</p>
                        <p class="cvr-kpi-value">{{ factoringCompanies.length }}</p>
                    </div>
                </div>
            </div>

            <!-- Tabs + New button -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="flex items-center gap-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="goToTab(tab.key)"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': activeTab === tab.key }"
                    >
                        {{ $t(tab.label) }}
                    </button>
                </div>

                <button
                    v-if="permissions.create && activeTab === 'leasing_companies'"
                    @click="openCreateCompanyModal('leasing')"
                    class="cvr-btn-copper inline-flex items-center gap-1 px-3 py-1.5 rounded text-sm"
                >
                    {{ $t('+ New Leasing Company') }}
                </button>
                <button
                    v-else-if="permissions.create && activeTab === 'factoring_companies'"
                    @click="openCreateCompanyModal('factoring')"
                    class="cvr-btn-copper inline-flex items-center gap-1 px-3 py-1.5 rounded text-sm"
                >
                    {{ $t('+ New Factoring Company') }}
                </button>
                <Link
                    v-else-if="permissions.create && createUrls[activeTab]"
                    :href="createUrls[activeTab]"
                    class="cvr-btn-copper inline-flex items-center gap-1 px-3 py-1.5 rounded text-sm"
                >
                    {{ $t('+ New Bank') }}
                </Link>
            </div>

            <!-- Search -->
            <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 mb-4 w-72">
                <span class="cvr-text-muted text-sm">🔍</span>
                <input
                    v-model="search"
                    type="text"
                    :placeholder="$t('Search name or branch...')"
                    class="bg-transparent outline-none text-sm w-full cvr-text-primary"
                />
            </div>

            <!-- ═══ BANKS TABLE ═══ -->
            <div v-if="activeTab === 'bank'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3 text-center">{{ $t('Bank') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Branch Name') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Company Account Number') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Control') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-primary">{{ i + 1 }}</td>
                            <td class="px-4 py-3 cvr-text-primary">{{ row.bank_name }}</td>
                            <td class="px-4 py-3 cvr-text-secondary whitespace-nowrap">{{ row.branch_name }}</td>
                            <td class="px-4 py-3 cvr-num">{{ row.company_account_number }}</td>

                            <!-- Control: Add Debit / Add Credit dropdowns -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <!-- Debit accounts -->
                                    <Dropdown>
                                        <template #trigger="{ toggle }">
                                            <button @click="toggle" class="cvr-tag inline-flex items-center gap-1 px-2 py-1 text-xs whitespace-nowrap">
                                                {{ $t('Add Debit Accounts ▾') }}
                                            </button>
                                        </template>
                                        <template #content>
                                            <Link v-if="permissions.create" :href="row.add_current_account_url" class="block px-3 py-2 text-xs cvr-dropdown-item whitespace-nowrap">{{ $t('Add Current Account') }}</Link>
                                            <Link v-if="permissions.view_time_of_deposit" :href="row.view_time_of_deposit_url" class="block px-3 py-2 text-xs cvr-dropdown-item whitespace-nowrap">{{ $t('Time Deposit "TDs"') }}</Link>
                                            <Link v-if="permissions.view_certificate_of_deposit" :href="row.view_certificates_of_deposit_url" class="block px-3 py-2 text-xs cvr-dropdown-item whitespace-nowrap">{{ $t('Certificate Of Deposit "CDs"') }}</Link>
                                        </template>
                                    </Dropdown>

                                    <!-- Credit facilities -->
                                    <Dropdown>
                                        <template #trigger="{ toggle }">
                                            <button @click="toggle" class="cvr-tag-copper inline-flex items-center gap-1 px-2 py-1 text-xs rounded whitespace-nowrap">
                                                {{ $t('Add Credit Facilities ▾') }}
                                            </button>
                                        </template>
                                        <template #content>
                                            <Link v-if="permissions.view_fully_secured_overdraft" :href="row.view_fully_secured_overdraft_url" class="block px-3 py-2 text-xs cvr-dropdown-item whitespace-nowrap">{{ $t('Fully Secured Overdraft') }}</Link>
                                            <Link v-if="permissions.view_clean_overdraft" :href="row.view_clean_overdraft_url" class="block px-3 py-2 text-xs cvr-dropdown-item whitespace-nowrap">{{ $t('Clean Overdraft') }}</Link>
                                            <Link v-if="permissions.view_overdraft_against_commercial_paper" :href="row.view_overdraft_against_commercial_paper_url" class="block px-3 py-2 text-xs cvr-dropdown-item whitespace-nowrap">{{ $t('Overdraft Against Commercial Papers') }}</Link>
                                            <Link v-if="permissions.view_overdraft_against_assignment_of_contract" :href="row.view_overdraft_against_assignment_of_contract_url" class="block px-3 py-2 text-xs cvr-dropdown-item whitespace-nowrap">{{ $t('Overdraft Against Contracts Assignment') }}</Link>
                                            <Link v-if="permissions.view_letter_of_guarantee_issuance" :href="row.view_letter_of_guarantee_facility_url" class="block px-3 py-2 text-xs cvr-dropdown-item whitespace-nowrap">{{ $t('Letter Of Guarantee') }}</Link>
                                            <Link v-if="permissions.view_letter_of_credit_facility" :href="row.view_letter_of_credit_facility_url" class="block px-3 py-2 text-xs cvr-dropdown-item whitespace-nowrap">{{ $t('Letter Of Credit') }}</Link>
                                            <Link v-if="permissions.view_medium_term_loan" :href="row.view_medium_term_loans_url" class="block px-3 py-2 text-xs cvr-dropdown-item whitespace-nowrap">{{ $t('Medium Term Loans') }}</Link>
                                        </template>
                                    </Dropdown>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link :href="row.view_accounts_url" class="cvr-action-btn" :title="$t('Show All Accounts')">👁</Link>
                                    <Link v-if="permissions.update" :href="row.edit_url" class="cvr-action-btn" :title="$t('Edit')">✎</Link>
                                    <button v-if="permissions.delete" @click="confirmDelete(row)" class="cvr-action-btn cvr-action-btn-danger" :title="$t('Delete')">🗑</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No banks found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ═══ LEASING / FACTORING (same shape: name + actions) ═══ -->
            <div v-else-if="activeTab === 'leasing_companies' || activeTab === 'factoring_companies'" class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-secondary">{{ i + 1 }}</td>
                            <td class="px-4 py-3 cvr-text-primary">{{ row.name }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link :href="row.contracts_url" class="cvr-action-btn" :title="$t('Contracts')">📄</Link>
                                    <button v-if="permissions.update && (activeTab === 'leasing_companies' || activeTab === 'factoring_companies')" @click="openEditCompanyModal(activeTab === 'factoring_companies' ? 'factoring' : 'leasing', row)" class="cvr-action-btn" :title="$t('Edit')">✎</button>
                                    <Link v-else-if="permissions.update" :href="row.edit_url" class="cvr-action-btn" :title="$t('Edit')">✎</Link>
                                    <button v-if="permissions.delete" @click="confirmDelete(row)" class="cvr-action-btn cvr-action-btn-danger" :title="$t('Delete')">🗑</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="3" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No companies found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ═══ MORTGAGE (name + branch + actions) ═══ -->
            <div v-else class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Branch Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Control') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-secondary">{{ i + 1 }}</td>
                            <td class="px-4 py-3 cvr-text-primary">{{ row.name }}</td>
                            <td class="px-4 py-3 cvr-text-secondary">{{ row.branch_name }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link v-if="permissions.update" :href="row.edit_url" class="cvr-action-btn" :title="$t('Edit')">✎</Link>
                                    <button v-if="permissions.delete" @click="confirmDelete(row)" class="cvr-action-btn cvr-action-btn-danger" :title="$t('Delete')">🗑</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No mortgage companies found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Leasing / Factoring Company create/edit modal -->
            <div v-if="companyModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ companyModal.mode === 'edit' ? $t('Edit') : $t('Add') }} {{ companyModalLabel }}
                    </h2>
                    <form @submit.prevent="submitCompanyModal">
                        <label class="cvr-form-label">{{ $t('Name') }} *</label>
                        <input v-model="companyModal.name" type="text" class="cvr-input w-full px-3 py-2 rounded mb-4" autofocus />
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="companyModal = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                            <button type="submit" :disabled="companyModalSubmitting" class="cvr-btn-primary px-3 py-1.5 rounded">
                                {{ companyModalSubmitting ? $t('Saving...') : $t('Save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Do you want to delete this item?') }}</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
