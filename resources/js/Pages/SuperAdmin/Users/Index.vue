<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/composables/usePermissions';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    company: Object, // null if not scoped to a company
    createUrl: String,
    paginator: Object,
    search: String,
    indexUrl: String,
    removeUrl: String,
});

const { can } = usePermissions();

const rows = computed(() => props.paginator?.data || []);

/* Server-side search so it spans every user the current admin may see,
   not just the page on screen. Debounced to one request per pause. */
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
    router.post(props.removeUrl, { user_id: deleteTarget.value.id }, {
        onFinish: () => { deleting.value = false; deleteTarget.value = null; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-xl font-semibold cvr-text-primary">Users Table</h1>
                <div class="flex items-center gap-3">
                    <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-64">
                        <span class="cvr-text-muted text-sm">🔍</span>
                        <input v-model="searchTerm" type="text" placeholder="Search users..." class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                    </div>
                    <Link v-if="can('user.create')" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                        + Add
                    </Link>
                </div>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-left">Avatar</th>
                            <th class="px-3 py-3 text-left">Name</th>
                            <th class="px-3 py-3 text-left">Role</th>
                            <th class="px-3 py-3 text-left">Companies</th>
                            <th class="px-3 py-3 text-left">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-3">
                                <img :src="row.avatar_url || '/images/user.png'" alt="avatar" class="w-16 h-16 object-cover rounded-full" />
                            </td>
                            <td class="px-3 py-3 cvr-text-primary">{{ row.name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary capitalize">{{ row.role_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.companies.join(', ') }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1.5">
                                    <Link v-if="can('user.update')" :href="row.edit_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs">Edit</Link>
                                    <Link v-if="can('user.assign_roles')" :href="row.permissions_url" class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs" title="Permissions">👁</Link>
                                    <button v-if="can('user.delete')" @click="confirmDelete(row)" class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center cvr-text-muted">No users found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="paginator" label="users" />

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Are you sure you want to delete this user?</h2>
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
