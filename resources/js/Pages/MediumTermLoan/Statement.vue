<script setup>
/**
 * MTL Statement — the loan's own statement, in two halves.
 *
 * Half 1 (always shown): interest and principle, DUE vs PAID vs REMAINING.
 *   An installment bundles both, so "how much interest do I still owe" is a
 *   different question from "how much of the loan itself have I paid back",
 *   and neither is answerable from the installment total alone. Payments are
 *   applied interest-first, which is why an early partial payment can move
 *   Interest Paid without moving Principle Paid at all.
 *
 * Half 2 (only when the loan is drawn from inside CashVero): the facility
 *   ledger — drawdowns against the limit, principle coming back off the
 *   drawn balance, and the room left to pay more suppliers with. Interest
 *   shows as its own column here and is deliberately NOT in the balance
 *   maths: it never was part of the drawn amount.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    financialInstitution: Object,
    loan: Object,
    breakdown: Array,
    totals: Object,
    ledger: Array,
    backUrl: String,
    navUrls: Object,
});

const fmt = (n) => Number(n || 0).toLocaleString('en-EG', { maximumFractionDigits: 0 });

const pct = (paid, due) => {
    const d = Number(due || 0);
    if (d <= 0) return 0;
    return Math.min(100, Math.round((Number(paid || 0) / d) * 100));
};

const interestPct = computed(() => pct(props.totals.interest_paid, props.totals.interest_due));
const princiblePct = computed(() => pct(props.totals.principle_paid, props.totals.principle_due));
const roomPct = computed(() => pct(props.loan.available_room, props.loan.limit));

const hasSchedule = computed(() => (props.breakdown || []).length > 0);
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="px-6 pt-4">
            <Link v-if="instructionsUrl" :href="instructionsUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('📖 Instructions') }}
            </Link>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Medium Term Loan') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('MTL Statement —') }} {{ loan.name }}</h1>
            <p class="text-sm cvr-text-blue mb-6">
                {{ financialInstitution.name }} · {{ loan.account_number }} · {{ loan.currency_formatted }}
                · {{ loan.start_date_formatted }} → {{ loan.end_date_formatted }}
                {{ $t('· Interest') }} {{ loan.interest_rate_formatted }}
            </p>

            <!-- ── Interest vs Principle: due, paid, remaining ─────────── -->
            <div class="grid gap-4 mb-6" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-3">{{ $t('Interest') }}</h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Total Due (scheduled)') }}</span>
                            <span class="cvr-num">{{ fmt(totals.interest_due) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Paid') }}</span>
                            <span class="cvr-num">{{ fmt(totals.interest_paid) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <span class="cvr-text-secondary">{{ $t('Remaining') }}</span>
                            <span class="cvr-num">{{ fmt(totals.interest_remaining) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 h-2 rounded cvr-border border overflow-hidden">
                        <div class="h-full cvr-btn-copper" :style="{ width: interestPct + '%' }"></div>
                    </div>
                    <p class="text-xs cvr-text-muted mt-1">{{ interestPct }}{{ $t('% of scheduled interest paid') }}</p>
                </div>

                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-3">{{ $t('Principle') }}</h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Total Due (scheduled)') }}</span>
                            <span class="cvr-num">{{ fmt(totals.principle_due) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Paid') }}</span>
                            <span class="cvr-num">{{ fmt(totals.principle_paid) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <span class="cvr-text-secondary">{{ $t('Remaining') }}</span>
                            <span class="cvr-num">{{ fmt(totals.principle_remaining) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 h-2 rounded cvr-border border overflow-hidden">
                        <div class="h-full cvr-btn-copper" :style="{ width: princiblePct + '%' }"></div>
                    </div>
                    <p class="text-xs cvr-text-muted mt-1">{{ princiblePct }}{{ $t('% of the loan itself repaid') }}</p>
                </div>

                <div v-if="loan.is_payable_facility" class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-3">{{ $t('Facility') }}</h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Limit') }}</span>
                            <span class="cvr-num">{{ fmt(loan.limit) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="cvr-text-muted">{{ $t('Drawn') }}</span>
                            <span class="cvr-num">{{ fmt(loan.drawn) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <span class="cvr-text-secondary">{{ $t('Available Room') }}</span>
                            <span class="cvr-num">{{ fmt(loan.available_room) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 h-2 rounded cvr-border border overflow-hidden">
                        <div class="h-full cvr-btn-copper" :style="{ width: roomPct + '%' }"></div>
                    </div>
                    <p class="text-xs cvr-text-muted mt-1">{{ roomPct }}{{ $t('% of the loan still available to pay suppliers') }}</p>
                </div>

                <div v-else class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-3">{{ $t('Facility') }}</h2>
                    <p class="text-sm cvr-text-muted">
                        {{ $t('This is an') }} <strong>{{ $t('existing') }}</strong> {{ $t('loan — it was drawn and spent before joining CashVero, so there is no drawdown ledger for it. It is repayment-only, and the interest/principle split above is tracked from its installment schedule.') }}
                    </p>
                </div>
            </div>

            <!-- ── Per-installment breakdown ───────────────────────────── -->
            <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-2">{{ $t('Installment Breakdown') }}</h2>
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden overflow-x-auto mb-8">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-3 py-3 text-start" rowspan="2">#</th>
                            <th class="px-3 py-3 text-start" rowspan="2">{{ $t('Date') }}</th>
                            <th class="px-3 py-3 text-start" rowspan="2">{{ $t('Status') }}</th>
                            <th class="px-3 py-2 text-center cvr-border border-l" colspan="3">{{ $t('Installment') }}</th>
                            <th class="px-3 py-2 text-center cvr-border border-l" colspan="3">{{ $t('Interest') }}</th>
                            <th class="px-3 py-2 text-center cvr-border border-l" colspan="3">{{ $t('Principle') }}</th>
                        </tr>
                        <tr>
                            <th class="px-3 py-2 text-right cvr-border border-l">{{ $t('Due') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('Paid') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('Left') }}</th>
                            <th class="px-3 py-2 text-right cvr-border border-l">{{ $t('Due') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('Paid') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('Left') }}</th>
                            <th class="px-3 py-2 text-right cvr-border border-l">{{ $t('Due') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('Paid') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('Left') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in breakdown" :key="row.number" class="cvr-table-row">
                            <td class="px-3 py-3 cvr-text-secondary">{{ row.number }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.date_formatted }}</td>
                            <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.status }}</td>
                            <td class="px-3 py-3 text-right cvr-num cvr-border border-l">{{ fmt(row.installment_due) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(row.installment_paid) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(row.installment_remaining) }}</td>
                            <td class="px-3 py-3 text-right cvr-num cvr-border border-l">{{ fmt(row.interest_due) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(row.interest_paid) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(row.interest_remaining) }}</td>
                            <td class="px-3 py-3 text-right cvr-num cvr-border border-l">{{ fmt(row.principle_due) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(row.principle_paid) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(row.principle_remaining) }}</td>
                        </tr>
                        <tr v-if="!hasSchedule">
                            <td colspan="12" class="px-4 py-8 text-center cvr-text-muted">
                                {{ $t('No schedule uploaded for this loan yet.') }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="hasSchedule" class="cvr-table-head font-semibold">
                        <tr>
                            <td class="px-3 py-3" colspan="3">{{ $t('Total') }}</td>
                            <td class="px-3 py-3 text-right cvr-num cvr-border border-l">{{ fmt(totals.installment_due) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(totals.installment_paid) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(totals.installment_remaining) }}</td>
                            <td class="px-3 py-3 text-right cvr-num cvr-border border-l">{{ fmt(totals.interest_due) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(totals.interest_paid) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(totals.interest_remaining) }}</td>
                            <td class="px-3 py-3 text-right cvr-num cvr-border border-l">{{ fmt(totals.principle_due) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(totals.principle_paid) }}</td>
                            <td class="px-3 py-3 text-right cvr-num">{{ fmt(totals.principle_remaining) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- ── Facility ledger (drawdowns / repayments / room) ─────── -->
            <template v-if="loan.is_payable_facility">
                <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-1">{{ $t('Facility Ledger') }}</h2>
                <p class="text-xs cvr-text-muted mb-2">
                    {{ $t('Drawdowns reduce the room; the principle half of each installment gives it back. Interest is shown for reference only — it is already inside the installment, so it never moves the drawn balance.') }}
                </p>
                <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-3 text-start">{{ $t('Date') }}</th>
                                <th class="px-3 py-3 text-start">{{ $t('Description') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Beginning') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Drawn (Credit)') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Principle (Debit)') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Interest Paid') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('End Balance') }}</th>
                                <th class="px-3 py-3 text-right">{{ $t('Room') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in ledger" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-3 whitespace-nowrap cvr-text-secondary">{{ row.date_formatted }}</td>
                                <td class="px-3 py-3 cvr-text-secondary">
                                    {{ row.is_repayment ? 'Installment Repayment' : 'Drawdown' }}
                                    <span v-if="row.comment" class="cvr-text-muted"> — {{ row.comment }}</span>
                                </td>
                                <td class="px-3 py-3 text-right cvr-num">{{ fmt(row.beginning_balance) }}</td>
                                <td class="px-3 py-3 text-right cvr-num">{{ row.credit ? fmt(row.credit) : '—' }}</td>
                                <td class="px-3 py-3 text-right cvr-num">{{ row.debit ? fmt(row.debit) : '—' }}</td>
                                <td class="px-3 py-3 text-right cvr-num">{{ row.interest_amount ? fmt(row.interest_amount) : '—' }}</td>
                                <td class="px-3 py-3 text-right cvr-num">{{ fmt(row.end_balance) }}</td>
                                <td class="px-3 py-3 text-right cvr-num">{{ fmt(row.room) }}</td>
                            </tr>
                            <tr v-if="!ledger.length">
                                <td colspan="8" class="px-4 py-8 text-center cvr-text-muted">
                                    {{ $t('Nothing drawn from this loan yet — the full limit of') }} {{ fmt(loan.limit) }} {{ $t('is available.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
