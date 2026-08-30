<script setup>
/**
 * Balances/Index.vue
 * ------------------------------------------------------------------
 * Served by BalancesController@index — a SHARED page. The exact same
 * component renders both "Customers Balances" and "Suppliers Balances"
 * from the sidebar; only the data (via $modelType server-side) differs.
 * See BalancesController's class docblock for the full explanation.
 *
 * This page is the entry point into a small family of report pages —
 * per-currency KPI cards, a customer table with "Statement Report" /
 * "Invoice Report" buttons per row, plus a per-currency bulk statement
 * button. All of these — Statement, Invoice Report, and the KPI card
 * drill-down (All Invoices / Coming Dues / Past Due) — are now
 * migrated Inertia pages too, linked with <Link> below.
 */
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { todayDate } from '@/composables/today';

const props = defineProps({
    company: Object,
    modelType: String,
    title: String,
    customersOrSupplierText: String,
    customersOrSupplierStatementText: String,
    mainFunctionalCurrency: String,
    currencyCards: Array, // [{ currency, total, rows, all_invoices_url, past_due_url, coming_dues_url, bulk_statement_url }]
    /* Whether a NEW internal settlement can be started from this page.
       False on Suppliers Balances — see BalancesController@index. */
    canSettleInternally: { type: Boolean, default: false },
    storeInternalSettlementUrl: String,
    /* Where the settle/edit dialog fetches the partner's open invoices
       from. Must be DECLARED here, not just sent by the controller —
       an undeclared prop is simply absent from `props`, and the fetch
       then resolves 'undefined' against the current path. */
    internalSettlementInvoicesUrl: String,
});

/* ── Tabs — one per currency, same shape as TimeOfDeposits' tabs.
   All rows for every currency arrive already loaded, so switching
   tabs is instant, no reload. ─────────────────────────────────── */
const activeCurrency = ref(props.currencyCards?.[0]?.currency || '');

const activeCard = computed(() =>
    props.currencyCards.find(c => c.currency === activeCurrency.value) || props.currencyCards[0]
);

function currencyLabel(currency) {
    return currency === 'main_currency'
        ? `Main Currency (${props.mainFunctionalCurrency})`
        : currency;
}

/* ── Search — client-side, on top of the already-loaded tab data ── */
const search = ref('');
const filteredRows = computed(() => {
    const rows = activeCard.value?.rows || [];
    const q = search.value.toLowerCase();
    const matched = !search.value
        ? rows
        : rows.filter(r => (r.client_name || '').toLowerCase().includes(q));
    /* Net Balance, high → low. The SQL in the controller does order by
       net_balance desc, but that ordering is lost afterwards: subtractQuery()
       rewrites net_balance in place (down payments), and addMainCurrency()
       builds the main_currency rows from scratch in partner-id order. So the
       main_currency tab in particular arrived effectively unsorted, with
       credit (negative) balances scattered across pages. Sorting here fixes
       every tab at once and touches no balance math.
       Copy before .sort() — it mutates, and `rows` is the same array the
       KPI card total was computed from. */
    return [...matched].sort((a, b) => b.net_balance - a.net_balance);
});

/* ── Client-side pagination ──────────────────────────────────────
   All rows for the active currency were already fetched in one
   response (see controller docblock) — the fix here is specifically
   for the BROWSER: on companies with hundreds/thousands of partners,
   rendering every row into the DOM at once is what actually freezes
   the page, not the network request. Chunking the already-loaded
   array client-side fixes that immediately, with zero changes to
   any balance-calculation code.
   This is a first step (project owner's call) — if the JSON payload
   itself ever becomes the bottleneck for a very large company, real
   server-side pagination (paginating the customer list BEFORE running
   the down-payment matching logic) is the follow-up, not done here. */
const pageSize = 50;
const currentPage = ref(1);

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / pageSize)));

const pagedRows = computed(() => {
    const start = (currentPage.value - 1) * pageSize;
    return filteredRows.value.slice(start, start + pageSize);
});

