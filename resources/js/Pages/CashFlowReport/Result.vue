<script setup>
import { ref, computed, reactive, nextTick } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const props = defineProps({
    company: Object,
    weeks: Object,           // { weekAndYear: weekNumber }
    allCurrencies: Array,
    finalResult: Object,     // { currency: { customers|suppliers|cash_expenses|lg: { rowName: { weeks:{}, total:{}, [subRowName]: {weeks:{}} } } } }
    dates: Object,           // { weekAndYear: { start_date, end_date } }
    pastDueCustomerInvoices: Object, // { currency: [invoice, ...] }
    customerDueInvoices: Object,     // { currency: [{ week_start_date, amount }] }
    pastDueSupplierInvoices: Array,
    supplierDueInvoices: Array,
    pastDueInstallments: Array,      // raw loan schedules, now including loan_name
    pastDueLoanInstallments: Array,  // [{ week_start_date, amount }]
    months: Array,
    days: Array,
    reportInterval: String,
    noRowHeaders: Number,
    title: String,
    currencyName: String,
    mainFunctionalCurrency: String,
    contractCode: String,
    letterOfGuaranteeModelData: Object,
    incomingTransferModelData: Object,
    crossCurrencyNotes: Object,
    cashflowReport: Object, // null | { id, name, is_contract }
    cashProjections: Object, // { in: [{id,name,amounts}], out: [...] }
    urls: Object,
});

const weekKeys = computed(() => Object.keys(props.weeks || {}));
const isContract = computed(() => !!props.contractCode);

/* ── Display-only currency tab label ─────────────────────────────
   A Contract Cash Flow Report is always fully converted into the
   company's main functional currency under the hood, even when the
   contract itself is in a foreign currency (e.g. a USD contract's
   report is entirely EGP-equivalent figures) — see
   CashFlowContractDetailPeriodBatchLoader, which unconditionally
   converts every movement via the FX rate at each date. The tab used
   to just show the raw currency code (e.g. "USD"), which read as if
   the numbers underneath were real USD amounts. This only changes
   what's shown on the tab button — `c` itself (used as the lookup
   key into finalResult/pastDueCustomerInvoices/etc., and sent back
   to the server on export and on due-invoice adjustments) is
   untouched, so no server-side filtering logic is affected. */
function tabLabel(currency) {
    if (isContract.value && props.mainFunctionalCurrency && currency !== props.mainFunctionalCurrency) {
        return `${currency} (${props.mainFunctionalCurrency} Equivalent)`;
    }
    return currency;
}

/* ── Row-name constants that never get an expandable breakdown and
   are computed on the fly (accumulated from every other row), not
   read directly from finalResult — mirrors the original blade's own
   hardcoded $hasSubRows exception list exactly. */
const NO_SUBROW_NAMES = new Set([
    'Customers Past Due Invoices', 'Suppliers Past Due Invoices', 'Loan Past Due Installments',
    'Net Cash (+/-)', 'Accumulated Net Cash (+/-)', 'Total Cash Inflow', 'Total Cash Outflow',
]);
const HIGHLIGHT_NAMES = new Set([
    'Total Cash Inflow', 'Total Cash Outflow', 'Total Cash',
    'Net Cash (+/-)', 'Accumulated Net Cash (+/-)',
]);
// Rows where an invoice issued in the currently-viewed currency can have
// been collected in a different currency. On those rows a per-cell "ℹ️"
// marker surfaces the informational, non-cash-additive equivalent so the
// user isn't confused by an invoice appearing uncollected on its own tab.
const CROSS_CURRENCY_ROW_NAMES = new Set([
    'Checks Collected', 'Bank Deposits', 'Cash Collections', 'Incoming Transfers',
]);

function sumAllRowsAtWeek(allTotals, weekKey) {
    return Object.values(allTotals).reduce((s, weekMap) => s + (Number(weekMap[weekKey]) || 0), 0);
}
function sumRowsAfterKeyAtWeek(allTotals, afterKey, weekKey) {
    const keys = Object.keys(allTotals);
    const idx = keys.indexOf(afterKey);
    const sliceKeys = idx >= 0 ? keys.slice(idx + 1) : keys;
    return sliceKeys.reduce((s, k) => s + (Number(allTotals[k][weekKey]) || 0), 0);
}

/**
 * ⚠️ This is a direct transliteration of the row-computation loop
 * embedded inside the original Blade template (admin/reports/
 * contract-cash-flow-report.blade.php + cash-flow-sub-row.blade.php).
 * The original PHP mutates $finalResult IN PLACE as it renders each
 * row top-to-bottom (Total Cash Inflow/Outflow, Net Cash, Accumulated
 * Net Cash all depend on rows rendered earlier in the SAME pass), so
 * row order matters and this function replicates that exact order
 * and mutation pattern on a local deep-cloned copy — never touching
 * the props themselves.
 */
