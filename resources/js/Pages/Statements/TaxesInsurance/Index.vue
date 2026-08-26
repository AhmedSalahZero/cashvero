<script setup>
/**
 * Statements/TaxesInsurance/Index.vue
 * ------------------------------------------------------------------
 * Feature (client requested, 2026-08-15): Taxes & Insurance used to be a
 * "Partner Type" option buried inside the generic Partner Statement
 * report — sharing that page's running-balance ledger layout. It's not
 * really a two-sided statement (see TaxesInsuranceStatementController's
 * docblock), so it now has its own simpler page: Date / Currency /
 * Paid To / Amount / Accumulated Amount / Comment. Filter and results
 * live on one page (no separate Result.vue) since there's no pagination
 * complexity to justify splitting it — this report is a flat list, not
 * a heavy multi-partner ledger.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import { todayDate, clampDateToToday } from '@/composables/today';

const maxDate = todayDate();

const props = defineProps({
    company: Object,
    currencies: Object, // { code: label }
    partners: Array, // [{ id, name }] — is_tax partners only
    urls: Object, // { result }
    filters: Object, // present once a search has been run: { currency, start_date, end_date, partner_id }
    rows: Array, // present once a search has been run
    totalAmount: [Number, String],
});

const currency = ref(props.filters?.currency || '');
const startDate = ref(props.filters?.start_date || new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().slice(0, 10));
const endDate = ref(props.filters?.end_date || todayDate());
const selectedPartnerIds = ref(props.filters?.partner_id || []);

watch(endDate, (value) => {
    const clamped = clampDateToToday(value);
    if (clamped !== value) {
        endDate.value = clamped;
    }
});

const partnerOptions = computed(() => props.partners.map(p => ({ value: p.id, label: p.name })));
const hasResults = computed(() => Array.isArray(props.rows));
const canSubmit = computed(() => startDate.value && endDate.value && endDate.value <= maxDate);

function submit() {
    if (!canSubmit.value) return;
    endDate.value = clampDateToToday(endDate.value);
    router.get(props.urls.result, {
        start_date: startDate.value,
        end_date: endDate.value,
        currency: currency.value || undefined,
        partner_id: selectedPartnerIds.value,
    });
}

function fmt(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Taxes & Insurance') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ $t('A list of payments made to tax and insurance partners, for a chosen date range and currency.') }}
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5 mb-6">
                <div class="cvr-form-grid-3 mb-5">
                    <div>
                        <label class="cvr-form-label">{{ $t('Start Date') }} *</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('End Date') }} *</label>
                        <input v-model="endDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Currency') }}</label>
                        <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('All currencies') }}</option>
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ String(label).toUpperCase() }}</option>
                        </select>
                    </div>
                </div>

                <div class="max-w-xl">
                    <label class="cvr-form-label">{{ $t('Paid To') }} <span class="cvr-text-muted font-normal">{{ $t('(leave empty for all)') }}</span></label>
                    <MultiSelectDropdown v-model="selectedPartnerIds" :options="partnerOptions" :placeholder="$t('All tax / insurance partners')" />
                </div>

                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-5"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit }"
                >
                    {{ $t('View Report') }}
                </button>
                <ul v-if="!canSubmit" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-if="!startDate">{{ $t('— Start Date is not set.') }}</li>
                    <li v-if="!endDate">{{ $t('— End Date is not set.') }}</li>
                    <li v-if="endDate > maxDate">{{ $t('— End Date can\'t be in the future.') }}</li>
                </ul>
            </div>

            <template v-if="hasResults">
                <div class="cvr-card-bg cvr-border border rounded-lg p-4 mb-4 flex items-center justify-between">
                    <span class="text-sm cvr-text-muted">{{ rows.length }} {{ $t('payment') }}{{ rows.length === 1 ? '' : 's' }}</span>
                    <span class="text-sm cvr-text-primary font-medium">{{ $t('Total Paid:') }} <span class="cvr-num">{{ fmt(totalAmount) }}</span></span>
                </div>

                <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-start">{{ $t('Date') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Currency') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Paid To') }}</th>
                                <th class="px-3 py-2 text-right">{{ $t('Amount') }}</th>
                                <th class="px-3 py-2 text-right">{{ $t('Accumulated Amount') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Comment') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2">{{ row.date }}</td>
                                <td class="px-3 py-2">{{ row.currency }}</td>
                                <td class="px-3 py-2">{{ row.paid_to }}</td>
                                <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.amount) }}</td>
                                <td class="px-3 py-2 text-right cvr-num">{{ fmt(row.accumulated_amount) }}</td>
                                <td class="px-3 py-2">
                                    <span class="block truncate max-w-xs" :title="row.comment">{{ row.comment || '—' }}</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No payments found for this filter.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
