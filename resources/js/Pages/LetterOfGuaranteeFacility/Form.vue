<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';

const props = defineProps({
    mode: String, // 'create' | 'edit'
    hasRenewals: Boolean,
    company: Object,
    financialInstitution: Object,
    currencies: Object,
    commissionIntervals: Object,
    lgTypes: Object, // { 'bid-bond': 'Bid Bond', ... }
    model: Object,   // null in create mode
    submitUrl: String,
    backUrl: String,
    navUrls: Object,
});

const page = usePage();
const isEdit = props.mode === 'edit';
const isLockedByRenewal = isEdit && props.hasRenewals;

const form = ref({
    name: props.model?.name ?? '',
    contract_start_date: props.model?.contract_start_date ?? '',
    contract_end_date: props.model?.contract_end_date ?? '',
    currency: props.model?.currency ?? '',
    limit: props.model?.limit ?? 0,
});

/*
 * Outstanding Date / Outstanding Amount (main record) and Outstanding
 * Balance (per LG type row) are no longer used — that tracking moved
 * to LG Issuance. The original Blade form didn't remove these
 * fields either, just hid them with a CSS class while quietly
 * sending safe defaults (confirmed by reading the raw markup: the
 * date submits empty → NULL in the database since hidden required
 * fields are excluded from HTML5 validation; the amount and each
 * row's balance already default to 0 in the original markup). Same
 * defaults sent here, just with no dead UI for them at all instead of
 * a CSS-hidden one.
 */
const outstandingDate = null;
const outstandingAmount = 0;

/*
 * Term & Conditions is always exactly 4 fixed rows, one per LG type —
 * not an open repeater. Pre-filled from the model in edit mode, or
 * blank defaults (commission_interval defaulting to 'quarterly',
 * matching what a browser would pick anyway since the original
 * blade's "default to monthly" condition never actually matched any
 * real option in the dropdown).
 */
const termAndConditions = ref(
    props.model?.term_and_conditions ?? Object.keys(props.lgTypes).map(lgType => ({
        lg_type: lgType,
        cash_cover_rate: 0,
        commission_rate: 0,
        commission_interval: 'quarterly',
        min_commission_fees: 0,
        issuance_fees: 0,
    }))
);

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        ...form.value,
        outstanding_date: outstandingDate,
        outstanding_amount: outstandingAmount,
        termAndConditions: termAndConditions.value.map(row => ({ ...row, outstanding_balance: 0 })),
    };
    /**
     * Facility Renewal: once a renewal exists, this only ever submits
     * limit/contract_end_date — name/dates/currency and the Term &
     * Conditions matrix belong to a specific dated chapter and can't
     * be changed from this reduced form (same pattern as every other
     * facility type).
     */
    if (isLockedByRenewal) {
        delete payload.name;
        delete payload.contract_start_date;
        delete payload.currency;
        delete payload.outstanding_date;
        delete payload.outstanding_amount;
        delete payload.termAndConditions;
    }
    if (isEdit) {
        router.put(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    }
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    ← Back to LG Facility
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ isEdit ? 'Edit' : 'Add' }} LG Facility
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ financialInstitution.name }}</p>

            <FormErrorSummary />

            <div v-if="isLockedByRenewal" class="mb-4 px-4 py-3 rounded border border-blue-400 bg-blue-50 text-blue-800 text-sm">
                This facility has an active renewal, so this edits the <strong>current chapter's</strong> terms only —
                Limit and Contract End Date. The facility name, currency, start date, and Term &amp; Conditions
                matrix belong to a specific chapter and can't be changed here. To change those, delete and re-do
                the renewal from the Archived Facilities tab instead.
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Main Information</h2>
                    <div class="cvr-form-grid-8-4 mb-3">
                        <div>
                            <label class="cvr-form-label">Bank Name</label>
                            <input disabled :value="financialInstitution.name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">LG Contract Name *</label>
                            <input v-model="form.name" type="text" :disabled="isLockedByRenewal" class="cvr-input w-full px-3 py-2 rounded" :class="{ 'opacity-60': isLockedByRenewal }" />
                            <p v-if="errorFor('name')" class="text-xs mt-1 cvr-num-red">{{ errorFor('name') }}</p>
                        </div>
                    </div>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ isLockedByRenewal ? 'Current Chapter Start Date' : 'Contract Start Date *' }}</label>
                            <input v-model="form.contract_start_date" type="date" :disabled="isLockedByRenewal" class="cvr-input w-full px-3 py-2 rounded" :class="{ 'opacity-60': isLockedByRenewal }" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Contract End Date *</label>
                            <input v-model="form.contract_end_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('contract_end_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('contract_end_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Limit *</label>
                            <input v-model="form.limit" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('limit')" class="text-xs mt-1 cvr-num-red">{{ errorFor('limit') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Currency *</label>
                            <select v-model="form.currency" :disabled="isLockedByRenewal" class="cvr-input w-full px-3 py-2 rounded" :class="{ 'opacity-60': isLockedByRenewal }">
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div v-if="!isLockedByRenewal" class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Term &amp; Conditions — by LG Type</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-2 text-left">LG Type</th>
                                    <th class="px-3 py-2 text-left">Cash Cover Rate (%)</th>
                                    <th class="px-3 py-2 text-left">Commission Rate (%)</th>
                                    <th class="px-3 py-2 text-left min-w-[11rem]">Commission Interval</th>
                                    <th class="px-3 py-2 text-left">Min Commission Fees</th>
                                    <th class="px-3 py-2 text-left">Issuance Fees</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in termAndConditions" :key="row.lg_type" class="cvr-table-row">
                                    <td class="px-3 py-2 whitespace-nowrap cvr-text-primary font-medium">{{ lgTypes[row.lg_type] }}</td>
                                    <td class="px-3 py-2"><input v-model="row.cash_cover_rate" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-24" /></td>
                                    <td class="px-3 py-2"><input v-model="row.commission_rate" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-24" /></td>
                                    <td class="px-3 py-2">
                                        <div class="relative">
                                            <select v-model="row.commission_interval" class="cvr-input pl-2 pr-8 py-1.5 rounded w-full min-w-[11rem] appearance-none">
                                                <option v-for="(label, code) in commissionIntervals" :key="code" :value="code">{{ label }}</option>
                                            </select>
                                            <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs cvr-text-muted">▾</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2"><input v-model="row.min_commission_fees" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-28" /></td>
                                    <td class="px-3 py-2"><input v-model="row.issuance_fees" type="number" step="any" class="cvr-input px-2 py-1.5 rounded w-28" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">Cancel</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>