<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import Pagination from '@/Components/Pagination.vue';

/*
 * Contracts/Index.vue
 * ------------------------------------------------------------------
 * ONE shared page for both Customer Contracts and Supplier Contracts
 * — same as the controller and old Blade view before it, distinguished
 * only by the `type` prop ('Customer' | 'Supplier'). This mirrors the
 * project's Section 2 pattern (one FullySecuredOverdraft-style page
 * serving several sibling product types) rather than building two
 * near-identical pages.
 *
 * Differences that DO change based on type (kept exactly as the old
 * Blade behaved):
 *   - Sub-item rows show "Sales Order Number" (Customer) or
 *     "Purchase Order Number" (Supplier).
 *   - The "Allocate" action on a sub-item row, and its modal, only
 *     exist on the Supplier side (PoAllocation is a Purchase-Order-only
 *     concept in this codebase).
 *
 * The "Invoices" button (🧾) now appears on BOTH Customer and
 * Supplier contracts — ⚠️ this is a real fix, not just a migration
 * choice. In the original Blade, the button was gated behind
 * `$hasProjectNameColumn`, which was hardcoded to `false` for every
 * Supplier contract (ContractsController::index()), AND the
 * underlying data came from Contract::customerInvoices() only —
 * there was no supplierInvoices() relation at all. So Supplier
 * Contracts never actually had a working Invoices button, even in
 * the original app. Both gaps are now fixed at the root (new
 * Contract::supplierInvoices() relation, type-aware selection in the
 * controller), so this button now shows real data for both sides.
 *
 * Each invoice row also gets an (ℹ️) icon whenever its currency
 * differs from the company's main functional currency — same
 * condition and same two figures (Amount In Main Currency, Exchange
 * Rate) as the original Blade's admin/reports/invoice-report-td.blade.php.
 *
 * Customer contracts also get a "Supplier Contracts" button, listing
 * the supplier contracts hanging off this one (Contract.parent_id).
 * Those rows are created by the Odoo sync — one supplier contract per
 * purchase order booked against the project — so the modal is the only
 * place the customer→supplier link is visible in the UI.
 *
 * Each tab paginates on the server independently (its own `<status>_page`
 * query parameter), so paging one tab leaves the other two where they
 * were. Search stays client-side, on the current page's rows.
 */

const props = defineProps({
    company: Object,
    type: String,
    activeTab: String,
    pageTitle: String,
    canCreate: Boolean,
    canUpdate: Boolean,
    // canDelete/canApprove are new: contract delete permissions existed
    // but were never sent to this page, so Delete was ungated in the UI
    // (2026-08 audit, F-04).
    canDelete: Boolean,
    canApprove: Boolean,
    hasProjectNameColumn: Boolean,
    // When the company is wired to Odoo, PO allocation is driven from
    // Odoo itself, so the Allocate action is removed from Supplier
    // contracts rather than left there to fight the sync.
    hasOdooCredentials: Boolean,
    contractStatues: Array,
    contracts: Object, // { running: [...], running_and_against: [...], finished: [...] }
    paginators: Object, // { running: {...}, running_and_against: {...}, finished: {...} } — Laravel paginator arrays
    createUrl: String,
    tabUrls: Object,
    clientsWithContracts: Array, // Supplier side only — customers to allocate a PO across
    getContractsForCustomerOrSupplierUrl: String,
    storePoAllocationUrl: String,
});

const tabLabels = {
    running: 'Running',
    running_and_against: 'Running And Against',
    finished: 'Finished',
};

const activeTab = ref(props.activeTab || 'running');
function switchTab(key) {
    activeTab.value = key;
    // The first page of every tab already arrived on first load (same
    // shape the old Blade rendered all three tab-panes at once) —
    // switching is instant, no reload needed. Only paging within a tab
    // goes back to the server.
}

const currentRows = computed(() => props.contracts[activeTab.value] || []);
const currentPaginator = computed(() => props.paginators?.[activeTab.value] || null);

