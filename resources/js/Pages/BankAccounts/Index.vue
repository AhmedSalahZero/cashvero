<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    financialInstitution: Object,
    bankAccounts: Array,
    navUrls: Object,
    backUrl: String,
    // { canCreate, canUpdate, canDelete, canLock }
    permissions: { type: Object, default: () => ({}) },
});

/* ── KPIs (computed client-side from data already on the page) ─── */
const totalAccounts = computed(() => props.bankAccounts.length);
const editableAccounts = computed(() => props.bankAccounts.filter(a => a.is_editable).length);
const currencyCount = computed(() => new Set(props.bankAccounts.map(a => a.currency_formatted)).size);

/* ── Search / filter ──────────────────────────────────────────── */
const search = ref('');
const activeCurrency = ref('all');

const currencies = computed(() => {
    const set = new Set(props.bankAccounts.map(a => a.currency_formatted));
    return ['all', ...set];
});

const filteredAccounts = computed(() => {
    return props.bankAccounts.filter(account => {
        const matchesSearch = !search.value ||
            account.account_number.toLowerCase().includes(search.value.toLowerCase()) ||
            (account.shareholder_name || '').toLowerCase().includes(search.value.toLowerCase()) ||
            account.type_label.toLowerCase().includes(search.value.toLowerCase());
        const matchesCurrency = activeCurrency.value === 'all' || account.currency_formatted === activeCurrency.value;
        return matchesSearch && matchesCurrency;
    });
});

/* ── Type badge styling — mirrors the account category ───────── */
function badgeClass(typeLabel) {
    const t = typeLabel.toLowerCase();
    if (t.includes('current')) return 'cvr-badge-current';
    if (t.includes('deposit')) return 'cvr-badge-deposit';
    if (t.includes('overdraft')) return 'cvr-badge-facility';
    return 'cvr-badge-info';
}

/* ── Delete confirmation ──────────────────────────────────────── */
const deleteTarget = ref(null);
function confirmDelete(account) { deleteTarget.value = account; }
function cancelDelete() { deleteTarget.value = null; }
function destroyAccount() {
    router.delete(deleteTarget.value.delete_url, {
        onFinish: () => { deleteTarget.value = null; },
    });
}

/* ── Lock / Unlock confirmation ──────────────────────────────────
   Uses LockBankAccountController@lockOrUnlock (route name
   'lock.or.unlock.bank.account') — the real, generic lock endpoint
   that covers all 7 lockable account/facility types. */
const lockTarget = ref(null);
function confirmLockToggle(account) { lockTarget.value = account; }
function cancelLockToggle() { lockTarget.value = null; }
function toggleLock() {
    router.put(lockTarget.value.lock_url, {}, {
        onFinish: () => { lockTarget.value = null; },
    });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <!-- Back link + title -->
            <div class="flex items-center gap-3 mb-1">
                <Link
                    :href="backUrl"
                    class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm"
                >
                    {{ $t('← Back to Banks') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ $t('Bank Accounts') }}
            </h1>
            <p class="text-md cvr-text-blue mb-6">{{ financialInstitution.name }}</p>

            <!-- KPI cards -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">🏦</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Total Accounts') }}</p>
                        <p class="cvr-kpi-value">{{ totalAccounts }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">✓</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Editable Accounts') }}</p>
                        <p class="cvr-kpi-value">{{ editableAccounts }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">⇄</div>
                    <div>
                        <p class="cvr-kpi-label">{{ $t('Currencies') }}</p>
                        <p class="cvr-kpi-value">{{ currencyCount }}</p>
                    </div>
                </div>
            </div>

            <!-- Filter bar -->
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <div class="flex items-center gap-1">
                    <button
                        v-for="cur in currencies"
                        :key="cur"
                        @click="activeCurrency = cur"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': activeCurrency === cur }"
                    >
                        {{ cur === 'all' ? $t('All') : cur }}
                    </button>
                </div>
                <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 ms-auto w-64">
                    <span class="cvr-text-muted text-sm">🔍</span>
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="$t('Search account number or type...')"
                        class="bg-transparent outline-none text-sm w-full cvr-text-primary"
                    />
                </div>
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Type') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Account Number') }}</th>
                            <!-- Owner column — only for users allowed to see
                                 shareholder-owned accounts (docs/shareholder-accounts.md, D6) -->
                            <th v-if="permissions.canViewShareholderAccounts" class="px-4 py-3 text-start">{{ $t('Owner') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Currency') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Balance') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(account, index) in filteredAccounts"
                            :key="account.id + '-' + index"
                            class="cvr-table-row"
                        >
                            <td class="px-4 py-3 cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-4 py-3">
                                <span class="cvr-badge" :class="badgeClass(account.type_label)">
                                    {{ account.type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap cvr-num">{{ account.account_number }}</td>
                            <td v-if="permissions.canViewShareholderAccounts" class="px-4 py-3 cvr-text-secondary">
                                {{ account.shareholder_name || $t('Company') }}
                            </td>
                            <td class="px-4 py-3 cvr-text-secondary">{{ account.currency_formatted }}</td>
                            <td class="px-4 py-3 cvr-num-green font-medium">{{ account.balance_formatted }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Link
                                        v-if="account.is_editable && permissions.canUpdate"
                                        :href="account.edit_url"
                                        class="cvr-btn-secondary inline-flex items-center px-2 py-1 rounded border text-xs"
                                    >
                                        {{ $t('Edit') }}
                                    </Link>
                                    <button
                                        v-if="account.is_editable && permissions.canDelete"
                                        @click="confirmDelete(account)"
                                        class="cvr-btn-danger inline-flex items-center px-2 py-1 rounded border text-xs"
                                    >
                                        {{ $t('Delete') }}
                                    </button>
                                    <button
                                        v-if="account.is_lockable && permissions.canLock"
                                        @click="confirmLockToggle(account)"
                                        class="cvr-action-btn"
                                        :class="account.is_active ? '' : 'cvr-action-btn-danger'"
                                        :title="account.is_active ? 'Lock account' : 'Unlock account'"
                                    >
                                        {{ account.is_active ? '🔓' : '🔒' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredAccounts.length === 0">
                            <td :colspan="permissions.canViewShareholderAccounts ? 7 : 6" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No accounts match your search.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ $t('Do you want to delete this item?') }}
                    </h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">
                            {{ $t('Close') }}
                        </button>
                        <button @click="destroyAccount" class="cvr-btn-danger px-3 py-1.5 rounded border">
                            {{ $t('Confirm Delete') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lock/Unlock confirmation -->
            <div v-if="lockTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ lockTarget.is_active ? $t('Do you want to lock this account?') : $t('Do you want to unlock this account?') }}
                    </h2>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelLockToggle" class="cvr-btn-secondary px-3 py-1.5 rounded border">
                            {{ $t('Close') }}
                        </button>
                        <button @click="toggleLock" class="cvr-btn-danger px-3 py-1.5 rounded border">
                            {{ lockTarget.is_active ? $t('Confirm Lock') : $t('Confirm Unlock') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
