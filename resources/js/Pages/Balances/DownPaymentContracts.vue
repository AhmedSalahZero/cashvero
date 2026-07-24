<script setup>
/**
 * Balances/DownPaymentContracts.vue
 * ------------------------------------------------------------------
 * Served by DownPaymentContractsController@viewContractsWithDownPayments.
 * Reached from the "Down Payment Amount Settlement" button on the
 * Invoice Report page. Read-only list — every down payment for this
 * customer + currency that's still open (no finished contract).
 *
 * The "Start Settlement" action is deliberately still a plain link to
 * the existing Blade settlement form — that page shares its
 * settlement engine with the Money Received/Payment forms (Treasury
 * Operations) and is being migrated together with those, not here.
 * See the controller's class docblock for the full reasoning.
 *
 * Note carried over from the original: the date filter shown on this
 * page's title area never actually filtered results even in the
 * original Blade (the dates were computed but never applied to the
 * query) — not something introduced by this migration, and not
 * silently "fixed" here without a decision from the project owner.
 */
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    title: String,
    currency: String,
    backUrl: String,
    rows: Array, // [{ id, date_formatted, down_payment_amount_formatted, settlement_amount_formatted, net_amount_formatted, currency, contract_name, contract_amount_formatted, settlement_url }]
});
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                ← Back to Invoice Report
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ title }}
                <span class="cvr-text-secondary font-normal">[ {{ currency }} ]</span>
            </h1>
            <p class="text-sm cvr-text-muted mb-6">Down payments available to settle against invoices</p>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3 text-center">Date</th>
                            <th class="px-4 py-3 text-right">Down Payment Amount</th>
                            <th class="px-4 py-3 text-right">Settlement Amount</th>
                            <th class="px-4 py-3 text-right">Net Amount</th>
                            <th class="px-4 py-3 text-center">Currency</th>
                            <th class="px-4 py-3 text-left">Contract Name</th>
                            <th class="px-4 py-3 text-right">Contract Amount</th>
                            <th class="px-4 py-3 text-center">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in rows" :key="row.id" class="cvr-table-row">
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ i + 1 }}</td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ row.date_formatted }}</td>
                            <td class="px-4 py-3 text-right cvr-num">{{ row.down_payment_amount_formatted }}</td>
                            <td class="px-4 py-3 text-right cvr-num">{{ row.settlement_amount_formatted }}</td>
                            <td class="px-4 py-3 text-right font-medium cvr-num-amber">{{ row.net_amount_formatted }}</td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ row.currency }}</td>
                            <td class="px-4 py-3 text-left cvr-text-primary">{{ row.contract_name || '-' }}</td>
                            <td class="px-4 py-3 text-right cvr-num">{{ row.contract_amount_formatted }}</td>
                            <td class="px-4 py-3 text-center">
                                <Link :href="row.settlement_url" class="cvr-btn-copper px-3 py-1 rounded text-xs whitespace-nowrap" title="Start Settlement">
                                    Start Settlement
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center cvr-text-muted">No open down payments found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
