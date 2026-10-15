<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { todayDate } from '@/composables/today';
import { mapAccountNumberOptions, accountNumberOption } from '@/composables/useAccountNumberOptions';

/**
 * Client-requested (2026-08-11): invoice/net-balance amounts should
 * always display to exactly 2 decimal places — raw backend values can
 * carry more (or fewer) digits depending on the underlying arithmetic.
 * Display-only; never touches what actually gets submitted.
 */
function formatMoney(value) {
    const n = Number(value);
    if (isNaN(n)) return value;
    return n.toFixed(2);
}
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate();

const props = defineProps({
    /* Link to this screen's written guide — see App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    model: Object,          // null on create
    singleModel: [Number, String],
    invoiceNumber: String,
    warningMessage: String,
    suppliers: Array,
    partnerTypes: Array,
    moneyTypes: Array,
    currencies: Array,
    selectedBranches: Array,
    financialInstitutionBanks: Array,
    accountTypes: Array,
    /**
     * Leasing companies for the "Through Leasing" card. That money type
     * has no bank, no account type and no account number — the leasing
     * company pays the supplier straight out of a contract — so its card
     * asks only for the company and then the contract.
     */
    leasingCompanies: { type: Array, default: () => [] },
    urls: Object,
});

const page = usePage();
const isEdit = computed(() => !!props.model);
const errors = computed(() => page.props.errors || {});
// Guarantees no validation failure is ever silent — several server-side
// rules are attached to a whole field or a pseudo-field with no
// natural place in this UI (net_balance_rules, downPayment_over_contract,
// amount_can_not_be_greater_than_end_balance_at_payment_date, etc.),
// so relying only on inline per-field messages can miss one entirely.
const errorMessages = computed(() => Object.values(errors.value).flat().filter(Boolean));

/** Same fix as Money Received's Form.vue: send the headers jQuery's
 *  $.ajax() sent automatically, so a real server-side error comes
 *  back as JSON instead of Laravel's HTML error page. */
async function fetchJson(url) {
    const res = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const text = await res.text();
    let data = null;
    let parseError = null;
    try {
        data = JSON.parse(text);
    } catch (e) {
        parseError = String(e);
    }
    return { url, status: res.status, ok: res.ok, data, parseError, rawText: parseError ? text.slice(0, 800) : null };
}

/* ── Header fields ────────────────────────────────────────────── */
const deliveryDate = ref(props.model?.delivery_date || '');
const partnerType = ref(props.model?.partner_type || 'is_supplier');
const singleInvoiceCurrency = props.singleModel ? props.currencies[0]?.code : null;
const invoiceCurrency = ref(props.model?.currency || singleInvoiceCurrency || props.company.mainFunctionalCurrency || '');
const supplierId = ref(props.model?.supplier_id || (props.singleModel ? props.suppliers[0]?.id : ''));
const paymentCurrency = ref(props.model?.payment_currency || singleInvoiceCurrency || props.company.mainFunctionalCurrency || '');
const moneyType = ref(props.model?.type || '');
const transactionType = ref(props.model?.transaction_type || '');
const userComment = ref(props.model?.user_comment || '');

const suppliersList = ref([...props.suppliers]);
const branchesList = ref([...props.selectedBranches]);

/* Confirmed against the real custom/money-payment.js's
   showOrHideTransaction() — options are gated per partner type, not
   one shared list. Money Payment has one extra partner type Money
   Received doesn't (is_tax). */
const transactionTypeOptions = computed(() => {
    if (partnerType.value === 'is_employee') {
        return [
            { value: 'custody', label: 'Custody' },
            { value: 'loan', label: 'Loan' },
        ];
    }
    if (partnerType.value === 'is_shareholder') {
        return [
            { value: 'funding-to', label: 'Funding To' },
            { value: 'dividend-payment', label: 'Dividend Payment' },
        ];
    }
    if (partnerType.value === 'is_subsidiary_company') {
        return [
            { value: 'funding-to', label: 'Funding To' },
            { value: 'investment-in-subsidiary-company', label: 'Investment In Subsidiary Company' },
        ];
    }
    if (partnerType.value === 'is_other_partner') {
        return [{ value: 'insurance-to', label: 'Insurance To' }];
    }
    if (partnerType.value === 'is_tax') {
        return [{ value: 'pay-to', label: 'Pay To' }];
    }
    return [];
});

/* ── Type-specific fields — one flat set, namespaced at submit time
   by whichever `moneyType` is currently selected (same reasoning as
   Money Received's Form.vue). ───────────────────────────────────── */
const paidAmount = ref(props.model?.paid_amount || 0);

/* Exchange Rate now accepts either a plain number ("50") or a division
   expression ("1/50" or "=1/50", spreadsheet-style) — some rates are
   naturally quoted as a fraction and forcing manual division invites
   typos. `exchangeRateInput` holds exactly what the person typed;
   `exchangeRate` is the parsed numeric value everything else (amount
   calculations, the submitted payload) actually uses. Mirrors Money
   Received's Form.vue exactly. */