/* ── Footer totals ───────────────────────────────────────────────
   Added because the page was genuinely misleading: with 50 rows per
   page and no total anywhere in the table, summing the visible column
   gives the PAGE total, which a reader naturally compares against the
   KPI card's TAB total. On company 92 that was 98,571,638.67 (page 1
   of 86 rows) vs 86,517,636.15 (all 86) — the gap was two credit
   balances sitting on page 2. Both numbers are now spelled out.
   `tabTotal` matches the KPI card exactly whenever the search box is
   empty; with a search active it's the total of what matched, and the
   label says so. */
const sumColumn = (rows, key) => rows.reduce((sum, r) => sum + Number(r[key] || 0), 0);
const totalsFor = rows => ({
    invoices: sumColumn(rows, 'invoices'),
    down_payments: sumColumn(rows, 'down_payments'),
    internal_settlements: sumColumn(rows, 'internal_settlements'),
    net_balance: sumColumn(rows, 'net_balance'),
});
const tabTotals = computed(() => totalsFor(filteredRows.value));
const pageTotals = computed(() => totalsFor(pagedRows.value));
const isPaginated = computed(() => filteredRows.value.length > pageSize);

// Number of columns after Net Balance (Statement Report, Internal
// Settlements, Invoice Report) — used to pad the footer rows so their
// cells line up with the header.
const trailingColumns = computed(() => (activeCard.value?.currency !== 'main_currency' ? 3 : 2));

/* ── Internal settlements ──────────────────────────────────────────
   Offsetting a partner who is BOTH a customer and a supplier against
   themselves: what they owe us as a customer is handed back to them
   as a supplier instead of each side paying the other in full. See
   App\Models\InternalSettlement.

   The column sits between the two report buttons and is ALWAYS shown,
   with a dash on the rows it cannot apply to. It was hidden entirely
   when no partner in the tab was in both roles, which read as "the
   feature is missing" on a company where nobody happens to be both —
   a dash says "not applicable here", which is the true answer. */

// Switching currency or searching should always land back on page 1 —
// otherwise "page 4" of one tab could silently show as an empty
// "page 4" of a shorter tab/search result.
function goToPage(page) {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
}

/* ── Number Color Rule (Style Guide §4) applied to net_balance:
   net_balance is "how much this partner still owes/is owed" —
   > 0  → still outstanding  → amber (pending/at-risk)
   = 0  → fully settled      → green (positive)
   < 0  → credit/overpayment → red (unusual, worth a second look) */
function balanceClass(balance) {
    if (balance > 0) return 'cvr-num-amber';
    if (balance < 0) return 'cvr-num-red';
    return 'cvr-num-green';
}

/* Same rule as balanceClass, as a raw custom-property value for the
   places that need an inline style rather than a class. */
function balanceColorVar(balance) {
    if (balance > 0) return 'var(--cvr-num-amber)';
    if (balance < 0) return 'var(--cvr-num-red)';
    return 'var(--cvr-num-green)';
}

const settleTarget = ref(null);
const settleForm = ref({ settlement_date: todayDate(), user_comment: '' });
const settleError = ref('');
const settleSubmitting = ref(false);
const settleLoading = ref(false);
/* The settlement being edited, or null when creating a new one. */
const settleEditing = ref(null);

/* Open invoices on each side, loaded when the dialog opens.
   { id, invoice_number, invoice_date, net_invoice_amount, open, allocated } */
const settleInvoices = ref({ customer: [], supplier: [] });
/* What the user has typed against each invoice: { [invoiceId]: amount } */
const settleAllocations = ref({ customer: {}, supplier: {} });

function allocationTotal(side) {
    return Object.values(settleAllocations.value[side] || {})
        .reduce((sum, v) => sum + (Number(v) || 0), 0);
}
const customerAllocated = computed(() => Math.round(allocationTotal('customer') * 100) / 100);
const supplierAllocated = computed(() => Math.round(allocationTotal('supplier') * 100) / 100);

/* Both sides have to move the same money — that is what makes it an
   offset rather than two unrelated adjustments. */
const sidesMatch = computed(() =>
    customerAllocated.value > 0 && Math.abs(customerAllocated.value - supplierAllocated.value) < 0.01
);

/* The ceiling. It is the smaller of the two sides now: the money has
   to come off real customer invoices AND land on real supplier
   invoices, so whichever side runs out first decides. */
const settleCeiling = computed(() => {
    const open = side => (settleInvoices.value[side] || []).reduce((sum, i) => sum + Number(i.open || 0), 0);
    return Math.round(Math.min(open('customer'), open('supplier')) * 100) / 100;
});

