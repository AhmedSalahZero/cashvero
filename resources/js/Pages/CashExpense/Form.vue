<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import { todayDate } from '@/composables/today';
import { mapAccountNumberOptions, accountNumberOption, hasAccountNumber } from '@/composables/useAccountNumberOptions';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate();

/*
 * CashExpense/Form.vue
 * ------------------------------------------------------------------
 * One shared page for Add + Edit and all three payment types — Cash
 * Payment (safe/branch), Payable Cheque, Outgoing Transfer (bank) —
 * matching the old page's single `type` dropdown that toggled field
 * groups, rather than InternalMoneyTransfer's per-type-route pattern.
 *
 * Scope note (see controller docblock): this covers the core expense
 * entry, including "Allocating With Customer Contracts" (a repeater:
 * pick a customer, their contracts load via the same AJAX endpoint
 * used by the Contracts page's PO Allocation modal, code/amount
 * auto-fill, you set an allocate amount per contract). Two more
 * advanced pieces of the old form are still NOT included, both
 * genuinely optional per StoreCashExpenseRequest — pre-filling from a
 * specific supplier invoice, and inline "add a new category/expense
 * name" from the form itself (existing categories only for now — the
 * old page's version of that used a generic modal component whose
 * AJAX endpoint isn't a named route).
 *
 * The Balance / Net Balance preview works the same way as Buy Or Sell
 * Currencies / Internal Money Transfer: fed by the selected Account
 * Number, shown for Outgoing Transfer and Payable Cheque (both are
 * bank-account-based); Cash Payment doesn't have one (branch/safe
 * entries here don't carry a running account balance the way a bank
 * account does).
 */

const props = defineProps({
    company: Object,
    mode: String, // 'create' | 'edit'
    locale: String,
    types: Object, // {type: label}
    currencies: Object, // {code: label}
    categories: Array, // [{id, name}]
    categoryNames: Array, // [{id, name, category_id}]
    branches: Array, // [{id, name}]
    financialInstitutionBanks: Array, // [{id, name}]
    accountTypes: Array, // [{id, name}]
    clientsWithContracts: Array, // [{id, name}]
    getContractsForCustomerUrl: String,
    existingAllocations: Array, // [{partner_id, contract_id, contract_code, contract_amount, contract_currency, amount}]
    /* Opened via the list's Copy button: an ordinary create that
       happens to arrive pre-filled. Only the contract lookup below
       cares — see CashExpenseController::buildFormProps(). */
    isCopy: { type: Boolean, default: false },
    model: Object, // null in create mode
    submitUrl: String,
    backUrl: String,
    getBankBalanceUrl: String,
});

const page = usePage();
const isEdit = props.mode === 'edit';

const TYPES = {
    CASH_PAYMENT: 'cash_payment',
    PAYABLE_CHEQUE: 'payable_cheque',
    OUTGOING_TRANSFER: 'outgoing-transfer',
};

/**
 * Exchange Rate should only ever matter when the expense currency
 * differs from the company's main functional currency — same reasoning
 * as Money Payment / Money Received's Form.vue. Shared across all three
 * types since Currency is the same field regardless of which type tab
 * is active.
 */
const isForeignCurrency = computed(() => form.value.currency && form.value.currency !== props.company.mainFunctionalCurrency);

/**
 * Exchange Rate now accepts either a plain number ("50") or a division
 * expression ("1/50" or "=1/50", spreadsheet-style) — mirrors Money
 * Payment / Money Received's Form.vue exactly. One input + parsed value
 * per type (cash payment / outgoing transfer / payable cheque), since
 * each type keeps its own exchange rate field already.
 */
