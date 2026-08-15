<script setup>
/**
 * InvoiceUpload/Index.vue
 * ------------------------------------------------------------------
 * Served by SalesGatheringController@index — the listing page of
 * already-uploaded/committed Customer or Supplier invoices. Shared
 * by both via modelName, same pattern as everywhere else.
 *
 * Columns are DYNAMIC — each company has its own saved field
 * template (which columns exist, in what order), not a fixed set.
 *
 * Still links out to Blade for: Import (the async upload + preview
 * flow — its own dedicated build, not this page), Export, and the
 * Template Field Selection page. Edit also links out to a separate
 * single-record edit form (create-excel-by-form.blade.php, 865
 * lines, shared across many model types — its own scope question).
 * Delete (single row) is a real Inertia action since
 * SalesGatheringController@destroy already returns a plain redirect.
 *
 * "+ Create" (single-record add, via the existing InvoiceForm.vue —
 * see SalesGatheringTestController@createModel) was present in the
 * original Blade toolbar but had been dropped from this page during
 * migration; restored here. For CustomerInvoice/SupplierInvoice on
 * an Odoo-synced company, Create/Edit/Delete are all hidden — those
 * rows come from Odoo itself, same rule already applied to
 * "Add Partner" (PartnersController) and the Customer/Supplier Name
 * field lock elsewhere. companyHasOdoo is only ever true for those
 * two model types (see SalesGatheringController@index); every other
 * upload type (SalesGathering, LoanSchedule, etc.) is unaffected.
 *
 * Business-rule update: for Odoo-synced companies, Edit is now
 * allowed (per explicit product decision) — only Create and Delete
 * stay hidden, since those rows should still originate from and be
 * removed via Odoo itself, not this app.
 *
 * NOT built here (see controller docblock): bulk row-checkbox delete
 * (shared DeletingClass, out of scope) and "Close Period" (the
 * original's own backend method is empty — a dead feature, not
 * replicated as if it worked).
 */
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    modelName: String,
    modelDisplayName: String,
    isScheduleModel: Boolean,
    isProcessing: Boolean,
    columns: Array, // [{ label, field }]
    rows: Array, // [{ id, cells: [...], status, remaining, settlementUrl, editUrl, deleteUrl }]
    pagination: Object, // { current_page, last_page, total, per_page }
    canUpload: Boolean,
    canExport: Boolean,
    canDelete: Boolean,
    companyHasOdoo: Boolean,
    createUrl: String,
    importUrl: String,
    exportUrl: String,
    templateFieldsUrl: String,
    currentField: String,
    currentValue: String,
    currentFrom: String,
    currentTo: String,
    indexUrl: String,
});

/* ── Search/filter — since this page is itself already migrated,
   re-filtering re-fetches via a real Inertia visit (not a native
   form), keeping the same field/value/from/to query contract the
   backend already expects. ─────────────────────────────────────── */
const searchField = ref(props.currentField || (props.columns[0]?.field ?? ''));
const searchValue = ref(props.currentValue || '');
const fromDate = ref(props.currentFrom || '');
const toDate = ref(props.currentTo || '');

function applyFilters() {
    router.get(props.indexUrl, {
        field: searchField.value,
        value: searchValue.value,
        from: fromDate.value,
        to: toDate.value,
    }, { preserveState: true });
}

function clearFilters() {
    searchValue.value = '';
    fromDate.value = '';
    toDate.value = '';
    router.get(props.indexUrl, {}, { preserveState: true });
}

function goToPage(page) {
    router.get(props.indexUrl, {
        field: searchField.value,
        value: searchValue.value,
        from: fromDate.value,
        to: toDate.value,
        page,
    }, { preserveState: true, preserveScroll: true });
}

/* ── "Still processing" banner + auto-poll ──────────────────────────
   insertToMainTable() dispatches the actual DB-insert job and
   redirects immediately, without waiting for it to finish — so this
   page can render before a queue worker has caught up, looking
   "empty" right after Save even though nothing failed. isProcessing
   (backed by the same ActiveJob row the backend already tracks this
   with) tells us that's what's happening.

   ⚠️ Hard-capped, deliberately: if a queue worker isn't running, or a
   job silently fails and never clears its ActiveJob row, isProcessing
   would never turn false — an uncapped setInterval would then poll
   forever, and if reloads pile up faster than they resolve, the tab's
   memory/CPU climbs unbounded until the browser becomes unresponsive.
   Stops after 10 tries (~30s) and falls back to a manual "Check Again"
   button instead. Also guards against a second interval ever starting
   if this somehow runs twice. */
const pollAttempts = ref(0);
const pollGaveUp = ref(false);
const MAX_POLL_ATTEMPTS = 10;
let pollTimer = null;

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function pollUntilProcessed() {
    if (!props.isProcessing || pollTimer) return;
    pollTimer = setInterval(() => {
        pollAttempts.value++;
        if (pollAttempts.value > MAX_POLL_ATTEMPTS) {
            stopPolling();
            pollGaveUp.value = true;
            return;
        }
        router.reload({
            only: ['rows', 'pagination', 'isProcessing'],
            onSuccess: () => {
                if (!props.isProcessing) {
                    stopPolling();
                }
            },
        });
    }, 3000);
}

function checkAgain() {
    pollAttempts.value = 0;
    pollGaveUp.value = false;
    router.reload({ only: ['rows', 'pagination', 'isProcessing'] });
}

onMounted(pollUntilProcessed);
onBeforeUnmount(stopPolling);

