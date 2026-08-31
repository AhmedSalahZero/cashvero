<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    financialInstitution: Object,
    canCreate: Boolean,
    createUrl: String,
    rows: Array,
    canUpload: Boolean,
    canUpdate: Boolean,
    canDelete: Boolean,
    backUrl: String,
    navUrls: Object,
});

const search = ref('');
const filteredRows = computed(() => {
    if (!search.value) return props.rows;
    const q = search.value.toLowerCase();
    return props.rows.filter(r => (r.name || '').toLowerCase().includes(q));
});

const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="px-6 pt-4">
            <Link v-if="instructionsUrl" :href="instructionsUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('📖 Instructions') }}
            </Link>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Banks') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Medium Term Loan') }}</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ financialInstitution.name }}</p>

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" :placeholder="$t('Search by name...')" class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                    {{ $t('+ New Loan') }}
                </Link>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-start">#</th>
                            <th class="px-3 py-3 text-start">{{ $t('Name') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Start Date') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('End Date') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Currency') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Limit') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Account Number') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Borrowing Rate') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Margin Rate') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Duration') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Installment Interval') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-3 py-3 cvr-text-primary whitespace-nowrap">{{ row.name }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.start_date_formatted }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.end_date_formatted }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.currency_formatted }}</td>
                            <td class="px-3 py-3 cvr-num">{{ row.limit_formatted }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.account_number }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.borrowing_rate_formatted }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.margin_rate_formatted }}</td>
                            <td class="px-3 py-3 uppercase cvr-text-secondary">{{ row.duration_formatted }}</td>
                            <td class="px-3 py-3 capitalize cvr-text-secondary">{{ row.installment_interval_formatted }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <RecordLogButton subject="MediumTermLoan" :id="row.id" :company-id="company.id" />
                                    <a v-if="canUpload" :href="row.upload_schedule_url" class="cvr-action-btn" :title="$t('Upload Loan Schedule & Apply Payments')">📤💵</a>
                                    <Link :href="row.statement_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs" :title="$t('Interest & principle: due vs paid, plus the drawdown ledger')">{{ $t('Statement') }}</Link>
                                    <Link v-if="canUpdate" :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">{{ $t('Edit') }}</Link>
                                    <button v-if="canDelete" @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">{{ $t('Delete') }}</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="12" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No Medium Term Loan records found.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Do you want to delete this item?') }}</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
