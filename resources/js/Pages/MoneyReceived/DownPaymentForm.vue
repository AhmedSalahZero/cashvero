<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { todayDate } from '@/composables/today';
import { mapAccountNumberOptions, accountNumberOption, hasAccountNumber } from '@/composables/useAccountNumberOptions';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate();

const props = defineProps({
    company: Object,
    model: Object,          // null on create
    warningMessage: String,
    customers: Array,
    moneyTypes: Array,
    currencies: Array,
    selectedBranches: Array,
    selectedBanks: Array,       // full bank list (drawee bank) — see note below
    financialInstitutionBanks: Array,
    accountTypes: Array,
    urls: Object,
});

const page = usePage();
const isEdit = computed(() => !!props.model);
const errors = computed(() => page.props.errors || {});
const errorMessages = computed(() => Object.values(errors.value).flat().filter(Boolean));

/** Same header fix as Form.vue: send the headers jQuery's $.ajax() sent
 *  automatically, so a real server-side error comes back as JSON
 *  instead of Laravel's HTML error page. */
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
const downPaymentType = ref(props.model?.down_payment_type || 'over_contract');
const contractCurrency = ref(props.model?.currency || props.company.mainFunctionalCurrency || '');
const customerId = ref(props.model?.customer_id || '');
const receivingCurrency = ref(props.model?.receiving_currency || props.company.mainFunctionalCurrency || '');
const contractId = ref(props.model?.contract_id || '');
const moneyType = ref(props.model?.type || '');
const userComment = ref(props.model?.user_comment || '');

const customersList = ref([...props.customers]);
const branchesList = ref([...props.selectedBranches]);

/* ── Type-specific fields — identical shape to the plain Money
   Received form's, just labelled "Amount In Contract Currency"
   instead of "Amount In Invoice Currency" (same underlying field). */
const receivedAmount = ref(props.model?.received_amount || 0);
const exchangeRate = ref(props.model?.exchange_rate || 1);

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

/* ── Derived amounts ──────────────────────────────────────────── */
const isOverContract = computed(() => downPaymentType.value === 'over_contract');
const isSameCurrency = computed(() => contractCurrency.value && contractCurrency.value === receivingCurrency.value);
// Matches the original exactly: exchange-rate fields only ever show
// for Contract Down Payment — General hides them outright even if
// currencies differ (Contract Currency itself is hidden for General).
const showExchangeRateFields = computed(() => isOverContract.value && contractCurrency.value && receivingCurrency.value && !isSameCurrency.value);
const amountInContractCurrency = computed(() => {
    if (isSameCurrency.value || !exchangeRate.value) return Number(receivedAmount.value) || 0;
    return Math.round(((Number(receivedAmount.value) || 0) / Number(exchangeRate.value)) * 100) / 100;
});

/* ── Sales Orders allocation (Contract Down Payment only) ─────────
   Hidden entirely for General — and that's not just a UI nicety:
   submitting a genuinely EMPTY sales_orders_amounts array is what
   tells storeNewSalesOrdersAmounts() server-side to record the WHOLE
   received amount as one unattributed (contract_id = null) down
   payment settlement. Do not synthesize a placeholder row here. */
const salesOrders = ref([]); // [{ id, so_number, amount, received_amount }]
const contracts = ref([]);

