<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    financialInstitution: Object,
    timeOfDeposit: Object,
    rows: Array,
    backUrl: String,
    navUrls: Object,
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
                    ← Back to Time Of Deposit
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Period Interest Amounts</h1>
            <p class="text-sm cvr-text-muted mb-6">{{ financialInstitution.name }}</p>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Amount</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-text-secondary">{{ row.date }}</td>
                            <td class="px-4 py-3 cvr-num-green">{{ row.amount_formatted }} {{ timeOfDeposit.currency?.toUpperCase() }}</td>
                            <td class="px-4 py-3">
                                <button @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center cvr-text-muted">
                                No period interest postings yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        Delete Periodic Interest {{ deleteTarget.date }}?
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
