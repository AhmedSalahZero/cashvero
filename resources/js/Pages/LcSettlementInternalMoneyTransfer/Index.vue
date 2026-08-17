<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';

const props = defineProps({
    company: Object,
    transfers: Object, // Laravel paginator: { data: [...], links: [...], current_page, last_page }
    filterDates: Object, // { startDate, endDate }
    canCreate: Boolean,
    canUpdate: Boolean,
    canDelete: Boolean,
    urls: Object,
});

const rows = computed(() => props.transfers?.data || []);

const startDate = ref(props.filterDates?.startDate || '');
const endDate = ref(props.filterDates?.endDate || '');

function applyDateFilter() {
    router.get(props.urls.index, { startDate: startDate.value, endDate: endDate.value }, { preserveScroll: true });
}

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveScroll: true });
}

/* ── Delete confirmation ──────────────────────────────────────── */
const deleteTarget = ref(null);
function destroyRow() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-7xl mx-auto">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">LC Settlement Internal Money Transfer</h1>
            <p class="text-sm cvr-text-muted mb-6">Bank to Letter of Credit Transfer Table</p>

            <!-- Filter bar -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-4 mb-5 flex flex-wrap items-end gap-3">
                <div>
                    <label class="cvr-form-label">From</label>
                    <input v-model="startDate" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">To</label>
                    <input v-model="endDate" type="date" class="cvr-input px-3 py-2 rounded" />
                </div>
                <button @click="applyDateFilter" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">Filter</button>
                <div class="flex-1"></div>
                <Link v-if="canCreate" :href="urls.create" class="cvr-btn-primary px-4 py-2 rounded text-sm">
                    + Bank To Letter Of Credit
                </Link>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Amount</th>
                            <th class="px-4 py-3 text-left">Currency</th>
                            <th class="px-4 py-3 text-left">From Bank</th>
                            <th class="px-4 py-3 text-left">From Account Type</th>
                            <th class="px-4 py-3 text-left">From Account Number</th>
                            <th class="px-4 py-3 text-left">To Lc Issuance</th>
                            <th v-if="canUpdate || canDelete" class="px-4 py-3 text-left">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3">{{ (transfers.current_page - 1) * transfers.per_page + index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ row.transfer_date_formatted }}</td>
                            <td class="px-4 py-3 cvr-num">{{ row.amount_formatted }}</td>
                            <td class="px-4 py-3 uppercase">{{ row.currency }}</td>
                            <td class="px-4 py-3">{{ row.from_bank_name }}</td>
                            <td class="px-4 py-3 uppercase">{{ row.from_account_type_name }}</td>
                            <td class="px-4 py-3">{{ row.from_account_number }}</td>
                            <td class="px-4 py-3">{{ row.to_lc_issuance_name }}</td>
                            <td v-if="canUpdate || canDelete" class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <RecordLogButton subject="LcSettlementInternalMoneyTransfer" :id="row.id" :company-id="company.id" />
                                    <Link v-if="canUpdate" :href="row.edit_url" class="cvr-action-btn" title="Edit">✏️</Link>
                                    <button v-if="canDelete" @click="deleteTarget = row" class="cvr-action-btn-danger cvr-action-btn" title="Delete">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center cvr-text-muted">No records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="transfers?.last_page > 1" class="flex items-center justify-center gap-1 mt-4">
                <button
                    v-for="(link, i) in transfers.links"
                    :key="i"
                    v-html="link.label"
                    :disabled="!link.url"
                    @click="goToPage(link.url)"
                    class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm"
                    :class="{ 'cvr-nav-item-active': link.active, 'opacity-40 cursor-not-allowed': !link.url }"
                ></button>
            </div>

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
        </div>
    </AppLayout>
</template>