const exchangeRateInput = ref(String(props.model?.exchange_rate ?? 1));
function parseExchangeRateExpression(raw) {
    const s = String(raw ?? '').trim().replace(/^=/, '');
    if (!s) return 0;
    // Fast path: a plain number ("50", "0.02").
    if (/^-?\d+(\.\d+)?$/.test(s)) {
        const n = Number(s);
        return Number.isFinite(n) ? n : 0;
    }
    // Otherwise treat it as a small arithmetic expression — "1/50",
    // "1*50", "(1+1)/50", etc. Only allow digits/operators/parens/spaces
    // through before evaluating, so this can never run arbitrary code.
    if (/^[\d+\-*/().\s]+$/.test(s)) {
        try {
            // eslint-disable-next-line no-new-func
            const result = Function(`"use strict"; return (${s});`)();
            return Number.isFinite(result) ? result : 0;
        } catch (e) {
            return 0;
        }
    }
    return 0;
}
const exchangeRate = computed(() => parseExchangeRateExpression(exchangeRateInput.value));
/* Spreadsheet-style "commit on blur": once the person clicks away from
   the field, replace whatever they typed ("=1/50", "1*50", ...) with
   its plain numeric result ("0.02", "50"), same as Excel resolving a
   formula into the cell. A genuinely unparseable entry (parses to 0)
   is left as-is so they can see and fix their typo instead of having
   it silently wiped to "0". */
function onExchangeRateBlur() {
    const parsed = parseExchangeRateExpression(exchangeRateInput.value);
    if (parsed) {
        exchangeRateInput.value = String(Math.round(parsed * 1e6) / 1e6);
    }
}

const cashPayment = reactive({
    deliveryBranchId: props.model?.cash_payment?.delivery_branch_id || '',
    receiptNumber: props.model?.cash_payment?.receipt_number || '',
});
const outgoingTransfer = reactive({
    deliveryBankId: props.model?.outgoing_transfer?.delivery_bank_id || '',
    accountTypeId: props.model?.outgoing_transfer?.account_type_id || '',
    accountNumber: props.model?.outgoing_transfer?.account_number || '',
});
const payableCheque = reactive({
    deliveryBankId: props.model?.payable_cheque?.delivery_bank_id || '',
    accountTypeId: props.model?.payable_cheque?.account_type_id || '',
    accountNumber: props.model?.payable_cheque?.account_number || '',
    dueDate: props.model?.payable_cheque?.due_date || '',
    chequeNumber: props.model?.payable_cheque?.cheque_number || '',
});

const leasing = reactive({
    leasingCompanyId: props.model?.leasing_payment?.leasing_company_id || '',
    leasingContractId: props.model?.leasing_payment?.leasing_contract_id || '',
});

const leasingContracts = ref([]);
const outgoingTransferAccountNumbers = ref([]);
const payableChequeAccountNumbers = ref([]);
const cashPaymentBalance = ref(null);
const outgoingTransferBalance = ref(null);
const outgoingTransferNetBalance = ref(null);
const payableChequeBalance = ref(null);
const payableChequeNetBalance = ref(null);

/* ── Derived amounts ──────────────────────────────────────────── */
const isSameCurrency = computed(() => invoiceCurrency.value && invoiceCurrency.value === paymentCurrency.value);
const showExchangeRateFields = computed(() => partnerType.value === 'is_supplier' && invoiceCurrency.value && paymentCurrency.value && !isSameCurrency.value);
const amountInInvoiceCurrency = computed(() => {
    if (isSameCurrency.value || !exchangeRate.value) return Number(paidAmount.value) || 0;
    return Math.round(((Number(paidAmount.value) || 0) / Number(exchangeRate.value)) * 100) / 100;
});

/* ── Settlement Information (invoices repeater) — suppliers only ── */
const showSettlementCard = computed(() => partnerType.value === 'is_supplier');
const invoices = ref([]); // [{ id, invoice_number, invoice_date, invoice_due_date, currency, net_invoice_amount, paid_amount, net_balance, settlement_amount, withhold_amount }]
const invoicesFetchFailed = ref(null);
const supplierInvoiceCurrencies = ref([]);

async function fetchInvoices() {
    if (!showSettlementCard.value || !supplierId.value) { invoices.value = []; invoicesFetchFailed.value = null; supplierInvoiceCurrencies.value = []; return; }
    supplierInvoiceCurrencies.value = [];
    const params = new URLSearchParams();
    params.set('inEditMode', isEdit.value ? '1' : '0');
    params.set('money_payment_id', isEdit.value ? props.model.id : '0');
    params.set('downPaymentContractId', contractId.value && contractId.value !== 'general-down' ? contractId.value : '');
    const currencySegment = invoiceCurrency.value ? `/${invoiceCurrency.value}` : '';
    const url = `${props.urls.getInvoiceNumbers}/${supplierId.value}${currencySegment}?${params.toString()}`;
    try {
        const result = await fetchJson(url);
        if (!result.ok || result.parseError) {
            invoices.value = [];
            invoicesFetchFailed.value = result;
            return;
        }
        invoicesFetchFailed.value = null;
        const data = result.data || {};
        let fetchedInvoices = (data.invoices || []).map(inv => ({
            ...inv,
            settlement_amount: Number(inv.settlement_amount) || 0,
            withhold_amount: Number(inv.withhold_amount) || 0,
        }));
        if (props.singleModel) {
            fetchedInvoices = fetchedInvoices.filter(inv => String(inv.id) === String(props.singleModel));
        }
        invoices.value = fetchedInvoices;
        supplierInvoiceCurrencies.value = Object.keys(data.currencies || {});
    } catch (e) {
        invoices.value = [];
        invoicesFetchFailed.value = { url, status: 'network error', error: String(e) };
    }
}

