<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { todayDate } from '@/composables/today';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate();

const props = defineProps({
    company: Object,
    model: Object,          // null on create
    singleModel: [Number, String],
    invoiceNumber: String,
    warningMessage: String,
    customers: Array,
    partnerTypes: Array,
    moneyTypes: Array,
    currencies: Array,
    selectedBranches: Array,
    selectedBanks: Array,       // drawee/drawl banks (for Cheque)
    financialInstitutionBanks: Array,
    accountTypes: Array,
    urls: Object,
});

const page = usePage();
const isEdit = computed(() => !!props.model);
const errors = computed(() => page.props.errors || {});
// Same reasoning as MoneyPayment/Form.vue: several server-side rules
// are attached to a whole field or a pseudo-field with no natural
// inline home in this UI, so a catch-all is needed to guarantee no
// validation failure is ever silent.
const errorMessages = computed(() => Object.values(errors.value).flat().filter(Boolean));

/**
 * ⚠️ Real fix: plain fetch() does not send the headers jQuery's
 * $.ajax() (used by the original page) sent automatically — notably
 * `X-Requested-With: XMLHttpRequest`, which Laravel's own
 * Request::ajax()/expectsJson() checks lean on. Without it, an
 * unhandled server-side exception renders Laravel's normal HTML error
 * page instead of a JSON error body, which is why a genuine backend
 * error shows up client-side as "Unexpected token '<' ... not valid
 * JSON" instead of a readable message. This wraps every AJAX call on
 * this page so errors are always inspectable, not just successes.
 */
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
const receivingDate = ref(props.model?.receiving_date || '');
const partnerType = ref(props.model?.partner_type || 'is_customer');
// When arriving via the invoice-row "Money Receive" button, `currencies`
// is restricted server-side to that one invoice's currency only — use
// it as the default here (matches the original's $currencies-driven
// preselection for both Invoice Currency and Receive Currency).
const singleInvoiceCurrency = props.singleModel ? props.currencies[0]?.code : null;
const invoiceCurrency = ref(props.model?.currency || singleInvoiceCurrency || props.company.mainFunctionalCurrency || '');
const customerId = ref(props.model?.customer_id || (props.singleModel ? props.customers[0]?.id : ''));
const receivingCurrency = ref(props.model?.receiving_currency || singleInvoiceCurrency || props.company.mainFunctionalCurrency || '');
const moneyType = ref(props.model?.type || '');
const transactionType = ref(props.model?.transaction_type || '');
const userComment = ref(props.model?.user_comment || '');

/* Confirmed against custom/money-receive.js's showOrHideTransaction() —
   options are gated per partner type, not one shared list. */
const transactionTypeOptions = computed(() => {
    if (partnerType.value === 'is_employee') {
        return [
            { value: 'refund-custody', label: 'Refund Custody' },
            { value: 'pay-loan', label: 'Pay Loan' },
        ];
    }
    if (partnerType.value === 'is_shareholder' || partnerType.value === 'is_subsidiary_company') {
        return [{ value: 'funding-from', label: 'Funding From' }];
    }
    if (partnerType.value === 'is_other_partner') {
        return [{ value: 'insurance-from', label: 'Insurance From' }];
    }
    return [];
});

const customersList = ref([...props.customers]);
const branchesList = ref([...props.selectedBranches]);

/* ── Type-specific fields (one flat set, namespaced at submit time
   by whichever `moneyType` is currently selected — matches the
   backend, which only ever reads `.{$moneyType}` out of these
   arrays regardless of what else was posted) ───────────────────── */
const receivedAmount = ref(props.model?.received_amount || 0);

/* Exchange Rate now accepts either a plain number ("50") or a division
   expression ("1/50" or "=1/50", spreadsheet-style) — some rates are
   naturally quoted as a fraction and forcing manual division invites
   typos. `exchangeRateInput` holds exactly what the person typed;
   `exchangeRate` is the parsed numeric value everything else (amount
   calculations, the submitted payload) actually uses. */
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

