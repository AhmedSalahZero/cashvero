<script setup>
import { computed, ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
    company: Object,
    letterOfGuaranteeIssuance: Object,
    rows: Array,
    mode: String, // 'create' | 'edit'
    canShowForm: Boolean,
    formDefaults: Object,
    currentTerms: Object,
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

/*
 * The bank re-prices the LG when it renews it — a different cash
 * cover, a different commission — so those three ride along with the
 * date and the fee. They open pre-filled with what is in force today;
 * whatever is submitted becomes the terms of the NEW period, and the
 * backend posts only the DIFFERENCE in cash cover, dated at the start
 * of that period. See App\Support\LetterOfGuarantee\LgRenewalTerms.
 */
const form = ref({
    renewal_date: props.formDefaults?.renewal_date ?? '',
    fees_amount: props.formDefaults?.fees_amount ?? 0,
    cash_cover_amount: props.formDefaults?.cash_cover_amount ?? 0,
    lg_commission_amount: props.formDefaults?.lg_commission_amount ?? 0,
    min_lg_commission_fees: props.formDefaults?.min_lg_commission_fees ?? 0,
});

function toNumber(value) {
    const parsed = Number(String(value ?? '').replace(/,/g, ''));
    return Number.isFinite(parsed) ? parsed : 0;
}

const cashCoverDifference = computed(
    () => toNumber(form.value.cash_cover_amount) - toNumber(props.currentTerms?.cash_cover_amount),
);

function formatAmount(value) {
    return toNumber(value).toLocaleString('en-US');
}

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        renewal_date: toSlashDate(form.value.renewal_date),
        fees_amount: form.value.fees_amount,
        cash_cover_amount: form.value.cash_cover_amount,
        lg_commission_amount: form.value.lg_commission_amount,
        min_lg_commission_fees: form.value.min_lg_commission_fees,
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
                    {{ $t('← Back to LG Issuance') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Renewal Date History') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ letterOfGuaranteeIssuance.transaction_name }} · {{ letterOfGuaranteeIssuance.lg_code }}
                ({{ letterOfGuaranteeIssuance.source_formatted }})
            </p>

            <!-- Add / Edit form — only shown if editing, or the LG has
                 expired (matches the original's exact rule: you can't
                 add a renewal until the current term is done). -->
            <div v-if="canShowForm" class="cvr-card mb-6">
                <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
                    {{ isEdit ? $t('Edit Renewal') : $t('Adjusted Renewal Date Section') }}
                </h2>
                <FormErrorSummary />
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Transaction Name') }}</label>
                            <input disabled :value="letterOfGuaranteeIssuance.transaction_name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Source') }}</label>
                            <input disabled :value="letterOfGuaranteeIssuance.source_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('LG Code') }}</label>
                            <input disabled :value="letterOfGuaranteeIssuance.lg_code" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Issuance Date') }}</label>
                            <input disabled :value="letterOfGuaranteeIssuance.issuance_date_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Expiry Date') }}</label>
                            <input disabled :value="formDefaults?.expiry_date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('New Expiry Date') }} *</label>
                            <input v-model="form.renewal_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('renewal_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('renewal_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Renewal Fees') }}</label>
                            <input v-model="form.fees_amount" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('fees_amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor('fees_amount') }}</p>
                        </div>
                    </div>

                    <!-- The bank often re-prices the LG at renewal.
                         Whatever is entered here becomes the terms of
                         the new period; for cash cover only the
                         DIFFERENCE is posted, dated at the expiry date
                         above (the start of the new period). -->
                    <div>
                        <h3 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-3">
                            {{ $t('Terms For The New Period') }}
                        </h3>
                        <div class="cvr-form-grid-4">
                            <div>
                                <label class="cvr-form-label">{{ $t('Cash Cover') }}</label>
                                <input v-model="form.cash_cover_amount" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                                <p class="text-xs mt-1 cvr-text-muted">
                                    {{ $t('Current') }}: {{ formatAmount(currentTerms?.cash_cover_amount) }} {{ currentTerms?.currency }}
                                </p>
                                <p v-if="cashCoverDifference !== 0" class="text-xs mt-1" :class="cashCoverDifference > 0 ? 'cvr-num-red' : 'cvr-num-green'">
                                    {{ cashCoverDifference > 0 ? $t('To be deducted') : $t('To be refunded') }}:
                                    {{ formatAmount(Math.abs(cashCoverDifference)) }}
                                </p>
                                <p v-if="errorFor('cash_cover_amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor('cash_cover_amount') }}</p>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('LG Commission Amount') }}</label>
                                <input v-model="form.lg_commission_amount" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                                <p class="text-xs mt-1 cvr-text-muted">
                                    {{ $t('Current') }}: {{ formatAmount(currentTerms?.lg_commission_amount) }}
                                    <span v-if="currentTerms?.commission_interval"> · {{ currentTerms.commission_interval }}</span>
                                </p>
                                <p v-if="errorFor('lg_commission_amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor('lg_commission_amount') }}</p>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Min LG Commission Fees') }}</label>
                                <input v-model="form.min_lg_commission_fees" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                                <p class="text-xs mt-1 cvr-text-muted">
                                    {{ $t('Current') }}: {{ formatAmount(currentTerms?.min_lg_commission_fees) }}
                                </p>
                                <p v-if="errorFor('min_lg_commission_fees')" class="text-xs mt-1 cvr-num-red">{{ errorFor('min_lg_commission_fees') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Link :href="indexUrl" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Cancel') }}</Link>
                        <button v-if="can('lg_issuance.renew')" type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                            {{ submitting ? $t('Saving...') : $t('Save') }}
                        </button>
                    </div>
                </form>
            </div>
            <div v-else class="mb-6 px-4 py-3 rounded cvr-badge-pending text-sm">
                {{ $t('This LG hasn\'t reached its current expiry date yet — a new renewal can only be added once it\'s expired.') }}
            </div>

            <!-- History table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Days Count') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Fees Amount') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Cash Cover') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('LG Commission Amount') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-primary">
                                {{ row.renewal_date_formatted }}
                                <span v-if="row.is_original" class="cvr-text-muted text-xs"> {{ $t('(Original Renewal Date)') }}</span>
                            </td>
                            <td class="px-4 py-3 cvr-text-secondary">{{ row.days_count ?? '-' }}</td>
                            <td class="px-4 py-3 cvr-num">{{ row.fees_amount_formatted }}</td>
                            <td class="px-4 py-3 cvr-num">
                                {{ row.cash_cover_amount_formatted ?? '-' }}
                                <span v-if="row.cash_cover_difference_formatted" class="cvr-text-muted text-xs">
                                    ({{ row.cash_cover_difference_formatted }})
                                </span>
                            </td>
                            <td class="px-4 py-3 cvr-num">{{ row.lg_commission_amount_formatted ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <!-- Only the last row is editable/deletable —
                                     matches the original exactly. -->
                                <div v-if="row.is_last" class="flex items-center gap-2">
                                    <Link :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">
                                        {{ $t('Edit') }}
                                    </Link>
                                    <button v-if="can('lg_issuance.renew')" @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">
                                        {{ $t('Delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No renewal history yet.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ $t('Delete Renewal Date History') }} {{ deleteTarget.renewal_date_formatted }}?
                    </h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