const totalSettled = computed(() =>
    invoices.value.reduce((sum, inv) => sum + (Number(inv.settlement_amount) || 0), 0)
);
const totalDownPaymentOrdersAmount = computed(() =>
    purchaseOrders.value.reduce((sum, po) => sum + (Number(po.received_amount) || 0), 0)
);
const unappliedAmount = computed(() => {
    const remaining = amountInInvoiceCurrency.value - totalSettled.value;
    return Math.round((remaining || 0) * 100) / 100;
});
const unappliedAmountInPaymentCurrency = computed(() => {
    const remaining = unappliedAmount.value * (Number(exchangeRate.value) || 1) - totalDownPaymentOrdersAmount.value;
    return Math.round((remaining || 0) * 100) / 100;
});

/* ── Allocate (removed) ───────────────────────────────────────────
   Splitting a supplier payment's settlement across a customer's
   contracts used to live here as a per-invoice "Allocate" modal. It
   was dropped from this form on both Create and Edit, so nothing is
   posted under `allocations` any more.
   The server side is deliberately untouched: MoneyPaymentController
   still reads `$request->get('allocations', [])` and MoneyPayment
   still has settlementAllocations()/storeNewAllocation(), so any other
   caller keeps working. Note the consequence for existing records —
   an update clears settlementAllocations() before re-creating them
   from the request, so editing a payment that already had allocations
   now leaves it with none. */

/* ── "Choose Contract For Down Payment" — same mechanism as Money
   Received's Form.vue, using purchase orders instead of sales orders. */
const showDownPaymentPicker = computed(() => showSettlementCard.value && unappliedAmount.value > 0.009);
const contractId = ref(props.model?.contract_id || '');
const contracts = ref([]);
const purchaseOrders = ref([]); // [{ id, po_number, amount, received_amount }]

async function fetchContracts() {
    if (!supplierId.value || !paymentCurrency.value) { contracts.value = []; return; }
    const params = new URLSearchParams({ supplierId: supplierId.value, currency: paymentCurrency.value });
    const result = await fetchJson(`${props.urls.getContractsForSupplier}?${params.toString()}`);
    contracts.value = Object.entries(result.data?.contracts || {}).map(([id, name]) => ({ id, name }));
}
async function fetchPurchaseOrders() {
    if (!contractId.value) { purchaseOrders.value = []; return; }
    if (contractId.value === 'general-down') {
        // Same reasoning as Money Received's Form.vue: no real purchase
        // order behind "General Down Payment", so show one synthetic
        // "General" row instead. `id: -1` is a real sentinel the
        // backend understands (storeNewPurchaseOrders maps
        // purchases_order_id -1 → null) — keep it as -1, not null/0.
        purchaseOrders.value = [{ id: -1, po_number: 'General', amount: 0, received_amount: 0 }];
        return;
    }
    const params = new URLSearchParams();
    if (isEdit.value) params.set('down_payment_id', props.model.id);
    const result = await fetchJson(`${props.urls.getPurchaseOrdersForContract}/${contractId.value}/${paymentCurrency.value}?${params.toString()}`);
    purchaseOrders.value = (result.data?.sales_orders || result.data?.purchases_orders || []).map(po => ({
        id: po.id,
        po_number: po.po_number || po.so_number,
        amount: po.amount,
        received_amount: Number(po.received_amount ?? po.paid_amount) || 0,
    }));
}

/* ── Account number lookups + branch/currency lookups — same
   patterns as Money Received's Form.vue. ────────────────────────── */
async function fetchAccountNumbers(accountTypeId, financialInstitutionId, target) {
    target.value = [];
    if (!accountTypeId || !financialInstitutionId || !paymentCurrency.value) return;
    const url = `${props.urls.getAccountNumbersForType}/${accountTypeId}/${paymentCurrency.value}/${financialInstitutionId}`;
    const result = await fetchJson(url);
    target.value = mapAccountNumberOptions(result.data?.data);
}
async function fetchBranchesForCurrency() {
    if (!paymentCurrency.value) return;
    const result = await fetchJson(`${props.urls.getBranchBasedOnCurrency}?currencyName=${paymentCurrency.value}`);
    branchesList.value = Object.entries(result.data?.branches || {}).map(([name, id]) => ({ id, name }));
}
// Cash Payment's balance — confirmed against the real inline <script>
// in form.blade.php: triggered on branch/currency/date, backed by
// get.current.end.balance.of.cash.in.safe.statement.
async function fetchCashPaymentBalance() {
    cashPaymentBalance.value = null;
    if (!paymentCurrency.value || !cashPayment.deliveryBranchId || cashPayment.deliveryBranchId === '-1') return;
    const params = new URLSearchParams({
        branchId: cashPayment.deliveryBranchId,
        currencyName: paymentCurrency.value,
        modelId: props.model?.id || 0,
        modelType: 'MoneyPayment',
        balanceDate: deliveryDate.value,
    });
    const result = await fetchJson(`${props.urls.getCashInSafeEndBalance}?${params.toString()}`);
    cashPaymentBalance.value = result.data?.end_balance ?? null;
}
// Payable Cheque & Outgoing Transfer's balance — a DIFFERENT real
// mechanism (also confirmed against the same inline script): triggered
// on Account Number (not branch — these panels don't have one), backed
// by update.balance.and.net.balance.based.on.account.number, and
// returns both `balance` and `net_balance`. Payable Cheque has no
// insufficient-funds validation at all server-side (confirmed in
// AmountCanNotBeGreaterThanEndBalanceAtPaymentDate — only Cash Payment
// and Outgoing Transfer are checked), but the balance is still worth
// showing so the person isn't issuing a cheque blind.
async function fetchAccountBalance(accountType, accountNumber, financialInstitutionId, balanceTarget, netBalanceTarget) {
    balanceTarget.value = null;
    netBalanceTarget.value = null;
    if (!accountType || !accountNumber || !financialInstitutionId) return;
    const params = new URLSearchParams({
        accountType,
        accountNumber,
        financialInstitutionId,
        modelType: 'MoneyPayment',
        modelId: props.model?.id || 0,
        balanceDate: deliveryDate.value,
    });
    const result = await fetchJson(`${props.urls.balanceForAccountNumber}?${params.toString()}`);
    balanceTarget.value = result.data?.balance ?? null;
    netBalanceTarget.value = result.data?.net_balance ?? null;
}

