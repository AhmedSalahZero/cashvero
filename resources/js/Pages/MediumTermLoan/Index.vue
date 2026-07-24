<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
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
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    ← Back to Banks
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Medium Term Loan</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ financialInstitution.name }}</p>

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" placeholder="Search by name..." class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                    + New Loan
                </Link>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-left">#</th>
                            <th class="px-3 py-3 text-left">Name</th>
                            <th class="px-3 py-3 text-left">Start Date</th>
                            <th class="px-3 py-3 text-left">End Date</th>
                            <th class="px-3 py-3 text-left">Currency</th>
                            <th class="px-3 py-3 text-left">Limit</th>
                            <th class="px-3 py-3 text-left">Account Number</th>
                            <th class="px-3 py-3 text-left">Borrowing Rate</th>
                            <th class="px-3 py-3 text-left">Margin Rate</th>
                            <th class="px-3 py-3 text-left">Duration</th>
                            <th class="px-3 py-3 text-left">Installment Interval</th>
                            <th class="px-3 py-3 text-left">Actions</th>
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
                                    <a v-if="canUpload" :href="row.upload_schedule_url" class="cvr-action-btn" title="Upload Loan Schedule & Apply Payments">📤💵</a>
                                    <Link v-if="canUpdate" :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">Edit</Link>
                                    <button v-if="canDelete" @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="12" class="px-4 py-8 text-center cvr-text-muted">
                                No Medium Term Loan records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to delete this item?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