const cashInSafe = reactive({
    receivingBranchId: props.model?.cash_in_safe?.receiving_branch_id || '',
    receiptNumber: props.model?.cash_in_safe?.receipt_number || '',
});
const cashInBank = reactive({
    receivingBankId: props.model?.cash_in_bank?.receiving_bank_id || '',
    accountTypeId: props.model?.cash_in_bank?.account_type_id || '',
    accountNumber: props.model?.cash_in_bank?.account_number || '',
});
const incomingTransfer = reactive({
    receivingBankId: props.model?.incoming_transfer?.receiving_bank_id || '',
    accountTypeId: props.model?.incoming_transfer?.account_type_id || '',
    accountNumber: props.model?.incoming_transfer?.account_number || '',
});
const cheque = reactive({
    draweeBankId: props.model?.cheque?.drawee_bank_id || '',
    dueDate: props.model?.cheque?.due_date || '',
    chequeNumber: props.model?.cheque?.cheque_number || '',
    branchId: props.model?.cheque?.branch_id || '',
});

const cashInBankAccountNumbers = ref([]);
const incomingTransferAccountNumbers = ref([]);
const cashInSafeBalance = ref(null);

/* ── Derived amounts (mirrors the original's `recalculate-amount-
   class` / `currency-class` change handlers) ─────────────────── */
const isSameCurrency = computed(() => invoiceCurrency.value && invoiceCurrency.value === receivingCurrency.value);
const showExchangeRateFields = computed(() => partnerType.value === 'is_customer' && invoiceCurrency.value && receivingCurrency.value && !isSameCurrency.value);
const amountInInvoiceCurrency = computed(() => {
    if (isSameCurrency.value || !exchangeRate.value) return Number(receivedAmount.value) || 0;
    return Math.round(((Number(receivedAmount.value) || 0) / Number(exchangeRate.value)) * 100) / 100;
});

/* ── Settlement Information (invoices repeater) — customers only ── */
const showSettlementCard = computed(() => partnerType.value === 'is_customer');
const invoices = ref([]); // [{ id, invoice_number, project_name, invoice_date, invoice_due_date, currency, net_invoice_amount, collected_amount, net_balance, settlement_amount, withhold_amount }]
const invoicesFetchFailed = ref(null); // set only on a genuine request failure (bad HTTP status / non-JSON body) — a legitimate empty result never sets this
const customerInvoiceCurrencies = ref([]); // currencies this customer actually has invoices in, per the last successful fetch — used to explain a legitimately-empty result rather than leave it looking broken

async function fetchInvoices() {
    if (!showSettlementCard.value || !customerId.value) { invoices.value = []; invoicesFetchFailed.value = null; customerInvoiceCurrencies.value = []; return; }
    customerInvoiceCurrencies.value = [];
    const params = new URLSearchParams();
    // Always send these two, exactly like the original jQuery did
    // (`inEditMode = inEditMode ? inEditMode : 0`, always present in
    // the request) — formatInvoices() server-side requires a real
    // int, not an absent/null value.
    params.set('inEditMode', isEdit.value ? '1' : '0');
    params.set('money_received_id', isEdit.value ? props.model.id : '0');
    params.set('downPaymentContractId', contractId.value && contractId.value !== 'general-down' ? contractId.value : '');
    const currencySegment = invoiceCurrency.value ? `/${invoiceCurrency.value}` : '';
    const url = `${props.urls.getInvoiceNumbers}/${customerId.value}${currencySegment}?${params.toString()}`;
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
        // Arrived via the invoice-row "Money Receive" button — only
        // that one invoice may be settled here, matching the original's
        // client-side `invoiceId == specificInvoiceId` filter (the
        // server still returns every open invoice for this customer/
        // currency; only the display is restricted).
        if (props.singleModel) {
            fetchedInvoices = fetchedInvoices.filter(inv => String(inv.id) === String(props.singleModel));
        }
        invoices.value = fetchedInvoices;
        customerInvoiceCurrencies.value = Object.keys(data.currencies || {});
    } catch (e) {
        invoices.value = [];
        invoicesFetchFailed.value = { url, status: 'network error', error: String(e) };
    }
}

