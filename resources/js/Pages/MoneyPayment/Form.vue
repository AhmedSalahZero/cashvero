<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
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
    if (partnerType.value === 'is_shareholder' || partnerType.value === 'is_subsidiary_company') {
        return [{ value: 'funding-to', label: 'Funding To' }];
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
const exchangeRate = ref(props.model?.exchange_rate || 1);

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
const invoices = ref([]); // [{ id, invoice_number, invoice_date, invoice_due_date, currency, net_invoice_amount, paid_amount, net_balance, settlement_amount, withhold_amount, allocations: [...] }]
const invoicesFetchFailed = ref(null);
const supplierInvoiceCurrencies = ref([]);
const clientsWithContracts = ref([]); // server sends { id: name } — customers (with contracts) available for the Allocate modal

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
            allocations: (inv.settlement_allocations || []).map(a => ({
                id: a.id,
                partner_id: a.partner_id,
                contract_id: a.contract_id,
                contract_code: a.contract_code,
                contract_amount: a.contract_amount,
                allocation_amount: Number(a.allocation_amount) || 0,
                contracts: [],
            })),
        }));
        if (props.singleModel) {
            fetchedInvoices = fetchedInvoices.filter(inv => String(inv.id) === String(props.singleModel));
        }
        invoices.value = fetchedInvoices;
        clientsWithContracts.value = Object.entries(data.clientsWithContracts || {}).map(([id, name]) => ({ id, name }));
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

/* ── Allocate modal (per invoice row) ─────────────────────────────
   ⚠️ Real fix, not a guess: the original always submitted new/edited
   allocation rows under the WRONG top-level key (`settlements[...]`
   instead of `allocations[...]`), so anything pre-filled from an
   existing record was silently dropped on save — and the Contract
   dropdown inside this modal was never wired to any endpoint at all
   (verified: no handler for it exists anywhere in the real JS). Both
   are fixed here: allocations are tracked in clean Vue state and
   submitted under the correct `allocations[invoiceId]` key regardless
   of whether they're new or pre-filled, and the Contract dropdown
   reuses the same real, working getContractsForCustomer endpoint
   Money Received's own down-payment picker uses. */
const allocationModalInvoiceIndex = ref(null);

function openAllocationModal(invoiceIndex) {
    allocationModalInvoiceIndex.value = invoiceIndex;
    invoices.value[invoiceIndex].allocations.forEach(row => {
        if (row.partner_id && (!row.contracts || row.contracts.length === 0)) {
            fetchAllocationContractsKeepingSelection(row);
        }
    });
}
function closeAllocationModal() {
    allocationModalInvoiceIndex.value = null;
}
function addAllocationRow() {
    if (allocationModalInvoiceIndex.value === null) return;
    invoices.value[allocationModalInvoiceIndex.value].allocations.push({
        id: null, partner_id: '', contract_id: '', contract_code: '', contract_amount: '', allocation_amount: 0, contracts: [],
    });
}
function removeAllocationRow(rowIndex) {
    if (allocationModalInvoiceIndex.value === null) return;
    invoices.value[allocationModalInvoiceIndex.value].allocations.splice(rowIndex, 1);
}
async function fetchAllocationContracts(row) {
    row.contracts = [];
    row.contract_id = '';
    if (!row.partner_id) return;
    const params = new URLSearchParams({ customerId: row.partner_id, currency: paymentCurrency.value });
    const result = await fetchJson(`${props.urls.getContractsForCustomer}?${params.toString()}`);
    row.contracts = Object.entries(result.data?.contracts || {}).map(([id, name]) => ({ id, name }));
}
async function fetchAllocationContractsKeepingSelection(row) {
    if (!row.partner_id) return;
    const params = new URLSearchParams({ customerId: row.partner_id, currency: paymentCurrency.value });
    const result = await fetchJson(`${props.urls.getContractsForCustomer}?${params.toString()}`);
    row.contracts = Object.entries(result.data?.contracts || {}).map(([id, name]) => ({ id, name }));
}
const currentAllocationInvoice = computed(() => allocationModalInvoiceIndex.value !== null ? invoices.value[allocationModalInvoiceIndex.value] : null);
const currentAllocationTotal = computed(() => (currentAllocationInvoice.value?.allocations || []).reduce((sum, a) => sum + (Number(a.allocation_amount) || 0), 0));

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
    if (!contractId.value || contractId.value === 'general-down') { purchaseOrders.value = []; return; }
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
    target.value = Object.values(result.data?.data || {});
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

