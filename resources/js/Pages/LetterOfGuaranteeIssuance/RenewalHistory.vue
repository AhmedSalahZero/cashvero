<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
    company: Object,
    letterOfGuaranteeIssuance: Object,
    rows: Array,
    mode: String, // 'create' | 'edit'
    canShowForm: Boolean,
    formDefaults: Object,
    storeUrl: String,
    updateUrl: String,
    indexUrl: String,
    backUrl: String,
    navUrls: Object,
});

const { can } = usePermissions();

const page = usePage();
const isEdit = props.mode === 'edit';

/*
 * ⚠️ CRITICAL: the backend
 * (LetterOfGuaranteeIssuanceRenewalDateController@store / @update)
 * manually parses `renewal_date` with explode('/', ...), assuming
 * MM/DD/YYYY — exactly what the old jQuery datepicker sent. That
 * logic is UNCHANGED, deliberately. A native <input type="date">
 * produces ISO (YYYY-MM-DD) internally, so we must convert on submit
 * or the date silently corrupts. Same trap, same fix, as
 * TimeOfDeposits/RenewalHistory.vue.
 */
function toSlashDate(isoDate) {
    if (!isoDate) return '';
    const [year, month, day] = isoDate.split('-');
    if (!year || !month || !day) return '';
    return `${month}/${day}/${year}`;
}

const form = ref({
    renewal_date: props.formDefaults?.renewal_date ?? '',
    fees_amount: props.formDefaults?.fees_amount ?? 0,
});

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        renewal_date: toSlashDate(form.value.renewal_date),
        fees_amount: form.value.fees_amount,
        // expiry_date is only actually read server-side by update()
        // (store() recomputes it itself from the LG's current
        // renewal_date) — sent here regardless so edit mode always
        // carries the right value, matching the original form's
        // hidden input.
        expiry_date: props.formDefaults?.expiry_date ?? '',
    };
    if (isEdit) {
        router.patch(props.updateUrl, payload, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.storeUrl, payload, { onFinish: () => { submitting.value = false; } });
    }
}

const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    ← Back to LG Issuance
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Renewal Date History</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ letterOfGuaranteeIssuance.transaction_name }} · {{ letterOfGuaranteeIssuance.lg_code }}
                ({{ letterOfGuaranteeIssuance.source_formatted }})
            </p>

            <!-- Add / Edit form — only shown if editing, or the LG has
                 expired (matches the original's exact rule: you can't
                 add a renewal until the current term is done). -->
            <div v-if="canShowForm" class="cvr-card mb-6">
                <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
                    {{ isEdit ? 'Edit Renewal' : 'Adjusted Renewal Date Section' }}
                </h2>
                <div v-if="Object.keys(page.props.errors || {}).length" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
                    Please fix the highlighted field(s) below before saving.
                </div>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Transaction Name</label>
                            <input disabled :value="letterOfGuaranteeIssuance.transaction_name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Source</label>
                            <input disabled :value="letterOfGuaranteeIssuance.source_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Code</label>
                            <input disabled :value="letterOfGuaranteeIssuance.lg_code" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Issuance Date</label>
                            <input disabled :value="letterOfGuaranteeIssuance.issuance_date_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>

                        <div>
                            <label class="cvr-form-label">Expiry Date</label>
                            <input disabled :value="formDefaults?.expiry_date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">New Expiry Date *</label>
                            <input v-model="form.renewal_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('renewal_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('renewal_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Renewal Fees</label>
                            <input v-model="form.fees_amount" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('fees_amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor('fees_amount') }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Link :href="indexUrl" class="cvr-btn-secondary px-4 py-2 rounded border">Cancel</Link>
                        <button v-if="can('lg_issuance.renew')" type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                            {{ submitting ? 'Saving...' : 'Save' }}
                        </button>
                    </div>
                </form>
            </div>
            <div v-else class="mb-6 px-4 py-3 rounded cvr-badge-pending text-sm">
                This LG hasn't reached its current expiry date yet — a new renewal can only be added once it's expired.
            </div>

            <!-- History table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Days Count</th>
                            <th class="px-4 py-3 text-left">Fees Amount</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-primary">
                                {{ row.renewal_date_formatted }}
                                <span v-if="row.is_original" class="cvr-text-muted text-xs"> (Original Renewal Date)</span>
                            </td>
                            <td class="px-4 py-3 cvr-text-secondary">{{ row.days_count ?? '-' }}</td>
                            <td class="px-4 py-3 cvr-num">{{ row.fees_amount_formatted }}</td>
                            <td class="px-4 py-3">
                                <!-- Only the last row is editable/deletable —
                                     matches the original exactly. -->
                                <div v-if="row.is_last" class="flex items-center gap-2">
                                    <Link :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">
                                        Edit
                                    </Link>
                                    <button v-if="can('lg_issuance.renew')" @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center cvr-text-muted">
                                No renewal history yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        Delete Renewal Date History {{ deleteTarget.renewal_date_formatted }}?
                    </h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
