<script setup>
/**
 * InvoiceUpload/Failed.vue
 * ------------------------------------------------------------------
 * Served by SalesGatheringTestController@lastUploadFailed — shows
 * which fields in the last upload failed validation and why.
 * Reached from the "View last upload's failed rows" link on
 * InvoiceUpload/Import.vue.
 */
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    modelName: String,
    modelDisplayName: String,
    headers: Array,
    rows: Array, // [{ rowNumber, cells: [{ failed, message, value }] }]
    backUrl: String,
});
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                ← Back to {{ modelDisplayName }} Import
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ modelDisplayName }} — Failed Rows</h1>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-2 text-center">{{ $t('Row Number') }}</th>
                            <th v-for="h in headers" :key="h" class="px-3 py-2 text-center">{{ h }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.rowNumber" class="cvr-table-row">
                            <td class="px-3 py-2 text-center font-medium cvr-text-primary">{{ row.rowNumber }}</td>
                            <td v-for="(cell, ci) in row.cells" :key="ci" class="px-3 py-2 text-center" :class="cell.failed ? 'cvr-num-red' : 'cvr-text-muted'">
                                <span v-if="cell.failed">{{ cell.message }} [ {{ cell.value }} ]</span>
                                <span v-else>—</span>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td :colspan="headers.length + 1" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No failed rows found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