/* Suppliers (and, for other partner types, the shared generic list)
   — real, currency-scoped endpoint for suppliers (getSuppliersBased-
   OnCurrency), the same shared endpoint Money Received uses for
   every other partner type (getPartnersBasedOnCurrency). */
async function fetchSuppliersForPartnerType() {
    if (!invoiceCurrency.value) return;
    if (partnerType.value === 'is_supplier') {
        const result = await fetchJson(`${props.urls.getSuppliersBasedOnCurrency}/${invoiceCurrency.value}`);
        suppliersList.value = Object.entries(result.data?.supplierInvoices || {}).map(([name, id]) => ({ id, name }));
    } else {
        const params = new URLSearchParams({ partnerColumnName: partnerType.value });
        const result = await fetchJson(`${props.urls.getPartnersBasedOnCurrency}/${invoiceCurrency.value}?${params.toString()}`);
        suppliersList.value = Object.entries(result.data?.partners || {}).map(([name, id]) => ({ id, name }));
    }
}

/* The contracts of the chosen leasing company, in the payment
   currency, each already carrying the room available on the payment
   date — so picking one shows the remaining amount with no second
   round trip. Its own endpoint, not the account-number one: this money
   type has no account type to resolve. */
async function fetchLeasingContracts() {
    leasingContracts.value = [];
    if (!leasing.leasingCompanyId || !paymentCurrency.value) return;
    const params = new URLSearchParams({
        leasingCompanyId: leasing.leasingCompanyId,
        currency: paymentCurrency.value,
        date: deliveryDate.value || '',
    });
    const result = await fetchJson(`${props.urls.getLeasingContracts}?${params.toString()}`);
    leasingContracts.value = result.data?.contracts || [];
}

const selectedLeasingContract = computed(() =>
    leasingContracts.value.find(c => String(c.id) === String(leasing.leasingContractId)) || null,
);

/* ── Watchers ─────────────────────────────────────────────────── */
watch(paymentCurrency, () => {
    fetchBranchesForCurrency();
    if (showSettlementCard.value) fetchContracts();
    if (moneyType.value === 'outgoing-transfer') fetchAccountNumbers(outgoingTransfer.accountTypeId, outgoingTransfer.deliveryBankId, outgoingTransferAccountNumbers);
    if (moneyType.value === 'payable_cheque') fetchAccountNumbers(payableCheque.accountTypeId, payableCheque.deliveryBankId, payableChequeAccountNumbers);
    if (moneyType.value === 'cash_payment') fetchCashPaymentBalance();
    if (moneyType.value === 'leasing_payment') fetchLeasingContracts();
});
watch(invoiceCurrency, (newCurrency) => {
    if (partnerType.value === 'is_supplier' && newCurrency) {
        paymentCurrency.value = newCurrency;
    }
    fetchSuppliersForPartnerType();
});
watch(partnerType, () => {
    fetchSuppliersForPartnerType();
    transactionType.value = '';
    if (partnerType.value !== 'is_supplier') { invoices.value = []; contractId.value = ''; }
});
watch(supplierId, fetchContracts);
watch([supplierId, invoiceCurrency], fetchInvoices);
watch(contractId, fetchPurchaseOrders);
watch(() => outgoingTransfer.accountTypeId, () => fetchAccountNumbers(outgoingTransfer.accountTypeId, outgoingTransfer.deliveryBankId, outgoingTransferAccountNumbers));
watch(() => outgoingTransfer.deliveryBankId, () => fetchAccountNumbers(outgoingTransfer.accountTypeId, outgoingTransfer.deliveryBankId, outgoingTransferAccountNumbers));
watch(() => payableCheque.accountTypeId, () => fetchAccountNumbers(payableCheque.accountTypeId, payableCheque.deliveryBankId, payableChequeAccountNumbers));
watch(() => payableCheque.deliveryBankId, () => fetchAccountNumbers(payableCheque.accountTypeId, payableCheque.deliveryBankId, payableChequeAccountNumbers));
// The real trigger is Account Number itself, not bank/type — matches
// the inline script's `.js-account-number` change handler exactly.
watch(() => outgoingTransfer.accountNumber, () => fetchAccountBalance(outgoingTransfer.accountTypeId, outgoingTransfer.accountNumber, outgoingTransfer.deliveryBankId, outgoingTransferBalance, outgoingTransferNetBalance));
watch(() => payableCheque.accountNumber, () => fetchAccountBalance(payableCheque.accountTypeId, payableCheque.accountNumber, payableCheque.deliveryBankId, payableChequeBalance, payableChequeNetBalance));
watch(() => cashPayment.deliveryBranchId, fetchCashPaymentBalance);
watch(() => leasing.leasingCompanyId, () => {
    // A contract belongs to exactly one leasing company, so a leftover
    // selection from the previous one would be silently wrong.
    leasing.leasingContractId = '';
    fetchLeasingContracts();
});
watch(deliveryDate, () => {
    if (moneyType.value === 'cash_payment') fetchCashPaymentBalance();
    // The room is read AT the payment date, so moving the date moves it.
    if (moneyType.value === 'leasing_payment') fetchLeasingContracts();
    if (moneyType.value === 'outgoing-transfer') fetchAccountBalance(outgoingTransfer.accountTypeId, outgoingTransfer.accountNumber, outgoingTransfer.deliveryBankId, outgoingTransferBalance, outgoingTransferNetBalance);
    if (moneyType.value === 'payable_cheque') fetchAccountBalance(payableCheque.accountTypeId, payableCheque.accountNumber, payableCheque.deliveryBankId, payableChequeBalance, payableChequeNetBalance);
});
watch(moneyType, () => {
    if (moneyType.value === 'cash_payment') fetchCashPaymentBalance();
    if (moneyType.value === 'leasing_payment') fetchLeasingContracts();
    if (moneyType.value === 'outgoing-transfer') fetchAccountBalance(outgoingTransfer.accountTypeId, outgoingTransfer.accountNumber, outgoingTransfer.deliveryBankId, outgoingTransferBalance, outgoingTransferNetBalance);
    if (moneyType.value === 'payable_cheque') fetchAccountBalance(payableCheque.accountTypeId, payableCheque.accountNumber, payableCheque.deliveryBankId, payableChequeBalance, payableChequeNetBalance);
});