function openOf(side, invoiceId) {
    const inv = (settleInvoices.value[side] || []).find(i => i.id === invoiceId);
    return Number(inv?.open || 0);
}
function overAllocated(side, invoiceId) {
    return (Number(settleAllocations.value[side]?.[invoiceId]) || 0) > openOf(side, invoiceId) + 0.001;
}

async function loadSettleInvoices(row, settlement) {
    settleLoading.value = true;
    settleInvoices.value = { customer: [], supplier: [] };
    settleAllocations.value = { customer: {}, supplier: {} };
    try {
        const params = new URLSearchParams({ partner_id: row.client_id, currency: row.currency });
        if (settlement) params.append('internal_settlement_id', settlement.id);
        const res = await fetch(`${props.internalSettlementInvoicesUrl}?${params}`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        settleInvoices.value = { customer: data.customer || [], supplier: data.supplier || [] };
        // Editing opens on what this settlement already put where.
        for (const side of ['customer', 'supplier']) {
            for (const inv of settleInvoices.value[side]) {
                if (Number(inv.allocated) > 0) settleAllocations.value[side][inv.id] = Number(inv.allocated);
            }
        }
    } finally {
        settleLoading.value = false;
    }
}

function openSettle(row, settlement = null) {
    settleTarget.value = row;
    settleEditing.value = settlement;
    settleError.value = '';
    settleForm.value = {
        settlement_date: settlement?.date || todayDate(),
        user_comment: settlement?.user_comment || '',
    };
    loadSettleInvoices(row, settlement);
}

function closeSettle() {
    settleTarget.value = null;
    settleEditing.value = null;
    settleError.value = '';
}

function submitSettle() {
    if (!sidesMatch.value) {
        settleError.value = `The two sides must match — ${formatAmount(customerAllocated.value)} taken from customer invoices against ${formatAmount(supplierAllocated.value)} paid to supplier invoices.`;
        return;
    }
    for (const side of ['customer', 'supplier']) {
        for (const inv of settleInvoices.value[side]) {
            if (overAllocated(side, inv.id)) {
                settleError.value = `Invoice ${inv.invoice_number} only has ${formatAmount(inv.open)} ${settleTarget.value.currency} open.`;
                return;
            }
        }
    }

    const clean = side => Object.fromEntries(
        Object.entries(settleAllocations.value[side] || {})
            .map(([id, v]) => [id, Number(v) || 0])
            .filter(([, v]) => v > 0)
    );
    const payload = {
        partner_id: settleTarget.value.client_id,
        currency: settleTarget.value.currency,
        settlement_date: settleForm.value.settlement_date,
        user_comment: settleForm.value.user_comment,
        customer_allocations: clean('customer'),
        supplier_allocations: clean('supplier'),
    };

    settleSubmitting.value = true;
    const done = { onFinish: () => { settleSubmitting.value = false; closeSettle(); } };
    if (settleEditing.value) {
        router.put(settleEditing.value.update_url, payload, done);
    } else {
        router.post(props.storeInternalSettlementUrl, payload, done);
    }
}

/* Deleting the settlement IS the reversal — every allocation it made
   is taken back and both sides' invoices recompute. */
const settlementToDelete = ref(null);
function destroySettlement() {
    router.delete(settlementToDelete.value.delete_url, {
        onFinish: () => { settlementToDelete.value = null; closeSettle(); },
    });
}

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { maximumFractionDigits: 0 });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ title }}</h1>
            <p class="text-sm cvr-text-muted mb-6">{{ $t('Net balance per') }} {{ customersOrSupplierText.toLowerCase() }}{{ $t(', by currency') }}</p>

            <!-- KPI row — one card per currency, each with its own
                 drill-down buttons (hidden for the main-currency card,
                 matching the original's behavior — see controller note) -->
            <div class="cvr-kpi-row mb-6">
                <div v-for="card in currencyCards" :key="card.currency" class="cvr-kpi-card">
                    <div class="cvr-kpi-icon cvr-kpi-icon-blue">💰</div>
                    <div class="flex-1">
                        <p class="cvr-kpi-label">{{ currencyLabel(card.currency) }}</p>
                        <p class="cvr-kpi-value" :style="{ color: balanceColorVar(card.total) }">{{ formatAmount(card.total) }}</p>
                        <div v-if="card.all_invoices_url" class="flex gap-2 mt-2 flex-wrap">
                            <Link :href="card.all_invoices_url" class="cvr-btn-secondary px-2 py-1 rounded border text-xs whitespace-nowrap">{{ $t('All Invoices') }}</Link>
                            <Link :href="card.coming_dues_url" class="cvr-btn-primary px-2 py-1 rounded text-xs whitespace-nowrap">{{ $t('Coming Dues') }}</Link>
                            <Link :href="card.past_due_url" class="cvr-btn-danger px-2 py-1 rounded border text-xs whitespace-nowrap">{{ $t('Past Due') }}</Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Currency tabs -->
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div class="flex items-center gap-1 flex-wrap">
                    <button
                        v-for="card in currencyCards"
                        :key="card.currency"
                        @click="activeCurrency = card.currency"
                        class="cvr-filter-pill"
                        :class="{ 'cvr-filter-pill-active': activeCurrency === card.currency }"
                    >
                        {{ currencyLabel(card.currency) }}
                    </button>
                </div>

                <Link v-if="activeCard" :href="activeCard.bulk_statement_url" class="cvr-btn-primary px-3 py-1.5 rounded text-sm whitespace-nowrap">
                    {{ customersOrSupplierStatementText }} {{ $t('— All in') }} {{ currencyLabel(activeCard.currency) }}
                </Link>
            </div>

            <!-- Search -->
            <div class="cvr-search-bar flex items-center gap-2 px-3 py-1.5 mb-4 w-72">
                <span class="cvr-text-muted text-sm">🔍</span>
                <input
                    v-model="search"
                    type="text"
                    :placeholder="`Search ${customersOrSupplierText.toLowerCase()}...`"
                    class="bg-transparent outline-none text-sm w-full cvr-text-primary"
                />
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Name') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Currency') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Invoices') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Down Payments') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Net Balance') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Statement Report') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Internal Settlements') }}</th>
                            <th v-if="activeCard?.currency !== 'main_currency'" class="px-4 py-3 text-center">{{ $t('Invoice Report') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in pagedRows" :key="row.client_id" class="cvr-table-row">
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ (currentPage - 1) * pageSize + i + 1 }}</td>
                            <td class="px-4 py-3 text-start cvr-text-primary">
                                <span class="block truncate max-w-[280px]" :title="row.client_name">{{ row.client_name }}</span>
                            </td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ currencyLabel(row.currency) }}</td>
                            <td class="px-4 py-3 text-right cvr-text-secondary">{{ formatAmount(row.invoices) }}</td>
                            <td class="px-4 py-3 text-right cvr-text-secondary">{{ formatAmount(row.down_payments) }}</td>
                            <td class="px-4 py-3 text-right font-medium" :class="balanceClass(row.net_balance)">{{ formatAmount(row.net_balance) }}</td>
                            <td class="px-4 py-3 text-center">
                                <Link :href="row.statement_report_url" class="cvr-btn-primary px-3 py-1 rounded text-xs whitespace-nowrap">
                                    {{ customersOrSupplierStatementText }}
                                </Link>
                            </td>
                            <!--
                                Internal settlement. A dash means it does not apply
                                to this row — the partner is a customer only, so
                                there is no supplier side of theirs to offset
                                against. Where it does apply, whatever has already
                                been offset is shown next to the button.
                            -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <template v-if="row.is_dual_role">
                                    <span v-if="row.internal_settlements" class="cvr-num cvr-text-secondary me-2">{{ formatAmount(row.internal_settlements) }}</span>
                                    <button
                                        v-if="canSettleInternally && row.can_settle"
                                        @click="openSettle(row)"
                                        class="cvr-btn-secondary px-3 py-1 rounded border text-xs whitespace-nowrap"
                                    >⇄ {{ $t('Settle') }}</button>
                                    <span v-else-if="!row.internal_settlements" class="cvr-text-muted">—</span>
                                </template>
                                <span v-else class="cvr-text-muted">—</span>
                            </td>
                            <td v-if="row.invoice_report_url" class="px-4 py-3 text-center">
                                <Link :href="row.invoice_report_url" class="cvr-btn-copper px-3 py-1 rounded text-xs whitespace-nowrap">
                                    {{ $t('Invoices Report') }}
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td :colspan="3 + 3 + trailingColumns" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No balances found.') }}</td>
                        </tr>
                    </tbody>

                    <!-- Totals — page total only when pagination is actually
                         in effect (otherwise it's the same number twice). -->
                    <tfoot v-if="filteredRows.length" class="cvr-table-head">
                        <tr v-if="isPaginated">
                            <td colspan="3" class="px-4 py-3 text-right font-medium cvr-text-secondary">
                                {{ $t('Total — this page (') }}{{ pagedRows.length }} {{ $t('of') }} {{ filteredRows.length }})
                            </td>
                            <td class="px-4 py-3 text-right font-medium cvr-text-secondary">{{ formatAmount(pageTotals.invoices) }}</td>
                            <td class="px-4 py-3 text-right font-medium cvr-text-secondary">{{ formatAmount(pageTotals.down_payments) }}</td>
                            <td class="px-4 py-3 text-right font-medium" :class="balanceClass(pageTotals.net_balance)">{{ formatAmount(pageTotals.net_balance) }}</td>
                            <!-- Statement Report column — nothing to total. -->
                            <td></td>
                            <td class="px-4 py-3 text-center font-medium cvr-text-secondary">{{ formatAmount(pageTotals.internal_settlements) }}</td>
                            <td v-if="activeCard?.currency !== 'main_currency'"></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right font-semibold cvr-text-primary">
                                {{ search ? `Total — all ${filteredRows.length} matching rows` : `Total — ${currencyLabel(activeCard.currency)}` }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold cvr-text-primary">{{ formatAmount(tabTotals.invoices) }}</td>
                            <td class="px-4 py-3 text-right font-semibold cvr-text-primary">{{ formatAmount(tabTotals.down_payments) }}</td>
                            <td class="px-4 py-3 text-right font-semibold" :class="balanceClass(tabTotals.net_balance)">{{ formatAmount(tabTotals.net_balance) }}</td>
                            <td></td>
                            <td class="px-4 py-3 text-center font-semibold cvr-text-primary">{{ formatAmount(tabTotals.internal_settlements) }}</td>
                            <td v-if="activeCard?.currency !== 'main_currency'"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination — client-side, over the already-loaded tab data.
                 See the script's "Client-side pagination" note for why. -->
            <div v-if="filteredRows.length > pageSize" class="flex items-center justify-between mt-4 flex-wrap gap-3">
                <p class="text-xs cvr-text-muted">
                    {{ $t('Showing') }} {{ (currentPage - 1) * pageSize + 1 }}–{{ Math.min(currentPage * pageSize, filteredRows.length) }} {{ $t('of') }} {{ filteredRows.length }}
                </p>
                <div class="flex items-center gap-1 flex-wrap">
                    <button
                        @click="goToPage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        class="cvr-filter-pill"
                        :class="{ 'opacity-40 cursor-not-allowed': currentPage === 1 }"
                    >{{ $t('‹ Prev') }}</button>
                    <span class="text-xs cvr-text-muted px-2">{{ $t('Page') }} {{ currentPage }} {{ $t('of') }} {{ totalPages }}</span>
                    <button
                        @click="goToPage(currentPage + 1)"
                        :disabled="currentPage === totalPages"
                        class="cvr-filter-pill"
                        :class="{ 'opacity-40 cursor-not-allowed': currentPage === totalPages }"
                    >{{ $t('Next ›') }}</button>
                </div>
            </div>
        </div>

        <!--
            Internal Settlement — offsetting a partner against themselves.

            The balance is stated at the top and the input is capped by it,
            because the whole decision the user is making is "how much of
            what he owes me do I hand back to him as a supplier" — a number
            that means nothing without the one it comes out of.
        -->
        <teleport to="body">
            <div v-if="settleTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeSettle">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto mx-4">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-medium cvr-text-primary">
                            {{ settleEditing ? $t('Edit Internal Settlement') : $t('Internal Settlement') }}
                        </h2>
                        <button @click="closeSettle" class="cvr-action-btn" :title="$t('Close')">✕</button>
                    </div>

                    <p class="text-sm cvr-text-secondary mb-1">{{ settleTarget.client_name }}</p>
                    <p class="text-xs cvr-text-muted mb-4">
                        {{ $t('Take the money off the customer invoices on the left, and put the same money onto the supplier invoices on the right. Both sides must total the same — that is what makes it an offset rather than two separate adjustments.') }}
                    </p>

                    <div v-if="settleLoading" class="py-8 text-center cvr-text-muted text-sm">{{ $t('Loading…') }}</div>

                    <template v-else>
                        <div v-if="settleCeiling <= 0" class="cvr-card-bg cvr-border border rounded p-3 mb-4 text-sm cvr-text-muted">
                            {{ $t('Nothing can be settled: this partner needs open invoices on BOTH sides in this currency.') }}
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                            <!-- Customer side: where the money comes FROM -->
                            <div class="cvr-card-bg cvr-border border rounded-lg p-3">
                                <p class="text-xs font-semibold cvr-text-secondary uppercase tracking-wide mb-2">
                                    {{ $t('Take from these customer invoices') }}
                                </p>
                                <table class="w-full text-xs">
                                    <thead class="cvr-table-head">
                                        <tr>
                                            <th class="px-2 py-1 text-start">{{ $t('Invoice #') }}</th>
                                            <th class="px-2 py-1 text-right">{{ $t('Open') }}</th>
                                            <th class="px-2 py-1 text-right w-28">{{ $t('Allocate') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="inv in settleInvoices.customer" :key="inv.id" class="cvr-table-row">
                                            <td class="px-2 py-1 cvr-text-primary whitespace-nowrap">{{ inv.invoice_number }}</td>
                                            <td class="px-2 py-1 text-right cvr-num cvr-text-secondary">{{ formatAmount(inv.open) }}</td>
                                            <td class="px-2 py-1">
                                                <input
                                                    v-model="settleAllocations.customer[inv.id]"
                                                    type="number" step="0.01" min="0" :max="inv.open"
                                                    class="cvr-input w-full px-2 py-1 rounded text-end"
                                                    :class="{ 'cvr-num-red': overAllocated('customer', inv.id) }"
                                                />
                                            </td>
                                        </tr>
                                        <tr v-if="!settleInvoices.customer.length">
                                            <td colspan="3" class="px-2 py-4 text-center cvr-text-muted">{{ $t('No open invoices found for this customer in this currency.') }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot v-if="settleInvoices.customer.length" class="cvr-table-head">
                                        <tr>
                                            <td colspan="2" class="px-2 py-1 text-end cvr-text-secondary">{{ $t('Allocated') }}</td>
                                            <td class="px-2 py-1 text-end cvr-num font-semibold">{{ formatAmount(customerAllocated) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Supplier side: where the same money goes TO -->
                            <div class="cvr-card-bg cvr-border border rounded-lg p-3">
                                <p class="text-xs font-semibold cvr-text-secondary uppercase tracking-wide mb-2">
                                    {{ $t('Pay these supplier invoices') }}
                                </p>
                                <table class="w-full text-xs">
                                    <thead class="cvr-table-head">
                                        <tr>
                                            <th class="px-2 py-1 text-start">{{ $t('Invoice #') }}</th>
                                            <th class="px-2 py-1 text-right">{{ $t('Open') }}</th>
                                            <th class="px-2 py-1 text-right w-28">{{ $t('Allocate') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="inv in settleInvoices.supplier" :key="inv.id" class="cvr-table-row">
                                            <td class="px-2 py-1 cvr-text-primary whitespace-nowrap">{{ inv.invoice_number }}</td>
                                            <td class="px-2 py-1 text-right cvr-num cvr-text-secondary">{{ formatAmount(inv.open) }}</td>
                                            <td class="px-2 py-1">
                                                <input
                                                    v-model="settleAllocations.supplier[inv.id]"
                                                    type="number" step="0.01" min="0" :max="inv.open"
                                                    class="cvr-input w-full px-2 py-1 rounded text-end"
                                                    :class="{ 'cvr-num-red': overAllocated('supplier', inv.id) }"
                                                />
                                            </td>
                                        </tr>
                                        <tr v-if="!settleInvoices.supplier.length">
                                            <td colspan="3" class="px-2 py-4 text-center cvr-text-muted">{{ $t('No open invoices found for this supplier in this currency.') }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot v-if="settleInvoices.supplier.length" class="cvr-table-head">
                                        <tr>
                                            <td colspan="2" class="px-2 py-1 text-end cvr-text-secondary">{{ $t('Allocated') }}</td>
                                            <td class="px-2 py-1 text-end cvr-num font-semibold">{{ formatAmount(supplierAllocated) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- The one number that decides whether this can be saved. -->
                        <div class="cvr-card-bg cvr-border border rounded p-3 mb-4 flex items-center justify-between">
                            <span class="text-sm cvr-text-secondary">{{ $t('Settlement Amount') }}</span>
                            <span class="cvr-num font-medium" :class="sidesMatch ? 'cvr-num-green' : 'cvr-num-red'">
                                {{ formatAmount(customerAllocated) }} / {{ formatAmount(supplierAllocated) }} {{ settleTarget.currency }}
                            </span>
                        </div>

                        <div class="cvr-form-grid-2 mb-3">
                            <div>
                                <label class="cvr-form-label">{{ $t('Date') }}</label>
                                <input v-model="settleForm.settlement_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Comment') }}</label>
                                <input v-model="settleForm.user_comment" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                        </div>

                        <p v-if="settleError" class="text-sm mb-3" style="color: var(--cvr-danger-text);">{{ settleError }}</p>

                        <!-- Settlements already recorded on this balance, so the
                             same offset is not entered twice, and a wrong one can
                             be corrected or taken back. -->
                        <div v-if="settleTarget.settlements?.length && !settleEditing" class="mb-4">
                            <p class="text-xs cvr-text-muted mb-2">{{ $t('Already Settled') }}</p>
                            <table class="w-full text-xs">
                                <tbody>
                                    <tr v-for="settlement in settleTarget.settlements" :key="settlement.id" class="cvr-table-row">
                                        <td class="py-1 pe-2 cvr-text-secondary whitespace-nowrap">{{ settlement.date_formatted }}</td>
                                        <td class="py-1 pe-2 cvr-num cvr-text-primary">{{ formatAmount(settlement.amount) }}</td>
                                        <td class="py-1 pe-2 cvr-text-muted">
                                            {{ settlement.customer_invoice_numbers?.join(' / ') }}
                                            <span v-if="settlement.supplier_invoice_numbers?.length"> → {{ settlement.supplier_invoice_numbers.join(' / ') }}</span>
                                        </td>
                                        <td class="py-1 text-end whitespace-nowrap">
                                            <button @click="openSettle(settleTarget, settlement)" class="cvr-action-btn" :title="$t('Edit')">✎</button>
                                            <button @click="settlementToDelete = settlement" class="cvr-action-btn" :title="$t('Delete')">🗑️</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <button @click="closeSettle" class="cvr-btn-secondary px-4 py-2 rounded border text-sm">{{ $t('Cancel') }}</button>
                            <button
                                @click="submitSettle"
                                :disabled="settleSubmitting || !sidesMatch"
                                class="cvr-btn-primary px-4 py-2 rounded text-sm"
                                :class="{ 'opacity-50 cursor-not-allowed': settleSubmitting || !sidesMatch }"
                            >{{ $t('Save') }}</button>
                        </div>
                    </template>
                </div>
            </div>

            <div v-if="settlementToDelete" class="fixed inset-0 bg-black/60 flex items-center justify-center z-[60]" @click.self="settlementToDelete = null">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-md mx-4">
                    <h2 class="text-lg font-medium cvr-text-primary mb-2">{{ $t('Delete Internal Settlement') }}</h2>
                    <p class="text-sm cvr-text-secondary mb-4">
                        {{ $t('Both balances go back to what they were before this settlement, and it disappears from both statements.') }}
                    </p>
                    <div class="flex items-center justify-end gap-2">
                        <button @click="settlementToDelete = null" class="cvr-btn-secondary px-4 py-2 rounded border text-sm">{{ $t('Cancel') }}</button>
                        <button @click="destroySettlement" class="cvr-btn-danger px-4 py-2 rounded text-sm">{{ $t('Delete') }}</button>
                    </div>
                </div>
            </div>
        </teleport>
    </AppLayout>
</template>