function buildCurrencyTable(currency) {
    const local = JSON.parse(JSON.stringify(props.finalResult?.[currency] || {}));
    const customerDue = props.customerDueInvoices?.[currency] || [];
    const supplierDue = props.supplierDueInvoices || [];
    const loanDue = props.pastDueLoanInstallments || [];
    const allMainRowsTotals = {};
    const mainRows = [];

    for (const mainReportKey of ['customers', 'suppliers', 'cash_expenses', 'lg']) {
        const group = local[mainReportKey] || {};
        for (const parentKeyName of Object.keys(group)) {
            const rowData = group[parentKeyName] || {};
            const hasSubRows = !NO_SUBROW_NAMES.has(parentKeyName);
            const subRowKeys = Object.keys(rowData).filter(k => k !== 'total');

            let currentMainRowTotal = 0;
            const cells = [];

            for (const weekKey of weekKeys.value) {
                let currentValue = 0;

                if (parentKeyName === 'Total Cash Inflow') {
                    currentValue = sumAllRowsAtWeek(allMainRowsTotals, weekKey);
                    rowData.weeks = rowData.weeks || {};
                    rowData.weeks[weekKey] = currentValue;
                }
                if (parentKeyName === 'Total Cash Outflow') {
                    currentValue = sumRowsAfterKeyAtWeek(allMainRowsTotals, 'Total Cash Inflow', weekKey);
                    rowData.weeks = rowData.weeks || {};
                    rowData.weeks[weekKey] = currentValue;
                    const totalCashInForWeek = local.customers?.['Total Cash Inflow']?.weeks?.[weekKey] || 0;
                    const netCashAtWeek = totalCashInForWeek - currentValue;
                    local.cash_expenses = local.cash_expenses || {};
                    local.cash_expenses['Net Cash (+/-)'] = local.cash_expenses['Net Cash (+/-)'] || {};
                    local.cash_expenses['Net Cash (+/-)'].weeks = local.cash_expenses['Net Cash (+/-)'].weeks || {};
                    local.cash_expenses['Net Cash (+/-)'].weeks[weekKey] = netCashAtWeek;
                    const netWeeksSoFar = local.cash_expenses['Net Cash (+/-)'].weeks;
                    local.cash_expenses['Accumulated Net Cash (+/-)'] = local.cash_expenses['Accumulated Net Cash (+/-)'] || {};
                    local.cash_expenses['Accumulated Net Cash (+/-)'].weeks = local.cash_expenses['Accumulated Net Cash (+/-)'].weeks || {};
                    local.cash_expenses['Accumulated Net Cash (+/-)'].weeks[weekKey] =
                        Object.values(netWeeksSoFar).reduce((s, v) => s + (Number(v) || 0), 0);
                }

                if (rowData.weeks && rowData.weeks[weekKey] !== undefined) {
                    currentValue = rowData.weeks[weekKey];
                }
                if (rowData.total && rowData.total[weekKey] !== undefined) {
                    currentValue = rowData.total[weekKey];
                    currentMainRowTotal += Number(currentValue) || 0;
                }

                if (parentKeyName === 'Customers Past Due Invoices') {
                    const startDate = props.dates?.[weekKey]?.start_date;
                    const match = customerDue.find(item => item.week_start_date === startDate);
                    currentValue = match ? Number(match.amount) : 0;
                    currentMainRowTotal += currentValue;
                }
                if (parentKeyName === 'Suppliers Past Due Invoices') {
                    const startDate = props.dates?.[weekKey]?.start_date;
                    const match = supplierDue.find(item => item.week_start_date === startDate);
                    currentValue = match ? Number(match.amount) : 0;
                    currentMainRowTotal += currentValue;
                }
                if (parentKeyName === 'Loan Past Due Installments') {
                    const startDate = props.dates?.[weekKey]?.start_date;
                    const match = loanDue.find(item => item.week_start_date === startDate);
                    currentValue = match ? Number(match.amount) : 0;
                    currentMainRowTotal += currentValue;
                }

                allMainRowsTotals[parentKeyName] = allMainRowsTotals[parentKeyName] || {};
                allMainRowsTotals[parentKeyName][weekKey] = (allMainRowsTotals[parentKeyName][weekKey] || 0) + (Number(currentValue) || 0);

                cells.push(Number(currentValue) || 0);
            }

            if (parentKeyName === 'Accumulated Net Cash (+/-)') {
                currentMainRowTotal = 0;
            }

            const subRows = subRowKeys.map(subKey => buildSubRow(local, mainReportKey, parentKeyName, subKey, customerDue, supplierDue, loanDue));

            mainRows.push({
                key: `${mainReportKey}:${parentKeyName}`,
                mainReportKey,
                name: parentKeyName,
                hasSubRows,
                highlight: HIGHLIGHT_NAMES.has(parentKeyName),
                cells,
                total: currentMainRowTotal,
                subRows,
            });
        }
    }
    return mainRows;
}

