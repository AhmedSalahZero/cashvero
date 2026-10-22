<script setup>
/**
 * Statements/ChequesReport/Result.vue
 * ------------------------------------------------------------------
 * Served by ChequesReportController@result. The cheques of one type
 * (Received / Payable) due on or after the chosen date. The Bank filter
 * re-runs the report server side, so the totals, the Excel export and
 * the printout all follow it.
 */
import { computed, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    company: Object,
    type: String,
    typeLabel: String,
    partnerHeading: String,
    dueDate: String,
    currencies: Array,   // upper-cased codes picked on the form; empty = all
    bankId: Number,
    banks: Array,        // [{ id, name }]
    kpis: Object,        // { count, perCurrency: [{ currency, total }] }
    paginator: Object,
    urls: Object,
});

const rows = computed(() => props.paginator?.data || []);
const rowOffset = computed(() => ((props.paginator?.current_page || 1) - 1) * (props.paginator?.per_page || 0));

const bank = ref(props.bankId ?? '');
function applyBank() {
    router.get(props.urls.result, {
        type: props.type,
        due_date: props.dueDate,
        currencies: props.currencies,
        bank_id: bank.value || undefined,
    }, { preserveScroll: true });
}

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* Same meaning the app gives every status: done = green, waiting = amber,
   bounced = red. */
function statusClass(status) {
    if (status === 'collected' || status === 'paid') return 'cvr-badge-active';
    if (status === 'rejected') return 'cvr-badge-overdue';
    return 'cvr-badge-pending';
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="urls.index" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                {{ $t('← Back to Cheques Report') }}
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    {{ $t('Cheques Report') }}
                    <span class="cvr-text-secondary font-normal">— {{ typeLabel }} · {{ $t('Due Date From') }} {{ dueDate }}</span>
                </h1>
                <div class="flex items-center gap-2">
                    <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        {{ $t('⬇️ Export to Excel') }}
                    </a>
                    <a :href="urls.printUrl" target="_blank" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        {{ $t('🖨️ Print') }}
                    </a>
                </div>
            </div>
            <p class="text-sm cvr-text-muted mb-6">
                {{ kpis.count }} {{ $t('Cheques') }}
                <span v-if="currencies.length"> · {{ currencies.join(', ') }}</span>
                <span v-else> · {{ $t('All currencies') }}</span>
            </p>

            <!-- KPI row: one total per currency - they cannot be added together -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">🧾</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Cheques') }}</p>
                        <p class="cvr-kpi-value cvr-num">{{ kpis.count }}</p>
                    </div>
                </div>
                <div v-for="total in kpis.perCurrency" :key="total.currency" class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">💰</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total') }} · {{ total.currency }}</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(total.total) }}</p>
                    </div>
                </div>
            </div>

            <!-- Bank filter -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-3 mb-4 flex items-end gap-3 flex-wrap">
                <div class="min-w-[16rem]">
                    <label class="cvr-form-label">{{ $t('Bank') }}</label>
                    <select v-model="bank" @change="applyBank" class="cvr-input w-full px-3 py-2 rounded">
                        <option value="">{{ $t('All banks') }}</option>
                        <option v-for="b in banks" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <div class="overflow-auto cvr-table-scroll" style="max-height: 150vh;">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3 text-start">{{ partnerHeading }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Cheque Number') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Bank Name') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Amount') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Currency') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Due Date') }}</th>
                                <th class="px-3 py-3 text-center">{{ $t('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ rowOffset + index + 1 }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-primary">{{ row.partnerName }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.chequeNumber }}</td>
                                <td class="px-3 py-2.5 text-start cvr-text-secondary">{{ row.bankName }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.amount) }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.currency }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary whitespace-nowrap">{{ row.dueDate }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="cvr-badge" :class="statusClass(row.status)">{{ row.statusLabel }}</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No cheques found for these filters.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <Pagination :paginator="paginator" label="cheques" />
        </div>
    </AppLayout>
</template>
