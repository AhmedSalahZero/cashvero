<script setup>
/**
 * Statements/BankStatement/Result.vue
 * ------------------------------------------------------------------
 * Served by BankStatementController@result. A transaction-by-
 * transaction ledger for ONE bank account/facility (current account,
 * or one of the 4 overdraft types), for the chosen date range.
 *
 * Built as a genuinely heavy report from the start (per project owner
 * request): rows are paginated SERVER-SIDE (50/page, same as the
 * original Blade page's own pagination — see
 * PaginatesRawCollections), never loaded hundreds-at-once into the
 * browser. KPI totals (beginning/ending balance, total debit/credit)
 * are computed backend-side from the FULL date-range result set
 * before pagination, so they stay accurate regardless of which page
 * is currently visible.
 *
 * Two row types can be edited inline — both real, live Odoo
 * journal-entry writes, UNCHANGED business logic, only the modal UI
 * is new:
 *   - Letter of Guarantee commission fees rows (isCommissionFees)
 *   - End-of-month interest rows (interestType === 'end_of_month' |
 *     'end_of_month_final')
 * Note: the original Blade modal for interest also posted a
 * `is_end_of_month_final` checkbox value, but
 * BankStatementController@updateBankStatementRow never reads that
 * field — confirmed dead, not reproduced here.
 */
import { computed, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    currency: String,
    isCurrentAccount: Boolean,
    financialInstitutionName: String,
    accountTypeName: String,
    accountNumber: String,
    isAgainstCommercialPaper: Boolean,
    isAgainstAssignmentOfContract: Boolean,
    isMediumTermLoan: Boolean,
    statementModelName: String,
    kpis: Object, // { beginningBalance, endingBalance, totalDebit, totalCredit, transactionCount }
    paginator: Object, // { data, links, current_page, last_page, total }
    urls: Object, // { backUrl, withdrawalsSettlementReportUrl, updateCommissionFeesUrl, updateBankStatementRowUrl }
});

const rows = computed(() => props.paginator?.data || []);
const showActualLimitColumn = computed(() => props.isAgainstCommercialPaper || props.isAgainstAssignmentOfContract);
/**
 * * Reviewed/Actions don't apply to the MTL facility (client-requested,
 * * 2026-08-17): MTL rows are never "reviewed" (see getBankStatementReviewed()'s
 * * can_not_be_reviewed branch) and neither Action button (commission fees /
 * * end-of-month interest) ever applies to an MTL row either — both columns
 * * are hidden for MTL only, every other facility type keeps them exactly as
 * * before. Principle is the MTL-only replacement column.
 */
const showReviewedAndActionsColumns = computed(() => !props.isMediumTermLoan);

// Kept in sync with the v-if list on the header row below, so the
// "No movements found" row always spans the actual visible column count
// instead of a number that silently drifts as columns are added/removed.
const visibleColumnCount = computed(() => {
    let count = 6; // #, Date, Beginning Balance, Debit, Credit, End Balance
    if (!props.isCurrentAccount) count += 1; // Limit
    if (showActualLimitColumn.value) count += 1; // Actual Limit
    if (!props.isCurrentAccount) count += 2; // Room, Calculated Interest
    if (props.isMediumTermLoan) count += 1; // Principle
    if (showReviewedAndActionsColumns.value) count += 2; // Reviewed, Actions
    count += 1; // Comment
    return count;
});

function formatAmount(value) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* Running end-balance color — same sign convention already
   established on Balances/Statement.vue (the other ledger-style
   report in the app): positive → amber, negative → red, zero → green. */
function endBalanceColorVar(balance) {
    if (balance > 0) return 'var(--cvr-num-amber)';
    if (balance < 0) return 'var(--cvr-num-red)';
    return 'var(--cvr-num-green)';
}

function reviewedBadgeClass(text) {
    if (text === 'Yes') return 'cvr-badge cvr-badge-active';
    if (text === 'No') return 'cvr-badge cvr-badge-pending';
    return null;
}

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveScroll: true });
}