function buildSubRow(local, mainReportKey, parentKeyName, subKey, customerDue, supplierDue, loanDue) {
    const subData = local[mainReportKey]?.[parentKeyName]?.[subKey] || {};
    let currentSubTotal = 0;
    const cells = weekKeys.value.map(weekKey => {
        let currentValue = subData.weeks?.[weekKey] ?? 0;
        if (subKey === 'Customers Past Due Invoices') {
            const startDate = props.dates?.[weekKey]?.start_date;
            const match = customerDue.find(item => item.week_start_date === startDate);
            currentValue = match ? Number(match.amount) : 0;
        }
        if (subKey === 'Suppliers Past Due Invoices') {
            const startDate = props.dates?.[weekKey]?.start_date;
            const match = supplierDue.find(item => item.week_start_date === startDate);
            currentValue = match ? Number(match.amount) : 0;
        }
        if (subKey === 'Loan Past Due Installments') {
            const startDate = props.dates?.[weekKey]?.start_date;
            const match = loanDue.find(item => item.week_start_date === startDate);
            currentValue = match ? Number(match.amount) : 0;
        }
        currentValue = Number(currentValue) || 0;
        currentSubTotal += currentValue;
        return currentValue;
    });
    const lgBreakdown = (parentKeyName === 'Cancelled LGs Cash Cover' || parentKeyName === 'Issued LG Cash Cover')
        ? weekKeys.value.map(weekKey =>
            // New shape (Company Cash Flow): namespaced by row name first,
            // so Cancelled and Issued don't collide on the same lgType.
            // Falls back to the old flat shape (still used by Contract Cash
            // Flow, untouched) so that report keeps working unchanged.
            props.letterOfGuaranteeModelData?.[parentKeyName]?.[subKey]?.weeks?.[weekKey]
            ?? props.letterOfGuaranteeModelData?.[subKey]?.weeks?.[weekKey]
            ?? [])
        : null;
    const incomingTransferBreakdown = parentKeyName === 'Incoming Transfers'
        ? weekKeys.value.map(weekKey => props.incomingTransferModelData?.[subKey]?.weeks?.[weekKey] || [])
        : null;
    return {
        key: subKey,
        label: subData.label || subKey,
        cells,
        total: currentSubTotal,
        lgBreakdown,
        incomingTransferBreakdown,
        checksCollectedInfo: subData.checks_collected_info || null,
    };
}

const tablesByCurrency = computed(() => {
    const out = {};
    for (const c of props.allCurrencies) out[c] = buildCurrencyTable(c);
    return out;
});

/* ── Tabs: currencies + the two projection tabs ──────────────────── */
const activeTab = ref(props.allCurrencies[0]);

/* ── Expand/collapse ──────────────────────────────────────────── */
const expandedRows = ref(new Set());
const expandAll = ref(false);
function toggleRow(key) {
    const next = new Set(expandedRows.value);
    if (next.has(key)) next.delete(key); else next.add(key);
    expandedRows.value = next;
}
function toggleExpandAll() {
    expandAll.value = !expandAll.value;
    if (!expandAll.value) { expandedRows.value = new Set(); return; }
    const all = new Set();
    for (const rows of Object.values(tablesByCurrency.value)) {
        for (const r of rows) if (r.hasSubRows) all.add(r.key);
    }
    expandedRows.value = all;
}

/* ── Period column headers ───────────────────────────────────── */
function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return d;
    return `${String(dt.getDate()).padStart(2, '0')}-${String(dt.getMonth() + 1).padStart(2, '0')}-${dt.getFullYear()}`;
}
function fmtMonthYear(d) {
    if (!d) return '';
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return d;
    return `${String(dt.getMonth() + 1).padStart(2, '0')}-${dt.getFullYear()}`;
}
const periodLabels = computed(() => {
    if (props.reportInterval === 'weekly') return weekKeys.value.map(wk => `Week ${props.weeks[wk]}`);
    if (props.reportInterval === 'monthly') return (props.months || []).map((m, i, arr) =>
        (i === 0 || i === arr.length - 1) ? fmtDate(m) : fmtMonthYear(m));
    return (props.days || []).map(fmtDate);
});

function fmt(n) {
    return Math.round(Number(n) || 0).toLocaleString('en-EG');
}

/* ── Colored Excel export (project-owner requested, "same as the
   Statements reports") ──────────────────────────────────────────
   Built from THIS component's own already-rendered tablesByCurrency
   for the active currency tab — see buildCurrencyTable()'s docblock
   for why that row-mutation pass is deliberately kept in Vue and
   never re-implemented in PHP. Submitted via a plain, non-Inertia
   HTML form POST — the same "native form dodges Inertia's ajax()
   branch" technique already used elsewhere in this codebase (see
   roadmap bug #38) — so the browser handles the file download
   natively, no blob/axios plumbing needed. */
const exportFormRef = ref(null);
const exportPayloadJson = ref('');
const csrfTokenValue = document.querySelector('meta[name="csrf-token"]')?.content || '';

function exportExcel() {
    const rows = [];
    for (const row of (tablesByCurrency.value[activeTab.value] || [])) {
        let type = 'row';
        if (row.name === 'Net Cash (+/-)' || row.name === 'Accumulated Net Cash (+/-)') type = 'net';
        else if (row.highlight) type = 'total';
        rows.push({ label: row.name, type, values: row.cells, total: row.total });
        if (row.hasSubRows) {
            for (const sub of row.subRows) {
                rows.push({ label: `— ${sub.key}`, type: 'row', values: sub.cells, total: sub.total });
            }
        }
    }

    /* Projected Other Cash In/Out items (see saveProjectionTab above) were previously omitted
       from the Excel export entirely — confirmed via the Stage 2 audit follow-up discussion,
       2026-07-24 — even though they're real, saved data (CashProjection records) visible on
       screen in their own tabs. CashProjection has no currency column (confirmed against the
       model), so these aren't tied to any one currency the way the rows above are; they're
       appended once, as their own labeled section, to whichever currency is being exported —
       matching how they're already shown on screen, independent of the currency tabs.
       Presentation-only: this does NOT fold these amounts into Total Cash Inflow/Outflow, Net
       Cash, or Accumulated Net Cash — whether they should count toward those is a separate
       business-logic question, not decided here, so those totals are left exactly as before. */
    const blankPeriodValues = new Array(periodLabels.value.length).fill(0);
    if (projectionRows.in.length) {
        rows.push({ label: 'Projected Other Cash In Items', type: 'section', values: blankPeriodValues, total: 0 });
        for (const row of projectionRows.in) {
            const total = row.amounts.reduce((s, v) => s + (Number(v) || 0), 0);
            rows.push({ label: row.name || '(unnamed)', type: 'row', values: row.amounts, total });
        }
    }
    if (projectionRows.out.length) {
        rows.push({ label: 'Projected Other Cash Out Items', type: 'section', values: blankPeriodValues, total: 0 });
        for (const row of projectionRows.out) {
            const total = row.amounts.reduce((s, v) => s + (Number(v) || 0), 0);
            rows.push({ label: row.name || '(unnamed)', type: 'row', values: row.amounts, total });
        }
    }

    exportPayloadJson.value = JSON.stringify({
        title: `${props.title} — ${activeTab.value}`,
        currency: activeTab.value,
        periodLabels: periodLabels.value,
        rows,
    });
    nextTick(() => exportFormRef.value?.submit());
}

