<script setup>
/**
 * Shared page for simple, name-only master-data lists — Business
 * Sectors, Business Units, Sales Channels, Sales Persons, Deductions,
 * and (via the extraFields prop) Subsidiary Companies. One component,
 * used by six different controllers via Inertia::render(), instead of
 * five/six near-identical hand-copied pages.
 *
 * No separate create/edit pages: adding is an inline row at the top,
 * editing opens a small modal (just the fields), deleting opens the
 * existing confirm-delete modal pattern. This matches how small these
 * entities actually are — a single name (plus, for Subsidiary
 * Companies, two Odoo account-number fields) doesn't need a full page.
 */
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    title: String,
    subtitle: String,
    itemLabel: String, // e.g. "Business Sector" — used in placeholders/modal titles
    items: Array, // [{ id, name, ...extraFieldValues, update_url, delete_url }]
    createUrl: String,
    extraFields: {
        type: Array,
        default: () => [],
        // each: { key, label, placeholder }
    },
});

function blankExtra() {
    const obj = {};
    for (const f of props.extraFields) obj[f.key] = '';
    return obj;
}

// Picks the right ready-made grid layout based on how many extra
// fields this list has, so "Name + N extra fields" always lands on
// one line instead of wrapping:
//  - 0 extra fields (most lists): no grid, Name is full width
//  - 1 extra field: Name wide, one narrower field beside it
//  - 2 extra fields (Subsidiary Companies): Name wide, two narrower
//    fields beside it — this is the one that was missing before,
//    causing the third field to drop to its own line.
const gridClass = computed(() => {
    if (props.extraFields.length === 0) return '';
    if (props.extraFields.length === 1) return 'cvr-form-grid-8-4';
    return 'cvr-form-grid-6-3-3';
});

/* ── Inline "Add" row ─────────────────────────────────────────────── */
const newName = ref('');
const newExtra = ref(blankExtra());
const addSubmitting = ref(false);
const addError = ref('');

function submitAdd() {
    addSubmitting.value = true;
    addError.value = '';
    router.post(props.createUrl, { id: 0, company_id: props.company.id, name: newName.value, ...newExtra.value }, {
        preserveScroll: true,
        onSuccess: () => {
            newName.value = '';
            newExtra.value = blankExtra();
        },
        onError: (errors) => {
            addError.value = errors.name || Object.values(errors)[0] || 'Something went wrong.';
        },
        onFinish: () => { addSubmitting.value = false; },
    });
}

/* ── Search (client-side — these lists are small) ─────────────────── */
const search = ref('');
const filteredItems = computed(() => {
    if (!search.value) return props.items;
    const q = search.value.toLowerCase();
    return props.items.filter(i => i.name.toLowerCase().includes(q));
});

/* ── Edit modal ───────────────────────────────────────────────────── */
const editTarget = ref(null);
const editName = ref('');
const editExtra = ref({});
const editSubmitting = ref(false);
const editError = ref('');

function openEdit(row) {
    editTarget.value = row;
    editName.value = row.name;
    const obj = {};
    for (const f of props.extraFields) obj[f.key] = row[f.key] ?? '';
    editExtra.value = obj;
    editError.value = '';
}
function cancelEdit() {
    editTarget.value = null;
}
function submitEdit() {
    editSubmitting.value = true;
    editError.value = '';
    router.put(editTarget.value.update_url, { id: editTarget.value.id, company_id: props.company.id, name: editName.value, ...editExtra.value }, {
        preserveScroll: true,
        onSuccess: () => { editTarget.value = null; },
        onError: (errors) => {
            editError.value = errors.name || Object.values(errors)[0] || 'Something went wrong.';
        },
        onFinish: () => { editSubmitting.value = false; },
    });
}

