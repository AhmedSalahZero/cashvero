<script setup>
/**
 * InvoiceUpload/Import.vue
 * ------------------------------------------------------------------
 * Served by SalesGatheringTestController@import (GET). Four states,
 * driven by the same booleans the original Blade used:
 *   1. idle       — plain upload form (file + date format)
 *   2. parsing    — background job reading the file into a preview
 *                    cache; polls (via real page reloads) until a
 *                    stable state is reached — see the polling
 *                    section below for why a simple one-shot check
 *                    isn't reliable here
 *   3. review     — preview of the first 20 parsed rows, duplicate
 *                    warnings (NEW — see below), delete-before-save,
 *                    Save Data button
 *   4. saving     — background job writing the preview into the real
 *                    table; polls the percentage endpoint every 5s,
 *                    redirects to the Balances page on completion
 *
 * Duplicate detection (confirmed with project owner): rows whose
 * invoice_number+currency already exist for this company are
 * flagged here in review, and are silently SKIPPED (never
 * replaced/overwritten) by the actual save step in
 * SalesGatheringTestJob — see that job's docblock for why "replace"
 * was deliberately not offered.
 */
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    modelName: String,
    modelDisplayName: String,
    uploadUrl: String,
    saveDataUrl: String,
    lastUploadFailedUrl: String,
    percentagePollUrl: String,
    deleteSelectedUrl: String,
    deleteAllUrl: String,
    columns: Array, // [{ label, field }]
    previewRows: Array, // [{ id, cells, _dupKey, editUrl }]
    totalCachedRows: Number,
    duplicateInvoiceNumbers: Array, // ['INV-001|EGP', ...]
    duplicateCount: Number,
    isParsing: Boolean,
    isSaving: Boolean,
    canReview: Boolean,
    currentFileNameLabel: String,
    redirectUrlAfterSave: String,
    indexUrl: String,
    skippedDuplicateCount: Number,
});

const dupKeySet = new Set(props.duplicateInvoiceNumbers || []);
const rowsWithDupFlag = computed(() =>
    (props.previewRows || []).map(row => ({ ...row, isDuplicate: dupKeySet.has(row._dupKey) }))
);

/* ── Row selection (delete before save) ──────────────────────────── */
const selectedIds = ref([]);
function toggleAll(e) {
    selectedIds.value = e.target.checked ? rowsWithDupFlag.value.map(r => r.id).filter(Boolean) : [];
}
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

/* ── Upload form ──────────────────────────────────────────────── */
const form = useForm({
    excel_file: null,
    format: '',
});
const dateFormatOptions = [
    { value: 'd-m-Y', label: 'Day-Month-Year e.g. [15-01-2024]' },
    { value: 'd-M-Y', label: 'Day-Month-Year e.g. [15-Jan-2024]' },
    { value: 'm-d-Y', label: 'Month-Day-Year e.g. [05-15-2024]' },
    { value: 'Y-m-d', label: 'Year-Month-Day e.g. [2024-05-15]' },
    { value: 'Y-d-m', label: 'Year-Day-Month e.g. [2024-15-05]' },
];
function handleFileChange(e) {
    form.excel_file = e.target.files[0] || null;
}
function submitUpload() {
    form.post(props.uploadUrl, { forceFormData: true });
}

/* ── Polling — parsing phase ──────────────────────────────────────
   The backend marks completion in TWO separate steps, not one:
   NotifyUserOfCompletedImport deletes the "still working" marker
   first, THEN (a moment later) ShowCompletedMessageForSuccessJob
   sets the "ready to review" flag. A one-shot "poll once, reload
   once" approach can land in the gap between those two steps —
   isParsing already false, but canReview not yet true and no rows
   visible yet — and then stop polling entirely, looking stuck.
   This keeps polling (via real Inertia reloads, so all props stay
   in sync) until we reach a genuinely stable state. ───────────────── */
const waitingForParse = ref(props.isParsing);
const parseGaveUp = ref(false);
let parseTimer = null;
let parseAttempts = 0;
const MAX_PARSE_ATTEMPTS = 30; // ~60s at 2s intervals

watch(waitingForParse, (val) => {
    if (val && !parseTimer) {
        parseAttempts = 0;
        parseGaveUp.value = false;
        // ⚠️ Confirmed bug fix: this had no attempt cap. If a queue
        // worker isn't running (or the parse job stalls/fails before
        // either completion marker is set), isParsing/canReview never
        // change and this polled forever — every 2s, indefinitely,
        // for as long as the tab stayed open. Piling-up requests like
        // that can exhaust PHP-FPM's worker pool server-side, making
        // the whole app unresponsive for everyone, not just this tab.
        // Same class of bug as InvoiceUpload/Index.vue's polling, now
        // fixed the same way: hard cap, manual fallback after that.
        parseTimer = setInterval(() => {
            parseAttempts++;
            if (parseAttempts > MAX_PARSE_ATTEMPTS) {
                clearInterval(parseTimer);
                parseTimer = null;
                parseGaveUp.value = true;
                return;
            }
            router.reload();
        }, 2000);
    } else if (!val && parseTimer) {
        clearInterval(parseTimer);
        parseTimer = null;
    }
}, { immediate: true });