/* ⚠️ Fixed against the real original source (custom/money-receive.js,
   the `.js-settlement-amount,.settlement-amount-class,[data-max-cheque-
   value]` change handler) — do not "simplify" this back to a naive
   subtraction, both details below are load-bearing:
   1. Only `settlement_amount` is subtracted here — `withhold_amount`
      is NOT part of this calculation (it only matters for the
      per-invoice net-balance validation, a separate concern).
   2. The receiving-currency figure additionally subtracts whatever's
      already been typed into the down-payment sales-order rows below
      (`totalOrdersAmount`), so it live-updates as that repeater fills
      in — it is NOT simply unappliedAmount * exchangeRate. */
const totalSettled = computed(() =>
    invoices.value.reduce((sum, inv) => sum + (Number(inv.settlement_amount) || 0), 0)
);
const totalDownPaymentOrdersAmount = computed(() =>
    salesOrders.value.reduce((sum, so) => sum + (Number(so.received_amount) || 0), 0)
);

/* Unapplied Amount — shown/submitted in INVOICE currency (matches the
   original's `#remaining-settlement-js` / name="unapplied_amount"). */
const unappliedAmount = computed(() => {
    const remaining = amountInInvoiceCurrency.value - totalSettled.value;
    return Math.round((remaining || 0) * 100) / 100;
});
const unappliedAmountInReceivingCurrency = computed(() => {
    const remaining = unappliedAmount.value * (Number(exchangeRate.value) || 1) - totalDownPaymentOrdersAmount.value;
    return Math.round((remaining || 0) * 100) / 100;
});

/* ── "Choose Contract For Down Payment" — appears whenever there's a
   real unapplied amount left over after settling selected invoices,
   exactly like the original's nested contract-row-id section. Also
   covers "General Down Payment" (contract_id = 'general-down', which
   the backend already treats as no real contract — see store()). ── */
const showDownPaymentPicker = computed(() => showSettlementCard.value && unappliedAmount.value > 0.009);
const contractId = ref(props.model?.contract_id || '');
const contracts = ref([]);
const salesOrders = ref([]); // [{ id, so_number, amount, received_amount }]

async function fetchContracts() {
    if (!customerId.value || !receivingCurrency.value) { contracts.value = []; return; }
    const params = new URLSearchParams({ customerId: customerId.value, currency: receivingCurrency.value });
    const result = await fetchJson(`${props.urls.getContractsForCustomer}?${params.toString()}`);
    contracts.value = Object.entries(result.data?.contracts || {}).map(([id, name]) => ({ id, name }));
}
async function fetchSalesOrders() {
    if (!contractId.value) { salesOrders.value = []; return; }
    if (contractId.value === 'general-down') {
        // No real sales order behind "General Down Payment" — show one
        // synthetic row instead ("General", disabled, 0 order amount)
        // so the person can still type how much of the unapplied
        // amount to send as a down payment. `id: -1` is a real sentinel
        // the backend already understands (storeNewSalesOrdersAmounts
        // maps sales_order_id -1 → null), not a UI-only placeholder —
        // don't change it to null/0 here or the submitted row breaks.
        salesOrders.value = [{ id: -1, so_number: 'General', amount: 0, received_amount: 0 }];
        return;
    }
    const params = new URLSearchParams();
    params.set('down_payment_id', isEdit.value ? props.model.id : '0');
    const result = await fetchJson(`${props.urls.getSalesOrdersForContract}/${contractId.value}/${receivingCurrency.value}?${params.toString()}`);
    salesOrders.value = (result.data?.sales_orders || []).map(so => ({ ...so, received_amount: Number(so.received_amount) || 0 }));
}

/* ── Account number lookups (real AJAX, scoped by bank + type +
   currency — same endpoints the original jQuery form called) ───── */
