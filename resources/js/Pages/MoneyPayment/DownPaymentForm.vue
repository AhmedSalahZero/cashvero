<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { todayDate } from '@/composables/today';
import { mapAccountNumberOptions, accountNumberOption } from '@/composables/useAccountNumberOptions';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate();

const props = defineProps({
    company: Object,
    model: Object,          // null on create
    warningMessage: String,
    suppliers: Array,
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
const errorMessages = computed(() => Object.values(errors.value).flat().filter(Boolean));

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
const downPaymentType = ref(props.model?.down_payment_type || 'over_contract');
const contractCurrency = ref(props.model?.currency || props.company.mainFunctionalCurrency || '');
const supplierId = ref(props.model?.supplier_id || '');
const paymentCurrency = ref(props.model?.payment_currency || props.company.mainFunctionalCurrency || '');
const contractId = ref(props.model?.contract_id || '');
const moneyType = ref(props.model?.type || '');
const userComment = ref(props.model?.user_comment || '');

const suppliersList = ref([...props.suppliers]);
const branchesList = ref([...props.selectedBranches]);

/* ── Type-specific fields — identical shape to the plain Money
   Payment form's, just labelled "Amount In Contract Currency". ──── */
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
const isOverContract = computed(() => downPaymentType.value === 'over_contract');
const isSameCurrency = computed(() => contractCurrency.value && contractCurrency.value === paymentCurrency.value);
// Matches the plain form exactly: exchange-rate fields only ever show
// for Contract Down Payment — General hides them outright even if
// currencies differ (Contract Currency itself is hidden for General).
const showExchangeRateFields = computed(() => isOverContract.value && contractCurrency.value && paymentCurrency.value && !isSameCurrency.value);
const amountInContractCurrency = computed(() => {
    if (isSameCurrency.value || !exchangeRate.value) return Number(paidAmount.value) || 0;
    return Math.round(((Number(paidAmount.value) || 0) / Number(exchangeRate.value)) * 100) / 100;
});

/* ── Purchase Orders allocation (Contract Down Payment only) ──────
   Hidden entirely for General — submitting a genuinely EMPTY
   purchases_orders_amounts array is what tells the server to record
   the WHOLE paid amount as one unattributed (contract_id = null)
   down payment settlement. Do not synthesize a placeholder row. */
const purchaseOrders = ref([]);
const contracts = ref([]);

async function fetchSuppliersForDownPaymentType() {
    const params = new URLSearchParams({ type: downPaymentType.value });
    const result = await fetchJson(`${props.urls.getSuppliersWithOpeningBalance}?${params.toString()}`);
    suppliersList.value = Object.entries(result.data?.invoices || {}).map(([name, id]) => ({ id, name }));
}
async function fetchContracts() {
    if (!isOverContract.value || !supplierId.value || !contractCurrency.value) { contracts.value = []; return; }
    const params = new URLSearchParams({ supplierId: supplierId.value, currency: contractCurrency.value });
    const result = await fetchJson(`${props.urls.getContractsForSupplier}?${params.toString()}`);
    contracts.value = Object.entries(result.data?.contracts || {}).map(([id, name]) => ({ id, name }));
}
async function fetchPurchaseOrders() {
    if (!isOverContract.value || !contractId.value) { purchaseOrders.value = []; return; }
    const params = new URLSearchParams();
    if (isEdit.value) params.set('down_payment_id', props.model.id);
    const result = await fetchJson(`${props.urls.getPurchaseOrdersForContract}/${contractId.value}/${paymentCurrency.value}?${params.toString()}`);
    purchaseOrders.value = (result.data?.purchases_orders || []).map(po => ({
        id: po.id,
        po_number: po.po_number,
        amount: po.amount,
        received_amount: Number(po.paid_amount) || 0,
    }));
}

/* ── Account number lookups + branch/currency lookups — same
   patterns as the plain Money Payment form's Form.vue. ─────────── */
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
// Cash Payment: branch/currency/date-triggered, via
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
// Payable Cheque & Outgoing Transfer: Account-Number-triggered, via
// update.balance.and.net.balance.based.on.account.number.
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

/* ── Watchers ─────────────────────────────────────────────────── */
watch(downPaymentType, () => {
    fetchSuppliersForDownPaymentType();
    if (!isOverContract.value) {
        contractId.value = '';
        contracts.value = [];
        purchaseOrders.value = [];
    } else {
        fetchContracts();
    }
});
watch(paymentCurrency, () => {
    fetchBranchesForCurrency();
    if (isOverContract.value) fetchContracts();
    if (moneyType.value === 'outgoing-transfer') fetchAccountNumbers(outgoingTransfer.accountTypeId, outgoingTransfer.deliveryBankId, outgoingTransferAccountNumbers);
    if (moneyType.value === 'payable_cheque') fetchAccountNumbers(payableCheque.accountTypeId, payableCheque.deliveryBankId, payableChequeAccountNumbers);
    if (moneyType.value === 'cash_payment') fetchCashPaymentBalance();
});
watch(contractCurrency, () => {
    if (isOverContract.value) fetchContracts();
});
watch(supplierId, fetchContracts);
watch(contractId, fetchPurchaseOrders);
watch(() => outgoingTransfer.accountTypeId, () => fetchAccountNumbers(outgoingTransfer.accountTypeId, outgoingTransfer.deliveryBankId, outgoingTransferAccountNumbers));
watch(() => outgoingTransfer.deliveryBankId, () => fetchAccountNumbers(outgoingTransfer.accountTypeId, outgoingTransfer.deliveryBankId, outgoingTransferAccountNumbers));
watch(() => payableCheque.accountTypeId, () => fetchAccountNumbers(payableCheque.accountTypeId, payableCheque.deliveryBankId, payableChequeAccountNumbers));
watch(() => payableCheque.deliveryBankId, () => fetchAccountNumbers(payableCheque.accountTypeId, payableCheque.deliveryBankId, payableChequeAccountNumbers));
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
    fetchSuppliersForDownPaymentType();
    fetchBranchesForCurrency();
    if (isOverContract.value) {
        fetchContracts();
        if (contractId.value) fetchPurchaseOrders();
    }
    if (outgoingTransfer.accountTypeId) fetchAccountNumbers(outgoingTransfer.accountTypeId, outgoingTransfer.deliveryBankId, outgoingTransferAccountNumbers);
    if (payableCheque.accountTypeId) fetchAccountNumbers(payableCheque.accountTypeId, payableCheque.deliveryBankId, payableChequeAccountNumbers);
    if (moneyType.value === 'cash_payment') fetchCashPaymentBalance();
    if (moneyType.value === 'outgoing-transfer' && outgoingTransfer.accountNumber) fetchAccountBalance(outgoingTransfer.accountTypeId, outgoingTransfer.accountNumber, outgoingTransfer.deliveryBankId, outgoingTransferBalance, outgoingTransferNetBalance);
    if (moneyType.value === 'payable_cheque' && payableCheque.accountNumber) fetchAccountBalance(payableCheque.accountTypeId, payableCheque.accountNumber, payableCheque.deliveryBankId, payableChequeBalance, payableChequeNetBalance);
});