/* ── Delete confirmation ─────────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function cancelDelete() { deleteTarget.value = null; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, {
        preserveScroll: true,
        onFinish: () => { deleteTarget.value = null; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-5xl mx-auto">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ title }}</h1>
            <p class="text-sm cvr-text-muted mb-6">{{ subtitle }}</p>

            <!-- KPI -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">🗂️</div>
                    <div>
                        <p class="cvr-kpi-label">Total {{ title }}</p>
                        <p class="cvr-kpi-value">{{ items.length }}</p>
                    </div>
                </div>
            </div>

            <!-- Inline Add -->
            <div class="cvr-card mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-base">➕</span>
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">Add {{ itemLabel }}</h2>
                </div>
                <div :class="gridClass" class="items-end">
                    <div>
                        <label class="cvr-form-label">Name <span class="text-red-500">*</span></label>
                        <input
                            v-model="newName"
                            type="text"
                            :placeholder="`e.g. New ${itemLabel}`"
                            class="cvr-input w-full px-3 py-2 rounded-lg text-sm"
                            @keyup.enter="newName.trim() && submitAdd()"
                        />
                    </div>
                    <div v-for="f in extraFields" :key="f.key">
                        <label class="cvr-form-label">{{ f.label }}</label>
                        <input
                            v-model="newExtra[f.key]"
                            type="text"
                            :placeholder="f.placeholder"
                            class="cvr-input w-full px-3 py-2 rounded-lg text-sm"
                        />
                    </div>
                </div>
                <p v-if="addError" class="text-xs mt-2" style="color: var(--cvr-danger-text);">{{ addError }}</p>
                <div class="flex justify-end mt-3">
                    <button
                        @click="submitAdd"
                        :disabled="addSubmitting || !newName.trim()"
                        class="cvr-btn-copper px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-60"
                    >
                        {{ addSubmitting ? 'Adding…' : `+ Add ${itemLabel}` }}
                    </button>
                </div>
            </div>

            <!-- Search -->
            <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 mb-4 w-72">
                <span class="cvr-text-muted text-sm">🔍</span>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name..."
                    class="bg-transparent outline-none text-sm w-full cvr-text-primary"
                />
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th v-for="f in extraFields" :key="f.key" class="px-4 py-3 text-left">{{ f.label }}</th>
                            <th class="px-4 py-3 text-center">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in filteredItems" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 cvr-text-primary">
                                <span class="block truncate max-w-[280px]" :title="row.name">{{ row.name }}</span>
                            </td>
                            <td v-for="f in extraFields" :key="f.key" class="px-4 py-3 cvr-text-secondary">
                                {{ row[f.key] || '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="openEdit(row)" class="cvr-action-btn" title="Edit">✎</button>
                                    <button @click="confirmDelete(row)" class="cvr-action-btn cvr-action-btn-danger" title="Delete">🗑</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredItems.length === 0">
                            <td :colspan="2 + extraFields.length" class="px-4 py-8 text-center cvr-text-muted">No {{ title.toLowerCase() }} found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Edit modal -->
            <div v-if="editTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Edit {{ itemLabel }}</h2>
                    <div class="mb-3">
                        <label class="cvr-form-label">Name <span class="text-red-500">*</span></label>
                        <input v-model="editName" type="text" class="cvr-input w-full px-3 py-2 rounded-lg text-sm" @keyup.enter="submitEdit" />
                    </div>
                    <div v-for="f in extraFields" :key="f.key" class="mb-3">
                        <label class="cvr-form-label">{{ f.label }}</label>
                        <input v-model="editExtra[f.key]" type="text" :placeholder="f.placeholder" class="cvr-input w-full px-3 py-2 rounded-lg text-sm" />
                    </div>
                    <p v-if="editError" class="text-xs mb-3" style="color: var(--cvr-danger-text);">{{ editError }}</p>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelEdit" class="cvr-btn-secondary px-3 py-1.5 rounded border">Cancel</button>
                        <button @click="submitEdit" :disabled="editSubmitting || !editName.trim()" class="cvr-btn-copper px-3 py-1.5 rounded disabled:opacity-60">
                            {{ editSubmitting ? 'Saving…' : 'Save' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Delete "{{ deleteTarget.name }}"?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>