function parseExchangeRateExpression(raw) {
    const s = String(raw ?? '').trim().replace(/^=/, '');
    if (!s) return 0;
    if (/^-?\d+(\.\d+)?$/.test(s)) {
        const n = Number(s);
        return Number.isFinite(n) ? n : 0;
    }
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

const form = ref({
    type: props.model?.type ?? TYPES.CASH_PAYMENT,
    payment_date: props.model?.payment_date ?? todayDate(),
    currency: props.model?.currency ?? '',
    expense_category_id: props.categoryNames.find(n => n.id === props.model?.cash_expense_category_name_id)?.category_id ?? '',
    cash_expense_category_name_id: props.model?.cash_expense_category_name_id ?? '',
    user_comment: props.model?.user_comment ?? '',
    // Cash Payment
    delivery_branch_id: props.model?.delivery_branch_id ?? '',
    paid_amount_cash_payment: props.model?.type === TYPES.CASH_PAYMENT ? props.model?.paid_amount : 0,
    receipt_number: props.model?.receipt_number ?? '',
    exchange_rate_input_cash_payment: String(props.model?.type === TYPES.CASH_PAYMENT ? (props.model?.exchange_rate ?? 1) : 1),
    // Outgoing Transfer
    outgoing_transfer_delivery_bank_id: props.model?.outgoing_transfer_delivery_bank_id ?? '',
    outgoing_transfer_account_type: props.model?.outgoing_transfer_account_type ?? '',
    outgoing_transfer_account_number: props.model?.outgoing_transfer_account_number ?? '',
    paid_amount_outgoing_transfer: props.model?.type === TYPES.OUTGOING_TRANSFER ? props.model?.paid_amount : 0,
    is_bank_charges: props.model?.is_bank_charges ?? false,
    exchange_rate_input_outgoing_transfer: String(props.model?.type === TYPES.OUTGOING_TRANSFER ? (props.model?.exchange_rate ?? 1) : 1),
    // Payable Cheque
    payable_cheque_delivery_bank_id: props.model?.payable_cheque_delivery_bank_id ?? '',
    payable_cheque_account_type: props.model?.payable_cheque_account_type ?? '',
    payable_cheque_account_number: props.model?.payable_cheque_account_number ?? '',
    paid_amount_payable_cheque: props.model?.type === TYPES.PAYABLE_CHEQUE ? props.model?.paid_amount : 0,
    due_date: props.model?.due_date ?? '',
    cheque_number: props.model?.cheque_number ?? '',
    exchange_rate_input_payable_cheque: String(props.model?.type === TYPES.PAYABLE_CHEQUE ? (props.model?.exchange_rate ?? 1) : 1),
});

/* Parsed numeric value behind each exchange-rate input — only
   recalculated when the person leaves the field (@blur), same UX as
   Money Payment / Money Received. */
const exchangeRateCashPayment = ref(parseExchangeRateExpression(form.value.exchange_rate_input_cash_payment));
const exchangeRateOutgoingTransfer = ref(parseExchangeRateExpression(form.value.exchange_rate_input_outgoing_transfer));
const exchangeRatePayableCheque = ref(parseExchangeRateExpression(form.value.exchange_rate_input_payable_cheque));

function onExchangeRateBlur(type) {
    if (type === TYPES.CASH_PAYMENT) {
        const parsed = parseExchangeRateExpression(form.value.exchange_rate_input_cash_payment);
        form.value.exchange_rate_input_cash_payment = String(Math.round(parsed * 1e6) / 1e6);
        exchangeRateCashPayment.value = parsed;
    } else if (type === TYPES.OUTGOING_TRANSFER) {
        const parsed = parseExchangeRateExpression(form.value.exchange_rate_input_outgoing_transfer);
        form.value.exchange_rate_input_outgoing_transfer = String(Math.round(parsed * 1e6) / 1e6);
        exchangeRateOutgoingTransfer.value = parsed;
    } else {
        const parsed = parseExchangeRateExpression(form.value.exchange_rate_input_payable_cheque);
        form.value.exchange_rate_input_payable_cheque = String(Math.round(parsed * 1e6) / 1e6);
        exchangeRatePayableCheque.value = parsed;
    }
}

/**
 * Amount in the company's main currency — shown read-only right after
 * Exchange Rate, same "Amount in [currency]" pattern as Money Payment /
 * Money Received's Form.vue, but labeled with the real main-currency
 * code (e.g. "OMR") instead of a generic label. Matches the same
 * amount * exchangeRate convention CashExpense's own Odoo sync code
 * uses server-side (see HasNonCustomerOrSupplier::createNonCustomerOrSupplierOdooExpense).
 */
const amountInMainCurrencyCashPayment = computed(() => Math.round((Number(form.value.paid_amount_cash_payment) || 0) * exchangeRateCashPayment.value * 100) / 100);
const amountInMainCurrencyOutgoingTransfer = computed(() => Math.round((Number(form.value.paid_amount_outgoing_transfer) || 0) * exchangeRateOutgoingTransfer.value * 100) / 100);
const amountInMainCurrencyPayableCheque = computed(() => Math.round((Number(form.value.paid_amount_payable_cheque) || 0) * exchangeRatePayableCheque.value * 100) / 100);

/* ── Category → Expense Name cascade ─────────────────────────────
   All category names arrive up front (with their parent category id)
   so this filters client-side — no extra AJAX round trip needed. */
const filteredCategoryNames = computed(() =>
    props.categoryNames.filter(n => String(n.category_id) === String(form.value.expense_category_id))
);
watch(() => form.value.expense_category_id, () => {
    if (!filteredCategoryNames.value.some(n => n.id === form.value.cash_expense_category_name_id)) {
        form.value.cash_expense_category_name_id = filteredCategoryNames.value[0]?.id || '';
    }
});

/* ── Cascading: Account Numbers, based on Bank + Account Type + Currency
   Same AJAX endpoint the old jQuery form used
   (CashExpenseController@getAccountNumbersForAccountType) — read-only,
   untouched, just called from Vue via axios instead. */
const outgoingTransferAccountNumberOptions = ref(accountNumberOption(props.model?.outgoing_transfer_account_number));
const payableChequeAccountNumberOptions = ref(accountNumberOption(props.model?.payable_cheque_account_number));

async function loadAccountNumbers(kind) {
    const bankId = kind === 'outgoing_transfer' ? form.value.outgoing_transfer_delivery_bank_id : form.value.payable_cheque_delivery_bank_id;
    const accountTypeId = kind === 'outgoing_transfer' ? form.value.outgoing_transfer_account_type : form.value.payable_cheque_account_type;
    if (!bankId || !accountTypeId || !form.value.currency) return;
    const url = `/${props.locale}/${props.company.id}/cash-expense/get-account-numbers-based-on-account-type/${accountTypeId}/${form.value.currency}/${bankId}`;
    const { data } = await window.axios.get(url);
    const options = mapAccountNumberOptions(data.data);
    if (kind === 'outgoing_transfer') {
        outgoingTransferAccountNumberOptions.value = options;
        if (!hasAccountNumber(options, form.value.outgoing_transfer_account_number)) form.value.outgoing_transfer_account_number = options[0]?.value || '';
    } else {
        payableChequeAccountNumberOptions.value = options;
        if (!hasAccountNumber(options, form.value.payable_cheque_account_number)) form.value.payable_cheque_account_number = options[0]?.value || '';
    }
}
watch(() => [form.value.outgoing_transfer_delivery_bank_id, form.value.outgoing_transfer_account_type, form.value.currency], () => loadAccountNumbers('outgoing_transfer'));
watch(() => [form.value.payable_cheque_delivery_bank_id, form.value.payable_cheque_account_type, form.value.currency], () => loadAccountNumbers('payable_cheque'));

/* ── Balance / Net Balance preview (Outgoing Transfer / Payable Cheque) ─
   Same pre-existing endpoint used on the other Treasury forms
   (MoneyReceivedController@updateNetBalanceBasedOnAccountNumber). */
const bankBalance = ref({ balance: 0, balanceDate: '', netBalance: 0, netBalanceDate: '' });
async function loadBankBalance() {
    const isOutgoing = form.value.type === TYPES.OUTGOING_TRANSFER;
    const isCheque = form.value.type === TYPES.PAYABLE_CHEQUE;
    if (!isOutgoing && !isCheque) return;
    const bankId = isOutgoing ? form.value.outgoing_transfer_delivery_bank_id : form.value.payable_cheque_delivery_bank_id;
    const accountType = isOutgoing ? form.value.outgoing_transfer_account_type : form.value.payable_cheque_account_type;
    const accountNumber = isOutgoing ? form.value.outgoing_transfer_account_number : form.value.payable_cheque_account_number;
    if (!bankId || !accountType || !accountNumber) return;
    const { data } = await window.axios.get(props.getBankBalanceUrl, {
        params: {
            accountNumber,
            accountType,
            financialInstitutionId: bankId,
            balanceDate: form.value.payment_date,
            modelType: 'CashExpense',
            modelId: props.model?.id || 0,
        },
    });
    bankBalance.value = {
        balance: data.balance || 0,
        balanceDate: data.balance_date || '',
        netBalance: data.net_balance || 0,
        netBalanceDate: data.net_balance_date || '',
    };
}
watch(() => [
    form.value.type,
    form.value.outgoing_transfer_delivery_bank_id, form.value.outgoing_transfer_account_type, form.value.outgoing_transfer_account_number,
    form.value.payable_cheque_delivery_bank_id, form.value.payable_cheque_account_type, form.value.payable_cheque_account_number,
    form.value.payment_date,
], loadBankBalance);
onMounted(loadBankBalance);

function formatNumber(value) {
    return Math.round(Number(value) || 0).toLocaleString('en-US');
}

/* ── Allocating With Customer Contracts ──────────────────────────
   A repeater: pick a customer, their contracts load via the same
   AJAX endpoint the Contracts page's PO Allocation modal uses,
   Contract Code/Amount auto-fill (read-only), you set an Allocate
   Amount. Saves as contracts: [{contract_id, amount}] via the
   pre-existing, UNCHANGED saveAllocations(). */
function emptyAllocationRow() {
    return { partner_id: '', contract_id: '', contract_code: '', contract_amount: null, contract_currency: '', amount: 0 };
}
const allocationRows = ref(
    props.existingAllocations.length
        ? props.existingAllocations.map(a => ({ ...a }))
        : []
);
const contractsByPartner = ref({}); // partnerId -> [{id, name, code, amount, currency}]

function addAllocationRow() {
    allocationRows.value.push(emptyAllocationRow());
}
function removeAllocationRow(index) {
    allocationRows.value.splice(index, 1);
}

async function loadContractsForPartner(partnerId) {
    if (!partnerId || contractsByPartner.value[partnerId]) return;
    const { data } = await window.axios.get(props.getContractsForCustomerUrl, {
        params: { partnerId, model: 'CashExpense', inEditMode: isEdit || props.isCopy ? 1 : 0 },
    });
    contractsByPartner.value = { ...contractsByPartner.value, [partnerId]: data.contracts || [] };
}
// Pre-load contract options for any partner already selected in a
// saved allocation row, so the Contract dropdown isn't empty on open.
allocationRows.value.forEach(row => { if (row.partner_id) loadContractsForPartner(row.partner_id); });

function onAllocationPartnerChange(row) {
    row.contract_id = '';
    row.contract_code = '';
    row.contract_amount = null;
    row.contract_currency = '';
    loadContractsForPartner(row.partner_id);
}

function onAllocationContractChange(row) {
    const options = contractsByPartner.value[row.partner_id] || [];
    const selected = options.find(c => String(c.id) === String(row.contract_id));
    row.contract_code = selected ? selected.code : '';
    row.contract_amount = selected ? Number(selected.amount) : null;
    row.contract_currency = selected ? String(selected.currency || '').toUpperCase() : '';
}

/* ── Error display ────────────────────────────────────────────── */
function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}
// The old page's own validation rule (AmountCanNotBeGreaterThan
// EndBalanceAtPaymentDate, server-side, UNCHANGED) fails under this
// specific key when there isn't enough balance to cover the payment —
// worth its own clear message instead of the generic "fix the
// highlighted fields" banner.
const insufficientBalanceError = computed(() => page.props.errors?.amount_can_not_be_greater_than_end_balance_at_payment_date ?? null);
const otherErrorCount = computed(() => {
    const errors = { ...(page.props.errors || {}) };
    delete errors.amount_can_not_be_greater_than_end_balance_at_payment_date;
    return Object.keys(errors).length;
});

