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
        (r.bank_name || '').toLowerCase().includes(q) ||
        (r.contract_name || '').toLowerCase().includes(q) ||
        (r.facility_type || '').toLowerCase().includes(q)
    );
});

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('LG & LC Facilities') }}</h1>
            <p class="text-sm cvr-text-blue mb-6">Every Letter of Guarantee and Letter of Credit facility across every bank for {{ company.name }}</p>

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-72">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" :placeholder="$t('Search by bank, contract, or type...')" class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-start">#</th>
                            <th class="px-3 py-3 text-start">{{ $t('Type') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Bank Name') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Contract Name') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Start Date') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('End Date') }}</th>
                            <th class="px-3 py-3 text-right">{{ $t('Limit Amount') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Currency') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span :class="row.facility_type === 'Letter Of Guarantee' ? 'cvr-badge-info' : 'cvr-badge-facility'" class="px-2 py-0.5 rounded text-xs font-medium">
                                    {{ row.facility_type }}
                                </span>
                            </td>
                            <td class="px-3 py-3 cvr-text-primary ">{{ row.bank_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.contract_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.start_date }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.end_date }}</td>
                            <td class="px-3 py-3 cvr-text-secondary text-right whitespace-nowrap">{{ formatAmount(row.limit_amount) }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.currency }}</td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No LG or LC facilities found.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
