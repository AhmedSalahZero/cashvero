<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    rows: Array,
    // docs/shareholder-accounts.md (D6) — the Owner column only exists
    // for users allowed to see shareholder-owned accounts.
    canViewShareholderAccounts: { type: Boolean, default: false },
});

const search = ref('');
const filteredRows = computed(() => {
    if (!search.value) return props.rows;
    const q = search.value.toLowerCase();
    return props.rows.filter(r =>
        (r.bank_name || '').toLowerCase().includes(q) ||
        (r.account_number || '').toLowerCase().includes(q) ||
        (r.owner_name || '').toLowerCase().includes(q)
    );
});
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Bank Accounts') }}</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ $t('Every current account across every bank for') }} {{ company.name }}</p>

            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 w-72">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input v-model="search" type="text" :placeholder="$t('Search by bank or account number...')" class="bg-transparent outline-none text-sm w-full cvr-text-primary" />
                </div>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-start">#</th>
                            <th class="px-3 py-3 text-start">{{ $t('Bank Name') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Account Type') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Account Number') }}</th>
                            <th v-if="canViewShareholderAccounts" class="px-3 py-3 text-start">{{ $t('Owner') }}</th>
                            <th class="px-3 py-3 text-start">{{ $t('Currency') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in filteredRows" :key="row.id" class="cvr-table-row">
                            <td class="px-3 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-3 py-3 cvr-text-primary whitespace-nowrap">{{ row.bank_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.account_type }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.account_number }}</td>
                            <td v-if="canViewShareholderAccounts" class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.owner_name }}</td>
                            <td class="px-3 py-3 cvr-text-secondary whitespace-nowrap">{{ row.currency }}</td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td :colspan="canViewShareholderAccounts ? 6 : 5" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No bank accounts found.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