onMounted(() => {
    if (isEdit.value) {
        fetchInvoices();
        if (contractId.value) fetchContracts().then(fetchPurchaseOrders);
        if (outgoingTransfer.accountTypeId) fetchAccountNumbers(outgoingTransfer.accountTypeId, outgoingTransfer.deliveryBankId, outgoingTransferAccountNumbers);
        if (payableCheque.accountTypeId) fetchAccountNumbers(payableCheque.accountTypeId, payableCheque.deliveryBankId, payableChequeAccountNumbers);
        if (moneyType.value === 'cash_payment') fetchCashPaymentBalance();
        if (moneyType.value === 'leasing_payment') fetchLeasingContracts();
        if (moneyType.value === 'outgoing-transfer' && outgoingTransfer.accountNumber) fetchAccountBalance(outgoingTransfer.accountTypeId, outgoingTransfer.accountNumber, outgoingTransfer.deliveryBankId, outgoingTransferBalance, outgoingTransferNetBalance);
        if (moneyType.value === 'payable_cheque' && payableCheque.accountNumber) fetchAccountBalance(payableCheque.accountTypeId, payableCheque.accountNumber, payableCheque.deliveryBankId, payableChequeBalance, payableChequeNetBalance);
    } else {
        /**
         * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): fetchInvoices()
         * used to live INSIDE the `!props.singleModel` branch — meaning
         * arriving via the "Money Payments" button from a specific
         * invoice (exactly when props.singleModel IS set) was the one
         * case where invoices were never fetched at all. The working
         * Money Received form gets this right: only the SUPPLIER LIST
         * re-fetch should be skipped when arriving from a single
         * invoice (the server already scoped it to the one correct
         * supplier) — invoice fetching itself should always run
         * whenever there's a supplier to fetch for, regardless of how
         * the form was reached.
         */
        if (!props.singleModel) {
            fetchSuppliersForPartnerType();
        }
        if (showSettlementCard.value && supplierId.value) fetchInvoices();
    }
    fetchBranchesForCurrency();
});