/* ── Submit ───────────────────────────────────────────────────── */
function buildPayload() {
    const payload = {
        delivery_date: deliveryDate.value,
        down_payment_type: downPaymentType.value,
        currency: contractCurrency.value,
        supplier_id: supplierId.value,
        payment_currency: paymentCurrency.value,
        type: moneyType.value,
        is_down_payment: 1,
        user_comment: userComment.value,
        paid_amount: { [moneyType.value]: paidAmount.value },
        exchange_rate: { [moneyType.value]: exchangeRate.value },
        amount_in_invoice_currency: { [moneyType.value]: amountInContractCurrency.value },
        contract_id: isOverContract.value && contractId.value ? contractId.value : null,
        // Submitting every fetched row (not just positive ones), and a
        // genuinely EMPTY array for General — matches the plain form's
        // own fix for the same reasoning (see purchases_orders_amounts
        // in Form.vue's buildPayload).
        purchases_orders_amounts: isOverContract.value
            ? purchaseOrders.value.map(po => ({
                sales_order_id: po.id,
                sales_order_name: po.po_number,
                net_invoice_amount: po.amount,
                received_amount: po.received_amount,
            }))
            : [],
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
                {{ $t('← Back to Money Payment') }}
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ isEdit ? $t('Edit Down Payment') : $t('Down Payment') }}</h1>
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
                <h2 class="text-base font-medium cvr-text-primary mb-4">{{ $t('Down Payment') }}</h2>
                <div :class="isOverContract ? 'cvr-form-grid-2-2-2-4-2' : 'cvr-form-grid-2-2-6-2'">
                    <div>
                        <label class="cvr-form-label">{{ $t('Payment Date') }} *</label>
                        <input v-model="deliveryDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.delivery_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.delivery_date }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Down Payment Type') }} *</label>
                        <select v-model="downPaymentType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="over_contract">{{ $t('Contract Down Payment') }}</option>
                            <option value="general">{{ $t('General Down Payment') }}</option>
                        </select>
                    </div>
                    <div v-if="isOverContract">
                        <label class="cvr-form-label">{{ $t('Contract Currency') }} *</label>
                        <select v-model="contractCurrency" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Supplier Name') }} *</label>
                        <select v-model="supplierId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="s in suppliersList" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="errors.supplier_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.supplier_id }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Pay Currency') }} *</label>
                        <select v-model="paymentCurrency" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                        </select>
                    </div>
                </div>
                <div :class="isOverContract ? 'cvr-form-grid-8-4' : 'cvr-form-grid-3'" class="mt-4">
                    <div v-if="isOverContract">
                        <label class="cvr-form-label">{{ $t('Contract Name') }} *</label>
                        <select v-model="contractId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="c in contracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="errors.contract_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.contract_id }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Select Money Type') }} *</label>
                        <select v-model="moneyType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="t in moneyTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p v-if="errors.type" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.type }}</p>
                    </div>
                </div>
            </div>

            <!-- Cash Payment -->
            <div v-if="moneyType === 'cash_payment'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">{{ $t('Cash Payment Information') }}</h2>
                    <div v-if="cashPaymentBalance !== null" class="text-sm cvr-text-secondary">{{ $t('Balance:') }} <span class="cvr-num">{{ Number(cashPaymentBalance).toLocaleString('en-EG') }}</span></div>
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
                        <label class="cvr-form-label">Paid Amount [{{ paymentCurrency }}] *</label>
                        <input v-model="paidAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Receipt Number') }} *</label>
                        <input v-model="cashPayment.receiptNumber" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2 mt-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Exchange Rate') }} *</label>
                        <input v-model="exchangeRate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Amount In Contract Currency [{{ contractCurrency }}]</label>
                        <input :value="amountInContractCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Payable Cheque -->
            <div v-if="moneyType === 'payable_cheque'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">{{ $t('Payable Cheque Information') }}</h2>
                    <div class="flex gap-4 text-sm cvr-text-secondary">
                        <div v-if="payableChequeBalance !== null">{{ $t('Balance:') }} <span class="cvr-num">{{ Number(payableChequeBalance).toLocaleString('en-EG') }}</span></div>
                        <div v-if="payableChequeNetBalance !== null">{{ $t('Net Balance:') }} <span class="cvr-num">{{ Number(payableChequeNetBalance).toLocaleString('en-EG') }}</span></div>
                    </div>
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
                        <label class="cvr-form-label">Cheque Amount [{{ paymentCurrency }}] *</label>
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
                        <div>
                            <label class="cvr-form-label">Exchange Rate *</label>
                            <input v-model="exchangeRate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </template>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2 mt-4">
                    <div>
                        <label class="cvr-form-label">Amount In Contract Currency [{{ contractCurrency }}]</label>
                        <input :value="amountInContractCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Outgoing Transfer -->
            <div v-if="moneyType === 'outgoing-transfer'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-medium cvr-text-primary">{{ $t('Outgoing Transfer Information') }}</h2>
                    <div class="flex gap-4 text-sm cvr-text-secondary">
                        <div v-if="outgoingTransferBalance !== null">{{ $t('Balance:') }} <span class="cvr-num">{{ Number(outgoingTransferBalance).toLocaleString('en-EG') }}</span></div>
                        <div v-if="outgoingTransferNetBalance !== null">{{ $t('Net Balance:') }} <span class="cvr-num">{{ Number(outgoingTransferNetBalance).toLocaleString('en-EG') }}</span></div>
                    </div>
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
                        <label class="cvr-form-label">Transfer Amount [{{ paymentCurrency }}] *</label>
                        <input v-model="paidAmount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2 mt-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Exchange Rate') }} *</label>
                        <input v-model="exchangeRate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Amount In Contract Currency [{{ contractCurrency }}]</label>
                        <input :value="amountInContractCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Purchase Orders allocation — Contract Down Payment only -->
            <div v-if="isOverContract" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">{{ $t('Received Amount Information') }}</h2>
                <div v-if="purchaseOrders.length === 0" class="text-sm cvr-text-muted mb-2">
                    {{ $t('Choose a contract above to allocate this down payment across its purchase orders.') }}
                </div>
                <div v-for="po in purchaseOrders" :key="po.id" class="cvr-form-grid-3 mb-3">
                    <div>
                        <label class="cvr-form-label">{{ $t('PO Number') }}</label>
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
