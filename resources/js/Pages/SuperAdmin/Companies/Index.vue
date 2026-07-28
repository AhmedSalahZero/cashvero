<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    paginator: Object,
    search: String,
    indexUrl: String,
    createUrl: String,
    removeUrl: String,
});

const rows = computed(() => props.paginator?.data || []);

/* Search runs server-side so it covers every company, not just the page
   currently rendered. Debounced so typing does not fire a request per key. */
const searchTerm = ref(props.search || '');
let searchTimer = null;
watch(searchTerm, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(props.indexUrl, { search: value }, { preserveState: true, replace: true });
    }, 350);
});

const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
const deleting = ref(false);
function destroyRow() {
    deleting.value = true;
    // ⚠️ Matches the original exactly: the real delete mechanism is
    // RemoveCompanyController (AJAX POST with company_id), not
    // CompanyController::destroy() — that method is genuinely unused
    // in the original app.
    router.post(props.removeUrl, { company_id: deleteTarget.value.id }, {
        onFinish: () => { deleting.value = false; deleteTarget.value = null; },
    });
}

function removeImage(row) {
    if (!row.remove_image_url) return;
    router.get(row.remove_image_url);
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-xl font-semibold cvr-text-primary">Companies Table</h1>
                <div class="flex items-center gap-3">
                    <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                        <span class="cvr-text-muted text-sm">🔍</span>
                        <input v-model="searchTerm" type="text" placeholder="Search companies..." class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                    </div>
                    <Link :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                        + Add
                    </Link>
                </div>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-left">Company</th>
                            <th class="px-3 py-3 text-left">Company Name</th>
                            <th class="px-3 py-3 text-left">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-3">
                                <img v-if="row.image_url" :src="row.image_url" alt="image" class="w-20 h-20 object-cover rounded" />
                                <button v-if="row.remove_image_url" @click="removeImage(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs mt-2">
                                    Delete Image
                                </button>
                            </td>
                            <td class="px-3 py-3 cvr-text-primary">{{ row.name }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1.5">
                                    <Link :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">Edit</Link>
                                    <button @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="3" class="px-4 py-8 text-center cvr-text-muted">No companies found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="paginator" label="companies" />

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Are you sure you want to delete this company?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">Cancel</button>
                        <button @click="destroyRow" :disabled="deleting" class="cvr-btn-danger px-3 py-1.5 rounded border">
                            {{ deleting ? 'Deleting...' : 'Confirm Delete' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