/* ── Submit ───────────────────────────────────────────────────── */
const submitting = ref(false);
function submit() {
    submitting.value = true;

    const paidAmount = {};
    const exchangeRate = {};
    const deliveryBankId = {};
    const accountType = {};
    const accountNumber = {};
    paidAmount[form.value.type] =
        form.value.type === TYPES.CASH_PAYMENT ? form.value.paid_amount_cash_payment :
        form.value.type === TYPES.OUTGOING_TRANSFER ? form.value.paid_amount_outgoing_transfer :
        form.value.paid_amount_payable_cheque;
    exchangeRate[form.value.type] =
        form.value.type === TYPES.CASH_PAYMENT ? exchangeRateCashPayment.value :
        form.value.type === TYPES.OUTGOING_TRANSFER ? exchangeRateOutgoingTransfer.value :
        exchangeRatePayableCheque.value;
    if (form.value.type === TYPES.OUTGOING_TRANSFER) {
        deliveryBankId[TYPES.OUTGOING_TRANSFER] = form.value.outgoing_transfer_delivery_bank_id;
        accountType[TYPES.OUTGOING_TRANSFER] = form.value.outgoing_transfer_account_type;
        accountNumber[TYPES.OUTGOING_TRANSFER] = form.value.outgoing_transfer_account_number;
    } else if (form.value.type === TYPES.PAYABLE_CHEQUE) {
        deliveryBankId[TYPES.PAYABLE_CHEQUE] = form.value.payable_cheque_delivery_bank_id;
        accountType[TYPES.PAYABLE_CHEQUE] = form.value.payable_cheque_account_type;
        accountNumber[TYPES.PAYABLE_CHEQUE] = form.value.payable_cheque_account_number;
    }

    const payload = {
        type: form.value.type,
        payment_date: form.value.payment_date,
        currency: form.value.currency,
        cash_expense_category_name_id: form.value.cash_expense_category_name_id,
        user_comment: form.value.user_comment,
        paid_amount: paidAmount,
        exchange_rate: exchangeRate,
        delivery_branch_id: form.value.type === TYPES.CASH_PAYMENT ? form.value.delivery_branch_id : null,
        receipt_number: form.value.type === TYPES.CASH_PAYMENT ? form.value.receipt_number : null,
        delivery_bank_id: deliveryBankId,
        account_type: accountType,
        account_number: accountNumber,
        is_bank_charges: form.value.type === TYPES.OUTGOING_TRANSFER ? form.value.is_bank_charges : false,
        due_date: form.value.type === TYPES.PAYABLE_CHEQUE ? form.value.due_date : null,
        cheque_number: form.value.type === TYPES.PAYABLE_CHEQUE ? form.value.cheque_number : null,
        /**
         * FIX (per bug report, 2026-08-13): these tell the server which
         * record to EXCLUDE from the Cheque Number / Receipt Number
         * uniqueness check — without them, editing a cheque or receipt
         * falsely reports "already exists" against itself. Only
         * meaningful (and only sent as non-null) in edit mode; on
         * create there's nothing yet to exclude.
         */
        current_cheque_id: isEdit ? (props.model?.payable_cheque_id ?? null) : null,
        cash_id: isEdit ? (props.model?.cash_payment_id ?? null) : null,
        contracts: allocationRows.value
            .filter(r => r.contract_id && Number(r.amount) > 0)
            .map(r => ({ contract_id: r.contract_id, amount: r.amount })),
        // Pre-fill from a specific supplier invoice isn't built into
        // this page yet — see docblock. Sending 0 keeps a plain
        // expense entry working exactly like the old form did when
        // this was unused.
        unapplied_amount: 0,
    };

    if (isEdit) {
        router.put(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    }
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Cash Expense') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">
                {{ isEdit ? $t('Edit') : $t('Add') }} {{ $t('Cash Expense') }}
            </h1>

            <div v-if="insufficientBalanceError" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm font-semibold">
                {{ $t('No Enough Balance Amount to Process The Payment') }}
            </div>
            <FormErrorSummary :except="['amount_can_not_be_greater_than_end_balance_at_payment_date']" />

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card space-y-4">
                    <!-- Balance / Net Balance preview -->
                    <div v-if="form.type === TYPES.OUTGOING_TRANSFER || form.type === TYPES.PAYABLE_CHEQUE" class="flex justify-end">
                        <div class="cvr-form-grid-2 w-full max-w-md">
                            <div>
                                <label class="cvr-form-label">
                                    {{ $t('Balance') }} <span v-if="bankBalance.balanceDate">[ {{ bankBalance.balanceDate }} ]</span>
                                </label>
                                <input :value="formatNumber(bankBalance.balance)" disabled class="cvr-input w-full px-3 py-2 rounded opacity-80" />
                            </div>
                            <div>
                                <label class="cvr-form-label">
                                    {{ $t('Net Balance') }} <span v-if="bankBalance.netBalanceDate">[ {{ bankBalance.netBalanceDate }} ]</span>
                                </label>
                                <input :value="formatNumber(bankBalance.netBalance)" disabled class="cvr-input w-full px-3 py-2 rounded opacity-80" />
                            </div>
                        </div>
                    </div>

                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Payment Date') }} *</label>
                            <input v-model="form.payment_date" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Expense Category') }}</label>
                            <select v-model="form.expense_category_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Expense Name') }}</label>
                            <select v-model="form.cash_expense_category_name_id" class="cvr-input w-full px-3 py-2 rounded" :disabled="!form.expense_category_id">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="n in filteredCategoryNames" :key="n.id" :value="n.id">{{ n.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Type') }} *</label>
                            <select v-model="form.type" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Cash Payment -->
                    <div v-if="form.type === TYPES.CASH_PAYMENT" class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Branch') }} *</label>
                            <select v-model="form.delivery_branch_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                            <p v-if="errorFor('delivery_branch_id')" class="text-xs mt-1 cvr-num-red">{{ errorFor($t('delivery_branch_id')) }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Paid Amount') }} *</label>
                            <input v-model="form.paid_amount_cash_payment" type="number" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                            <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="(clabel, code) in currencies" :key="code" :value="code">{{ clabel }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Receipt Number') }} *</label>
                            <input v-model="form.receipt_number" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('receipt_number')" class="text-xs mt-1 cvr-num-red">{{ errorFor('receipt_number') }}</p>
                        </div>
                        <div v-if="isForeignCurrency">
                            <label class="cvr-form-label">{{ $t('Exchange Rate') }}</label>
                            <input v-model="form.exchange_rate_input_cash_payment" @blur="onExchangeRateBlur(TYPES.CASH_PAYMENT)" type="text" inputmode="decimal" :placeholder="$t('e.g. 50 or 1/50')" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div v-if="isForeignCurrency">
                            <label class="cvr-form-label">{{ $t('Amount In') }} {{ company.mainFunctionalCurrency }}</label>
                            <input :value="amountInMainCurrencyCashPayment" readonly class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>

                    <!-- Outgoing Transfer -->
                    <template v-if="form.type === TYPES.OUTGOING_TRANSFER">
                        <div class="cvr-form-grid-3">
                            <div>
                                <label class="cvr-form-label">{{ $t('Currency *') }}</label>
                                <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="">{{ $t('Select') }}</option>
                                    <option v-for="(clabel, code) in currencies" :key="code" :value="code">{{ clabel }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Paid Amount *') }}</label>
                                <input v-model="form.paid_amount_outgoing_transfer" type="number" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div v-if="isForeignCurrency">
                                <label class="cvr-form-label">{{ $t('Exchange Rate') }}</label>
                                <input v-model="form.exchange_rate_input_outgoing_transfer" @blur="onExchangeRateBlur(TYPES.OUTGOING_TRANSFER)" type="text" inputmode="decimal" :placeholder="$t('e.g. 50 or 1/50')" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div v-if="isForeignCurrency">
                                <label class="cvr-form-label">{{ $t('Amount In') }} {{ company.mainFunctionalCurrency }}</label>
                                <input :value="amountInMainCurrencyOutgoingTransfer" readonly class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                        </div>
                        <!-- Bank-name field — 6:3:3, bank names run long -->
                        <div class="cvr-form-grid-6-3-3">
                            <div>
                                <label class="cvr-form-label">{{ $t('Payment Bank *') }}</label>
                                <select v-model="form.outgoing_transfer_delivery_bank_id" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="">{{ $t('Select') }}</option>
                                    <option v-for="bank in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Account Type *') }}</label>
                                <select v-model="form.outgoing_transfer_account_type" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="">{{ $t('Select') }}</option>
                                    <option v-for="at in accountTypes" :key="at.id" :value="at.id">{{ at.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Account Number *') }}</label>
                                <select v-model="form.outgoing_transfer_account_number" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="">{{ $t('Select') }}</option>
                                    <option v-for="num in outgoingTransferAccountNumberOptions" :key="num.value" :value="num.value">{{ num.label }}</option>
                                </select>
                                <p v-if="errorFor('account_number')" class="text-xs mt-1 cvr-num-red">{{ errorFor('account_number') }}</p>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 cvr-text-primary text-sm">
                            <input type="checkbox" v-model="form.is_bank_charges" />
                            {{ $t('This transfer is a bank charge') }}
                        </label>
                    </template>

                    <!-- Payable Cheque -->
                    <template v-if="form.type === TYPES.PAYABLE_CHEQUE">
                        <div class="cvr-form-grid-4">
                            <div>
                                <label class="cvr-form-label">{{ $t('Currency *') }}</label>
                                <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="">{{ $t('Select') }}</option>
                                    <option v-for="(clabel, code) in currencies" :key="code" :value="code">{{ clabel }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Cheque Amount *') }}</label>
                                <input v-model="form.paid_amount_payable_cheque" type="number" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Due Date *') }}</label>
                                <input v-model="form.due_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                                <p v-if="errorFor('due_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('due_date') }}</p>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Cheque Number *') }}</label>
                                <input v-model="form.cheque_number" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                                <p v-if="errorFor('cheque_number')" class="text-xs mt-1 cvr-num-red">{{ errorFor('cheque_number') }}</p>
                            </div>
                        </div>
                        <!-- Bank-name field — 6:3:3, bank names run long -->
                        <div class="cvr-form-grid-6-3-3">
                            <div>
                                <label class="cvr-form-label">{{ $t('Payment Bank *') }}</label>
                                <select v-model="form.payable_cheque_delivery_bank_id" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="">{{ $t('Select') }}</option>
                                    <option v-for="bank in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Account Type *') }}</label>
                                <select v-model="form.payable_cheque_account_type" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="">{{ $t('Select') }}</option>
                                    <option v-for="at in accountTypes" :key="at.id" :value="at.id">{{ at.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Account Number *') }}</label>
                                <select v-model="form.payable_cheque_account_number" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="">{{ $t('Select') }}</option>
                                    <option v-for="num in payableChequeAccountNumberOptions" :key="num.value" :value="num.value">{{ num.label }}</option>
                                </select>
                                <p v-if="errorFor('account_number')" class="text-xs mt-1 cvr-num-red">{{ errorFor('account_number') }}</p>
                            </div>
                        </div>
                        <div v-if="isForeignCurrency" class="cvr-form-grid-3">
                            <div>
                                <label class="cvr-form-label">{{ $t('Exchange Rate') }}</label>
                                <input v-model="form.exchange_rate_input_payable_cheque" @blur="onExchangeRateBlur(TYPES.PAYABLE_CHEQUE)" type="text" inputmode="decimal" :placeholder="$t('e.g. 50 or 1/50')" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Amount In') }} {{ company.mainFunctionalCurrency }}</label>
                                <input :value="amountInMainCurrencyPayableCheque" readonly class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Allocating With Customer Contracts -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Allocating With Customer Contracts') }}</h2>

                    <table v-if="allocationRows.length" class="min-w-full text-xs mb-3">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-2 py-2 text-start">{{ $t('Customer') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Contract Name') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Contract Code') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Contract Amount') }}</th>
                                <th class="px-2 py-2 text-start">{{ $t('Allocate Amount') }}</th>
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in allocationRows" :key="index" class="cvr-table-row">
                                <td class="px-2 py-2">
                                    <select v-model="row.partner_id" @change="onAllocationPartnerChange(row)" class="cvr-input px-2 py-1 rounded w-full">
                                        <option value="">{{ $t('Select customer...') }}</option>
                                        <option v-for="c in clientsWithContracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </td>
                                <td class="px-2 py-2">
                                    <select v-model="row.contract_id" @change="onAllocationContractChange(row)" class="cvr-input px-2 py-1 rounded w-full" :disabled="!row.partner_id">
                                        <option value="">{{ $t('Select contract...') }}</option>
                                        <option v-for="c in (contractsByPartner[row.partner_id] || [])" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </td>
                                <td class="px-2 py-2 cvr-text-muted">{{ row.contract_code }}</td>
                                <td class="px-2 py-2 cvr-num">
                                    <span v-if="row.contract_amount !== null">{{ formatNumber(row.contract_amount) }} {{ row.contract_currency }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <input v-model="row.amount" type="number" step="0.01" class="cvr-input px-2 py-1 rounded w-28" />
                                </td>
                                <td class="px-2 py-2">
                                    <button type="button" @click="removeAllocationRow(index)" class="cvr-btn-danger px-2 py-1 rounded border text-xs">✕</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-xs cvr-text-muted mb-3">{{ $t('No contract allocations yet — optional, only needed if this expense should be settled against a customer\'s contract.') }}</p>

                    <button type="button" @click="addAllocationRow" class="cvr-btn-secondary px-3 py-1.5 rounded border text-xs">
                        {{ $t('+ Add Allocation') }}
                    </button>
                </div>

                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('User Comment') }}</h2>
                    <textarea v-model="form.user_comment" rows="3" class="cvr-input w-full px-3 py-2 rounded"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Cancel') }}</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? $t('Saving...') : $t('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>