/* ── Submit ───────────────────────────────────────────────────── */
function buildPayload() {
    const settlements = {};
    invoices.value.forEach(inv => {
        if ((Number(inv.settlement_amount) || 0) > 0 || (Number(inv.withhold_amount) || 0) > 0) {
            settlements[inv.id] = {
                invoice_id: inv.id,
                invoice_number: inv.invoice_number,
                net_balance: inv.net_balance,
                settlement_amount: inv.settlement_amount,
                withhold_amount: inv.withhold_amount,
            };
        }
    });

    // ⚠️ Keys matter here: storeNewPurchaseOrders() (app/Models/MoneyPayment.php)
    // reads `purchases_order_id` and `paid_amount` off each row — not
    // `sales_order_id`/`received_amount` (those are the Money Received
    // naming, which this form used to copy by mistake). With the wrong
    // keys, `isset($purchaseOrderArr['paid_amount'])` is always false,
    // so every down-payment settlement row was silently skipped
    // server-side, contract or General alike.
    const purchasesOrdersAmounts = purchaseOrders.value.map(po => ({
        purchases_order_id: po.id,
        purchases_order_name: po.po_number,
        net_invoice_amount: po.amount,
        paid_amount: po.received_amount,
    }));

    const payload = {
        delivery_date: deliveryDate.value,
        partner_type: partnerType.value,
        currency: invoiceCurrency.value,
        supplier_id: supplierId.value,
        payment_currency: paymentCurrency.value,
        type: moneyType.value,
        transaction_type: transactionType.value,
        user_comment: userComment.value,
        paid_amount: { [moneyType.value]: paidAmount.value },
        exchange_rate: { [moneyType.value]: exchangeRate.value },
        amount_in_invoice_currency: { [moneyType.value]: amountInInvoiceCurrency.value },
        settlements,
        unapplied_amount: showDownPaymentPicker.value ? unappliedAmount.value : 0,
        contract_id: showDownPaymentPicker.value && contractId.value !== 'general-down' ? contractId.value : null,
        purchases_orders_amounts: showDownPaymentPicker.value ? purchasesOrdersAmounts : [],
    };

    if (moneyType.value === 'cash_payment') {
        payload.delivery_branch_id = cashPayment.deliveryBranchId;
        payload.receipt_number = cashPayment.receiptNumber;
    }
    if (moneyType.value === 'outgoing-transfer') {
        payload.delivery_bank_id = { 'outgoing-transfer': outgoingTransfer.deliveryBankId };
        payload.account_type = { 'outgoing-transfer': outgoingTransfer.accountTypeId };
        payload.account_number = { 'outgoing-transfer': outgoingTransfer.accountNumber };
    }
    if (moneyType.value === 'leasing_payment') {
        // Flat, not keyed by money type: there is exactly one of each on
        // this form, unlike the bank fields which the original markup
        // duplicates per money type.
        payload.leasing_company_id = leasing.leasingCompanyId;
        payload.leasing_contract_id = leasing.leasingContractId;
    }
    if (moneyType.value === 'payable_cheque') {
        payload.delivery_bank_id = { payable_cheque: payableCheque.deliveryBankId };
        payload.account_type = { payable_cheque: payableCheque.accountTypeId };
        payload.account_number = { payable_cheque: payableCheque.accountNumber };
        payload.due_date = payableCheque.dueDate;
        payload.cheque_number = payableCheque.chequeNumber;
    }

    return payload;
}

function submit() {
    const payload = buildPayload();
    if (isEdit.value) {
        router.put(props.urls.update, payload);
    } else {
        router.post(props.urls.store, payload);
    }
}
</script>

