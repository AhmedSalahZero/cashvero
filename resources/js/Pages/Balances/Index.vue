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
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    modelType: String,
    title: String,
    customersOrSupplierText: String,
    customersOrSupplierStatementText: String,
    mainFunctionalCurrency: String,
    currencyCards: Array, // [{ currency, total, rows, all_invoices_url, past_due_url, coming_dues_url, bulk_statement_url }]
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
    net_balance: sumColumn(rows, 'net_balance'),
});
const tabTotals = computed(() => totalsFor(filteredRows.value));
const pageTotals = computed(() => totalsFor(pagedRows.value));
const isPaginated = computed(() => filteredRows.value.length > pageSize);

// Number of columns after Net Balance (the report buttons) — used to pad
// the footer rows so their cells line up with the header.
const trailingColumns = computed(() => (activeCard.value?.currency !== 'main_currency' ? 2 : 1));

// Switching currency or searching should always land back on page 1 —
// otherwise "page 4" of one tab could silently show as an empty
// "page 4" of a shorter tab/search result.
watch([activeCurrency, search], () => { currentPage.value = 1; });

function goToPage(page) {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
}
/* ── Number Color Rule (Style Guide §4) applied to net_balance:
   net_balance is "how much this partner still owes/is owed" —
   > 0  → still outstanding  → amber (pending/at-risk)
   = 0  → fully settled      → green (positive)
   < 0  → credit/overpayment → red (unusual, worth a second look)
   This reading was not spelled out in the original Blade (plain
   black text everywhere) — flagging it here as a judgment call
   made during migration, easy to adjust if a different meaning
   is intended. ─────────────────────────────────────────────────── */
function balanceClass(balance) {
    if (balance > 0) return 'cvr-num-amber';
    if (balance < 0) return 'cvr-num-red';
    return 'cvr-num-green';
}

// .cvr-kpi-value sets its own `color`, and it's declared AFTER the
// .cvr-num-* classes in app.css — so on an element with BOTH classes,
// cvr-kpi-value silently wins regardless of class order in the HTML.
// No other page in this project combines them for that reason. An
// inline style always wins over both, so that's how the KPI cards'
// numbers get their Number-Color-Rule color instead.
function balanceColorVar(balance) {
    if (balance > 0) return 'var(--cvr-num-amber)';
    if (balance < 0) return 'var(--cvr-num-red)';
    return 'var(--cvr-num-green)';
}

// Whole numbers only — balances here are read at a glance and the
// piasters were pure noise. Also puts this page in step with the same
// report in system.veroanalysis.com, whose Blade has always rendered
// these through number_format() with no decimals.
function formatAmount(value) {
    return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ title }}</h1>
            <p class="text-sm cvr-text-muted mb-6">Net balance per {{ customersOrSupplierText.toLowerCase() }}, by currency</p>

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
                            <Link :href="card.all_invoices_url" class="cvr-btn-secondary px-2 py-1 rounded border text-xs whitespace-nowrap">All Invoices</Link>
                            <Link :href="card.coming_dues_url" class="cvr-btn-primary px-2 py-1 rounded text-xs whitespace-nowrap">Coming Dues</Link>
                            <Link :href="card.past_due_url" class="cvr-btn-danger px-2 py-1 rounded border text-xs whitespace-nowrap">Past Due</Link>
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
                    {{ customersOrSupplierStatementText }} — All in {{ currencyLabel(activeCard.currency) }}
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
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-center">Currency</th>
                            <th class="px-4 py-3 text-right">Invoices</th>
                            <th class="px-4 py-3 text-right">Down Payments</th>
                            <th class="px-4 py-3 text-right">Net Balance</th>
                            <th class="px-4 py-3 text-center">Statement Report</th>
                            <th v-if="activeCard?.currency !== 'main_currency'" class="px-4 py-3 text-center">Invoice Report</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in pagedRows" :key="row.client_id" class="cvr-table-row">
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ (currentPage - 1) * pageSize + i + 1 }}</td>
                            <td class="px-4 py-3 text-left cvr-text-primary">
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
                            <td v-if="row.invoice_report_url" class="px-4 py-3 text-center">
                                <Link :href="row.invoice_report_url" class="cvr-btn-copper px-3 py-1 rounded text-xs whitespace-nowrap">
                                    Invoices Report
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="filteredRows.length === 0">
                            <td :colspan="3 + 3 + trailingColumns" class="px-4 py-8 text-center cvr-text-muted">No balances found.</td>
                        </tr>
                    </tbody>

                    <!-- Totals — page total only when pagination is actually
                         in effect (otherwise it's the same number twice). -->
                    <tfoot v-if="filteredRows.length" class="cvr-table-head">
                        <tr v-if="isPaginated">
                            <td colspan="3" class="px-4 py-3 text-right font-medium cvr-text-secondary">
                                Total — this page ({{ pagedRows.length }} of {{ filteredRows.length }})
                            </td>
                            <td class="px-4 py-3 text-right font-medium cvr-text-secondary">{{ formatAmount(pageTotals.invoices) }}</td>
                            <td class="px-4 py-3 text-right font-medium cvr-text-secondary">{{ formatAmount(pageTotals.down_payments) }}</td>
                            <td class="px-4 py-3 text-right font-medium" :class="balanceClass(pageTotals.net_balance)">{{ formatAmount(pageTotals.net_balance) }}</td>
                            <td :colspan="trailingColumns"></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right font-semibold cvr-text-primary">
                                {{ search ? `Total — all ${filteredRows.length} matching rows` : `Total — ${currencyLabel(activeCard.currency)}` }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold cvr-text-primary">{{ formatAmount(tabTotals.invoices) }}</td>
                            <td class="px-4 py-3 text-right font-semibold cvr-text-primary">{{ formatAmount(tabTotals.down_payments) }}</td>
                            <td class="px-4 py-3 text-right font-semibold" :class="balanceClass(tabTotals.net_balance)">{{ formatAmount(tabTotals.net_balance) }}</td>
                            <td :colspan="trailingColumns"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination — client-side, over the already-loaded tab data.
                 See the script's "Client-side pagination" note for why. -->
            <div v-if="filteredRows.length > pageSize" class="flex items-center justify-between mt-4 flex-wrap gap-3">
                <p class="text-xs cvr-text-muted">
                    Showing {{ (currentPage - 1) * pageSize + 1 }}–{{ Math.min(currentPage * pageSize, filteredRows.length) }} of {{ filteredRows.length }}
                </p>
                <div class="flex items-center gap-1 flex-wrap">
                    <button
                        @click="goToPage(currentPage - 1)"
                        :disabled="currentPage === 1"
                        class="cvr-filter-pill"
                        :class="{ 'opacity-40 cursor-not-allowed': currentPage === 1 }"
                    >‹ Prev</button>
                    <span class="text-xs cvr-text-muted px-2">Page {{ currentPage }} of {{ totalPages }}</span>
                    <button
                        @click="goToPage(currentPage + 1)"
                        :disabled="currentPage === totalPages"
                        class="cvr-filter-pill"
                        :class="{ 'opacity-40 cursor-not-allowed': currentPage === totalPages }"
                    >Next ›</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
