<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    rows: Array,
});

const search = ref('');
const filteredRows = computed(() => {
    if (!search.value) return props.rows;
    const q = search.value.toLowerCase();
    return props.rows.filter(r =>
        (r.leasing_company_name || '').toLowerCase().includes(q) ||
        (r.contract_name || '').toLowerCase().includes(q)
    );
});

function formatAmount(value) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Leasing Facilities</h1>
            <p class="text-sm cvr-text-blue mb-6">Every leasing contract across every leasing company for {{ company.name }}</p>

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-72">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" placeholder="Search by leasing company or contract..." class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-left">#</th>
                            <th class="px-3 py-3 text-left">Leasing Company Name</th>
                            <th class="px-3 py-3 text-left">Contract Name</th>
                            <th class="px-3 py-3 text-left">Start Date</th>
                            <th class="px-3 py-3 text-left">End Date</th>
                            <th class="px-3 py-3 text-right">Limit Amount</th>
                            <th class="px-3 py-3 text-left">Currency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-3 py-3 cvr-text-primary whitespace-nowrap">{{ row.leasing_company_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.contract_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.start_date }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.end_date }}</td>
                            <td class="px-3 py-3 cvr-text-secondary text-right whitespace-nowrap">{{ formatAmount(row.limit_amount) }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.currency }}</td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center cvr-text-muted">
                                No leasing facilities found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