/* ── Form helper — Inertia POST so flash toasts arrive on the same visit ── */
function postForm(url, body) {
    return new Promise((resolve) => {
        router.post(url, body, {
            preserveScroll: true,
            onSuccess: () => resolve({ ok: true }),
            onError: () => resolve({ ok: false }),
            onFinish: () => {},
        });
    });
}

/* ── Past Due Invoices modal (Customer & Supplier share this) ─────
   ⚠️ Known simplification: the original pre-filled each row's
   Percentage/Week from a live DB lookup done at Blade render time
   (weekly_cashflow_custom_due_invoices). That table isn't part of
   this page's props, so previously-saved adjustments default to
   Percentage=100 / Week=unselected here instead of showing what was
   last saved — worth a follow-up if that matters in practice. */
const dueInvoiceModal = ref(null); // { invoiceType: 'CustomerInvoice' | 'SupplierInvoice', rows }
const dueInvoiceForm = reactive({}); // { [invoiceId]: { percentage, week_start_date } }
function openDueInvoiceModal(invoiceType) {
    const rows = invoiceType === 'CustomerInvoice'
        ? (props.pastDueCustomerInvoices?.[activeTab.value] || [])
        : props.pastDueSupplierInvoices;
    for (const row of rows) {
        dueInvoiceForm[row.id] = dueInvoiceForm[row.id] || { percentage: 100, week_start_date: '' };
    }
    dueInvoiceModal.value = { invoiceType, rows };
}
async function submitDueInvoiceModal() {
    const rows = dueInvoiceModal.value.rows;
    const payload = {
        cashFlowReportId: props.cashflowReport?.id || 0,
        invoiceType: dueInvoiceModal.value.invoiceType,
        currency_name: activeTab.value,
        cashflow_report_id: props.cashflowReport?.id || 0,
        is_contract: isContract.value ? 1 : 0,
        contract_code: props.contractCode || undefined,
        customer_invoice_id: rows.map(r => r.id),
        invoice_amount: {},
        percentage: {},
        week_start_date: {},
    };
    for (const row of rows) {
        payload.invoice_amount[row.id] = row.net_balance_in_main_currency;
        payload.percentage[row.id] = dueInvoiceForm[row.id]?.percentage ?? 100;
        payload.week_start_date[row.id] = dueInvoiceForm[row.id]?.week_start_date ?? '';
    }
    const result = await postForm(props.urls.adjustCustomerDueInvoices, payload);
    if (result.ok) {
        dueInvoiceModal.value = null;
    }
}

/* ── Loan Past Due Installments modal ─────────────────────────── */
const loanInstallmentModal = ref(false);
const loanInstallmentForm = reactive({});
function openLoanInstallmentModal() {
    for (const row of (props.pastDueInstallments || [])) {
        loanInstallmentForm[row.id] = loanInstallmentForm[row.id] || { percentage: 100, week_start_date: '' };
    }
    loanInstallmentModal.value = true;
}
async function submitLoanInstallmentModal() {
    const rows = props.pastDueInstallments || [];
    const payload = {
        currency_name: activeTab.value,
        cashflow_report_id: props.cashflowReport?.id || 0,
        is_contract: isContract.value ? 1 : 0,
        contract_code: props.contractCode || undefined,
        loan_schedule_id: rows.map(r => r.id),
        invoice_amount: {},
        percentage: {},
        week_start_date: {},
    };
    for (const row of rows) {
        payload.invoice_amount[row.id] = row.remaining_in_main_currency ?? row.remaining;
        payload.percentage[row.id] = loanInstallmentForm[row.id]?.percentage ?? 100;
        payload.week_start_date[row.id] = loanInstallmentForm[row.id]?.week_start_date ?? '';
    }
    const result = await postForm(props.urls.adjustLoanPastDueInstallments, payload);
    if (result.ok) {
        loanInstallmentModal.value = false;
    }
}

/* ── Breakdown popover (Cancelled LGs Cash Cover + Incoming Transfers) ── */
const lgBreakdownModal = ref(null); // { label, weekKey, items, type: 'lg' | 'incoming_transfer' }
function openLgBreakdown(subKey, weekKey, items, type = 'lg') {
    lgBreakdownModal.value = { label: subKey, weekKey, items: items || [], type };
}

/* ── Cross-currency collection notes (informational only, not part of
   any tab's totals) ─────────────────────────────────────────────── */
