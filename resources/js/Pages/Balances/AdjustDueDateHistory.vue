<script setup>
/**
 * Balances/AdjustDueDateHistory.vue
 * ------------------------------------------------------------------
 * Served by AdjustedDueDateHistoriesController@index (add mode) and
 * @edit (edit mode, editingHistory populated) — one shared page for
 * both, same as the original Blade's single-view-two-modes pattern.
 * Reached from the "Adjust Due Date" button on the Invoice Report page.
 *
 * Business rule preserved exactly: only the MOST RECENT history row
 * can be edited or deleted (row.is_last) — earlier rows are a locked
 * audit trail, shown read-only.
 *
 * The backend still expects due_date as MM/DD/YYYY (see the
 * controller's docblock — DueDateHistory::setDueDateAttribute()'s
 * mutator relies on that exact format), so the native ISO date input
 * here is converted before submitting — same pattern used on Time Of
 * Deposit's renewal dates.
 */
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
    company: Object,
    invoice: Object, // { id, name, invoice_number, due_date_formatted, net_balance_formatted, currency }
    modelType: String,
    customerNameOrSupplierNameText: String,
    dueDateHistories: Array, // [{ id, due_date_formatted, is_original, days_count, amount_formatted, is_last, edit_url, delete_url }]
    editingHistory: Object, // { id, due_date_iso } or null when adding a new adjustment
    storeUrl: String,
    updateUrl: String, // null unless editingHistory is set
    indexUrl: String,
    backUrl: String,
});

const { can } = usePermissions();

/* ── ISO → MM/DD/YYYY, same converter used on Time Of Deposit's
   renewal dates — the backend's mutator only understands the slash
   format (matching the old jQuery datepicker). ────────────────────── */
function toSlashDate(isoDate) {
    if (!isoDate) return '';
    const [year, month, day] = isoDate.split('-');
    if (!year || !month || !day) return '';
    return `${month}/${day}/${year}`;
}

const form = useForm({
    due_date: props.editingHistory?.due_date_iso || '',
});

function submit() {
    const submission = form.transform(data => ({ due_date: toSlashDate(data.due_date) }));
    if (props.editingHistory) {
        submission.patch(props.updateUrl);
    } else {
        submission.post(props.storeUrl);
    }
}

/* ── Delete confirmation ─────────────────────────────────────────── */
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
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                {{ $t('← Back to Invoice Report') }}
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Adjusted Due Date') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">{{ editingHistory ? $t('Editing the most recent adjustment') : $t('Adjusted Collection Date Section') }}</p>

            <!-- Form -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-4 mb-6">
                <div class="cvr-form-grid-3">
                    <div>
                        <label class="cvr-form-label">{{ customerNameOrSupplierNameText }}</label>
                        <input type="text" disabled :value="invoice.name" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Invoice Number') }}</label>
                        <input type="text" disabled :value="invoice.invoice_number" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Invoice Due Date') }}</label>
                        <input type="text" disabled :value="invoice.due_date_formatted" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Invoice Net Balance') }}</label>
                        <input type="text" disabled :value="invoice.net_balance_formatted" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Invoice Currency') }}</label>
                        <input type="text" disabled :value="invoice.currency" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Adjusted Collection Date') }} *</label>
                        <input v-model="form.due_date" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="form.errors.due_date" class="text-xs text-red-500 mt-1">{{ form.errors.due_date }}</p>
                    </div>
                </div>
                <button @click="submit" :disabled="form.processing" class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-4">
                    {{ editingHistory ? $t('Save Change') : $t('Submit') }}
                </button>
                <Link v-if="editingHistory" :href="indexUrl" class="cvr-btn-secondary px-4 py-1.5 rounded border text-sm mt-4 ms-2 inline-block">
                    {{ $t('Cancel') }}
                </Link>
            </div>

            <!-- History table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3 text-center">{{ $t('Date') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Days Count') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Amount') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in dueDateHistories" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ i + 1 }}</td>
                            <td class="px-4 py-3 text-center cvr-text-primary">
                                {{ row.due_date_formatted }}
                                <span v-if="row.is_original" class="cvr-text-muted text-xs"> {{ $t('(Original Due Date)') }}</span>
                            </td>
                            <td class="px-4 py-3 text-center cvr-num">{{ row.days_count ?? '-' }}</td>
                            <td class="px-4 py-3 text-right cvr-num">{{ row.amount_formatted }}</td>
                            <td class="px-4 py-3 text-center">
                                <div v-if="row.is_last" class="flex items-center justify-center gap-2">
                                    <Link :href="row.edit_url" class="cvr-action-btn" :title="$t('Edit')">✎</Link>
                                    <button v-if="can('adjusted_due_date.delete')" @click="confirmDelete(row)" class="cvr-action-btn cvr-action-btn-danger" :title="$t('Delete')">🗑</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="dueDateHistories.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No due date adjustments yet.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Delete due date history') }} {{ deleteTarget.due_date_formatted }}?</h2>
                    <p class="text-sm cvr-text-muted mb-4">{{ $t('Are you sure you want to delete this item?') }}</p>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
