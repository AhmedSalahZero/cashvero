<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

/*
 * BuyOrSellCurrencies/Index.vue
 * ------------------------------------------------------------------
 * One shared page, four tabs — Bank→Bank, Safe→Bank, Bank→Safe,
 * Safe→Safe — all driven by the same `type` field on the model, same
 * as the old page. Which columns show depends on the type: a "bank"
 * leg needs Bank/Account Type/Account Number, a "safe" leg needs just
 * a Branch. See columnsByType below.
 *
 * All four tabs' first page arrive together (each already its own
 * real SQL-paginated query — see controller docblock), so switching
 * tabs is instant/client-side, same as the old page. Search and the
 * date-range filter are scoped to whichever tab is active when you
 * submit them (matching the old page's per-tab filter forms), so
 * submitting either includes `active` in the request.
 */

const props = defineProps({
    company: Object,
    activeTab: String,
    allTypes: Object, // {type: label}
    tabs: Object, // {type: {label, rows: paginator, startDate, endDate}}
    searchValue: String,
    canCreate: Boolean,
    canUpdate: Boolean,
    canDelete: Boolean,
    indexUrl: String,
    createUrl: String,
});

const activeTab = ref(props.activeTab);
function switchTab(type) {
    activeTab.value = type;
}

const columnsByType = {
    'bank-to-bank': {
        from: ['from_bank_name', 'from_account_type_name', 'from_account_number'],
        to: ['to_bank_name', 'to_account_type_name', 'to_account_number'],
    },
    'safe-to-bank': {
        from: ['from_branch_name'],
        to: ['to_bank_name', 'to_account_type_name', 'to_account_number'],
    },
    'bank-to-safe': {
        from: ['from_bank_name', 'from_account_type_name', 'from_account_number'],
        to: ['to_branch_name'],
    },
    'safe-to-safe': {
        from: ['from_branch_name'],
        to: ['to_branch_name'],
    },
};
const columnLabels = {
    from_bank_name: 'From Bank',
    from_account_type_name: 'From Account Type',
    from_account_number: 'From Account Number',
    to_bank_name: 'To Bank',
    to_account_type_name: 'To Account Type',
    to_account_number: 'To Account Number',
    from_branch_name: 'From Branch',
    to_branch_name: 'To Branch',
};
function columnsFor(type) {
    return [...columnsByType[type].from, ...columnsByType[type].to];
}
// Bank names were being truncated because they came through as one
// combined "English + Arabic" string. The controller now sends the
// two names separately (from_bank_name_en/ar, to_bank_name_en/ar) so
// this page can stack them — English on top, Arabic underneath —
// instead of cutting text off.
const bankNameColumns = ['from_bank_name', 'to_bank_name'];

/* ── Per-tab search + date range ──────────────────────────────────
   Each tab keeps its own filter state locally; submitting either
   sends `active: <this tab>` plus every OTHER tab's currently-saved
   date range (so switching tabs doesn't silently reset them —
   matches the old page's per-type startDate[]/endDate[] arrays). */
const filters = ref(
    Object.fromEntries(Object.keys(props.tabs).map(type => [type, {
        startDate: props.tabs[type].startDate,
        endDate: props.tabs[type].endDate,
    }]))
);
const searchValue = ref(props.searchValue || '');

function applyFilters(type) {
    const startDate = {};
    const endDate = {};
    Object.keys(filters.value).forEach(t => {
        startDate[t] = filters.value[t].startDate;
        endDate[t] = filters.value[t].endDate;
    });
    router.get(props.indexUrl, {
        active: type,
        startDate,
        endDate,
        value: searchValue.value,
    }, { preserveState: true, preserveScroll: true });
}

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