function checkParseAgain() {
    parseGaveUp.value = false;
    router.reload();
}

watch(
    () => [props.isParsing, props.canReview, props.totalCachedRows],
    () => {
        if (props.isParsing) {
            waitingForParse.value = true;
        } else if (props.canReview || props.totalCachedRows > 0) {
            waitingForParse.value = false;
        }
        // else: isParsing false, canReview false, no rows yet — the
        // gap between the two completion jobs. Deliberately leave
        // waitingForParse unchanged: if we were already polling,
        // keep polling through it; if nothing was happening (a cold
        // page load with no upload in progress), don't start
        // polling for no reason.
    }
);

/* ── Polling — saving phase ───────────────────────────────────────
   Matches the original's 5s interval + redirect-on-completion.
   Triggered via a watcher (not onMounted) for the same reason as
   parsing above — this component may not remount between visits. ── */
const savingPercent = ref(0);
const saveGaveUp = ref(false);
let saveTimer = null;
let saveAttempts = 0;
const MAX_SAVE_ATTEMPTS = 60; // ~5min at 5s intervals — saving can legitimately take longer than parsing for large files

function pollSaving() {
    if (saveTimer) return;
    saveAttempts = 0;
    saveGaveUp.value = false;
    // ⚠️ Same confirmed bug fix as the parsing poller above — this had
    // no attempt cap either, and would poll every 5s forever if the
    // save job stalled.
    saveTimer = setInterval(async () => {
        saveAttempts++;
        if (saveAttempts > MAX_SAVE_ATTEMPTS) {
            clearInterval(saveTimer);
            saveTimer = null;
            saveGaveUp.value = true;
            return;
        }
        const { data } = await window.axios.post(props.percentagePollUrl);
        savingPercent.value = data.totalPercentage;
        if (data.totalPercentage >= 100 || data.reloadPage) {
            clearInterval(saveTimer);
            saveTimer = null;
            router.visit(props.redirectUrlAfterSave);
        }
    }, 5000);
}

function checkSaveAgain() {
    pollSaving();
}

watch(() => props.isSaving, (val) => { if (val) pollSaving(); }, { immediate: true });

