<script setup>
/**
 * Balances/TotalNetBalanceDetails.vue
 * ------------------------------------------------------------------
 * Served by BalancesController@showTotalNetBalanceDetailsReport —
 * reached from the KPI card buttons on the Balances summary page.
 * One shared page for all three modes (All Invoices / Coming Dues /
 * Past Due) — reportTitle tells you which one you're looking at.
 *
 * "Coming Dues" is a new filter mode, added at the project owner's
 * request (not part of the original app) — see the controller's
 * docblock for the confirmed definition
 * (invoice_status = 'not_due_yet').
 */
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    invoicesBalances: Array, // [{ id, client_name, invoice_number, invoice_date, currency, net_balance_formatted, invoice_due_date_formatted, status_formatted, money_action_url }]
    currency: String,
    clientNameText: String,
    moneyReceivedOrPaidText: String,
    reportTitle: String,
    backUrl: String,
});

function statusBadgeClass(status) {
    // Same meaning as the Invoice Report page's status badges.
    if (status === 'collected') return 'cvr-badge-active';
    if (status === 'pastDue' || status === 'partiallyCollectedAndPastDue') return 'cvr-badge-overdue';
    return 'cvr-badge-pending';
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                {{ $t('← Back to Balances') }}
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ reportTitle }}
                <span class="cvr-text-secondary font-normal">[ {{ currency }} ]</span>
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ invoicesBalances.length }} invoice(s)</p>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3 text-start">{{ clientNameText }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Invoice Number') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Invoice Date') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Currency') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Net Balance') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Invoice Due Date') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Status') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in invoicesBalances" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ i + 1 }}</td>
                            <td class="px-4 py-3 text-start cvr-text-primary">
                                <span class="block truncate max-w-[280px]" :title="row.client_name">{{ row.client_name }}</span>
                            </td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ row.invoice_number }}</td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ row.invoice_date }}</td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ row.currency }}</td>
                            <td class="px-4 py-3 text-right cvr-num-amber font-medium">{{ row.net_balance_formatted }}</td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ row.invoice_due_date_formatted }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="cvr-badge" :class="statusBadgeClass(row.status_formatted)">{{ row.status_formatted }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a :href="row.money_action_url" class="cvr-btn-copper px-3 py-1 rounded text-xs whitespace-nowrap">
                                    {{ moneyReceivedOrPaidText }}
                                </a>
                            </td>
                        </tr>
                        <tr v-if="invoicesBalances.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No invoices found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
