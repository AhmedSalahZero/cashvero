<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';

const props = defineProps({
    company: Object,
    categories: Array,
    createUrl: String,
    // { canCreate, canUpdate, canDelete }
    permissions: { type: Object, default: () => ({}) },
});

/* ── Search (client-side) — matches category name or any of its item
   names. This list is a settings/taxonomy list (categories, not
   per-customer records), so it doesn't have the same scale concern
   Partners did; kept simple and client-side. */
const search = ref('');
const filteredCategories = computed(() => {
    if (!search.value) return props.categories;
    const q = search.value.toLowerCase();
    return props.categories.filter(c =>
        c.name.toLowerCase().includes(q) ||
        c.items.some(i => i.name.toLowerCase().includes(q))
    );
});

const totalItems = computed(() => props.categories.reduce((sum, c) => sum + c.items.length, 0));

/* ── Expand/collapse per category ─────────────────────────────────── */
const openIds = ref(new Set());
function toggle(id) {
    const next = new Set(openIds.value);
    next.has(id) ? next.delete(id) : next.add(id);
    openIds.value = next;
}

/* ── Delete confirmation ─────────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, {
        onFinish: () => { deleteTarget.value = null; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Cash Expense Categories</h1>
            <p class="text-sm cvr-text-muted mb-6">Categories and their expense item names, used when logging cash expenses</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">🗂️</div>
                    <div>
                        <p class="cvr-kpi-label">Categories</p>
                        <p class="cvr-kpi-value">{{ categories.length }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">💳</div>
                    <div>
                        <p class="cvr-kpi-label">Expense Items</p>
                        <p class="cvr-kpi-value">{{ totalItems }}</p>
                    </div>
                </div>
            </div>

            <!-- Search + New button -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-72">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search category or item name..."
                        class="bg-transparent outline-none text-sm w-full cvr-text-primary"
                    />
                </div>

                <Link v-if="permissions.canCreate" :href="createUrl" class="cvr-btn-copper inline-flex items-center gap-1 px-3 py-1.5 rounded text-sm whitespace-nowrap">
                    + New Category
                </Link>
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-center">Items</th>
                            <th class="px-4 py-3 text-center">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="category in filteredCategories" :key="category.id">
                            <tr
                                class="cvr-table-row"
                                :style="category.items.length ? 'cursor:pointer' : ''"
                                @click="category.items.length && toggle(category.id)"
                            >
                                <td class="px-4 py-3 cvr-text-primary">
                                    <div class="flex items-center gap-2">
                                        <span v-if="category.items.length" class="text-xs cvr-text-muted">
                                            {{ openIds.has(category.id) ? '▾' : '▸' }}
                                        </span>
                                        <span class="block truncate max-w-[320px]" :title="category.name">{{ category.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="cvr-tag">{{ category.items.length }}</span>
                                </td>
                                <td class="px-4 py-3 text-center" @click.stop>
                                    <div class="flex items-center justify-center gap-2">
                                        <RecordLogButton subject="CashExpenseCategory" :id="category.id" :company-id="company.id" />
                                        <Link v-if="permissions.canUpdate" :href="category.edit_url" class="cvr-action-btn" title="Edit">✎</Link>
                                        <button v-if="permissions.canDelete" @click="confirmDelete(category)" class="cvr-action-btn cvr-action-btn-danger" title="Delete">🗑</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="openIds.has(category.id)" class="cvr-table-row">
                                <td colspan="3" class="px-4 py-3 cvr-card-bg">
                                    <ul class="pl-6 space-y-1">
                                        <li v-for="item in category.items" :key="item.id" class="text-sm cvr-text-secondary flex items-center gap-2">
                                            <span class="cvr-text-muted">•</span>
                                            <span class="truncate max-w-[400px]" :title="item.name">{{ item.name }}</span>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="filteredCategories.length === 0">
                            <td colspan="3" class="px-4 py-8 text-center cvr-text-muted">No expense categories found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-2">Delete "{{ deleteTarget.name }}"?</h2>
                    <p class="text-sm cvr-text-muted mb-4">This will also remove its {{ deleteTarget.items.length }} expense item(s).</p>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
