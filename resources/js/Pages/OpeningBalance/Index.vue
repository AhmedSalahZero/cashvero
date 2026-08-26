<script setup>
/**
 * Read-only summary for the company's Opening Balance — a singleton
 * (one per company), not a list. Shows what's currently recorded
 * across its four repeaters (Cash In Safe, Cheques In Safe, Cheques
 * Under Collection, Payable Cheques) with a "Manage" button to the
 * real create/edit form (still Blade, deliberately not migrated in
 * this pass — see OpeningBalancesController's docblock).
 */
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    exists: Boolean,
    date: String,
    manageUrl: String,
    cashInSafe: { type: Array, default: () => [] },
    chequesInSafe: { type: Array, default: () => [] },
    chequesUnderCollection: { type: Array, default: () => [] },
    payableCheques: { type: Array, default: () => [] },
});

function fmt(amount) {
    return Number(amount || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center justify-between mb-1 flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Cash in Safe & Cheque Balance') }}</h1>
                    <p class="text-sm cvr-text-muted">{{ $t('Opening balance snapshot for this company') }}</p>
                </div>
                <!-- Plain link, not an Inertia <Link> — the destination is
                     still a Blade page, not an Inertia one. -->
                <a :href="manageUrl" class="cvr-btn-copper inline-flex items-center gap-1 px-4 py-2 rounded-lg text-sm font-medium">
                    {{ exists ? $t('✎ Manage Opening Balance') : $t('+ Set Up Opening Balance') }}
                </a>
            </div>

            <!-- Empty state -->
            <div v-if="!exists" class="cvr-card mt-8 text-center py-12">
                <p class="text-4xl mb-3">🗄️</p>
                <h2 class="text-lg font-medium cvr-text-primary mb-1">{{ $t('No opening balance set up yet') }}</h2>
                <p class="text-sm cvr-text-muted mb-5">{{ $t('Set the starting cash, cheques, and payables for this company.') }}</p>
                <a :href="manageUrl" class="cvr-btn-copper inline-flex items-center gap-1 px-4 py-2 rounded-lg text-sm font-medium">
                    {{ $t('+ Set Up Opening Balance') }}
                </a>
            </div>

            <template v-else>
                <p class="text-sm cvr-text-secondary mt-4 mb-6">
                    {{ $t('As of') }} <span class="cvr-text-primary font-medium">{{ date }}</span>
                </p>

                <!-- KPI row -->
                <div class="cvr-kpi-row mb-8">
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-green">🗄️</div>
                        <div>
                            <p class="cvr-kpi-label">{{ $t('Cash In Safe Entries') }}</p>
                            <p class="cvr-kpi-value">{{ cashInSafe.length }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">📄</div>
                        <div>
                            <p class="cvr-kpi-label">{{ $t('Cheques In Safe') }}</p>
                            <p class="cvr-kpi-value">{{ chequesInSafe.length }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-copper">⏳</div>
                        <div>
                            <p class="cvr-kpi-label">{{ $t('Cheques Under Collection') }}</p>
                            <p class="cvr-kpi-value">{{ chequesUnderCollection.length }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">💸</div>
                        <div>
                            <p class="cvr-kpi-label">{{ $t('Payable Cheques') }}</p>
                            <p class="cvr-kpi-value">{{ payableCheques.length }}</p>
                        </div>
                    </div>
                </div>

                <!-- Cash In Safe -->
                <div class="mb-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary mb-3">{{ $t('Cash In Safe') }}</h2>
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ $t('Branch') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Currency') }}</th>
                                    <th class="px-4 py-3 text-right">{{ $t('Amount') }}</th>
                                    <th class="px-4 py-3 text-right">{{ $t('Exchange Rate') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in cashInSafe" :key="row.id" class="cvr-table-row">
                                    <td class="px-4 py-3 cvr-text-primary">{{ row.branch || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.currency }}</td>
                                    <td class="px-4 py-3 text-right cvr-num">{{ fmt(row.amount) }}</td>
                                    <td class="px-4 py-3 text-right cvr-num">{{ row.exchange_rate }}</td>
                                </tr>
                                <tr v-if="cashInSafe.length === 0">
                                    <td colspan="4" class="px-4 py-6 text-center cvr-text-muted">{{ $t('No cash in safe entries.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cheques In Safe -->
                <div class="mb-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary mb-3">{{ $t('Cheques In Safe') }}</h2>
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ $t('Customer') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Cheque #') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Drawee Bank') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Currency') }}</th>
                                    <th class="px-4 py-3 text-right">{{ $t('Amount') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Due Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in chequesInSafe" :key="row.id" class="cvr-table-row">
                                    <td class="px-4 py-3 cvr-text-primary">{{ row.customer || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.cheque_number || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.drawee_bank || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.currency }}</td>
                                    <td class="px-4 py-3 text-right cvr-num">{{ fmt(row.amount) }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.due_date || '—' }}</td>
                                </tr>
                                <tr v-if="chequesInSafe.length === 0">
                                    <td colspan="6" class="px-4 py-6 text-center cvr-text-muted">{{ $t('No cheques in safe.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cheques Under Collection -->
                <div class="mb-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary mb-3">{{ $t('Cheques Under Collection') }}</h2>
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ $t('Customer') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Cheque #') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Drawee Bank') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Currency') }}</th>
                                    <th class="px-4 py-3 text-right">{{ $t('Amount') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Due Date') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Deposit Date') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Account Type') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Account Number') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in chequesUnderCollection" :key="row.id" class="cvr-table-row">
                                    <td class="px-4 py-3 cvr-text-primary">{{ row.customer || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.cheque_number || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.drawee_bank || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.currency }}</td>
                                    <td class="px-4 py-3 text-right cvr-num-amber">{{ fmt(row.amount) }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.due_date || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.deposit_date || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.account_type || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.account_number || '—' }}</td>
                                </tr>
                                <tr v-if="chequesUnderCollection.length === 0">
                                    <td colspan="9" class="px-4 py-6 text-center cvr-text-muted">{{ $t('No cheques under collection.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payable Cheques -->
                <div class="mb-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary mb-3">{{ $t('Payable Cheques') }}</h2>
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ $t('Supplier') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Cheque #') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Delivery Bank') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Currency') }}</th>
                                    <th class="px-4 py-3 text-right">{{ $t('Amount') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Due Date') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Account Type') }}</th>
                                    <th class="px-4 py-3 text-start">{{ $t('Account Number') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in payableCheques" :key="row.id" class="cvr-table-row">
                                    <td class="px-4 py-3 cvr-text-primary">{{ row.supplier || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.cheque_number || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.delivery_bank || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.currency }}</td>
                                    <td class="px-4 py-3 text-right cvr-num-amber">{{ fmt(row.amount) }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.due_date || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.account_type || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.account_number || '—' }}</td>
                                </tr>
                                <tr v-if="payableCheques.length === 0">
                                    <td colspan="8" class="px-4 py-6 text-center cvr-text-muted">{{ $t('No payable cheques.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