async function fetchAccountNumbers(accountTypeId, financialInstitutionId, target) {
    target.value = [];
    if (!accountTypeId || !financialInstitutionId || !receivingCurrency.value) return;
    const url = `${props.urls.getAccountNumbersForType}/${accountTypeId}/${receivingCurrency.value}/${financialInstitutionId}`;
    const result = await fetchJson(url);
    target.value = Object.values(result.data?.data || {});
}

async function fetchCashInSafeBalance() {
    if (!cashInSafe.receivingBranchId || cashInSafe.receivingBranchId === '-1') { cashInSafeBalance.value = null; return; }
    const params = new URLSearchParams({
        branchId: cashInSafe.receivingBranchId,
        currencyName: receivingCurrency.value,
        modelId: props.model?.id || 0,
        modelType: 'MoneyReceived',
        balanceDate: receivingDate.value,
    });
    const result = await fetchJson(`${props.urls.getCashInSafeEndBalance}?${params.toString()}`);
    cashInSafeBalance.value = result.data?.end_balance ?? null;
}

async function fetchBranchesForCurrency() {
    if (!receivingCurrency.value) return;
    const result = await fetchJson(`${props.urls.getBranchBasedOnCurrency}?currencyName=${receivingCurrency.value}`);
    branchesList.value = Object.entries(result.data?.branches || {}).map(([name, id]) => ({ id, name }));
}

async function fetchCustomersForPartnerType() {
    if (!invoiceCurrency.value) return;
    const params = new URLSearchParams({ partnerColumnName: partnerType.value });
    const result = await fetchJson(`${props.urls.getPartnersBasedOnCurrency}/${invoiceCurrency.value}?${params.toString()}`);
    customersList.value = Object.entries(result.data?.partners || {}).map(([name, id]) => ({ id, name }));
}


/* ── Watchers — mirror the original's chained AJAX triggers ──────── */
watch(receivingCurrency, () => {
    fetchBranchesForCurrency();
    if (showSettlementCard.value) fetchContracts();
});
watch(invoiceCurrency, (newCurrency) => {
    // Matches `select.invoice-currency-class`'s change handler: for
    // customers, Receive Currency is kept in sync with Invoice
    // Currency (the person can still change it afterward — this only
    // fires again if they change Invoice Currency again).
    if (partnerType.value === 'is_customer' && newCurrency) {
        receivingCurrency.value = newCurrency;
    }
    fetchCustomersForPartnerType();
});
watch(partnerType, () => {
    fetchCustomersForPartnerType();
    transactionType.value = '';
    if (partnerType.value !== 'is_customer') { invoices.value = []; contractId.value = ''; }
});
watch(customerId, fetchContracts);
watch([customerId, invoiceCurrency], fetchInvoices);
watch(contractId, fetchSalesOrders);
watch(() => cashInBank.accountTypeId, () => fetchAccountNumbers(cashInBank.accountTypeId, cashInBank.receivingBankId, cashInBankAccountNumbers));
watch(() => cashInBank.receivingBankId, () => fetchAccountNumbers(cashInBank.accountTypeId, cashInBank.receivingBankId, cashInBankAccountNumbers));
watch(() => incomingTransfer.accountTypeId, () => fetchAccountNumbers(incomingTransfer.accountTypeId, incomingTransfer.receivingBankId, incomingTransferAccountNumbers));
watch(() => incomingTransfer.receivingBankId, () => fetchAccountNumbers(incomingTransfer.accountTypeId, incomingTransfer.receivingBankId, incomingTransferAccountNumbers));
watch(receivingCurrency, () => {
    if (moneyType.value === 'cash-in-bank') fetchAccountNumbers(cashInBank.accountTypeId, cashInBank.receivingBankId, cashInBankAccountNumbers);
    if (moneyType.value === 'incoming-transfer') fetchAccountNumbers(incomingTransfer.accountTypeId, incomingTransfer.receivingBankId, incomingTransferAccountNumbers);
});
watch(() => cashInSafe.receivingBranchId, fetchCashInSafeBalance);
watch(receivingDate, fetchCashInSafeBalance);