onBeforeUnmount(() => {
    if (parseTimer) clearInterval(parseTimer);
    if (saveTimer) clearInterval(saveTimer);
});
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="indexUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                {{ $t('← Back to') }} {{ modelDisplayName }} {{ $t('Table') }}
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ modelDisplayName }} {{ $t('Import') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">{{ $t('Maximum 50,000 rows per upload') }}</p>

            <p v-if="currentFileNameLabel" class="text-sm cvr-text-secondary mb-4">{{ currentFileNameLabel }}</p>
            <div v-if="skippedDuplicateCount > 0" class="cvr-card-bg cvr-border border rounded-lg p-3 mb-4" style="border-color: var(--cvr-num-amber)">
                <p class="text-sm cvr-num-amber">{{ $t('Your last save skipped') }} {{ skippedDuplicateCount }} {{ $t('row(s) that already existed and were not re-added.') }}</p>
            </div>
            <Link v-if="lastUploadFailedUrl" :href="lastUploadFailedUrl" class="inline-block text-sm cvr-num-red hover:underline mb-4">{{ $t('View last upload\'s failed rows →') }}</Link>

            <!-- State: parsing -->
            <div v-if="isParsing && !parseGaveUp" class="cvr-card-bg cvr-border border rounded-lg p-6 mb-6">
                <p class="cvr-num-green font-medium mb-3">{{ $t('Uploading and parsing your file…') }}</p>
                <div class="h-2 rounded-full bg-white/5 overflow-hidden">
                    <div class="h-full rounded-full animate-pulse" style="width: 100%; background-color: var(--cvr-green-bright)"></div>
                </div>
            </div>
            <div v-else-if="isParsing && parseGaveUp" class="cvr-card-bg cvr-border border rounded-lg p-6 mb-6 flex items-center justify-between gap-3 flex-wrap">
                <p class="text-sm">{{ $t('This is taking longer than expected. Your file may still be processing in the background.') }}</p>
                <button type="button" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm whitespace-nowrap" @click="checkParseAgain">{{ $t('Check Again') }}</button>
            </div>

            <!-- State: saving -->
            <div v-else-if="isSaving && !saveGaveUp" class="cvr-card-bg cvr-border border rounded-lg p-6 mb-6">
                <p class="cvr-num-green font-medium mb-3">{{ $t('Saving to the database…') }} {{ savingPercent.toFixed(0) }}%</p>
                <div class="h-2 rounded-full bg-white/5 overflow-hidden">
                    <div class="h-full rounded-full transition-all" :style="{ width: savingPercent + '%', backgroundColor: 'var(--cvr-green-bright)' }"></div>
                </div>
            </div>
            <div v-else-if="isSaving && saveGaveUp" class="cvr-card-bg cvr-border border rounded-lg p-6 mb-6 flex items-center justify-between gap-3 flex-wrap">
                <p class="text-sm">{{ $t('This is taking longer than expected (') }}{{ savingPercent.toFixed(0) }}{{ $t('% when we stopped checking). Your data may still be saving in the background.') }}</p>
                <button type="button" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm whitespace-nowrap" @click="checkSaveAgain">{{ $t('Check Again') }}</button>
            </div>

            <!-- State: review (preview + duplicates + Save Data) -->
            <template v-else-if="canReview && totalCachedRows > 0">
                <div v-if="duplicateCount > 0" class="cvr-card-bg cvr-border border rounded-lg p-3 mb-4" style="border-color: var(--cvr-num-red)">
                    <p class="text-sm cvr-num-red">
                        {{ duplicateCount }} {{ $t('of') }} {{ totalCachedRows }} {{ $t('row(s) already exist for this company (same invoice number + currency) and will be') }} <strong>{{ $t('skipped') }}</strong>{{ $t(', not replaced, when you save.') }}
                    </p>
                </div>

                <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                    <p class="text-sm cvr-text-muted">{{ $t('Showing') }} {{ previewRows.length }} {{ $t('of') }} {{ totalCachedRows }} {{ $t('row(s) — review before saving') }}</p>
                    <div class="flex items-center gap-2">
                        <Link :href="deleteAllUrl" class="cvr-btn-danger px-3 py-1.5 rounded border text-sm whitespace-nowrap" as="button" method="get">{{ $t('Delete All / Start Over') }}</Link>
                        <Link :href="saveDataUrl" class="cvr-btn-primary px-4 py-1.5 rounded text-sm whitespace-nowrap">{{ $t('Save Data') }}</Link>
                    </div>
                </div>

                <!--
                  Real native form (not an Inertia router.delete request):
                  DeleteMultiRowsFromCaching checks $request->ajax(), which
                  is TRUE for Inertia's axios-based requests — meaning an
                  Inertia delete would hit its raw-JSON response branch
                  instead of the redirect branch, which Inertia can't
                  render as a page update. A plain browser form submit
                  doesn't carry that header, so it correctly gets the
                  redirect branch, which Inertia's "non-Inertia response"
                  fallback turns into a normal full visit. Same pattern
                  used for other still-shared, not-yet-Inertia-audited
                  controllers.
                -->
                <form :action="deleteSelectedUrl" method="POST">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input type="hidden" name="_method" value="DELETE" />

                    <div class="flex items-center justify-between mb-2">
                        <label class="flex items-center gap-2 text-sm cvr-text-secondary">
                            <input type="checkbox" @change="toggleAll" />
                            {{ $t('Select all shown') }}
                        </label>
                        <button type="submit" class="cvr-btn-danger px-3 py-1.5 rounded border text-sm">{{ $t('Delete Selected') }}</button>
                    </div>

                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-center">{{ $t('Select') }}</th>
                                    <th class="px-3 py-2 text-center">#</th>
                                    <th v-for="col in columns" :key="col.field" class="px-3 py-2 text-center">{{ col.label }}</th>
                                    <th class="px-3 py-2 text-center">{{ $t('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in rowsWithDupFlag" :key="row.id ?? i" class="cvr-table-row" :class="{ 'cvr-sub-row': row.isDuplicate }">
                                    <td class="px-3 py-2 text-center">
                                        <input v-if="row.id" type="checkbox" name="rows[]" :value="row.id" v-model="selectedIds" />
                                    </td>
                                    <td class="px-3 py-2 text-center cvr-text-secondary">{{ i + 1 }}</td>
                                    <td v-for="(cell, ci) in row.cells" :key="ci" class="px-3 py-2 text-center cvr-text-primary">
                                        <span class="block truncate max-w-[200px]" :title="cell">{{ cell }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span v-if="row.isDuplicate" class="cvr-num-red text-xs font-medium">{{ $t('Duplicate — will skip') }}</span>
                                        <Link v-else-if="row.editUrl" :href="row.editUrl" class="cvr-action-btn" :title="$t('Edit')">✎</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </form>
            </template>

            <!-- State: idle — upload form -->
            <div v-else class="cvr-card-bg cvr-border border rounded-lg p-4 space-y-4">
                <div class="cvr-form-grid-2">
                    <div>
                        <label class="cvr-form-label">{{ $t('Import File') }} *</label>
                        <input type="file" required accept=".xlsx,.xls,.csv" @change="handleFileChange" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="form.errors.excel_file" class="text-xs text-red-500 mt-1">{{ form.errors.excel_file }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Date Formatting') }} *</label>
                        <select v-model="form.format" required class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select') }}</option>
                            <option v-for="opt in dateFormatOptions" :key="opt.value" :value="opt.value">{{ $t(opt.label) }}</option>
                        </select>
                    </div>
                </div>
                <button @click="submitUpload" :disabled="form.processing" class="cvr-btn-primary px-4 py-1.5 rounded text-sm">{{ $t('Upload') }}</button>
            </div>
        </div>
    </AppLayout>
</template>