/* ── Commission Fees modal ──────────────────────────────────────── */
const feesTarget = ref(null);
const feesAmount = ref(0);
const feesDate = ref('');
function openFeesModal(row) {
    feesTarget.value = row;
    feesAmount.value = row.credit;
    feesDate.value = row.rawDate;
}
function submitFees() {
    router.post(props.urls.updateCommissionFeesUrl, {
        statement_model_name: props.statementModelName,
        statement_id: feesTarget.value.id,
        credit: feesAmount.value,
        date: feesDate.value,
    }, { onFinish: () => { feesTarget.value = null; } });
}

/* ── End-of-Month Interest modal ─────────────────────────────────── */
const interestTarget = ref(null);
const interestAmount = ref(0);
const interestDate = ref('');
const interestIsCredit = ref(true);
function openInterestModal(row) {
    interestTarget.value = row;
    interestIsCredit.value = row.credit > 0;
    interestAmount.value = row.credit > 0 ? row.credit : row.debit;
    interestDate.value = row.rawDate;
}
function submitInterest() {
    router.post(props.urls.updateBankStatementRowUrl, {
        statement_model_name: props.statementModelName,
        statement_id: interestTarget.value.id,
        credit: interestIsCredit.value ? interestAmount.value : 0,
        debit: interestIsCredit.value ? 0 : interestAmount.value,
        date: interestDate.value,
    }, { onFinish: () => { interestTarget.value = null; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="urls.backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                ← Back to Bank Statement
            </Link>

            <div class="flex items-start justify-between flex-wrap gap-2 mt-2 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    Bank Statement
                    <span class="cvr-text-secondary font-normal">
                        — {{ financialInstitutionName }} · {{ accountTypeName }} · {{ accountNumber }} · {{ String(currency).toUpperCase() }}
                    </span>
                </h1>
                <div class="flex items-center gap-2">
                    <a :href="urls.exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        ⬇️ Export to Excel
                    </a>
                    <Link v-if="!isCurrentAccount" :href="urls.withdrawalsSettlementReportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        📖 Withdrawals Settlement Report
                    </Link>
                </div>
            </div>
            <p class="text-sm cvr-text-muted mb-6">{{ kpis.transactionCount }} transactions in this date range.</p>

            <!-- KPI row -->
            <div class="cvr-kpi-row mb-6">
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">📅</div>
                    <div>
                        <p class="cvr-kpi-label">Beginning Balance</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.beginningBalance) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-green">⬆️</div>
                    <div>
                        <p class="cvr-kpi-label">Total Debit</p>
                        <p class="cvr-kpi-value cvr-num-green">{{ formatAmount(kpis.totalDebit) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-copper">⬇️</div>
                    <div>
                        <p class="cvr-kpi-label">Total Credit</p>
                        <p class="cvr-kpi-value cvr-num">{{ formatAmount(kpis.totalCredit) }}</p>
                    </div>
                </div>
                <div class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">🏁</div>
                    <div>
                        <p class="cvr-kpi-label">Ending Balance</p>
                        <p class="cvr-kpi-value cvr-num-blue">{{ formatAmount(kpis.endingBalance) }}</p>
                    </div>
                </div>
            </div>

            <!-- Heavy transaction table — sticky header, horizontal + vertical scroll -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <div class="overflow-auto" style="max-height: 150vh;">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-3 text-center">#</th>
                                <th class="px-3 py-3 text-center">Date</th>
                                <th v-if="!isCurrentAccount" class="px-3 py-3 text-right">Limit</th>
                                <th v-if="showActualLimitColumn" class="px-3 py-3 text-right">Actual Limit</th>
                                <th class="px-3 py-3 text-right">Beginning Balance</th>
                                <th class="px-3 py-3 text-right">Debit</th>
                                <th class="px-3 py-3 text-right">Credit</th>
                                <th class="px-3 py-3 text-right">End Balance</th>
                                <th v-if="!isCurrentAccount" class="px-3 py-3 text-right">Room</th>
                                <th v-if="!isCurrentAccount" class="px-3 py-3 text-right">Calculated Interest</th>
                                <th v-if="isMediumTermLoan" class="px-3 py-3 text-right">Principle</th>
                                <th v-if="showReviewedAndActionsColumns" class="px-3 py-3 text-center">Reviewed</th>
                                <th v-if="showReviewedAndActionsColumns" class="px-3 py-3 text-center">Actions</th>
                                <th class="px-3 py-3 text-left min-w-[280px]">Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in rows" :key="row.id" class="cvr-table-row">
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">
                                    {{ (paginator.current_page - 1) * 50 + index + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-center cvr-text-secondary">{{ row.date }}</td>
                                <td v-if="!isCurrentAccount" class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.limit) }}</td>
                                <td v-if="showActualLimitColumn" class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.statementLimit) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.beginningBalance) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.debit) }}</td>
                                <td class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.credit) }}</td>
                                <td class="px-3 py-2.5 text-right font-medium" :style="{ color: endBalanceColorVar(row.endBalance) }">{{ formatAmount(row.endBalance) }}</td>
                                <td v-if="!isCurrentAccount" class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.room) }}</td>
                                <td v-if="!isCurrentAccount" class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.interestAmount) }}</td>
                                <td v-if="isMediumTermLoan" class="px-3 py-2.5 text-right cvr-num">{{ formatAmount(row.principle) }}</td>
                                <td v-if="showReviewedAndActionsColumns" class="px-3 py-2.5 text-center">
                                    <span v-if="reviewedBadgeClass(row.reviewedText)" :class="reviewedBadgeClass(row.reviewedText)">{{ row.reviewedText }}</span>
                                    <span v-else class="cvr-text-muted">{{ row.reviewedText }}</span>
                                </td>
                                <td v-if="showReviewedAndActionsColumns" class="px-3 py-2.5 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button v-if="row.isCommissionFees" @click="openFeesModal(row)" class="cvr-action-btn" title="Edit Commission Fees">✏️</button>
                                        <button v-if="row.interestType === 'end_of_month' || row.interestType === 'end_of_month_final'" @click="openInterestModal(row)" class="cvr-action-btn" title="Edit End-of-Month Interest">✏️</button>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-left cvr-text-secondary">
                                    <span class="block max-w-[360px] whitespace-normal">{{ row.comment }}</span>
                                    <span v-if="row.userComment" class="block max-w-[360px] whitespace-normal cvr-text-muted text-xs mt-0.5">{{ row.userComment }}</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td :colspan="visibleColumnCount" class="px-4 py-8 text-center cvr-text-muted">No movements found for this date range.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="paginator.last_page > 1" class="flex items-center justify-center gap-1 mt-4 flex-wrap">
                <button
                    v-for="(link, i) in paginator.links"
                    :key="i"
                    v-html="link.label"
                    :disabled="!link.url"
                    @click="goToPage(link.url)"
                    class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm"
                    :class="{ 'cvr-nav-item-active': link.active, 'opacity-40 cursor-not-allowed': !link.url }"
                ></button>
            </div>

            <!-- Commission Fees modal -->
            <div v-if="feesTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Confirm Commission Fees Date &amp; Amount</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="cvr-form-label">Amount</label>
                            <input v-model.number="feesAmount" type="number" step="0.01" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Commission Date</label>
                            <input v-model="feesDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-5">
                        <button @click="feesTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitFees" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>

            <!-- End-of-Month Interest modal -->
            <div v-if="interestTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Confirm End-of-Month Interest Date &amp; Amount</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="cvr-form-label">Amount ({{ interestIsCredit ? 'Credit' : 'Debit' }})</label>
                            <input v-model.number="interestAmount" type="number" step="0.01" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Date</label>
                            <input v-model="interestDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-5">
                        <button @click="interestTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitInterest" class="cvr-btn-primary px-3 py-1.5 rounded">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>