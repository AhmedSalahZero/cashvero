<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
    company: Object,
    timeOfDeposit: Object,
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
 * ⚠️ CRITICAL: the backend (TimeOfDepositRenewalDateController@store /
 * @update) manually parses `renewal_date` with explode('/', ...),
 * assuming MM/DD/YYYY — exactly what the old jQuery datepicker sent.
 * That logic is UNCHANGED, deliberately. A native <input type="date">
 * produces ISO (YYYY-MM-DD) internally, so we must convert on submit
 * or the date silently corrupts. `expiry_date` has no such trap (its
 * model mutator tolerates both formats), so it's sent as plain ISO.
 */
function toSlashDate(isoDate) {
    if (!isoDate) return '';
    const [year, month, day] = isoDate.split('-');
    if (!year || !month || !day) return '';
    return `${month}/${day}/${year}`;
}

const form = ref({
    expiry_date: props.formDefaults?.expiry_date ?? '',
    renewal_date: props.formDefaults?.renewal_date ?? '',
    interest_rate: props.formDefaults?.interest_rate ?? 0,
    interest_amount: props.formDefaults?.interest_amount ?? 0,
});

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        expiry_date: form.value.expiry_date,
        renewal_date: toSlashDate(form.value.renewal_date),
        interest_rate: form.value.interest_rate,
        interest_amount: form.value.interest_amount,
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
                    {{ $t('← Back to Time Of Deposit') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Renewal Date History') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ timeOfDeposit.financial_institution_name }} · {{ timeOfDeposit.account_number }}
                ({{ timeOfDeposit.currency?.toUpperCase() }})
            </p>

            <!-- Add / Edit form — only shown if editing, or the TD has
                 expired (matches the original's exact rule: you can't
                 add a renewal until the current term is done). -->
            <div v-if="canShowForm" class="cvr-card mb-6">
                <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
                    {{ isEdit ? $t('Edit Renewal') : $t('Adjusted Renewal Date') }}
                </h2>
                <FormErrorSummary />
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Financial Institution') }}</label>
                            <input disabled :value="timeOfDeposit.financial_institution_name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Number') }}</label>
                            <input disabled :value="timeOfDeposit.account_number" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency') }}</label>
                            <input disabled :value="timeOfDeposit.currency" class="cvr-input w-full px-3 py-2 rounded uppercase" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Current Interest Rate') }}</label>
                            <input disabled :value="timeOfDeposit.interest_rate_formatted" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Interest Amount') }}</label>
                            <input v-model="form.interest_amount" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Expiry Date') }}</label>
                            <input v-model="form.expiry_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('expiry_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('expiry_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('New Interest Rate (%)') }}</label>
                            <input v-model="form.interest_rate" type="number" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('New Renewal Date') }} *</label>
                            <input v-model="form.renewal_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('renewal_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('renewal_date') }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Link :href="indexUrl" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Cancel') }}</Link>
                        <button v-if="can('time_of_deposit.renew')" type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                            {{ submitting ? $t('Saving...') : $t('Save') }}
                        </button>
                    </div>
                </form>
            </div>
            <div v-else class="mb-6 px-4 py-3 rounded cvr-badge-pending text-sm">
                {{ $t('This Time Of Deposit hasn\'t reached its end date yet — a new renewal can only be added once it\'s expired.') }}
            </div>

            <!-- History table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Days Count') }}</th>
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
                            <td class="px-4 py-3">
                                <!-- Only the last row is editable/deletable —
                                     matches the original exactly. -->
                                <div v-if="row.is_last" class="flex items-center gap-2">
                                    <Link :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">
                                        {{ $t('Edit') }}
                                    </Link>
                                    <button v-if="can('time_of_deposit.renew')" @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">
                                        {{ $t('Delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center cvr-text-muted">
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