function crossCurrencyNotesFor(rowName, weekKey) {
    return props.crossCurrencyNotes?.[rowName]?.weeks?.[weekKey] || [];
}
function crossCurrencySumFor(rowName, weekKey) {
    return crossCurrencyNotesFor(rowName, weekKey).reduce((s, item) => s + (Number(item.amount_in_invoice_currency) || 0), 0);
}
const crossCurrencyModal = ref(null); // { label, items }
function openCrossCurrencyNotes(rowName, weekKey) {
    crossCurrencyModal.value = { label: rowName, items: crossCurrencyNotesFor(rowName, weekKey) };
}

const checksCollectedModal = ref(null);
function openChecksCollectedModal(subRow) {
    checksCollectedModal.value = subRow?.checksCollectedInfo || null;
}

/* ── Projected Cash In / Out repeater tabs ───────────────────────
   Each row is a named projection with one amount per period, stored
   as a CashProjection (amounts keyed by weekAndYear). Posts to the
   original, unchanged save.projection endpoint. */
let projectionKeySeed = 0;
function newProjectionRow(name = '', amounts = {}) {
    projectionKeySeed += 1;
    return {
        key: projectionKeySeed,
        id: 0,
        name,
        amounts: weekKeys.value.map(wk => Number(amounts[wk]) || 0),
    };
}
const projectionRows = reactive({
    in: (props.cashProjections?.in || []).map(p => newProjectionRow(p.name, p.amounts)),
    out: (props.cashProjections?.out || []).map(p => newProjectionRow(p.name, p.amounts)),
});
function addProjectionRow(type) {
    projectionRows[type].push(newProjectionRow());
}
function removeProjectionRow(type, index) {
    if (!confirm(t('Are you sure you want to delete this element?'))) return;
    projectionRows[type].splice(index, 1);
}
const savingProjection = ref(false);
function saveProjectionTab(type) {
    savingProjection.value = true;
    const tableId = `projection-${type}id`;
    const payload = {
        'tableIds': [tableId],
        'dates': [JSON.stringify(props.dates)],
        type,
        cashFlowReportId: props.cashflowReport?.id || 0,
        is_contract: isContract.value ? 1 : 0,
        [tableId]: projectionRows[type].map(row => ({
            id: row.id,
            type,
            name: row.name,
            amounts: row.amounts,
        })),
    };
    router.post(props.urls.saveProjection, payload, {
        preserveScroll: true,
        onFinish: () => { savingProjection.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-full mx-auto">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ title }}</h1>
                    <Link :href="urls.index" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                        {{ $t('← Back to Cash Flow Report') }}
                    </Link>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="exportExcel" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">{{ $t('Export Excel') }}</button>
                    <button @click="toggleExpandAll" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        {{ expandAll ? $t('Collapse All') : $t('Expand All') }}
                    </button>
                </div>
            </div>

            <!-- Hidden form: colored Excel export (see exportExcel() above) -->
            <form ref="exportFormRef" :action="urls.exportExcel" method="POST" target="_blank" class="hidden">
                <input type="hidden" name="_token" :value="csrfTokenValue" />
                <input type="hidden" name="payload" :value="exportPayloadJson" />
            </form>

            <!-- Tabs -->
            <div class="flex flex-wrap gap-1 border-b cvr-border mb-4">
                <button v-for="c in allCurrencies" :key="c"
                    @click="activeTab = c"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    :class="activeTab === c ? 'border-current cvr-text-primary' : 'border-transparent cvr-text-muted'">
                    {{ tabLabel(c) }}
                </button>
                <button @click="activeTab = 'projection-in'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    :class="activeTab === 'projection-in' ? 'border-current cvr-text-primary' : 'border-transparent cvr-text-muted'">
                    {{ $t('Projected Other Cash In Items') }}
                </button>
                <button @click="activeTab = 'projection-out'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    :class="activeTab === 'projection-out' ? 'border-current cvr-text-primary' : 'border-transparent cvr-text-muted'">
                    {{ $t('Projected Other Cash Out Items') }}
                </button>
            </div>

            <!-- Currency report table -->
            <div v-for="c in allCurrencies" v-show="activeTab === c" :key="c" class="cvr-card-bg cvr-border border rounded-lg overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-2 py-2 text-start whitespace-nowrap">{{ $t('Item') }}</th>
                            <th v-for="(label, i) in periodLabels" :key="i" class="px-2 py-2 text-center whitespace-nowrap">{{ label }}</th>
                            <th class="px-2 py-2 text-center whitespace-nowrap">{{ $t('Total') }}</th>
                        </tr>
                        <tr v-if="reportInterval === 'weekly'">
                            <th class="px-2 py-1 text-start text-xs cvr-text-muted">{{ $t('Start Date') }}</th>
                            <th v-for="wk in weekKeys" :key="'sd'+wk" class="px-2 py-1 text-center text-xs cvr-text-muted whitespace-nowrap">{{ dates[wk]?.start_date }}</th>
                            <th></th>
                        </tr>
                        <tr v-if="reportInterval === 'weekly'">
                            <th class="px-2 py-1 text-start text-xs cvr-text-muted">{{ $t('End Date') }}</th>
                            <th v-for="wk in weekKeys" :key="'ed'+wk" class="px-2 py-1 text-center text-xs cvr-text-muted whitespace-nowrap">{{ dates[wk]?.end_date }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in tablesByCurrency[c]" :key="row.key">
                            <tr class="cvr-table-row cursor-pointer" :class="{ 'font-semibold cvr-summary-row': row.highlight }" @click="row.hasSubRows && toggleRow(row.key)">
                                <td class="px-2 py-2 whitespace-nowrap">
                                    <span v-if="row.hasSubRows">{{ expandedRows.has(row.key) ? '−' : '+' }}</span>
                                    {{ row.name }}
                                    <button v-if="row.name === 'Customers Past Due Invoices'" @click.stop="openDueInvoiceModal('CustomerInvoice')" class="cvr-btn-secondary px-2 py-0.5 rounded border text-xs ms-2">{{ $t('View') }}</button>
                                    <button v-if="row.name === 'Suppliers Past Due Invoices'" @click.stop="openDueInvoiceModal('SupplierInvoice')" class="cvr-btn-secondary px-2 py-0.5 rounded border text-xs ms-2">{{ $t('View') }}</button>
                                    <button v-if="row.name === 'Loan Past Due Installments'" @click.stop="openLoanInstallmentModal()" class="cvr-btn-secondary px-2 py-0.5 rounded border text-xs ms-2">{{ $t('View') }}</button>
                                </td>
                                <td v-for="(cell, i) in row.cells" :key="i" class="px-2 py-2 text-center cvr-num whitespace-nowrap">
                                    {{ fmt(cell) }}
                                    <i v-if="CROSS_CURRENCY_ROW_NAMES.has(row.name) && crossCurrencyNotesFor(row.name, weekKeys[i]).length"
                                        @click.stop="openCrossCurrencyNotes(row.name, weekKeys[i])"
                                        class="ms-1 cursor-pointer" style="opacity:0.7"
                                        :title="`Also collected in a different currency: ${fmt(crossCurrencySumFor(row.name, weekKeys[i]))} ${currencyName}-equivalent — see details`">ℹ️</i>
                                </td>
                                <td class="px-2 py-2 text-center cvr-num font-semibold whitespace-nowrap">{{ fmt(row.total) }}</td>
                            </tr>
                            <tr v-if="row.hasSubRows && expandedRows.has(row.key)" v-for="sub in row.subRows" :key="row.key + ':' + sub.key" class="cvr-subrow">
                                <td class="px-2 py-2 ps-8 whitespace-nowrap text-xs">
                                    {{ sub.label }}
                                    <button
                                        v-if="row.name === 'Checks Collected' && sub.checksCollectedInfo"
                                        @click.stop="openChecksCollectedModal(sub)"
                                        type="button"
                                        class="ms-1 text-xs cvr-btn-secondary px-1.5 py-0.5 rounded border"
                                        :title="$t('Details')"
                                    >
                                        i
                                    </button>
                                </td>
                                <td v-for="(cell, i) in sub.cells" :key="i" class="px-2 py-2 text-center cvr-num whitespace-nowrap text-xs">
                                    {{ fmt(cell) }}
                                    <i v-if="(row.name === 'Cancelled LGs Cash Cover' || row.name === 'Issued LG Cash Cover') && cell"
                                        @click.stop="openLgBreakdown(sub.label, weekKeys[i], sub.lgBreakdown?.[i])"
                                        class="ms-1 cursor-pointer" :title="$t('Breakdown')">ℹ️</i>
                                    <i v-if="row.name === 'Incoming Transfers' && cell"
                                        @click.stop="openLgBreakdown(sub.label, weekKeys[i], sub.incomingTransferBreakdown?.[i], 'incoming_transfer')"
                                        class="ms-1 cursor-pointer" :title="$t('Breakdown')">ℹ️</i>
                                </td>
                                <td class="px-2 py-2 text-center cvr-num whitespace-nowrap text-xs">{{ fmt(sub.total) }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Projected Other Cash In Items -->
            <div v-show="activeTab === 'projection-in'" class="cvr-card-bg cvr-border border rounded-lg overflow-auto p-4">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-base font-medium cvr-text-primary">{{ $t('Projected Other Cash In Items') }}</h2>
                    <div class="flex gap-2">
                        <button @click="addProjectionRow('in')" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">{{ $t('+ Add Row') }}</button>
                        <button @click="saveProjectionTab('in')" :disabled="savingProjection" class="cvr-btn-primary px-3 py-1.5 rounded text-sm">{{ $t('Save') }}</button>
                    </div>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-2 py-2 text-start">{{ $t('Actions') }}</th>
                            <th class="px-2 py-2 text-start">{{ $t('Item') }}</th>
                            <th v-for="(label, i) in periodLabels" :key="i" class="px-2 py-2 text-center whitespace-nowrap">{{ label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in projectionRows.in" :key="row.key" class="cvr-table-row">
                            <td class="px-2 py-2 text-center">
                                <button @click="removeProjectionRow('in', index)" class="cvr-action-btn-danger cvr-action-btn" :title="$t('Remove')">🗑️</button>
                            </td>
                            <td class="px-2 py-2"><input v-model="row.name" class="cvr-input px-2 py-1 rounded w-40" /></td>
                            <td v-for="(wk, i) in weekKeys" :key="wk" class="px-2 py-2 text-center">
                                <input v-model.number="row.amounts[i]" type="number" step="any" class="cvr-input px-2 py-1 rounded w-24 text-center" />
                            </td>
                        </tr>
                        <tr v-if="projectionRows.in.length === 0"><td :colspan="weekKeys.length + 2" class="px-2 py-6 text-center cvr-text-muted">{{ $t('No rows yet — click "+ Add Row".') }}</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Projected Other Cash Out Items -->
            <div v-show="activeTab === 'projection-out'" class="cvr-card-bg cvr-border border rounded-lg overflow-auto p-4">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-base font-medium cvr-text-primary">{{ $t('Projected Other Cash Out Items') }}</h2>
                    <div class="flex gap-2">
                        <button @click="addProjectionRow('out')" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">{{ $t('+ Add Row') }}</button>
                        <button @click="saveProjectionTab('out')" :disabled="savingProjection" class="cvr-btn-primary px-3 py-1.5 rounded text-sm">{{ $t('Save') }}</button>
                    </div>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-2 py-2 text-start">{{ $t('Actions') }}</th>
                            <th class="px-2 py-2 text-start">{{ $t('Item') }}</th>
                            <th v-for="(label, i) in periodLabels" :key="i" class="px-2 py-2 text-center whitespace-nowrap">{{ label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in projectionRows.out" :key="row.key" class="cvr-table-row">
                            <td class="px-2 py-2 text-center">
                                <button @click="removeProjectionRow('out', index)" class="cvr-action-btn-danger cvr-action-btn" :title="$t('Remove')">🗑️</button>
                            </td>
                            <td class="px-2 py-2"><input v-model="row.name" class="cvr-input px-2 py-1 rounded w-40" /></td>
                            <td v-for="(wk, i) in weekKeys" :key="wk" class="px-2 py-2 text-center">
                                <input v-model.number="row.amounts[i]" type="number" step="any" class="cvr-input px-2 py-1 rounded w-24 text-center" />
                            </td>
                        </tr>
                        <tr v-if="projectionRows.out.length === 0"><td :colspan="weekKeys.length + 2" class="px-2 py-6 text-center cvr-text-muted">{{ $t('No rows yet — click "+ Add Row".') }}</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Past Due Invoices modal (Customer & Supplier) -->
            <div v-if="dueInvoiceModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-6xl max-h-[95vh] overflow-auto">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">
                        {{ dueInvoiceModal.invoiceType === $t('CustomerInvoice') ? $t('Customer Past Due Invoices') : $t('Supplier Past Due Invoices') }}
                    </h2>
                    <table class="min-w-full text-sm mb-4">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-2 py-2 text-start">{{ dueInvoiceModal.invoiceType === $t('CustomerInvoice') ? $t('Customer Name') : $t('Supplier Name') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Invoice No.') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Currency') }}</th>
                                <th class="px-2 py-2 text-right">{{ $t('Net Balance') }}{{ activeTab === mainFunctionalCurrency ? $t(' (in ') + mainFunctionalCurrency + ')' : '' }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Due Date') }}</th>
                                <th class="px-2 py-2 text-center">{{ $t('Collection %') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Collection Week') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in dueInvoiceModal.rows" :key="row.id" class="cvr-table-row">
                                <td class="px-2 py-2">{{ row.customer_name || row.supplier_name }}</td>
                                <td class="px-2 py-2">{{ row.invoice_number }}</td>
                                <td class="px-2 py-2">{{ row.currency }}</td>
                                <td class="px-2 py-2 text-right cvr-num">{{ fmt(row.net_balance_in_main_currency) }}</td>
                                <td class="px-2 py-2">{{ row.invoice_due_date }}</td>
                                <td class="px-2 py-2">
                                    <input v-model.number="dueInvoiceForm[row.id].percentage" type="number" step="any" class="cvr-input px-2 py-1 rounded w-20 text-center" />
                                </td>
                                <td class="px-2 py-2">
                                    <select v-model="dueInvoiceForm[row.id].week_start_date" class="cvr-input px-2 py-1 rounded">
                                        <option value="">{{ $t('Select') }}</option>
                                        <option v-for="wk in weekKeys" :key="wk" :value="dates[wk]?.start_date">
                                            {{ $t('Week') }} {{ weeks[wk] }} ({{ dates[wk]?.start_date }} - {{ dates[wk]?.end_date }})
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex justify-end gap-2">
                        <button @click="dueInvoiceModal = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitDueInvoiceModal" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Save') }}</button>
                    </div>
                </div>
            </div>

            <!-- Loan Past Due Installments modal -->
            <div v-if="loanInstallmentModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-4xl max-h-[85vh] overflow-auto">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Loan Past Due Installments') }}</h2>
                    <table class="min-w-full text-sm mb-4">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-2 py-2 text-start">{{ $t('Name') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Currency') }}</th>
                                <th class="px-2 py-2 text-right">{{ $t('Remaining') }}{{ activeTab === mainFunctionalCurrency ? $t(' (in ') + mainFunctionalCurrency + ')' : '' }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Due Date') }}</th>
                                <th class="px-2 py-2 text-center">{{ $t('Collection %') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Collection Week') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in pastDueInstallments" :key="row.id" class="cvr-table-row">
                                <td class="px-2 py-2">{{ row.loan_name }}</td>
                                <td class="px-2 py-2">{{ row.currency }}</td>
                                <td class="px-2 py-2 text-right cvr-num">{{ fmt(row.remaining_in_main_currency ?? row.remaining) }}</td>
                                <td class="px-2 py-2">{{ row.date }}</td>
                                <td class="px-2 py-2">
                                    <input v-model.number="loanInstallmentForm[row.id].percentage" type="number" step="any" class="cvr-input px-2 py-1 rounded w-20 text-center" />
                                </td>
                                <td class="px-2 py-2">
                                    <select v-model="loanInstallmentForm[row.id].week_start_date" class="cvr-input px-2 py-1 rounded">
                                        <option value="">{{ $t('Select') }}</option>
                                        <option v-for="wk in weekKeys" :key="wk" :value="dates[wk]?.start_date">
                                            {{ $t('Week') }} {{ weeks[wk] }} ({{ dates[wk]?.start_date }} - {{ dates[wk]?.end_date }})
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex justify-end gap-2">
                        <button @click="loanInstallmentModal = false" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="submitLoanInstallmentModal" class="cvr-btn-primary px-3 py-1.5 rounded">{{ $t('Save') }}</button>
                    </div>
                </div>
            </div>

            <!-- LG Breakdown modal -->
            <div v-if="lgBreakdownModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-4xl">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Breakdown [') }}{{ lgBreakdownModal.label }}]</h2>
                    <table class="min-w-full text-sm mb-4">
                        <thead class="cvr-table-head">
                            <tr v-if="lgBreakdownModal.type === 'incoming_transfer'">
                                <th class="px-2 py-2 text-start">{{ $t('Bank Name') }}</th><th class="px-2 py-2 text-start">{{ $t('Date') }}</th><th class="px-2 py-2 text-right">{{ $t('Amount') }}</th>
                            </tr>
                            <tr v-else>
                                <th class="px-2 py-2 text-start">{{ $t('Name') }}</th><th class="px-2 py-2 text-start">{{ $t('LG Code') }}</th><th class="px-2 py-2 text-right">{{ $t('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, i) in lgBreakdownModal.items" :key="i" class="cvr-table-row">
                                <template v-if="lgBreakdownModal.type === 'incoming_transfer'">
                                    <td class="px-2 py-2">{{ item.bank_name || '—' }}</td>
                                    <td class="px-2 py-2">{{ item.movement_date }}</td>
                                </template>
                                <template v-else>
                                    <td class="px-2 py-2">{{ item.name }}</td>
                                    <td class="px-2 py-2">{{ item.lg_code }}</td>
                                </template>
                                <td class="px-2 py-2 text-right cvr-num">{{ fmt(item.amount) }}</td>
                            </tr>
                            <tr v-if="!lgBreakdownModal.items.length"><td colspan="3" class="px-2 py-4 text-center cvr-text-muted">{{ $t('No breakdown entries.') }}</td></tr>
                        </tbody>
                    </table>
                    <div class="flex justify-end">
                        <button @click="lgBreakdownModal = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>

            <!-- Cross-currency collection notes (informational only) -->
            <div v-if="crossCurrencyModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-4xl">
                    <h2 class="text-lg font-medium cvr-text-primary mb-2">{{ $t('Collected in a Different Currency [') }}{{ crossCurrencyModal.label }}]</h2>
                    <p class="text-xs cvr-text-muted mb-4">{{ $t('These amounts are shown for reference only and are not included in this tab\'s totals — the cash itself is counted under the currency it was actually collected in.') }}</p>
                    <table class="min-w-full text-sm mb-4">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-2 py-2 text-start">{{ $t('Name') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Date') }}</th>
                                <th class="px-2 py-2 text-right">{{ currencyName }}{{ $t('-Equivalent') }}</th>
                                <th class="px-2 py-2 text-right">{{ $t('Actually Collected') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, i) in crossCurrencyModal.items" :key="i" class="cvr-table-row">
                                <td class="px-2 py-2">{{ item.partner_name }}</td>
                                <td class="px-2 py-2">{{ item.movement_date }}</td>
                                <td class="px-2 py-2 text-right cvr-num">{{ fmt(item.amount_in_invoice_currency) }}</td>
                                <td class="px-2 py-2 text-right cvr-num">{{ fmt(item.collected_amount) }} {{ item.collected_currency }}</td>
                            </tr>
                            <tr v-if="!crossCurrencyModal.items.length"><td colspan="4" class="px-2 py-4 text-center cvr-text-muted">{{ $t('No entries.') }}</td></tr>
                        </tbody>
                    </table>
                    <div class="flex justify-end">
                        <button @click="crossCurrencyModal = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>

            <!-- Checks Collected modal -->
            <div v-if="checksCollectedModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-lg">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Cheque Details') }}</h2>
                    <table class="min-w-full text-sm mb-4">
                        <tbody>
                            <tr class="cvr-table-row">
                                <td class="px-2 py-2 font-semibold">{{ $t('Customer Name') }}</td>
                                <td class="px-2 py-2">{{ checksCollectedModal.customer_name }}</td>
                            </tr>
                            <tr class="cvr-table-row">
                                <td class="px-2 py-2 font-semibold">{{ $t('Cheque Number') }}</td>
                                <td class="px-2 py-2">{{ checksCollectedModal.cheque_number || '—' }}</td>
                            </tr>
                            <tr class="cvr-table-row">
                                <td class="px-2 py-2 font-semibold">{{ $t('Collection Date') }}</td>
                                <td class="px-2 py-2">{{ checksCollectedModal.movement_date }}</td>
                            </tr>
                            <tr class="cvr-table-row">
                                <td class="px-2 py-2 font-semibold">{{ $t('Amount') }}</td>
                                <td class="px-2 py-2 text-right cvr-num">{{ fmt(checksCollectedModal.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex justify-end">
                        <button @click="checksCollectedModal = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                    </div>
                </div>
            </div>

            <!-- Incoming Transfers breakdown reuses the Breakdown modal above -->
        </div>
    </AppLayout>
</template>