onMounted(() => {
    if (isEdit.value) {
        fetchInvoices();
        if (contractId.value) fetchSalesOrders();
        if (cashInBank.accountTypeId) fetchAccountNumbers(cashInBank.accountTypeId, cashInBank.receivingBankId, cashInBankAccountNumbers);
        if (incomingTransfer.accountTypeId) fetchAccountNumbers(incomingTransfer.accountTypeId, incomingTransfer.receivingBankId, incomingTransferAccountNumbers);
        fetchCashInSafeBalance();
    } else {
        // Matches the original's own on-load triggers for a fresh
        // create (`$('select#partner_type').trigger('change')` +
        // the unconditional invoice-numbers trigger in
        // custom/money-receive.js, which fires on every load
        // regardless of the single-invoice button) — without this,
        // neither the currency-filtered customer list nor the
        // single-invoice row appear until the person manually
        // touches a dropdown.
        if (!props.singleModel) {
            // Arriving via the single-invoice button already has its
            // customer list correctly restricted server-side — don't
            // overwrite it with the generic currency-filtered fetch.
            fetchCustomersForPartnerType();
        }
        if (showSettlementCard.value && customerId.value) fetchInvoices();
    }
});

/* ── Submit ───────────────────────────────────────────────────── */
function buildPayload() {
    const settlements = {};
    invoices.value.forEach(inv => {
        if ((Number(inv.settlement_amount) || 0) > 0 || (Number(inv.withhold_amount) || 0) > 0) {
            settlements[inv.invoice_number] = {
                invoice_id: inv.id,
                invoice_number: inv.invoice_number,
                project_name: inv.project_name,
                net_balance: inv.net_balance,
                settlement_amount: inv.settlement_amount,
                withhold_amount: inv.withhold_amount,
            };
        }
    });

    // ⚠️ Submit every row, not just positive ones — storeNewSalesOrdersAmounts()
    // treats a genuinely EMPTY array as "no contract chosen, put the whole
    // amount into one unattributed settlement" (contract_id forced to null
    // server-side). Filtering out zero-amount rows here would trigger that
    // same fallback even when a real contract WAS chosen but nothing had
    // been allocated yet — the server already skips zero-amount rows
    // per-row (`if ($row['received_amount'] > 0)`), so there's no need to
    // pre-filter, and doing so changes the meaning of an empty submission.
    const salesOrdersAmounts = salesOrders.value.map(so => ({
        sales_order_id: so.id,
        sales_order_name: so.so_number,
        net_invoice_amount: so.amount,
        received_amount: so.received_amount,
    }));

    const payload = {
        receiving_date: receivingDate.value,
        partner_type: partnerType.value,
        currency: invoiceCurrency.value,
        customer_id: customerId.value,
        receiving_currency: receivingCurrency.value,
        type: moneyType.value,
        transaction_type: transactionType.value,
        user_comment: userComment.value,
        received_amount: { [moneyType.value]: receivedAmount.value },
        exchange_rate: { [moneyType.value]: exchangeRate.value },
        amount_in_invoice_currency: { [moneyType.value]: amountInInvoiceCurrency.value },
        settlements,
        unapplied_amount: showDownPaymentPicker.value ? unappliedAmount.value : 0,
        contract_id: showDownPaymentPicker.value && contractId.value !== 'general-down' ? contractId.value : null,
        sales_orders_amounts: showDownPaymentPicker.value ? salesOrdersAmounts : [],
    };

    if (moneyType.value === 'cash-in-safe') {
        payload.receiving_branch_id = cashInSafe.receivingBranchId;
        payload.receipt_number = cashInSafe.receiptNumber;
    }
    if (moneyType.value === 'cash-in-bank') {
        payload.receiving_bank_id = { 'cash-in-bank': cashInBank.receivingBankId };
        payload.account_type = { 'cash-in-bank': cashInBank.accountTypeId };
        payload.account_number = { 'cash-in-bank': cashInBank.accountNumber };
    }
    if (moneyType.value === 'incoming-transfer') {
        payload.receiving_bank_id = { 'incoming-transfer': incomingTransfer.receivingBankId };
        payload.account_type = { 'incoming-transfer': incomingTransfer.accountTypeId };
        payload.account_number = { 'incoming-transfer': incomingTransfer.accountNumber };
    }
    if (moneyType.value === 'cheque') {
        payload.drawee_bank_id = cheque.draweeBankId;
        payload.due_date = cheque.dueDate;
        payload.cheque_number = cheque.chequeNumber;
        payload.cheque_branch_id = cheque.branchId;
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
        <div class="p-6">
            <Link :href="urls.index" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                ← Back to Money Received
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ isEdit ? 'Edit Money Received' : 'Money Received' }}</h1>
            <p class="text-sm cvr-text-blue mb-6">{{ company.name }}</p>

            <p v-if="warningMessage" class="cvr-badge cvr-badge-pending px-3 py-2 rounded mb-4 block">{{ warningMessage }}</p>
            <div v-if="errorMessages.length" class="cvr-border rounded-lg p-3 mb-4 text-sm" style="border-color: var(--cvr-danger-text); background: var(--cvr-bg-card-alt)">
                <p class="font-medium mb-1" style="color: var(--cvr-danger-text)">Couldn't save — please fix the following:</p>
                <ul class="list-disc list-inside" style="color: var(--cvr-danger-text)">
                    <li v-for="(msg, i) in errorMessages" :key="i">{{ msg }}</li>
                </ul>
            </div>

            <!-- Header -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">Money Received</h2>
                <div :class="partnerType === 'is_customer' ? 'cvr-form-grid-2-2-1-4-1-2' : 'cvr-form-grid-2-2-5-1-3'">
                    <div>
                        <label class="cvr-form-label">Receiving Date *</label>
                        <input v-model="receivingDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.receiving_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.receiving_date }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Partner Type *</label>
                        <select v-model="partnerType" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="t in partnerTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div v-if="partnerType === 'is_customer'">
                        <label class="cvr-form-label">Invoice Currency*</label>
                        <select v-model="invoiceCurrency" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Name *</label>
                        <select v-model="customerId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="c in customersList" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="errors.customer_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.customer_id }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Receive Currency*</label>
                        <select v-model="receivingCurrency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Money Type *</label>
                        <select v-model="moneyType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="t in moneyTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p v-if="errors.type" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.type }}</p>
                    </div>
                </div>
                <div v-if="partnerType !== 'is_customer'" class="mt-4">
                    <div class="cvr-field-narrow">
                        <label class="cvr-form-label">Transaction *</label>
                        <select v-model="transactionType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="opt in transactionTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Cash In Safe -->
            <div v-if="moneyType === 'cash-in-safe'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">Cash Information</h2>
                    <div v-if="cashInSafeBalance !== null" class="text-sm cvr-text-secondary">Balance: <span class="cvr-num">{{ Number(cashInSafeBalance).toLocaleString() }}</span></div>
                </div>
                <div class="cvr-form-grid-6-3-3">
                    <div>
                        <label class="cvr-form-label">Receiving Branch *</label>
                        <select v-model="cashInSafe.receivingBranchId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="-1">Select Branch</option>
                            <option v-for="b in branchesList" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Received Amount [{{ receivingCurrency }}] *</label>
                        <input v-model="receivedAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Receipt Number *</label>
                        <input v-model="cashInSafe.receiptNumber" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2-narrow mt-4">
                    <div>
                        <label class="cvr-form-label">Exchange Rate *</label>
                        <input v-model="exchangeRateInput" @blur="onExchangeRateBlur" type="text" inputmode="decimal" placeholder="e.g. 50 or 1/50" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Amount In Invoice Currency [{{ invoiceCurrency }}]</label>
                        <input :value="amountInInvoiceCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Bank Deposit -->
            <div v-if="moneyType === 'cash-in-bank'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">Bank Deposit Information</h2>
                <div class="cvr-form-grid-6-2-2-2">
                    <div>
                        <label class="cvr-form-label">Receiving Bank *</label>
                        <select v-model="cashInBank.receivingBankId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="b in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Deposit Amount [{{ receivingCurrency }}] *</label>
                        <input v-model="receivedAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Account Type *</label>
                        <select v-model="cashInBank.accountTypeId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <p v-if="errors['account_type.cash-in-bank']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors['account_type.cash-in-bank'] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Account Number *</label>
                        <select v-model="cashInBank.accountNumber" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="n in cashInBankAccountNumbers" :key="n" :value="n">{{ n }}</option>
                        </select>
                        <p v-if="errors['account_number.cash-in-bank']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors['account_number.cash-in-bank'] }}</p>
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2-narrow mt-4">
                    <div>
                        <label class="cvr-form-label">Exchange Rate *</label>
                        <input v-model="exchangeRateInput" @blur="onExchangeRateBlur" type="text" inputmode="decimal" placeholder="e.g. 50 or 1/50" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Amount In Invoice Currency [{{ invoiceCurrency }}]</label>
                        <input :value="amountInInvoiceCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Cheque -->
            <div v-if="moneyType === 'cheque'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">Cheque Information</h2>
                <div class="cvr-form-grid-6-2-2-2">
                    <div>
                        <label class="cvr-form-label">Select Drawee Bank *</label>
                        <select v-model="cheque.draweeBankId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="b in selectedBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Cheque Amount [{{ receivingCurrency }}] *</label>
                        <input v-model="receivedAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Due Date *</label>
                        <input v-model="cheque.dueDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.due_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.due_date }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Cheque Number *</label>
                        <input v-model="cheque.chequeNumber" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.cheque_number" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.cheque_number }}</p>
                    </div>
                </div>
                <div class="cvr-form-grid-3 mt-4">
                    <div>
                        <label class="cvr-form-label">Branch *</label>
                        <select v-model="cheque.branchId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="b in branchesList" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <template v-if="showExchangeRateFields">
                        <div class="cvr-field-narrow">
                            <label class="cvr-form-label">Exchange Rate *</label>
                            <input v-model="exchangeRateInput" @blur="onExchangeRateBlur" type="text" inputmode="decimal" placeholder="e.g. 50 or 1/50" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div class="cvr-field-narrow">
                            <label class="cvr-form-label">Amount In Invoice Currency [{{ invoiceCurrency }}]</label>
                            <input :value="amountInInvoiceCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </template>
                </div>
            </div>

            <!-- Incoming Transfer -->
            <div v-if="moneyType === 'incoming-transfer'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">Incoming Transfer Information</h2>
                <div class="cvr-form-grid-6-2-2-2">
                    <div>
                        <label class="cvr-form-label">Receiving Bank *</label>
                        <select v-model="incomingTransfer.receivingBankId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="b in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Transfer Amount [{{ receivingCurrency }}] *</label>
                        <input v-model="receivedAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Account Type *</label>
                        <select v-model="incomingTransfer.accountTypeId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <p v-if="errors['account_type.incoming-transfer']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors['account_type.incoming-transfer'] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Account Number *</label>
                        <select v-model="incomingTransfer.accountNumber" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="n in incomingTransferAccountNumbers" :key="n" :value="n">{{ n }}</option>
                        </select>
                        <p v-if="errors['account_number.incoming-transfer']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors['account_number.incoming-transfer'] }}</p>
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2-narrow mt-4">
                    <div>
                        <label class="cvr-form-label">Exchange Rate *</label>
                        <input v-model="exchangeRateInput" @blur="onExchangeRateBlur" type="text" inputmode="decimal" placeholder="e.g. 50 or 1/50" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Amount In Invoice Currency [{{ invoiceCurrency }}]</label>
                        <input :value="amountInInvoiceCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Settlement Information -->
            <div v-if="showSettlementCard" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">Settlement Information</h2>

                <div v-if="!customerId" class="text-sm cvr-text-muted mb-4">
                    Select a customer to see their open invoices.
                </div>
                <div v-else-if="invoicesFetchFailed" class="cvr-border rounded-lg p-3 mb-4 text-sm" style="border-color: var(--cvr-danger-text); background: var(--cvr-bg-card-alt)">
                    <p style="color: var(--cvr-danger-text)">Couldn't load invoices for this customer — please try again, or reload the page. If this keeps happening, contact support.</p>
                </div>
                <div v-else-if="invoices.length === 0 && customerInvoiceCurrencies.length && !customerInvoiceCurrencies.includes(invoiceCurrency)" class="text-sm cvr-text-muted mb-4">
                    This customer has no open invoices in {{ invoiceCurrency }}. They do have invoices in: {{ customerInvoiceCurrencies.join(', ') }} — try switching Invoice Currency above.
                </div>
                <div v-else-if="invoices.length === 0" class="text-sm cvr-text-muted mb-4">
                    This customer has no open invoices in {{ invoiceCurrency }}.
                </div>

                <div v-for="(inv, i) in invoices" :key="inv.id" class="cvr-border border rounded-lg p-4 mb-3">
                    <div class="cvr-form-grid-3-3-2-2-2 mb-3">
                        <div>
                            <label class="cvr-form-label">Project Name</label>
                            <input :value="inv.project_name || '--'" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Invoice Number</label>
                            <input :value="inv.invoice_number" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Invoice Date</label>
                            <input :value="inv.invoice_date" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Due Date</label>
                            <input :value="inv.invoice_due_date" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Invoice Amount [{{ inv.currency }}]</label>
                            <input :value="inv.net_invoice_amount" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Collected Amount</label>
                            <input :value="inv.collected_amount" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Net Balance</label>
                            <input :value="inv.net_balance" readonly class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Settlement Amount *</label>
                            <input v-model.number="inv.settlement_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors[`settlements.${inv.invoice_number}.settlement_amount`]" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors[`settlements.${inv.invoice_number}.settlement_amount`] }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Withhold Amount</label>
                            <input v-model.number="inv.withhold_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <hr class="my-4 cvr-border" />

                <!-- Choose Contract For Down Payment (only when there's real unapplied money left over) -->
                <div v-if="showDownPaymentPicker" class="mb-4">
                    <h3 class="text-sm font-medium cvr-text-primary mb-3">Choose Contract For Down Payment</h3>
                    <div class="cvr-form-grid-3 mb-3">
                        <div>
                            <label class="cvr-form-label">Contract</label>
                            <select v-model="contractId" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="general-down">General Down Payment</option>
                                <option v-for="c in contracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="errors.contract_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.contract_id }}</p>
                        </div>
                    </div>
                    <div v-for="(so, i) in salesOrders" :key="so.id" class="cvr-form-grid-3 mb-2">
                        <div>
                            <label class="cvr-form-label">SO Number</label>
                            <input :value="so.so_number" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount [{{ receivingCurrency }}]</label>
                            <input :value="so.amount" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Received Amount [{{ receivingCurrency }}] *</label>
                            <input v-model.number="so.received_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <p v-if="errors.sales_orders_amounts" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.sales_orders_amounts }}</p>
                </div>

                <div class="cvr-form-row-unapplied">
                    <div>
                        <label class="cvr-form-label">Unapplied Amount [{{ receivingCurrency }}]</label>
                        <input :value="unappliedAmountInReceivingCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <!-- Second field only when Invoice Currency and Receive Currency differ —
                         with the same currency both figures are identical, so showing both is redundant. -->
                    <div v-if="!isSameCurrency">
                        <label class="cvr-form-label">Unapplied Amount [{{ invoiceCurrency }}]</label>
                        <input :value="unappliedAmount" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <p v-if="errors.settlements" class="text-xs mt-2" style="color: var(--cvr-danger-text)">{{ errors.settlements }}</p>
            </div>

            <!-- Comment -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <label class="cvr-form-label">Comment</label>
                <textarea v-model="userComment" rows="2" class="cvr-input w-full px-3 py-2 rounded"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="urls.index" class="cvr-btn-secondary px-4 py-2 rounded border">Cancel</Link>
                <button @click="submit" class="cvr-btn-primary px-4 py-2 rounded">{{ isEdit ? 'Update' : 'Save' }}</button>
            </div>
        </div>
    </AppLayout>
</template>