/* ── Watchers ─────────────────────────────────────────────────── */
watch(paymentCurrency, () => {
    fetchBranchesForCurrency();
    if (showSettlementCard.value) fetchContracts();
    if (moneyType.value === 'outgoing-transfer') fetchAccountNumbers(outgoingTransfer.accountTypeId, outgoingTransfer.deliveryBankId, outgoingTransferAccountNumbers);
    if (moneyType.value === 'payable_cheque') fetchAccountNumbers(payableCheque.accountTypeId, payableCheque.deliveryBankId, payableChequeAccountNumbers);
    if (moneyType.value === 'cash_payment') fetchCashPaymentBalance();
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
watch(deliveryDate, () => {
    if (moneyType.value === 'cash_payment') fetchCashPaymentBalance();
    if (moneyType.value === 'outgoing-transfer') fetchAccountBalance(outgoingTransfer.accountTypeId, outgoingTransfer.accountNumber, outgoingTransfer.deliveryBankId, outgoingTransferBalance, outgoingTransferNetBalance);
    if (moneyType.value === 'payable_cheque') fetchAccountBalance(payableCheque.accountTypeId, payableCheque.accountNumber, payableCheque.deliveryBankId, payableChequeBalance, payableChequeNetBalance);
});
watch(moneyType, () => {
    if (moneyType.value === 'cash_payment') fetchCashPaymentBalance();
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
        if (moneyType.value === 'outgoing-transfer' && outgoingTransfer.accountNumber) fetchAccountBalance(outgoingTransfer.accountTypeId, outgoingTransfer.accountNumber, outgoingTransfer.deliveryBankId, outgoingTransferBalance, outgoingTransferNetBalance);
        if (moneyType.value === 'payable_cheque' && payableCheque.accountNumber) fetchAccountBalance(payableCheque.accountTypeId, payableCheque.accountNumber, payableCheque.deliveryBankId, payableChequeBalance, payableChequeNetBalance);
    } else if (!props.singleModel) {
        fetchSuppliersForPartnerType();
        if (showSettlementCard.value && supplierId.value) fetchInvoices();
    }
    fetchBranchesForCurrency();
});

/* ── Submit ───────────────────────────────────────────────────── */
function buildPayload() {
    const settlements = {};
    const allocations = {};
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
        if (inv.allocations && inv.allocations.length) {
            allocations[inv.id] = inv.allocations
                .filter(a => a.partner_id && a.contract_id)
                .map(a => ({ partner_id: a.partner_id, contract_id: a.contract_id, allocation_amount: a.allocation_amount }));
        }
    });

    const purchasesOrdersAmounts = purchaseOrders.value.map(po => ({
        sales_order_id: po.id,
        sales_order_name: po.po_number,
        net_invoice_amount: po.amount,
        received_amount: po.received_amount,
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
        allocations,
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
        <div class="p-6">
            <Link :href="urls.index" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                ← Back to Money Payment
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ isEdit ? 'Edit Money Payment' : 'Money Payment' }}</h1>
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
                <h2 class="text-base font-medium cvr-text-primary mb-4">Money Payment</h2>
                <div class="cvr-form-grid-2-2-1-4-1-2">
                    <div>
                        <label class="cvr-form-label">Payment Date *</label>
                        <input v-model="deliveryDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.delivery_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.delivery_date }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Partner Type *</label>
                        <select v-model="partnerType" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="t in partnerTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div v-show="partnerType === 'is_supplier'">
                        <label class="cvr-form-label">Invoice Currency*</label>
                        <select v-model="invoiceCurrency" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Name *</label>
                        <select v-model="supplierId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="s in suppliersList" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="errors.supplier_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.supplier_id }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Pay Currency *</label>
                        <select v-model="paymentCurrency" class="cvr-input w-full px-3 py-2 rounded">
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
                <div v-if="partnerType !== 'is_supplier'" class="cvr-form-grid-4 mt-4">
                    <div>
                        <label class="cvr-form-label">Transaction *</label>
                        <select v-model="transactionType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="opt in transactionTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Cash Payment -->
            <div v-if="moneyType === 'cash_payment'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">Cash Payment Information</h2>
                    <div v-if="cashPaymentBalance !== null" class="text-sm cvr-text-secondary">Balance: <span class="cvr-num">{{ Number(cashPaymentBalance).toLocaleString() }}</span></div>
                </div>
                <div class="cvr-form-grid-6-3-3">
                    <div>
                        <label class="cvr-form-label">Paying Branch *</label>
                        <select v-model="cashPayment.deliveryBranchId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="-1">Select Branch</option>
                            <option v-for="b in branchesList" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Paid Amount [{{ paymentCurrency }}] *</label>
                        <input v-model="paidAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Receipt Number *</label>
                        <input v-model="cashPayment.receiptNumber" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2 mt-4">
                    <div>
                        <label class="cvr-form-label">Exchange Rate *</label>
                        <input v-model="exchangeRate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Amount In Invoice Currency [{{ invoiceCurrency }}]</label>
                        <input :value="amountInInvoiceCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Payable Cheque -->
            <div v-if="moneyType === 'payable_cheque'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">Payable Cheque Information</h2>
                    <div v-if="payableChequeBalance !== null" class="text-sm cvr-text-secondary">Balance: <span class="cvr-num">{{ Number(payableChequeBalance).toLocaleString() }}</span></div>
                    <div v-if="payableChequeNetBalance !== null" class="text-sm cvr-text-secondary">Net Balance: <span class="cvr-num">{{ Number(payableChequeNetBalance).toLocaleString() }}</span></div>
                </div>
                <div class="cvr-form-grid-6-2-2-2">
                    <div>
                        <label class="cvr-form-label">Payment Bank *</label>
                        <select v-model="payableCheque.deliveryBankId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="b in financialInstitutionBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Account Type *</label>
                        <select v-model="payableCheque.accountTypeId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <p v-if="errors['account_type.payable_cheque']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors['account_type.payable_cheque'] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Account Number *</label>
                        <select v-model="payableCheque.accountNumber" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="n in payableChequeAccountNumbers" :key="n" :value="n">{{ n }}</option>
                        </select>
                        <p v-if="errors['account_number.payable_cheque']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors['account_number.payable_cheque'] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Cheque Amount [{{ paymentCurrency }}] *</label>
                        <input v-model="paidAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div class="cvr-form-grid-3 mt-4">
                    <div>
                        <label class="cvr-form-label">Due Date *</label>
                        <input v-model="payableCheque.dueDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.due_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.due_date }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Cheque Number *</label>
                        <input v-model="payableCheque.chequeNumber" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.cheque_number" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.cheque_number }}</p>
                    </div>
                    <template v-if="showExchangeRateFields">
                        <div>
                            <label class="cvr-form-label">Exchange Rate *</label>
                            <input v-model="exchangeRate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </template>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2 mt-4">
                    <div>
                        <label class="cvr-form-label">Amount In Invoice Currency [{{ invoiceCurrency }}]</label>
                        <input :value="amountInInvoiceCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Outgoing Transfer -->
            <div v-if="moneyType === 'outgoing-transfer'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">Outgoing Transfer Information</h2>
                    <div v-if="outgoingTransferBalance !== null" class="text-sm cvr-text-secondary">Balance: <span class="cvr-num">{{ Number(outgoingTransferBalance).toLocaleString() }}</span></div>
                    <div v-if="outgoingTransferNetBalance !== null" class="text-sm cvr-text-secondary">Net Balance: <span class="cvr-num">{{ Number(outgoingTransferNetBalance).toLocaleString() }}</span></div>
                </div>
                <div class="cvr-form-grid-6-2-2-2">
                    <div>
                        <label class="cvr-form-label">Payment Bank *</label>
                        <select v-model="outgoingTransfer.deliveryBankId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="b in financialInstitutionBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Account Type *</label>
                        <select v-model="outgoingTransfer.accountTypeId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <p v-if="errors['account_type.outgoing-transfer']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors['account_type.outgoing-transfer'] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Account Number *</label>
                        <select v-model="outgoingTransfer.accountNumber" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="n in outgoingTransferAccountNumbers" :key="n" :value="n">{{ n }}</option>
                        </select>
                        <p v-if="errors['account_number.outgoing-transfer']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors['account_number.outgoing-transfer'] }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Transfer Amount [{{ paymentCurrency }}] *</label>
                        <input v-model="paidAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2 mt-4">
                    <div>
                        <label class="cvr-form-label">Exchange Rate *</label>
                        <input v-model="exchangeRate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
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

                <div v-if="!supplierId" class="text-sm cvr-text-muted mb-4">Select a supplier to see their open invoices.</div>
                <div v-else-if="invoicesFetchFailed" class="cvr-border rounded-lg p-3 mb-4 text-sm" style="border-color: var(--cvr-danger-text); background: var(--cvr-bg-card-alt)">
                    <p style="color: var(--cvr-danger-text)">Couldn't load invoices for this supplier — please try again, or reload the page.</p>
                </div>
                <div v-else-if="invoices.length === 0 && supplierInvoiceCurrencies.length && !supplierInvoiceCurrencies.includes(invoiceCurrency)" class="text-sm cvr-text-muted mb-4">
                    This supplier has no open invoices in {{ invoiceCurrency }}. They do have invoices in: {{ supplierInvoiceCurrencies.join(', ') }} — try switching Invoice Currency above.
                </div>
                <div v-else-if="invoices.length === 0" class="text-sm cvr-text-muted mb-4">
                    This supplier has no open invoices in {{ invoiceCurrency }}.
                </div>

                <div v-for="(inv, i) in invoices" :key="inv.id" class="cvr-border border rounded-lg p-4 mb-3">
                    <div class="cvr-form-grid-6-2-2-2 mb-3">
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
                            <label class="cvr-form-label">Paid Amount</label>
                            <input :value="inv.paid_amount" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Net Balance</label>
                            <input :value="inv.net_balance" readonly class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Settlement Amount *</label>
                            <input v-model.number="inv.settlement_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors[`settlements.${inv.id}.settlement_amount`]" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors[`settlements.${inv.id}.settlement_amount`] }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Withhold Amount</label>
                            <input v-model.number="inv.withhold_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between">
                        <button @click="openAllocationModal(i)" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                            📎 Allocate{{ inv.allocations.length ? ` (${inv.allocations.length})` : '' }}
                        </button>
                        <span v-if="errors.allocations" class="text-xs" style="color: var(--cvr-danger-text)">{{ errors.allocations }}</span>
                    </div>
                </div>

                <hr class="my-4 cvr-border" />

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
                    <div v-for="po in purchaseOrders" :key="po.id" class="cvr-form-grid-3 mb-2">
                        <div>
                            <label class="cvr-form-label">PO Number</label>
                            <input :value="po.po_number" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount [{{ paymentCurrency }}]</label>
                            <input :value="po.amount" disabled class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Paid Amount [{{ paymentCurrency }}] *</label>
                            <input v-model.number="po.received_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                    <p v-if="errors.purchases_orders_amounts" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.purchases_orders_amounts }}</p>
                </div>

                <div class="cvr-form-grid-2">
                    <div>
                        <label class="cvr-form-label">Unapplied Amount [{{ paymentCurrency }}]</label>
                        <input :value="unappliedAmountInPaymentCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
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

            <!-- Allocate modal -->
            <div v-if="currentAllocationInvoice" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-6xl">
                    <div class="flex items-start justify-between mb-1">
                        <h2 class="text-lg font-medium cvr-text-primary">Allocate — Invoice {{ currentAllocationInvoice.invoice_number }}</h2>
                        <button @click="closeAllocationModal" class="cvr-text-muted text-xl leading-none px-2" title="Close">✕</button>
                    </div>
                    <p class="text-xs cvr-text-muted mb-4">Split this invoice's settlement across one or more customer contracts. Total allocated must not exceed the Settlement Amount ({{ currentAllocationInvoice.settlement_amount }}).</p>

                    <div v-for="(row, rowIndex) in currentAllocationInvoice.allocations" :key="rowIndex" class="cvr-form-grid-4-5-2-1 mb-3 items-end">
                        <div>
                            <label class="cvr-form-label">Customer</label>
                            <select v-model="row.partner_id" @change="fetchAllocationContracts(row)" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="c in clientsWithContracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Project Name</label>
                            <select v-model="row.contract_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="c in row.contracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Allocation Amount *</label>
                            <input v-model.number="row.allocation_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <button @click="removeAllocationRow(rowIndex)" class="cvr-btn-danger px-3 py-2 rounded border">Remove</button>
                    </div>
                    <button @click="addAllocationRow" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm mb-4">+ Add Row</button>

                    <p class="text-sm cvr-text-secondary mb-4">Total allocated: {{ currentAllocationTotal }}</p>

                    <div class="flex justify-end">
                        <button @click="closeAllocationModal" class="cvr-btn-secondary px-4 py-2 rounded border">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