<template>
    <AppLayout>
        <div class="px-6 pt-4">
            <Link v-if="instructionsUrl" :href="instructionsUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('📖 Instructions') }}
            </Link>
        </div>
        <div class="p-6">
            <Link :href="urls.index" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                {{ $t('← Back to Money Payment') }}
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ isEdit ? $t('Edit Money Payment') : $t('Money Payment') }}</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ company.name }}</p>

            <p v-if="warningMessage" class="cvr-badge cvr-badge-pending px-3 py-2 rounded mb-4 block">{{ warningMessage }}</p>
            <div v-if="errorMessages.length" class="cvr-border rounded-lg p-3 mb-4 text-sm" style="border-color: var(--cvr-danger-text); background: var(--cvr-bg-card-alt)">
                <p class="font-medium mb-1" style="color: var(--cvr-danger-text)">{{ $t('Couldn\'t save — please fix the following:') }}</p>
                <ul class="list-disc list-inside" style="color: var(--cvr-danger-text)">
                    <li v-for="(msg, i) in errorMessages" :key="i">{{ msg }}</li>
                </ul>
            </div>

            <!-- Header -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">{{ $t('Money Payment') }}</h2>
                <div :class="partnerType === 'is_supplier' ? 'cvr-form-grid-2-2-1-4-1-2' : 'cvr-form-grid-2-2-5-1-3'">
                    <div>
                        <label class="cvr-form-label">{{ $t('Payment Date') }} *</label>
                        <input v-model="deliveryDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.delivery_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.delivery_date }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Partner Type') }} *</label>
                        <select v-model="partnerType" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="t in partnerTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div v-if="partnerType === 'is_supplier'">
                        <label class="cvr-form-label">{{ $t('Invoice Currency*') }}</label>
                        <select v-model="invoiceCurrency" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Name') }} *</label>
                        <select v-model="supplierId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="s in suppliersList" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="errors.supplier_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.supplier_id }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Pay Currency') }} *</label>
                        <select v-model="paymentCurrency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Money Type') }} *</label>
                        <select v-model="moneyType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="t in moneyTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p v-if="errors.type" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.type }}</p>
                    </div>
                </div>
                <div v-if="partnerType !== 'is_supplier'" class="mt-4">
                    <div class="cvr-field-narrow">
                        <label class="cvr-form-label">{{ $t('Transaction') }} *</label>
                        <select v-model="transactionType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="opt in transactionTypeOptions" :key="opt.value" :value="opt.value">{{ $t(opt.label) }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Cash Payment -->
            <div v-if="moneyType === 'cash_payment'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">{{ $t('Cash Payment Information') }}</h2>
                    <div v-if="cashPaymentBalance !== null" class="text-sm cvr-text-secondary">{{ $t('Balance:') }} <span class="cvr-num">{{ Number(cashPaymentBalance).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span></div>
                </div>
                <div class="cvr-form-grid-6-3-3">
                    <div>
                        <label class="cvr-form-label">{{ $t('Paying Branch') }} *</label>
                        <select v-model="cashPayment.deliveryBranchId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="-1">{{ $t('Select Branch') }}</option>
                            <option v-for="b in branchesList" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Paid Amount [') }}{{ paymentCurrency }}] *</label>
                        <input v-model="paidAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Receipt Number') }} *</label>
                        <input v-model="cashPayment.receiptNumber" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2-narrow mt-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Exchange Rate') }} *</label>
                        <input v-model="exchangeRateInput" @blur="onExchangeRateBlur" type="text" inputmode="decimal" :placeholder="$t('e.g. 50 or 1/50')" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Amount In Invoice Currency [') }}{{ invoiceCurrency }}]</label>
                        <input :value="amountInInvoiceCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Payable Cheque -->
            <div v-if="moneyType === 'payable_cheque'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">{{ $t('Payable Cheque Information') }}</h2>
                    <div v-if="payableChequeBalance !== null" class="text-sm cvr-text-secondary">{{ $t('Balance:') }} <span class="cvr-num">{{ Number(payableChequeBalance).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span></div>
                    <div v-if="payableChequeNetBalance !== null" class="text-sm cvr-text-secondary">{{ $t('Net Balance:') }} <span class="cvr-num">{{ Number(payableChequeNetBalance).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span></div>
                </div>
                <div class="cvr-form-grid-6-2-2-2">
                    <div>
                        <label class="cvr-form-label">{{ $t('Payment Bank') }} *</label>
                        <select v-model="payableCheque.deliveryBankId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="b in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Account Type') }} *</label>
                        <select v-model="payableCheque.accountTypeId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <p v-if="errors['account_type.payable_cheque']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors[$t('account_type.payable_cheque')] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                        <select v-model="payableCheque.accountNumber" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="n in payableChequeAccountNumbers" :key="n.value" :value="n.value">{{ n.label }}</option>
                        </select>
                        <p v-if="errors['account_number.payable_cheque']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors[$t('account_number.payable_cheque')] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Cheque Amount [') }}{{ paymentCurrency }}] *</label>
                        <input v-model="paidAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div class="cvr-form-grid-3 mt-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Due Date') }} *</label>
                        <input v-model="payableCheque.dueDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.due_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.due_date }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Cheque Number') }} *</label>
                        <input v-model="payableCheque.chequeNumber" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.cheque_number" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.cheque_number }}</p>
                    </div>
                    <template v-if="showExchangeRateFields">
                        <div class="cvr-field-narrow">
                            <label class="cvr-form-label">{{ $t('Exchange Rate *') }}</label>
                            <input v-model="exchangeRateInput" @blur="onExchangeRateBlur" type="text" inputmode="decimal" :placeholder="$t('e.g. 50 or 1/50')" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </template>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2-narrow mt-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Amount In Invoice Currency [') }}{{ invoiceCurrency }}]</label>
                        <input :value="amountInInvoiceCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Outgoing Transfer -->
            <div v-if="moneyType === 'outgoing-transfer'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">{{ $t('Outgoing Transfer Information') }}</h2>
                    <div v-if="outgoingTransferBalance !== null" class="text-sm cvr-text-secondary">{{ $t('Balance:') }} <span class="cvr-num">{{ Number(outgoingTransferBalance).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span></div>
                    <div v-if="outgoingTransferNetBalance !== null" class="text-sm cvr-text-secondary">{{ $t('Net Balance:') }} <span class="cvr-num">{{ Number(outgoingTransferNetBalance).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span></div>
                </div>
                <div class="cvr-form-grid-6-2-2-2">
                    <div>
                        <label class="cvr-form-label">{{ $t('Payment Bank') }} *</label>
                        <select v-model="outgoingTransfer.deliveryBankId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="b in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Account Type') }} *</label>
                        <select v-model="outgoingTransfer.accountTypeId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <p v-if="errors['account_type.outgoing-transfer']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors[$t('account_type.outgoing-transfer')] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                        <select v-model="outgoingTransfer.accountNumber" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="n in outgoingTransferAccountNumbers" :key="n.value" :value="n.value">{{ n.label }}</option>
                        </select>
                        <p v-if="errors['account_number.outgoing-transfer']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors[$t('account_number.outgoing-transfer')] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Transfer Amount [') }}{{ paymentCurrency }}] *</label>
                        <input v-model="paidAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2-narrow mt-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Exchange Rate') }} *</label>
                        <input v-model="exchangeRateInput" @blur="onExchangeRateBlur" type="text" inputmode="decimal" :placeholder="$t('e.g. 50 or 1/50')" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Amount In Invoice Currency [') }}{{ invoiceCurrency }}]</label>
                        <input :value="amountInInvoiceCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Through Leasing -->
            <div v-if="moneyType === 'leasing_payment'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">{{ $t('Leasing Information') }}</h2>
                    <div v-if="selectedLeasingContract" class="text-sm cvr-text-secondary">
                        {{ $t('Available:') }} <span class="cvr-num">{{ Number(selectedLeasingContract.available_room).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                        <span class="cvr-text-muted"> / {{ Number(selectedLeasingContract.limit).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                    </div>
                </div>
                <p class="text-xs cvr-text-muted mb-4">
                    {{ $t('The leasing company pays the supplier directly out of the contract — your own bank accounts are not touched.') }}
                </p>
                <div class="cvr-form-grid-6-3-3">
                    <div>
                        <label class="cvr-form-label">{{ $t('Leasing Company') }} *</label>
                        <select v-model="leasing.leasingCompanyId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="c in [...leasingCompanies].sort((a, b) => a.name.localeCompare(b.name))" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="errors['leasing_company_id']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors[$t('leasing_company_id')] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Contract Name') }} *</label>
                        <select v-model="leasing.leasingContractId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="c in leasingContracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="errors['leasing_contract_id']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors[$t('leasing_contract_id')] }}</p>
                        <p v-else-if="leasing.leasingCompanyId && leasingContracts.length === 0" class="text-xs cvr-text-muted mt-1">
                            {{ $t('This leasing company has no running contract in') }} {{ paymentCurrency }}.
                        </p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Paid Amount [') }}{{ paymentCurrency }}] *</label>
                        <input v-model="paidAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2-narrow mt-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Exchange Rate') }} *</label>
                        <input v-model="exchangeRateInput" @blur="onExchangeRateBlur" type="text" inputmode="decimal" :placeholder="$t('e.g. 50 or 1/50')" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Amount In Invoice Currency [') }}{{ invoiceCurrency }}]</label>
                        <input :value="amountInInvoiceCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Settlement Information -->
            <div v-if="showSettlementCard" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">{{ $t('Settlement Information') }}</h2>

                <div v-if="!supplierId" class="text-sm cvr-text-muted mb-4">{{ $t('Select a supplier to see their open invoices.') }}</div>
                <div v-else-if="invoicesFetchFailed" class="cvr-border rounded-lg p-3 mb-4 text-sm" style="border-color: var(--cvr-danger-text); background: var(--cvr-bg-card-alt)">
                    <p style="color: var(--cvr-danger-text)">{{ $t('Couldn\'t load invoices for this supplier — please try again, or reload the page.') }}</p>
                </div>
                <div v-else-if="invoices.length === 0 && supplierInvoiceCurrencies.length && !supplierInvoiceCurrencies.includes(invoiceCurrency)" class="text-sm cvr-text-muted mb-4">
                    {{ $t('This supplier has no open invoices in') }} {{ invoiceCurrency }}{{ $t('. They do have invoices in:') }} {{ supplierInvoiceCurrencies.join(', ') }} {{ $t('— try switching Invoice Currency above.') }}
                </div>
                <div v-else-if="invoices.length === 0" class="text-sm cvr-text-muted mb-4">
                    {{ $t('This supplier has no open invoices in') }} {{ invoiceCurrency }}.
                </div>

                <div v-for="inv in invoices" :key="inv.id" class="cvr-border border rounded-lg p-4 mb-3">
                    <div class="cvr-form-grid-6-2-2-2 mb-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Invoice Number') }}</label>
                            <input :value="inv.invoice_number" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Invoice Date') }}</label>
                            <input :value="inv.invoice_date" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Due Date') }}</label>
                            <input :value="inv.invoice_due_date" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Invoice Amount [') }}{{ inv.currency }}]</label>
                            <input :value="formatMoney(inv.net_invoice_amount)" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Paid Amount') }}</label>
                            <input :value="formatMoney(inv.paid_amount)" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Net Balance') }}</label>
                            <input :value="formatMoney(inv.net_balance)" readonly class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Settlement Amount') }} *</label>
                            <input v-model.number="inv.settlement_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors[`settlements.${inv.id}.settlement_amount`]" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors[`settlements.${inv.id}.settlement_amount`] }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Withhold Amount') }}</label>
                            <input v-model.number="inv.withhold_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <hr class="my-4 cvr-border" />

                <div v-if="showDownPaymentPicker" class="mb-4">
                    <h3 class="text-sm font-medium cvr-text-primary mb-3">{{ $t('Choose Contract For Down Payment') }}</h3>
                    <div class="cvr-form-grid-3 mb-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Contract') }}</label>
                            <select v-model="contractId" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="general-down">{{ $t('General Down Payment') }}</option>
                                <option v-for="c in contracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="errors.contract_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.contract_id }}</p>
                        </div>
                    </div>
                    <div v-for="po in purchaseOrders" :key="po.id" class="cvr-form-grid-3 mb-2">
                        <div>
                            <label class="cvr-form-label">{{ $t('PO Number') }}</label>
                            <input :value="po.po_number" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Amount [') }}{{ paymentCurrency }}]</label>
                            <input :value="po.amount" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Paid Amount [') }}{{ paymentCurrency }}] *</label>
                            <input v-model.number="po.received_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <p v-if="errors.purchases_orders_amounts" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.purchases_orders_amounts }}</p>
                </div>

                <div class="cvr-form-row-unapplied">
                    <div>
                        <label class="cvr-form-label">{{ $t('Unapplied Amount [') }}{{ paymentCurrency }}]</label>
                        <input :value="unappliedAmountInPaymentCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <!-- Second field only when Invoice Currency and Payment Currency differ —
                         with the same currency both figures are identical, so showing both is redundant. -->
                    <div v-if="!isSameCurrency">
                        <label class="cvr-form-label">{{ $t('Unapplied Amount [') }}{{ invoiceCurrency }}]</label>
                        <input :value="unappliedAmount" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <p v-if="errors.settlements" class="text-xs mt-2" style="color: var(--cvr-danger-text)">{{ errors.settlements }}</p>
            </div>

            <!-- Comment -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <label class="cvr-form-label">{{ $t('Comment') }}</label>
                <textarea v-model="userComment" rows="2" class="cvr-input w-full px-3 py-2 rounded"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="urls.index" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Cancel') }}</Link>
                <button @click="submit" class="cvr-btn-primary px-4 py-2 rounded">{{ isEdit ? $t('Update') : $t('Save') }}</button>
            </div>

        </div>
    </AppLayout>
</template>