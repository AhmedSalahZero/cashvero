<script setup>
/**
 * Read-only summary for the company's Customers Opening Balance — a
 * singleton (one per company), not a list. Shows opening invoices and
 * advanced down payments, with a "Manage" button to the real
 * create/edit form (still Blade, deliberately not migrated in this
 * pass — see CustomerOpeningBalancesController's docblock).
 */
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    exists: Boolean,
    date: String,
    manageUrl: String,
    invoices: { type: Array, default: () => [] },
    downPayments: { type: Array, default: () => [] },
});

function fmt(amount) {
    return Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center justify-between mb-1 flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-semibold cvr-text-primary mb-1">Customers Opening Balances</h1>
                    <p class="text-sm cvr-text-muted">Opening invoices and advanced down payments for this company's customers</p>
                </div>
                <!-- Plain link, not an Inertia <Link> — destination is still a Blade page. -->
                <a :href="manageUrl" class="cvr-btn-copper inline-flex items-center gap-1 px-4 py-2 rounded-lg text-sm font-medium">
                    {{ exists ? '✎ Manage Opening Balances' : '+ Set Up Opening Balances' }}
                </a>
            </div>

            <!-- Empty state -->
            <div v-if="!exists" class="cvr-card mt-8 text-center py-12">
                <p class="text-4xl mb-3">👥</p>
                <h2 class="text-lg font-medium cvr-text-primary mb-1">No opening balances set up yet</h2>
                <p class="text-sm cvr-text-muted mb-5">Set the starting invoices and down payments for this company's customers.</p>
                <a :href="manageUrl" class="cvr-btn-copper inline-flex items-center gap-1 px-4 py-2 rounded-lg text-sm font-medium">
                    + Set Up Opening Balances
                </a>
            </div>

            <template v-else>
                <p class="text-sm cvr-text-secondary mt-4 mb-6">
                    As of <span class="cvr-text-primary font-medium">{{ date }}</span>
                </p>

                <!-- KPI row -->
                <div class="cvr-kpi-row mb-8">
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-blue">🧾</div>
                        <div>
                            <p class="cvr-kpi-label">Opening Invoices</p>
                            <p class="cvr-kpi-value">{{ invoices.length }}</p>
                        </div>
                    </div>
                    <div class="cvr-kpi-card">
                        <div class="cvr-kpi-icon cvr-kpi-icon-green">💰</div>
                        <div>
                            <p class="cvr-kpi-label">Advanced Down Payments</p>
                            <p class="cvr-kpi-value">{{ downPayments.length }}</p>
                        </div>
                    </div>
                </div>

                <!-- Opening Invoices -->
                <div class="mb-8">
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary mb-3">Opening Invoices</h2>
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-4 py-3 text-left">Customer</th>
                                    <th class="px-4 py-3 text-left">Invoice #</th>
                                    <th class="px-4 py-3 text-left">Due Date</th>
                                    <th class="px-4 py-3 text-left">Currency</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3 text-left">Contract</th>
                                    <th class="px-4 py-3 text-left">Sales Order #</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in invoices" :key="row.id" class="cvr-table-row">
                                    <td class="px-4 py-3 cvr-text-primary">
                                        <span class="block truncate max-w-[220px]" :title="row.customer">{{ row.customer || '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.invoice_number || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.invoice_due_date || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.currency }}</td>
                                    <td class="px-4 py-3 text-right cvr-num">{{ fmt(row.amount) }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">
                                        <span class="block truncate max-w-[200px]" :title="row.contract_name">
                                            {{ row.contract_name || '—' }}<span v-if="row.contract_code"> ({{ row.contract_code }})</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.sales_order_number || '—' }}</td>
                                </tr>
                                <tr v-if="invoices.length === 0">
                                    <td colspan="7" class="px-4 py-6 text-center cvr-text-muted">No opening invoices.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Advanced Down Payments -->
                <div class="mb-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary mb-3">Advanced Down Payments</h2>
                    <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-4 py-3 text-left">Customer</th>
                                    <th class="px-4 py-3 text-left">Type</th>
                                    <th class="px-4 py-3 text-left">Contract</th>
                                    <th class="px-4 py-3 text-left">Currency</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in downPayments" :key="row.id" class="cvr-table-row">
                                    <td class="px-4 py-3 cvr-text-primary">
                                        <span class="block truncate max-w-[220px]" :title="row.customer">{{ row.customer || '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.down_payment_type || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.contract_name || '—' }}</td>
                                    <td class="px-4 py-3 cvr-text-secondary">{{ row.currency }}</td>
                                    <td class="px-4 py-3 text-right cvr-num-green">{{ fmt(row.amount) }}</td>
                                </tr>
                                <tr v-if="downPayments.length === 0">
                                    <td colspan="5" class="px-4 py-6 text-center cvr-text-muted">No advanced down payments.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