async function fetchCustomersForDownPaymentType() {
    const params = new URLSearchParams({ type: downPaymentType.value });
    const result = await fetchJson(`${props.urls.getCustomersOfOpeningBalance}?${params.toString()}`);
    customersList.value = Object.entries(result.data?.invoices || {}).map(([name, id]) => ({ id, name }));
}
async function fetchContracts() {
    if (!isOverContract.value || !customerId.value || !contractCurrency.value) { contracts.value = []; return; }
    const params = new URLSearchParams({ customerId: customerId.value, currency: contractCurrency.value });
    const result = await fetchJson(`${props.urls.getContractsForCustomer}?${params.toString()}`);
    contracts.value = Object.entries(result.data?.contracts || {}).map(([id, name]) => ({ id, name }));
}
async function fetchSalesOrders() {
    if (!isOverContract.value || !contractId.value) { salesOrders.value = []; return; }
    const params = new URLSearchParams();
    if (isEdit.value) params.set('down_payment_id', props.model.id);
    const result = await fetchJson(`${props.urls.getSalesOrdersForContract}/${contractId.value}/${receivingCurrency.value}?${params.toString()}`);
    salesOrders.value = (result.data?.sales_orders || []).map(so => ({ ...so, received_amount: Number(so.received_amount) || 0 }));
}
async function fetchAccountNumbers(accountTypeId, financialInstitutionId, target) {
    target.value = [];
    if (!accountTypeId || !financialInstitutionId || !receivingCurrency.value) return;
    const url = `${props.urls.getAccountNumbersForType}/${accountTypeId}/${receivingCurrency.value}/${financialInstitutionId}`;
    const result = await fetchJson(url);
    target.value = mapAccountNumberOptions(result.data?.data);
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

/* ── Watchers ─────────────────────────────────────────────────── */
watch(downPaymentType, () => {
    fetchCustomersForDownPaymentType();
    if (!isOverContract.value) {
        contractId.value = '';
        contracts.value = [];
        salesOrders.value = [];
    } else {
        fetchContracts();
    }
});
watch(receivingCurrency, () => {
    fetchBranchesForCurrency();
    if (isOverContract.value) {
        fetchSalesOrders();
    }
    if (moneyType.value === 'cash-in-bank') fetchAccountNumbers(cashInBank.accountTypeId, cashInBank.receivingBankId, cashInBankAccountNumbers);
    if (moneyType.value === 'incoming-transfer') fetchAccountNumbers(incomingTransfer.accountTypeId, incomingTransfer.receivingBankId, incomingTransferAccountNumbers);
});
watch(contractCurrency, () => {
    if (isOverContract.value) fetchContracts();
});
watch(customerId, fetchContracts);
watch(contractId, fetchSalesOrders);
watch(() => cashInBank.accountTypeId, () => fetchAccountNumbers(cashInBank.accountTypeId, cashInBank.receivingBankId, cashInBankAccountNumbers));
watch(() => cashInBank.receivingBankId, () => fetchAccountNumbers(cashInBank.accountTypeId, cashInBank.receivingBankId, cashInBankAccountNumbers));
watch(() => incomingTransfer.accountTypeId, () => fetchAccountNumbers(incomingTransfer.accountTypeId, incomingTransfer.receivingBankId, incomingTransferAccountNumbers));
watch(() => incomingTransfer.receivingBankId, () => fetchAccountNumbers(incomingTransfer.accountTypeId, incomingTransfer.receivingBankId, incomingTransferAccountNumbers));
watch(() => cashInSafe.receivingBranchId, fetchCashInSafeBalance);
watch(receivingDate, fetchCashInSafeBalance);

onMounted(() => {
    fetchCustomersForDownPaymentType();
    fetchBranchesForCurrency();
    if (isOverContract.value) {
        fetchContracts();
        if (contractId.value) fetchSalesOrders();
    }
    if (cashInBank.accountTypeId) fetchAccountNumbers(cashInBank.accountTypeId, cashInBank.receivingBankId, cashInBankAccountNumbers);
    if (incomingTransfer.accountTypeId) fetchAccountNumbers(incomingTransfer.accountTypeId, incomingTransfer.receivingBankId, incomingTransferAccountNumbers);
    fetchCashInSafeBalance();
});

/* ── Submit ───────────────────────────────────────────────────── */
function buildPayload() {
    const payload = {
        receiving_date: receivingDate.value,
        down_payment_type: downPaymentType.value,
        currency: contractCurrency.value,
        customer_id: customerId.value,
        receiving_currency: receivingCurrency.value,
        type: moneyType.value,
        is_down_payment: 1,
        user_comment: userComment.value,
        received_amount: { [moneyType.value]: receivedAmount.value },
        exchange_rate: { [moneyType.value]: exchangeRate.value },
        amount_in_invoice_currency: { [moneyType.value]: amountInContractCurrency.value },
        contract_id: isOverContract.value && contractId.value ? contractId.value : null,
        // Submitting every fetched row (not just positive ones) —
        // and a genuinely EMPTY array for General — matches the
        // original exactly; see the comment above `salesOrders`.
        sales_orders_amounts: isOverContract.value
            ? salesOrders.value.map(so => ({
                sales_order_id: so.id,
                sales_order_name: so.so_number,
                net_invoice_amount: so.amount,
                received_amount: so.received_amount,
            }))
            : [],
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
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ isEdit ? 'Edit Down Payment' : 'Down Payment' }}</h1>
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
                <h2 class="text-base font-medium cvr-text-primary mb-4">Down Payment</h2>
                <div :class="isOverContract ? 'cvr-form-grid-2-2-2-4-2' : 'cvr-form-grid-2-2-6-2'">
                    <div>
                        <label class="cvr-form-label">Receiving Date *</label>
                        <input v-model="receivingDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="errors.receiving_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.receiving_date }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Down Payment Type *</label>
                        <select v-model="downPaymentType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="over_contract">Contract Down Payment</option>
                            <option value="general">General Down Payment</option>
                        </select>
                    </div>
                    <div v-if="isOverContract">
                        <label class="cvr-form-label">Contract Currency *</label>
                        <select v-model="contractCurrency" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Customer Name *</label>
                        <select v-model="customerId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="c in customersList" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="errors.customer_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.customer_id }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Receive Currency *</label>
                        <select v-model="receivingCurrency" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                        </select>
                    </div>
                </div>
                <div :class="isOverContract ? 'cvr-form-grid-8-4' : 'cvr-form-grid-3'" class="mt-4">
                    <div v-if="isOverContract">
                        <label class="cvr-form-label">Contract Name *</label>
                        <select v-model="contractId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="c in contracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="errors.contract_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.contract_id }}</p>
                    </div>
                    <div>
                        <label class="cvr-form-label">Select Money Type *</label>
                        <select v-model="moneyType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="t in moneyTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p v-if="errors.type" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.type }}</p>
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
                        <label class="cvr-form-label">Select Receiving Branch *</label>
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
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2 mt-4">
                    <div>
                        <label class="cvr-form-label">Exchange Rate *</label>
                        <input v-model="exchangeRate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Amount In Contract Currency [{{ contractCurrency }}]</label>
                        <input :value="amountInContractCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Bank Deposit -->
            <div v-if="moneyType === 'cash-in-bank'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">Bank Deposit Information</h2>
                <div class="cvr-form-grid-6-2-2-2">
                    <div>
                        <label class="cvr-form-label">Select Receiving Bank *</label>
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
                            <option v-for="n in cashInBankAccountNumbers" :key="n.value" :value="n.value">{{ n.label }}</option>
                        </select>
                        <p v-if="errors['account_number.cash-in-bank']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors['account_number.cash-in-bank'] }}</p>
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2 mt-4">
                    <div>
                        <label class="cvr-form-label">Exchange Rate *</label>
                        <input v-model="exchangeRate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Amount In Contract Currency [{{ contractCurrency }}]</label>
                        <input :value="amountInContractCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
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
                        <div>
                            <label class="cvr-form-label">Exchange Rate *</label>
                            <input v-model="exchangeRate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount In Contract Currency [{{ contractCurrency }}]</label>
                            <input :value="amountInContractCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </template>
                </div>
            </div>

            <!-- Incoming Transfer -->
            <div v-if="moneyType === 'incoming-transfer'" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">Incoming Transfer Information</h2>
                <div class="cvr-form-grid-6-2-2-2">
                    <div>
                        <label class="cvr-form-label">Select Receiving Bank *</label>
                        <select v-model="incomingTransfer.receivingBankId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="b in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Incoming Transfer Amount [{{ receivingCurrency }}] *</label>
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
                            <option v-for="n in incomingTransferAccountNumbers" :key="n.value" :value="n.value">{{ n.label }}</option>
                        </select>
                        <p v-if="errors['account_number.incoming-transfer']" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors['account_number.incoming-transfer'] }}</p>
                    </div>
                </div>
                <div v-if="showExchangeRateFields" class="cvr-form-grid-2 mt-4">
                    <div>
                        <label class="cvr-form-label">Exchange Rate *</label>
                        <input v-model="exchangeRate" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Amount In Contract Currency [{{ contractCurrency }}]</label>
                        <input :value="amountInContractCurrency" readonly class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
            </div>

            <!-- Received Amount Information (sales-orders allocation) — Contract Down Payment only -->
            <div v-if="isOverContract" class="cvr-card-bg cvr-border border rounded-lg p-5 mb-5">
                <h2 class="text-base font-medium cvr-text-primary mb-4">Received Amount Information</h2>
                <div v-if="salesOrders.length === 0" class="text-sm cvr-text-muted mb-2">
                    Choose a contract above to allocate this down payment across its sales orders.
                </div>
                <div v-for="so in salesOrders" :key="so.id" class="cvr-form-grid-3 mb-3">
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