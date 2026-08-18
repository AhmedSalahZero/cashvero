<script setup>
/**
 * Leasing Contract Statement.
 *
 * Deliberately built to read like Statements/BankStatement/Result.vue,
 * because that is what a statement in this application looks like and
 * what people already know how to read: back link, header line naming
 * the account, a KPI row, then the movement table.
 *
 * The ledger IS the statement. Its columns are the bank statement's
 * columns for a credit facility (Limit, Beginning Balance, Debit,
 * Credit, End Balance, Room), meaning exactly the same things:
 *
 *   credit = drawn — the leasing company paid a supplier out of the
 *            contract, so the balance goes more negative and the room
 *            shrinks
 *   debit  = the principle half of a repaid installment, which lifts
 *            the balance back toward zero and frees room again
 *
 * Interest sits in its own column and is deliberately NOT in the
 * balance maths: a leasing installment already bundles its interest,
 * so moving the drawn balance by it would count it twice.
 *
 * ⚠️ There is deliberately NO installment schedule on this page. It
 * lived here once and was removed (owner's decision, 2026-08-18): the
 * schedule screen at /uploading/ContractLoanSchedule/{contract} already
 * shows every installment with its interest and principle, so all this
 * page reproduced was a second copy of it. A statement is a record of
 * movements — the same thing Bank Statement is.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    leasingCompany: Object,
    contract: Object,
    kpis: Object,    // { beginningBalance, endingBalance, totalDebit, totalCredit, totalInterest, availableRoom, transactionCount }
    ledger: Array,
    backUrl: String,
    // The page is reached both from a leasing company's contract list
    // and from the Statements sidebar, so the way back differs.
    backLabel: { type: String, default: 'Back' },
    // Only the sidebar route filters by period; the contract list's
    // button shows the contract's whole life.
    period: { type: Object, default: () => ({}) },
    navUrls: Object,
});

function fmt(value) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* Same sign convention as Bank Statement's running balance: positive →
   amber, negative → red, zero → green. A drawn facility sits negative. */
function endBalanceColorVar(balance) {
    if (balance > 0) return 'var(--cvr-num-amber)';
    if (balance < 0) return 'var(--cvr-num-red)';
    return 'var(--cvr-num-green)';
}

const hasPeriod = computed(() => Boolean(props.period?.start_formatted || props.period?.end_formatted));
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                ← {{ backLabel }}
            </Link>

            <h1 class="text-xl font-semibold cvr-text-primary mt-2 mb-1">
                Leasing Contract Statement
                <span class="cvr-text-secondary font-normal">
                    — {{ leasingCompany.name }} · {{ contract.name }} · {{ String(contract.currency_formatted).toUpperCase() }}
                </span>
            </h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ kpis.transactionCount }} movement{{ kpis.transactionCount === 1 ? '' : 's' }}<template v-if="hasPeriod"> between {{ period.start_formatted || '…' }} and {{ period.end_formatted || '…' }}</template>.
                Contract runs {{ contract.start_date_formatted }} → {{ contract.end_date_formatted }} at {{ contract.interest_rate_formatted }}.
            </p>

            <!-- KPI row — computed over the whole period, not the page -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">📅</div>
                    <div>
                        <p class="cvr-kpi-label">Beginning Balance</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ fmt(kpis.beginningBalance) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">⬇️</div>
                    <div>
                        <p class="cvr-kpi-label">Drawn (Credit)</p>
                        <p class="cvr-kpi-value cvr-num">{{ fmt(kpis.totalCredit) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">⬆️</div>
                    <div>
                        <p class="cvr-kpi-label">Principle Repaid (Debit)</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ fmt(kpis.totalDebit) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">🏁</div>
                    <div>
                        <p class="cvr-kpi-label">Ending Balance</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ fmt(kpis.endingBalance) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">🏦</div>
                    <div>
                        <p class="cvr-kpi-label">Available Room</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ fmt(kpis.availableRoom) }}</p>
                        <p class="text-xs cvr-text-muted">of {{ fmt(contract.limit) }} limit</p>
                    </div>
                </div>
            </div>

            <!-- ── The statement itself ────────────────────────────────── -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <div class="overflow-auto" style="max-height: 150vh;">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3 text-center">Date</th>
                                <th class="px-3 py-3 text-right">Limit</th>
                                <th class="px-3 py-3 text-right">Beginning Balance</th>
                                <th class="px-3 py-3 text-right">Debit</th>
                                <th class="px-3 py-3 text-right">Credit</th>
                                <th class="px-3 py-3 text-right">End Balance</th>
                                <th class="px-3 py-3 text-right">Room</th>
                                <th class="px-3 py-3 text-right">Interest Paid</th>
                                <th class="px-3 py-3 text-left min-w-[280px]">Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in ledger" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ index + 1 }}</td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.date_formatted }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ fmt(contract.limit) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ fmt(row.beginning_balance) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ fmt(row.debit) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ fmt(row.credit) }}</td>
                                <td class="px-3 py-2.5 text-right font-medium" :style="{ color: endBalanceColorVar(row.end_balance) }">{{ fmt(row.end_balance) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ fmt(row.room) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ fmt(row.interest_amount) }}</td>
                                <td class="px-3 py-2.5 text-left cvr-text-secondary">
                                    <span class="block max-w-[360px] whitespace-normal">
                                        {{ row.is_repayment ? 'Installment repayment' : 'Supplier paid by the leasing company out of this contract' }}
                                    </span>
                                    <span v-if="row.comment" class="block max-w-[360px] whitespace-normal cvr-text-muted text-xs mt-0.5">{{ row.comment }}</span>
                                </td>
                            </tr>
                            <tr v-if="ledger.length === 0">
                                <td colspan="10" class="px-4 py-8 text-center cvr-text-muted">
                                    <template v-if="hasPeriod">No movements found for this date range.</template>
                                    <template v-else>
                                        Nothing has been paid out of this contract yet — the full limit of {{ fmt(contract.limit) }} is available.
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
