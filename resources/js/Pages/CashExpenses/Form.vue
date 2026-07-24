<script setup>
import { ref, computed } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    mode: String, // 'create' | 'edit'
    company: Object,
    companyHasOdoo: Boolean,
    submitUrl: String,
    backUrl: String,
    category: Object, // present only in edit mode
});

const page = usePage();

const name = ref(props.category?.name ?? '');

let nextRowId = 1;
function blankItemRow(existing = null) {
    return {
        _rowId: nextRowId++,
        id: existing?.id ?? 0,
        name: existing?.name ?? '',
        odoo_chart_of_account_number: existing?.odoo_chart_of_account_number ?? '',
    };
}

const items = ref(
    props.category?.items?.length
        ? props.category.items.map(i => blankItemRow(i))
        : [blankItemRow()]
);

function addItemRow() {
    items.value.push(blankItemRow());
}
function removeItemRow(rowId) {
    if (items.value.length <= 1) return;
    items.value = items.value.filter(r => r._rowId !== rowId);
}

/*
 * Server validation errors for the Odoo code come back as a single
 * message keyed 'odoo_chart_of_account_number' (not per-row indexed —
 * the backend rule checks every row's code against Odoo in one pass
 * and lists every failing code in one message). Shown in the general
 * error banner below rather than per-row, matching what the server
 * actually returns.
 */

const initials = computed(() => {
    const n = (name.value || '?').trim();
    return n ? n.slice(0, 2).toUpperCase() : '?';
});

const submitting = ref(false);

function submit() {
    submitting.value = true;
    const payload = {
        company_id: props.company.id,
        name: name.value,
        cashExpenseCategoryNames: items.value.map(r => {
            const row = { id: r.id, name: r.name };
            if (props.companyHasOdoo) {
                row.odoo_chart_of_account_number = r.odoo_chart_of_account_number;
            }
            return row;
        }),
    };
    const method = props.mode === 'edit' ? 'put' : 'post';
    router[method](props.submitUrl, payload, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-3xl mx-auto">
            <Link :href="backUrl" class="cvr-back-link inline-flex items-center gap-1 text-xs cvr-text-muted mb-4">
                ← Back to Cash Expense Categories
            </Link>

            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <div class="cvr-avatar" style="width: 3rem; height: 3rem; font-size: 1rem;">{{ initials }}</div>
                <div>
                    <h1 class="text-xl font-semibold cvr-text-primary">
                        {{ mode === 'edit' ? 'Edit Expense Category' : 'Add Expense Category' }}
                    </h1>
                    <p class="text-sm cvr-text-muted">
                        {{ mode === 'edit' ? 'Update this category and its expense item names' : 'Create a category and its expense item names' }}
                    </p>
                </div>
            </div>

            <!-- Validation errors -->
            <div
                v-if="Object.keys(page.props.errors || {}).length"
                class="mb-5 px-4 py-3 rounded-lg text-sm flex items-start gap-2"
                style="background: var(--cvr-danger-bg); border: 1px solid var(--cvr-danger-border); color: var(--cvr-danger-text);"
            >
                <span class="text-base leading-none">⚠</span>
                <div>
                    <p v-for="(msg, field) in page.props.errors" :key="field">{{ msg }}</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="cvr-card">
                <!-- Section: Category Name -->
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-base">🗂️</span>
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">Category Information</h2>
                </div>

                <div class="mb-2">
                    <label class="cvr-form-label">
                        Category Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="name"
                        type="text"
                        required
                        placeholder="e.g. Utilities"
                        class="cvr-input w-full px-3 py-2.5 rounded-lg text-sm"
                    />
                </div>

                <hr class="cvr-divider my-6" />

                <!-- Section: Expense Item Names -->
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2">
                        <span class="text-base">💳</span>
                        <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">Expense Item Names</h2>
                    </div>
                </div>
                <p class="text-xs cvr-text-muted mb-4">
                    These are the actual items chosen when logging a cash expense.
                    <template v-if="companyHasOdoo">Each one maps to an Odoo Chart Of Account Number.</template>
                </p>

                <div class="space-y-3">
                    <div
                        v-for="row in items"
                        :key="row._rowId"
                        class="cvr-card-bg cvr-border border rounded-lg p-3"
                    >
                        <div :class="companyHasOdoo ? 'cvr-form-grid-8-4' : ''" class="items-end">
                            <div>
                                <label class="cvr-form-label">Name <span class="text-red-500">*</span></label>
                                <input
                                    v-model="row.name"
                                    type="text"
                                    required
                                    placeholder="e.g. Electricity"
                                    class="cvr-input w-full px-3 py-2 rounded-lg text-sm"
                                />
                            </div>
                            <!-- Odoo Chart Of Account Number — ONLY shown when the
                                 company has Odoo integration credentials, exactly
                                 as in the original Blade form. -->
                            <div v-if="companyHasOdoo">
                                <label class="cvr-form-label">Odoo Chart Of Account Number <span class="text-red-500">*</span></label>
                                <input
                                    v-model="row.odoo_chart_of_account_number"
                                    type="number"
                                    required
                                    placeholder="e.g. 400012"
                                    class="cvr-input w-full px-3 py-2 rounded-lg text-sm"
                                />
                            </div>
                        </div>
                        <div class="flex justify-end mt-3" v-if="items.length > 1">
                            <button type="button" @click="removeItemRow(row._rowId)" class="cvr-btn-remove-row">
                                🗑 Remove Item
                            </button>
                        </div>
                    </div>
                </div>

                <button type="button" @click="addItemRow" class="cvr-btn-secondary mt-3 px-3 py-1.5 rounded-lg border text-sm inline-flex items-center gap-1">
                    + Add Item
                </button>

                <hr class="cvr-divider my-6" />

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded-lg border text-sm">Cancel</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-copper px-5 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                        {{ submitting ? 'Saving…' : (mode === 'edit' ? 'Save Changes' : 'Create Category') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.cvr-back-link {
    transition: var(--cvr-transition);
}
.cvr-back-link:hover {
    color: var(--cvr-text-primary);
}
</style>
