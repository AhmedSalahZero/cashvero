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
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    title: String,
    currency: String,
    backUrl: String,
    rows: Array, // [{ id, date_formatted, down_payment_amount_formatted, settlement_amount_formatted, net_amount_formatted, currency, contract_name, contract_amount_formatted, settlement_url, is_fully_integrated_with_odoo, odoo_reference_names, has_odoo_error, odoo_error, failed_settlement_errors }]
});

const odooRefTarget = ref(null);
const odooErrorTarget = ref(null);
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                {{ $t('← Back to Invoice Report') }}
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                {{ title }}
                <span class="cvr-text-secondary font-normal">[ {{ currency }} ]</span>
            </h1>
            <p class="text-sm cvr-text-muted mb-6">{{ $t('Down payments available to settle against invoices') }}</p>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3 text-center">{{ $t('Date') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Down Payment Amount') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Settlement Amount') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Net Amount') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Currency') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Contract Name') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Contract Amount') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Control') }}</th>
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
                            <td class="px-4 py-3 text-start cvr-text-primary">{{ row.contract_name || '-' }}</td>
                            <td class="px-4 py-3 text-right cvr-num">{{ row.contract_amount_formatted }}</td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button v-if="row.has_odoo_error" @click="odooErrorTarget = row" class="cvr-action-btn-danger cvr-action-btn" :title="$t('Odoo Error')">🐞</button>
                                    <button v-if="row.is_fully_integrated_with_odoo" @click="odooRefTarget = row" class="cvr-action-btn" :title="$t('Fully Integrated')">👍</button>
                                    <Link :href="row.settlement_url" class="cvr-btn-copper px-3 py-1 rounded text-xs whitespace-nowrap" :title="$t('Start Settlement')">
                                        {{ $t('Start Settlement') }}
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="9" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No open down payments found.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Odoo error modal -->
            <div v-if="odooErrorTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Odoo Error') }}</h2>
                    <p v-if="odooErrorTarget.odoo_error" class="cvr-text-secondary mb-4">{{ odooErrorTarget.odoo_error }}</p>
                    <template v-if="odooErrorTarget.failed_settlement_errors && odooErrorTarget.failed_settlement_errors.length">
                        <p class="text-sm font-medium cvr-text-primary mb-2">Failed invoice settlements:</p>
                        <ul class="list-disc ps-5 cvr-text-secondary mb-4">
                            <li v-for="(err, i) in odooErrorTarget.failed_settlement_errors" :key="i">{{ err }}</li>
                        </ul>
                    </template>
                    <div class="flex justify-end mt-4">
                        <button @click="odooErrorTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>

            <!-- Odoo references modal -->
            <div v-if="odooRefTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Odoo References') }}</h2>
                    <ul class="list-disc ps-5 cvr-text-secondary">
                        <li v-for="(ref, i) in odooRefTarget.odoo_reference_names" :key="i">{{ ref }}</li>
                    </ul>
                    <div class="flex justify-end mt-4">
                        <button @click="odooRefTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