/* ── Single-row delete — real Inertia action, controller already
   returns a plain redirect. ─────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function destroyRow() {
    router.delete(deleteTarget.value.deleteUrl, {
        onFinish: () => { deleteTarget.value = null; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">{{ modelDisplayName }} Table</h1>
                <div class="flex items-center gap-2 flex-wrap">
                    <Link v-if="canUpload && !companyHasOdoo" :href="createUrl" class="cvr-btn-primary px-3 py-1.5 rounded text-sm whitespace-nowrap">+ Create</Link>
                    <Link v-if="canUpload" :href="importUrl" class="cvr-btn-primary px-3 py-1.5 rounded text-sm whitespace-nowrap">Upload Data</Link>
                    <a v-if="canExport" :href="exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm whitespace-nowrap">Export All Data</a>
                    <a v-if="canUpload" :href="templateFieldsUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm whitespace-nowrap">Select Template Fields</a>
                </div>
            </div>
            <p class="text-sm cvr-text-muted mb-6">{{ pagination.total }} record(s)</p>

            <div v-if="isProcessing && !pollGaveUp" class="mb-4 px-4 py-3 rounded cvr-card-bg cvr-border border text-sm flex items-center gap-2">
                <span class="animate-spin inline-block w-3.5 h-3.5 border-2 border-current border-t-transparent rounded-full"></span>
                <span>Your upload is still being saved — this page will update automatically in a few seconds.</span>
            </div>
            <div v-else-if="isProcessing && pollGaveUp" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm flex items-center justify-between gap-3 flex-wrap">
                <span>This is taking longer than expected. Your upload may still be processing in the background.</span>
                <button type="button" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm whitespace-nowrap" @click="checkAgain">Check Again</button>
            </div>

            <!-- Filters -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-4 mb-6">
                <div class="cvr-form-grid-4">
                    <div>
                        <label class="cvr-form-label">Search In</label>
                        <select v-model="searchField" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="col in columns" :key="col.field" :value="col.field">{{ col.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Value</label>
                        <input v-model="searchValue" type="text" class="cvr-input w-full px-3 py-2 rounded" placeholder="Search text..." />
                    </div>
                    <div>
                        <label class="cvr-form-label">From Date</label>
                        <input v-model="fromDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">To Date</label>
                        <input v-model="toDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button @click="applyFilters" class="cvr-btn-primary px-4 py-1.5 rounded text-sm">Search</button>
                    <button @click="clearFilters" class="cvr-btn-secondary px-4 py-1.5 rounded border text-sm">Clear</button>
                </div>
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th v-for="col in columns" :key="col.field" class="px-3 py-3 text-center">{{ col.label }}</th>
                            <th v-if="isScheduleModel" class="px-3 py-3 text-center">Status</th>
                            <th v-if="isScheduleModel" class="px-3 py-3 text-center">Remaining</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in rows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-2 text-center cvr-text-secondary">{{ (pagination.current_page - 1) * pagination.per_page + i + 1 }}</td>
                            <td v-for="(cell, ci) in row.cells" :key="ci" class="px-3 py-2 text-center cvr-text-primary">
                                <span class="block truncate max-w-[220px]" :title="cell">{{ cell }}</span>
                            </td>
                            <td v-if="isScheduleModel" class="px-3 py-2 text-center cvr-text-secondary">{{ row.status }}</td>
                            <td v-if="isScheduleModel" class="px-3 py-2 text-center cvr-num">{{ row.remaining }}</td>
                            <td class="px-4 py-2 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a v-if="row.settlementUrl" :href="row.settlementUrl" class="cvr-action-btn" title="Settlement">💲</a>
                                    <!--
                                        Bug fix (client-flagged, confirmed 2026-08-15): an installment
                                        that's paid or partially paid shouldn't be edited or deleted
                                        from here anymore — doing so would now disagree with real
                                        settlement/ledger rows that already exist for it. Delete used
                                        to only hide once fully paid (remaining_raw === 0); Edit was
                                        never hidden. Both now hide as soon as isPaidOrPartiallyPaid is
                                        true, computed server-side (SalesGatheringController@index)
                                        from the installment's real cheque amount vs. remaining, not
                                        from whatever columns happen to be visible in this table.
                                    -->
                                    <Link
                                        v-if="canUpload && !row.isPaidOrPartiallyPaid"
                                        :href="row.editUrl"
                                        class="cvr-action-btn"
                                        title="Edit"
                                    >✎</Link>
                                    <button
                                        v-if="canDelete && !companyHasOdoo && !row.isPaidOrPartiallyPaid"
                                        @click="confirmDelete(row)"
                                        class="cvr-action-btn cvr-action-btn-danger"
                                        title="Delete"
                                    >🗑</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td :colspan="columns.length + (isScheduleModel ? 4 : 2)" class="px-4 py-8 text-center cvr-text-muted">No records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="flex items-center justify-between mt-4">
                <p class="text-xs cvr-text-muted">Page {{ pagination.current_page }} of {{ pagination.last_page }}</p>
                <div class="flex items-center gap-1">
                    <button @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="cvr-filter-pill" :class="{ 'opacity-40 cursor-not-allowed': pagination.current_page === 1 }">‹ Prev</button>
                    <button @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="cvr-filter-pill" :class="{ 'opacity-40 cursor-not-allowed': pagination.current_page === pagination.last_page }">Next ›</button>
                </div>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Delete this record?</h2>
                    <p class="text-sm cvr-text-muted mb-4">This action cannot be undone.</p>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>