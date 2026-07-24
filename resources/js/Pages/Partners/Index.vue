<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    activeType: String,
    search: String,
    counts: Object,
    partners: Object, // Laravel paginator: { data, links, current_page, last_page, from, to, total }
    companyHasOdoo: Boolean,
    indexUrl: String,
    createUrl: String,
    permissions: Object,
});

/* ── Type filter pills ───────────────────────────────────────────
   NEW: the original Blade page had no way to filter by partner
   type at all — just a flat table with a check/cross column per
   type. Server-side (?type=...), since the list is now paginated
   and can't be filtered from an in-memory array anymore. */
const types = [
    { key: 'all', label: 'All' },
    { key: 'customers', label: 'Customers' },
    { key: 'suppliers', label: 'Suppliers' },
    { key: 'employees', label: 'Employees' },
    { key: 'shareholders', label: 'Shareholders' },
    { key: 'subsidiary-companies', label: 'Subsidiary Companies' },
    { key: 'other-partners', label: 'Other Partners' },
];

function goToType(typeKey) {
    router.get(props.indexUrl, { type: typeKey, search: search.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

/* ── Search — server-side, debounced ───────────────────────────────
   Partner lists can run into the hundreds/thousands, so this can no
   longer filter an in-memory array like the first version did —
   every keystroke (debounced) triggers a real paginated query. */
const search = ref(props.search || '');
let searchTimer = null;
watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(props.indexUrl, { type: props.activeType, search: value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 350);
});

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
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
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Partners</h1>
            <p class="text-sm cvr-text-muted mb-6">Customers, suppliers, employees, shareholders &amp; more</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">🤝</div>
                    <div>
                        <p class="cvr-kpi-label">Total Partners</p>
                        <p class="cvr-kpi-value">{{ counts.all }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">👥</div>
                    <div>
                        <p class="cvr-kpi-label">Customers</p>
                        <p class="cvr-kpi-value">{{ counts.customers }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">🚚</div>
                    <div>
                        <p class="cvr-kpi-label">Suppliers</p>
                        <p class="cvr-kpi-value">{{ counts.suppliers }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">🏢</div>
                    <div>
                        <p class="cvr-kpi-label">Subsidiary Companies</p>
                        <p class="cvr-kpi-value">{{ counts['subsidiary-companies'] }}</p>
                    </div>
                </div>
            </div>

            <!-- Type filter pills + New button -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="flex items-center gap-1 flex-wrap">
                    <button
                        v-for="t in types"
                        :key="t.key"
                        @click="goToType(t.key)"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': activeType === t.key }"
                    >
                        {{ t.label }} <span class="cvr-text-muted">({{ counts[t.key] }})</span>
                    </button>
                </div>

                <Link
                    v-if="!companyHasOdoo"
                    :href="createUrl"
                    class="cvr-btn-copper inline-flex items-center gap-1 px-3 py-1.5 rounded text-sm whitespace-nowrap"
                >
                    + New Partner
                </Link>
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

            <!-- Table — wrapped so it scrolls horizontally instead of clipping
                 columns (and the Edit/Delete actions with them) on narrow
                 viewports. This was a real bug in the first version. -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-center">Customer</th>
                            <th class="px-4 py-3 text-center">Supplier</th>
                            <th class="px-4 py-3 text-center">Subsidiary</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap">Other Partner</th>
                            <th class="px-4 py-3 text-center">Employee</th>
                            <th class="px-4 py-3 text-center">Shareholder</th>
                            <th v-if="permissions.update" class="px-4 py-3 text-center">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in partners.data" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ partners.from + i }}</td>
                            <!-- Long partner names no longer blow out the table —
                                 capped width + ellipsis, full name on hover. -->
                            <td class="px-4 py-3 text-left cvr-text-primary">
                                <span class="block truncate max-w-[260px]" :title="row.name">{{ row.name }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="row.is_customer ? 'text-emerald-500' : 'text-red-500'">{{ row.is_customer ? '✓' : '✕' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="row.is_supplier ? 'text-emerald-500' : 'text-red-500'">{{ row.is_supplier ? '✓' : '✕' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="row.is_subsidiary_company ? 'text-emerald-500' : 'text-red-500'">{{ row.is_subsidiary_company ? '✓' : '✕' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="row.is_other_partner ? 'text-emerald-500' : 'text-red-500'">{{ row.is_other_partner ? '✓' : '✕' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="row.is_employee ? 'text-emerald-500' : 'text-red-500'">{{ row.is_employee ? '✓' : '✕' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="row.is_shareholder ? 'text-emerald-500' : 'text-red-500'">{{ row.is_shareholder ? '✓' : '✕' }}</span>
                            </td>
                            <td v-if="permissions.update" class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <Link :href="row.edit_url" class="cvr-action-btn" title="Edit">✎</Link>
                                    <button v-if="permissions.delete" @click="confirmDelete(row)" class="cvr-action-btn cvr-action-btn-danger" title="Delete">🗑</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="partners.data.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center cvr-text-muted">No partners found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="partners.last_page > 1" class="flex items-center justify-between mt-4 flex-wrap gap-3">
                <p class="text-xs cvr-text-muted">
                    Showing {{ partners.from }}–{{ partners.to }} of {{ partners.total }} partners
                </p>
                <div class="flex items-center gap-1 flex-wrap">
                    <button
                        v-for="(link, i) in partners.links"
                        :key="i"
                        @click="goToPage(link.url)"
                        :disabled="!link.url"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': link.active, 'opacity-40 cursor-not-allowed': !link.url }"
                        v-html="link.label"
                    ></button>
                </div>
            </div>

            <!-- Delete confirmation -->
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