/* ── KPIs ─────────────────────────────────────────────────────── */
// The paginator's `total` is the real contract count; currentRows is
// only the rows on this page.
const totalCount = computed(() => currentPaginator.value?.total ?? currentRows.value.length);
const totalOrders = computed(() =>
    currentRows.value.reduce((sum, c) => sum + (c.sub_items?.length || 0), 0)
);

/* ── Search (client-side, on top of the already-loaded tab data) ─ */
const search = ref('');
const filteredRows = computed(() => {
    if (!search.value) return currentRows.value;
    const q = search.value.toLowerCase();
    return currentRows.value.filter(c =>
        (c.client_name || '').toLowerCase().includes(q) ||
        (c.name || '').toLowerCase().includes(q) ||
        (c.contract_code || '').toLowerCase().includes(q)
    );
});

/* ── Expand / collapse sub-item rows ─────────────────────────────
   Matches the old page's toggleRow() — click the client name to show
   the Sales/Purchase Order breakdown underneath. */
const expandedIds = ref(new Set());
function toggleExpand(id) {
    const next = new Set(expandedIds.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expandedIds.value = next;
}

/* ── Delete confirmation ──────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

/* ── Mark As Finished / Back to Running / Running And Against ────
   Finished contracts that were never pledged as overdraft collateral
   get "Back to Running". Pledged ones keep the existing restore to
   Running And Against, which is the hook that undoes the reversing
   limit row written when they were finished. */
const statusChangeTarget = ref(null);
function confirmMarkFinished(row) { statusChangeTarget.value = { row, url: row.mark_finished_url, label: 'Finished' }; }
function confirmMarkRunning(row) { statusChangeTarget.value = { row, url: row.mark_running_url, label: 'Running' }; }
function confirmMarkRunningAndAgainst(row) { statusChangeTarget.value = { row, url: row.mark_running_and_against_url, label: 'Running And Against' }; }
function submitStatusChange() {
    router.put(statusChangeTarget.value.url, {}, { onFinish: () => { statusChangeTarget.value = null; } });
}

/* ── Invoices modal (both Customer and Supplier — see class docblock) ── */
const invoicesTarget = ref(null);
function openInvoices(row) { invoicesTarget.value = row; }

/* ── Related supplier contracts modal (Customer side only) ────────
   The supplier contracts created under this customer contract by the
   Odoo purchase-order sync. Totals come pre-summed per currency from
   the controller — supplier contracts on one project can be in
   different currencies, so a single total would be meaningless. */
const relatedContractsTarget = ref(null);
function openRelatedContracts(row) { relatedContractsTarget.value = row; }

/* ── FX breakdown popover (the ℹ️ icon on foreign-currency invoice
   rows) — same two figures the original Blade's invoice-report-td
   modal showed: Amount In Main Currency + Exchange Rate. */
const fxBreakdownTarget = ref(null); // the invoice row itself
function openFxBreakdown(invoice) { fxBreakdownTarget.value = invoice; }

/* ── PO Allocation modal (Supplier side only) ────────────────────
   Lets the business split a single Purchase Order's amount across
   one or more customers, each with their own contract + percentage.
   Percentage is against the PO's own amount (amount_raw) — not the
   contract's amount — matching the old jQuery's
   `.total-amount` / allocation-percentage-class handler exactly. */
const allocationTarget = ref(null);
const allocationRows = ref([]);
const contractsByPartner = ref({}); // partnerId -> [{id, name, code, amount, currency}]

async function openAllocationModal(subItem) {
    allocationTarget.value = subItem;
    allocationRows.value = subItem.allocations.length
        ? subItem.allocations.map(a => ({ ...a }))
        : [{ partner_id: '', contract_id: '', contract_code: '', contract_amount: null, contract_currency: '', percentage: 0, amount_formatted: '0.00' }];
    // Pre-load contract options for any partner already selected in a
    // saved allocation row, so the Contract dropdown isn't empty on open —
    // then back-fill Code/Amount/Currency for any row that already has a
    // contract picked. Those fields are normally only derived live by
    // onContractChange() when the user actively changes the dropdown,
    // which never fires for rows loaded straight from a saved allocation —
    // that's why Code/Amount showed blank when reopening an existing one.
    await Promise.all(
        allocationRows.value
            .filter(row => row.partner_id)
            .map(row => loadContractsForPartner(row.partner_id))
    );
    allocationRows.value.forEach(row => {
        if (row.contract_id) onContractChange(row);
    });
}
function closeAllocationModal() {
    allocationTarget.value = null;
    allocationRows.value = [];
}
function addAllocationRow() {
    allocationRows.value.push({ partner_id: '', contract_id: '', contract_code: '', contract_amount: null, contract_currency: '', percentage: 0, amount_formatted: '0.00' });
}
function removeAllocationRow(index) {
    allocationRows.value.splice(index, 1);
}

async function loadContractsForPartner(partnerId) {
    if (!partnerId || contractsByPartner.value[partnerId]) return;
    const { data } = await window.axios.get(props.getContractsForCustomerOrSupplierUrl, {
        params: { partnerId, inEditMode: 0 },
    });
    contractsByPartner.value = { ...contractsByPartner.value, [partnerId]: data.contracts || [] };
}

function onPartnerChange(row) {
    row.contract_id = '';
    row.contract_code = '';
    row.contract_amount = null;
    row.contract_currency = '';
    loadContractsForPartner(row.partner_id);
}

function onContractChange(row) {
    const options = contractsByPartner.value[row.partner_id] || [];
    const selected = options.find(c => String(c.id) === String(row.contract_id));
    row.contract_code = selected ? selected.code : '';
    row.contract_amount = selected ? Number(selected.amount) : null;
    row.contract_currency = selected ? String(selected.currency || '').toUpperCase() : '';
}

function recalcAmount(row) {
    const pct = Number(row.percentage) || 0;
    const baseAmount = Number(allocationTarget.value?.amount_raw) || 0;
    const amount = (pct / 100) * baseAmount;
    row.amount_formatted = amount.toFixed(2);
}

function submitAllocations() {
    router.post(props.storePoAllocationUrl, {
        po_id: allocationTarget.value.id,
        poAllocations: allocationRows.value.map(r => ({
            partner_id: r.partner_id,
            contract_id: r.contract_id,
            allocation_percentage: r.percentage,
            allocation_amount: r.amount_formatted,
        })),
    }, { onFinish: () => closeAllocationModal() });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ pageTitle }}</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ type }} Contracts</p>

            <!-- KPI cards -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">📄</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Contracts') }}</p>
                        <p class="cvr-kpi-value">{{ totalCount }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">🧾</div>
                    <div>
                        <p class="cvr-kpi-label">{{ type === $t('Supplier') ? $t('Purchase Orders') : $t('Sales Orders') }}</p>
                        <p class="cvr-kpi-value">{{ totalOrders }}</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="flex items-center gap-1">
                    <button
                        v-for="status in contractStatues"
                        :key="status"
                        @click="switchTab(status)"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': activeTab === status }"
                    >
                        {{ tabLabels[status] }}
                    </button>
                </div>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                    {{ $t('+ Create') }}
                </Link>
            </div>

            <!-- Search -->
            <div class="flex flex-wrap items-end gap-3 mb-4">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 ms-auto w-full sm:w-72 max-w-full">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" :placeholder="$t('Search partner, contract name, or code...')" class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto cvr-table-scroll">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ $t('Partner Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Contract Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Contract Code') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Start Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('End Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Amount') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in filteredRows" :key="row.id">
                            <tr class="cvr-table-row">
                                <td class="px-4 py-3 cvr-text-primary cursor-pointer" @click="toggleExpand(row.id)">
                                    <span v-if="row.sub_items.length" class="me-1">{{ expandedIds.has(row.id) ? '▾' : '▸' }}</span>
                                    <span class="font-medium">{{ row.client_name }}</span>
                                </td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.name }}</td>
                                <td class="px-4 py-3 cvr-text-secondary">{{ row.contract_code }}</td>
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.start_date }}</td>
                                <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.end_date }}</td>
                                <td class="px-4 py-3 cvr-num">{{ row.amount_formatted }} {{ row.currency }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <RecordLogButton subject="Contract" :id="row.id" :company-id="company.id" />
                                        <Link :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">
                                            Edit
                                        </Link>

                                        <button
                                            @click="openInvoices(row)"
                                            class="cvr-action-btn"
                                            title="Invoices"
                                        >🧾</button>

                                        <!-- Supplier contracts hanging off this customer contract -->
                                        <button
                                            v-if="type === 'Customer' && row.related_contracts.length"
                                            @click="openRelatedContracts(row)"
                                            class="cvr-tag"
                                            title="Supplier Contracts"
                                        >Supplier Contracts ({{ row.related_contracts.length }})</button>

                                        <button
                                            v-if="activeTab === 'running'"
                                            @click="confirmMarkFinished(row)"
                                            class="cvr-action-btn"
                                            title="Mark As Finished"
                                        >✔️</button>

                                        <button
                                            v-if="activeTab === 'running_and_against' && canApprove"
                                            @click="confirmMarkFinished(row)"
                                            class="cvr-action-btn"
                                            title="Mark As Finished"
                                        >👍</button>

                                        <button
                                            v-if="activeTab === 'finished' && canApprove && !row.is_assigned_as_collateral"
                                            @click="confirmMarkRunning(row)"
                                            class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs"
                                            title="Back to Running"
                                        >Back to Running</button>

                                        <!-- Pledged contracts must return to Running And Against,
                                             not plain Running — that path is what reverses the
                                             overdraft-limit row written when they were finished. -->
                                        <button
                                            v-if="activeTab === 'finished' && canApprove && row.is_assigned_as_collateral"
                                            @click="confirmMarkRunningAndAgainst(row)"
                                            class="cvr-action-btn"
                                            title="Mark As Running And Against"
                                        >👍</button>

                                        <!-- The whole Options menu disappears when its
                                             only entry is one the user may not perform. -->
                                        <Dropdown v-if="canDelete">
                                            <template #trigger="{ toggle }">
                                                <button @click="toggle" class="cvr-tag">Options ▾</button>
                                            </template>
                                            <template #content>
                                                <button @click="confirmDelete(row)" class="block w-full text-start px-3 py-2 text-xs cvr-dropdown-item">
                                                    Delete
                                                </button>
                                            </template>
                                        </Dropdown>
                                    </div>
                                </td>
                            </tr>

                            <!-- Sales/Purchase Order sub-rows -->
                            <template v-if="expandedIds.has(row.id)">
                                <tr v-for="subItem in row.sub_items" :key="'sub-' + subItem.id" class="cvr-table-row bg-black/5">
                                    <td class="px-4 py-2 ps-10 cvr-text-muted text-xs" colspan="2">{{ subItem.order_label }}</td>
                                    <td class="px-4 py-2 cvr-text-secondary text-xs">{{ subItem.order_number }}</td>
                                    <td class="px-4 py-2 cvr-text-muted text-xs">Amount</td>
                                    <td class="px-4 py-2 cvr-num text-xs" colspan="2">{{ subItem.amount_formatted }}</td>
                                    <td class="px-4 py-2">
                                        <!-- Hidden once the company is on Odoo — allocation comes from there -->
                                        <button
                                            v-if="type === 'Supplier' && !hasOdooCredentials"
                                            @click="openAllocationModal(subItem)"
                                            class="cvr-btn-secondary px-2 py-1 rounded border text-xs"
                                        >
                                            Allocate
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </template>

                        <tr v-if="filteredRows.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center cvr-text-muted">
                                No {{ type.toLowerCase() }} contracts found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Each tab pages independently — its links carry `active` so
                 the same tab stays open after the visit -->
            <Pagination :paginator="currentPaginator" label="contracts" />

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Do you want to delete this contract?') }}</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>

            <!-- Mark As Finished / Running And Against confirmation -->
            <div v-if="statusChangeTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        Do you want to mark this contract as {{ statusChangeTarget.label }}?
                    </h2>
                    <div class="flex justify-end gap-2">
                        <button @click="statusChangeTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitStatusChange" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Confirm') }}</button>
                    </div>
                </div>
            </div>

            <!-- Related supplier contracts modal (Customer side only) -->
            <div v-if="relatedContractsTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-6xl max-h-[85vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium cvr-text-primary">
                            Related Supplier Contracts — {{ relatedContractsTarget.name }}
                            ({{ relatedContractsTarget.amount_formatted }} {{ relatedContractsTarget.currency }})
                        </h2>
                        <button @click="relatedContractsTarget = null" class="cvr-btn-secondary px-2 py-1 rounded border text-xs">✕</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-start">{{ $t('Supplier Name') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Contract Name') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Contract Code') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Purchase Order Number') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Start Date') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('End Date') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Currency') }}</th>
                                    <th class="px-3 py-2 text-right">{{ $t('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="related in relatedContractsTarget.related_contracts" :key="related.id" class="cvr-table-row">
                                    <td class="px-3 py-2 cvr-text-primary">{{ related.client_name }}</td>
                                    <td class="px-3 py-2 cvr-text-secondary">{{ related.name }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap cvr-text-secondary">{{ related.contract_code }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap cvr-text-secondary">{{ related.purchase_order_numbers }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap cvr-text-secondary">{{ related.start_date }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap cvr-text-secondary">{{ related.end_date }}</td>
                                    <td class="px-3 py-2 uppercase cvr-text-secondary">{{ related.currency }}</td>
                                    <td class="px-3 py-2 text-right cvr-num whitespace-nowrap">{{ related.amount_formatted }}</td>
                                </tr>
                            </tbody>
                            <!-- One total row per currency — supplier contracts on
                                 one project can be in different currencies -->
                            <tfoot>
                                <tr
                                    v-for="total in relatedContractsTarget.related_contracts_totals"
                                    :key="total.currency"
                                    class="cvr-table-head font-semibold"
                                >
                                    <td class="px-3 py-2" colspan="6">{{ $t('Total') }}</td>
                                    <td class="px-3 py-2 uppercase">{{ total.currency }}</td>
                                    <td class="px-3 py-2 text-right cvr-num whitespace-nowrap">{{ total.total_formatted }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button @click="relatedContractsTarget = null" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>

            <!-- Invoices modal (Customer and Supplier) -->
            <div v-if="invoicesTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-7xl max-h-[85vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium cvr-text-primary">
                            Contract Invoices — {{ invoicesTarget.client_name }} / {{ invoicesTarget.name }}
                            ({{ invoicesTarget.amount_formatted }} {{ invoicesTarget.currency }})
                        </h2>
                        <button @click="invoicesTarget = null" class="cvr-btn-secondary px-2 py-1 rounded border text-xs">✕</button>
                    </div>
                    <table class="min-w-full text-xs">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ $t('Invoice Date') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Invoice Number') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Currency') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Amount') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Withhold') }}</th>
                                <th class="px-3 py-2 text-start">VAT</th>
                                <th class="px-3 py-2 text-start">{{ $t('Deductions') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Collected') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Due Date') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Net Balance') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Status') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Aging') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invoice in invoicesTarget.invoices" :key="invoice.id" class="cvr-table-row">
                                <td class="px-3 py-2 whitespace-nowrap">{{ invoice.invoice_date }}</td>
                                <td class="px-3 py-2">{{ invoice.invoice_number }}</td>
                                <td class="px-3 py-2 uppercase">{{ invoice.currency }}</td>
                                <td class="px-3 py-2 cvr-num">
                                    {{ invoice.amount_formatted }}
                                    <i v-if="invoice.is_foreign_currency"
                                        @click="openFxBreakdown(invoice)"
                                        class="ms-1 cursor-pointer" :title="$t('Exchange Rate Breakdown')">ℹ️</i>
                                </td>
                                <td class="px-3 py-2 cvr-num">{{ invoice.withhold_amount_formatted }}</td>
                                <td class="px-3 py-2 cvr-num">{{ invoice.vat_amount_formatted }}</td>
                                <td class="px-3 py-2 cvr-num">{{ invoice.total_deduction_formatted }}</td>
                                <td class="px-3 py-2 cvr-num-green">{{ invoice.total_collected_formatted }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ invoice.due_date_formatted }}</td>
                                <td class="px-3 py-2 cvr-num">{{ invoice.net_balance_formatted }}</td>
                                <td class="px-3 py-2">{{ invoice.status_formatted }}</td>
                                <td class="px-3 py-2">{{ invoice.aging }}</td>
                            </tr>
                            <tr v-if="!invoicesTarget.invoices.length">
                                <td colspan="12" class="px-3 py-6 text-center cvr-text-muted">{{ $t('No invoices under this contract.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex justify-end mt-4">
                        <button @click="invoicesTarget = null" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>

            <!-- FX breakdown modal (ℹ️ icon on a foreign-currency invoice row) -->
            <div v-if="fxBreakdownTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60]">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-1">Invoice #{{ fxBreakdownTarget.invoice_number }}</h2>
                    <p class="text-sm cvr-text-muted mb-4">Dated {{ fxBreakdownTarget.invoice_date }}</p>
                    <table class="min-w-full text-sm mb-4">
                        <tbody>
                            <tr class="cvr-table-row">
                                <td class="px-3 py-2 cvr-text-secondary">{{ $t('Amount In Main Currency') }}</td>
                                <td class="px-3 py-2 text-right cvr-num font-medium">{{ fxBreakdownTarget.amount_in_main_currency_formatted }}</td>
                            </tr>
                            <tr class="cvr-table-row">
                                <td class="px-3 py-2 cvr-text-secondary">{{ $t('Exchange Rate') }}</td>
                                <td class="px-3 py-2 text-right cvr-num font-medium">{{ fxBreakdownTarget.exchange_rate_formatted }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex justify-end">
                        <button @click="fxBreakdownTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>

            <!-- PO Allocation modal (Supplier side only) -->
            <div v-if="allocationTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-7xl max-h-[85vh] overflow-y-auto">
                    <h2 class="text-lg font-medium cvr-text-primary mb-1">
                        Allocate Purchase Order {{ allocationTarget.order_number }}
                    </h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        PO Amount: {{ allocationTarget.amount_formatted }} — split it across one or more customers below.
                    </p>

                    <table class="min-w-full text-xs mb-3">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-2 py-2 text-start">{{ $t('Customer') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Contract') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Code') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Contract Amount') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Allocate %') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Allocate Amount') }}</th>
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in allocationRows" :key="index" class="cvr-table-row">
                                <td class="px-2 py-2">
                                    <select v-model="row.partner_id" @change="onPartnerChange(row)" class="cvr-input px-2 py-1 rounded w-full">
                                        <option value="">{{ $t('Select customer...') }}</option>
                                        <option v-for="client in clientsWithContracts" :key="client.id" :value="client.id">{{ client.name }}</option>
                                    </select>
                                </td>
                                <td class="px-2 py-2">
                                    <select v-model="row.contract_id" @change="onContractChange(row)" class="cvr-input px-2 py-1 rounded w-full" :disabled="!row.partner_id">
                                        <option value="">{{ $t('Select contract...') }}</option>
                                        <option v-for="c in (contractsByPartner[row.partner_id] || [])" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </td>
                                <td class="px-2 py-2 cvr-text-muted">{{ row.contract_code }}</td>
                                <td class="px-2 py-2 cvr-num">
                                    <span v-if="row.contract_amount !== null">{{ row.contract_amount }} {{ row.contract_currency }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <input v-model="row.percentage" @input="recalcAmount(row)" type="number" step="0.01" class="cvr-input px-2 py-1 rounded w-20" />
                                </td>
                                <td class="px-2 py-2 cvr-num">{{ row.amount_formatted }}</td>
                                <td class="px-2 py-2">
                                    <button @click="removeAllocationRow(index)" class="cvr-btn-danger px-2 py-1 rounded border text-xs">✕</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <button @click="addAllocationRow" class="cvr-btn-secondary px-3 py-1.5 rounded border text-xs mb-4">
                        {{ $t('+ Add Row') }}
                    </button>

                    <div class="flex justify-end gap-2">
                        <button @click="closeAllocationModal" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitAllocations" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Save Allocations') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