/* ── Delete confirmation ─────────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(row) { deleteTarget.value = row; }
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}

/* ── User comment / Odoo references modals ───────────────────────── */
const commentTarget = ref(null);
const odooRefTarget = ref(null);
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                <h1 class="text-xl font-semibold cvr-text-primary">Sell Or Buy Currencies</h1>
                <Link v-if="canCreate" :href="createUrl" class="cvr-btn-copper px-3 py-1.5 rounded text-sm inline-flex items-center gap-1">
                    + Sell Or Buy Currencies
                </Link>
            </div>

            <!-- Tabs -->
            <div class="flex items-center gap-1 mb-4 flex-wrap">
                <button
                    v-for="(label, type) in allTypes"
                    :key="type"
                    @click="switchTab(type)"
                    class="cvr-filter-pill"
                    :class="{ 'cvr-filter-pill-active': activeTab === type }"
                >
                    {{ label }}
                </button>
            </div>

            <template v-for="(label, type) in allTypes" :key="type">
                <div v-show="activeTab === type">
                    <!-- Filters -->
                    <div class="flex flex-wrap items-end gap-3 mb-4">
                        <div>
                            <label class="cvr-form-label">Search (Account Number)</label>
                            <input v-model="searchValue" type="text" placeholder="Search..." class="cvr-input px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Start Date</label>
                            <input v-model="filters[type].startDate" type="date" class="cvr-input px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">End Date</label>
                            <input v-model="filters[type].endDate" type="date" class="cvr-input px-3 py-2 rounded" />
                        </div>
                        <button @click="applyFilters(type)" class="cvr-btn-secondary px-4 py-2 rounded border">Apply</button>
                    </div>

                    <!-- Table -->
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-3 py-3 text-left">#</th>
                                    <th class="px-3 py-3 text-left">Transaction Date</th>
                                    <th class="px-3 py-3 text-left">Amount To Sell</th>
                                    <th class="px-3 py-3 text-left">Currency To Sell</th>
                                    <th class="px-3 py-3 text-left">Exchange Rate</th>
                                    <th class="px-3 py-3 text-left">Amount To Buy</th>
                                    <th class="px-3 py-3 text-left">Currency To Buy</th>
                                    <th
                                        v-for="col in columnsFor(type)"
                                        :key="col"
                                        class="px-3 py-3 text-left whitespace-nowrap"
                                    >
                                        {{ columnLabels[col] }}
                                    </th>
                                    <th v-if="canUpdate || canDelete" class="px-3 py-3 text-left">Control</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in tabs[type].rows.data" :key="row.id" class="cvr-table-row">
                                    <td class="px-3 py-3 cvr-text-muted">{{ tabs[type].rows.from + index }}</td>
                                    <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.transaction_date_formatted }}</td>
                                    <td class="px-3 py-3 cvr-num">{{ row.amount_to_sell_formatted }}</td>
                                    <td class="px-3 py-3 cvr-text-primary">{{ row.currency_to_sell }}</td>
                                    <td class="px-3 py-3 cvr-num text-xs">
                                        {{ row.exchange_rate_formatted }}
                                        <div class="cvr-text-muted">{{ row.reciprocal_exchange_rate_formatted }}</div>
                                    </td>
                                    <td class="px-3 py-3 cvr-num">{{ row.amount_to_buy_formatted }}</td>
                                    <td class="px-3 py-3 cvr-text-primary">{{ row.currency_to_buy }}</td>
                                    <template v-for="col in columnsFor(type)" :key="col">
                                        <td v-if="bankNameColumns.includes(col)" class="px-3 py-3 cvr-text-secondary align-top">
                                            <div class="leading-tight">{{ row[col + '_en'] || row[col] }}</div>
                                            <div v-if="row[col + '_ar']" class="leading-tight text-xs cvr-text-muted" dir="rtl">{{ row[col + '_ar'] }}</div>
                                        </td>
                                        <td v-else class="px-3 py-3 cvr-text-secondary whitespace-nowrap">
                                            {{ row[col] }}
                                        </td>
                                    </template>
                                    <td v-if="canUpdate || canDelete" class="px-3 py-3">
                                        <div class="flex items-center gap-2">
                                            <button v-if="row.user_comment" @click="commentTarget = row" class="cvr-action-btn" title="User Comment">💬</button>
                                            <button v-if="row.is_fully_integrated_with_odoo" @click="odooRefTarget = row" class="cvr-action-btn" title="Fully Integrated">👍</button>
                                            <Link v-if="canUpdate" :href="row.edit_url" class="cvr-action-btn" title="Edit">✏️</Link>
                                            <button v-if="canDelete" @click="confirmDelete(row)" class="cvr-action-btn" title="Delete">🗑️</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="tabs[type].rows.data.length === 0">
                                    <td :colspan="8 + columnsFor(type).length" class="px-4 py-8 text-center cvr-text-muted">
                                        No {{ label.toLowerCase() }} transactions found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="tabs[type].rows.last_page > 1" class="flex items-center justify-between mt-4 flex-wrap gap-3">
                        <p class="text-xs cvr-text-muted">
                            Showing {{ tabs[type].rows.from }}–{{ tabs[type].rows.to }} of {{ tabs[type].rows.total }}
                        </p>
                        <div class="flex items-center gap-1 flex-wrap">
                            <button
                                v-for="(link, i) in tabs[type].rows.links"
                                :key="i"
                                @click="goToPage(link.url)"
                                :disabled="!link.url"
                                class="cvr-filter-pill"
                                :class="{ 'cvr-filter-pill-active': link.active, 'opacity-40 cursor-not-allowed': !link.url }"
                                v-html="link.label"
                            ></button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Do you want to delete this item?</h2>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyRow" class="cvr-btn-danger px-3 py-1.5 rounded border">Confirm Delete</button>
                    </div>
                </div>
            </div>

            <!-- User comment modal -->
            <div v-if="commentTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">User Comment</h2>
                    <p class="cvr-text-secondary whitespace-pre-wrap">{{ commentTarget.user_comment }}</p>
                    <div class="flex justify-end mt-4">
                        <button @click="commentTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                    </div>
                </div>
            </div>

            <!-- Odoo references modal -->
            <div v-if="odooRefTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Odoo References</h2>
                    <ul class="list-disc pl-5 cvr-text-secondary">
                        <li v-for="(ref, i) in odooRefTarget.odoo_reference_names" :key="i">{{ ref }}</li>
                    </ul>
                    <div class="flex justify-end mt-4">
                        <button @click="odooRefTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>