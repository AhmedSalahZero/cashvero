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
 * BuyOrSellCurrencies/Form.vue
 * ------------------------------------------------------------------
 * ONE shared page for Add + Edit, and for all four transfer types
 * (Bank→Bank, Bank→Safe, Safe→Bank, Safe→Safe) — same `mode` and
 * `type` conventions used elsewhere in this project.
 *
 * Which fields show depends only on two independent questions derived
 * from `type`, exactly like the old page's data-type="..." groups:
 *   - is the FROM side a Bank or a Safe (Branch)?
 *   - is the TO side a Bank or a Safe (Branch)?
 * bank-to-bank   -> From: Bank, To: Bank
 * bank-to-safe   -> From: Bank, To: Branch
 * safe-to-bank   -> From: Branch, To: Bank
 * safe-to-safe   -> From: Branch, To: Branch
 *
 * The Balance / Net Balance preview boxes at the top mirror the old
 * form: a Bank→* type shows Balance + Net Balance (fed by the From
 * Account Number), a Safe→* type shows just Balance (fed by the From
 * Branch) — both against the same pre-existing AJAX endpoints the old
 * jQuery form called.
 */

const props = defineProps({
    company: Object,
    mode: String, // 'create' | 'edit'
    locale: String,
    allTypes: Object, // {type: label}
    currencies: Object, // {code: label}
    financialInstitutionBanks: Array, // [{id, name}]
    accountTypes: Array, // [{id, name}]
    branches: Array, // [{id, name}]
    model: Object, // null in create mode
    submitUrl: String,
    backUrl: String,
    getBranchesForCurrencyUrl: String,
    getBankBalanceUrl: String,
    getCashSafeBalanceUrl: String,
});

const page = usePage();
const isEdit = props.mode === 'edit';

const form = ref({
    type: props.model?.type ?? 'bank-to-bank',
    transaction_date: props.model?.transaction_date ?? todayDate(),
    currency_to_sell: props.model?.currency_to_sell ?? '',
    currency_to_buy: props.model?.currency_to_buy ?? '',
    currency_to_sell_amount: props.model?.currency_to_sell_amount ?? 0,
    exchange_rate: props.model?.exchange_rate ?? 0,
    currency_to_buy_amount: props.model?.currency_to_buy_amount ?? 0,
    from_bank_id: props.model?.from_bank_id || '',
    from_account_type_id: props.model?.from_account_type_id || '',
    from_account_number: props.model?.from_account_number || '',
    to_bank_id: props.model?.to_bank_id || '',
    to_account_type_id: props.model?.to_account_type_id || '',
    to_account_number: props.model?.to_account_number || '',
    from_branch_id: props.model?.from_branch_id || '',
    to_branch_id: props.model?.to_branch_id || '',
    user_comment: props.model?.user_comment ?? '',
});

/* ── Which side is Bank vs Branch, driven purely by `type` ────────── */
const isFromBank = computed(() => form.value.type.startsWith('bank-to-'));
const isFromSafe = computed(() => form.value.type.startsWith('safe-to-'));
const isToBank = computed(() => form.value.type.endsWith('-to-bank'));
const isToSafe = computed(() => form.value.type.endsWith('-to-safe'));

/* ── Auto-calculate Currency To Buy Amount ────────────────────────
   Mirrors the old page's calcField/multiplierField/resultField logic:
   Buy Amount = Sell Amount x Exchange Rate. */
function recalculateBuyAmount() {
    const sellAmount = Number(form.value.currency_to_sell_amount) || 0;
    const rate = Number(form.value.exchange_rate) || 0;
    form.value.currency_to_buy_amount = Math.round(sellAmount * rate * 100) / 100;
}
watch(() => [form.value.currency_to_sell_amount, form.value.exchange_rate], recalculateBuyAmount);

/* ── Exchange Rate field acts as a small calculator ────────────────
   Restores the original page's jquery.calculator behavior: type a
   plain number, or a formula starting with "=" (e.g. "=1/53"), and
   the evaluated result fills in once you leave the field — same
   "type it like a spreadsheet cell" UX as before.
   Self-contained (no eval/Function, no new dependency needed) — a
   small recursive-descent parser for +, -, *, /, and parentheses,
   which is all the old jquery.calculator formulas ever used. */
function evaluateFormula(expr) {
    let pos = 0;
    function skipSpaces() { while (expr[pos] === ' ') pos++; }
    function parseNumber() {
        skipSpaces();
        const start = pos;
        if (expr[pos] === '+' || expr[pos] === '-') pos++;
        while (/[0-9.]/.test(expr[pos])) pos++;
        const text = expr.slice(start, pos);
        if (!text || text === '+' || text === '-') throw new Error('Invalid number');
        return parseFloat(text);
    }
    function parseFactor() {
        skipSpaces();
        if (expr[pos] === '(') {
            pos++;
            const value = parseExpression();
            skipSpaces();
            if (expr[pos] !== ')') throw new Error('Missing closing parenthesis');
            pos++;
            return value;
        }
        return parseNumber();
    }
    function parseTerm() {
        let value = parseFactor();
        skipSpaces();
        while (expr[pos] === '*' || expr[pos] === '/') {
            const op = expr[pos];
            pos++;
            const next = parseFactor();
            value = op === '*' ? value * next : value / next;
            skipSpaces();
        }
        return value;
    }
    function parseExpression() {
        let value = parseTerm();
        skipSpaces();
        while (expr[pos] === '+' || expr[pos] === '-') {
            const op = expr[pos];
            pos++;
            const next = parseTerm();
            value = op === '+' ? value + next : value - next;
            skipSpaces();
        }
        return value;
    }
    const result = parseExpression();
    skipSpaces();
    if (pos !== expr.length) throw new Error('Unexpected characters');
    return result;
}

const exchangeRateDisplay = ref(props.model?.exchange_rate != null ? String(props.model.exchange_rate) : '');
function onExchangeRateBlur() {
    const raw = exchangeRateDisplay.value.trim();
    let result;
    if (raw.startsWith('=')) {
        try {
            result = evaluateFormula(raw.slice(1));
        } catch (e) {
            result = 0;
        }
    } else {
        result = parseFloat(raw);
    }
    if (!isFinite(result) || isNaN(result)) result = 0;
    result = Math.round(result * 10000) / 10000;
    form.value.exchange_rate = result;
    exchangeRateDisplay.value = String(result);
}

/* ── Cascading: Account Numbers, based on Bank + Account Type + Currency
   Same AJAX endpoint the old jQuery form used
   (MoneyReceivedController@getAccountNumbersForAccountType) — read-only,
   untouched, just called from Vue via axios instead. */
async function loadAccountNumbers(side) {
    const bankId = side === 'from' ? form.value.from_bank_id : form.value.to_bank_id;
    const accountTypeId = side === 'from' ? form.value.from_account_type_id : form.value.to_account_type_id;
    const currency = side === 'from' ? form.value.currency_to_sell : form.value.currency_to_buy;
    if (!bankId || !accountTypeId || !currency) return;
    const url = `/${props.locale}/${props.company.id}/money-received/get-account-numbers-based-on-account-type/${accountTypeId}/${currency}/${bankId}`;
    const { data } = await window.axios.get(url);
    const options = mapAccountNumberOptions(data.data);
    if (side === 'from') {
        fromAccountNumberOptions.value = options;
        if (!hasAccountNumber(options, form.value.from_account_number)) form.value.from_account_number = options[0]?.value || '';
    } else {
        toAccountNumberOptions.value = options;
        if (!hasAccountNumber(options, form.value.to_account_number)) form.value.to_account_number = options[0]?.value || '';
    }
}
const fromAccountNumberOptions = ref(accountNumberOption(props.model?.from_account_number));
const toAccountNumberOptions = ref(accountNumberOption(props.model?.to_account_number));
watch(() => [form.value.from_bank_id, form.value.from_account_type_id, form.value.currency_to_sell], () => loadAccountNumbers('from'));
watch(() => [form.value.to_bank_id, form.value.to_account_type_id, form.value.currency_to_buy], () => loadAccountNumbers('to'));

/* ── Cascading: Branches, based on Currency ────────────────────────
   Same AJAX endpoint the old jQuery form used
   (BranchesController@getBranchesForCurrency) — read-only, untouched.
   From Branch cascades off Currency To Sell, To Branch off Currency
   To Buy, matching the old page exactly. */
async function loadBranches(side) {
    const currency = side === 'from' ? form.value.currency_to_sell : form.value.currency_to_buy;
    if (!currency) return;
    const { data } = await window.axios.get(props.getBranchesForCurrencyUrl, { params: { currencyName: currency } });
    const options = Object.entries(data.branches || {}).map(([name, id]) => ({ id, name }));
    if (side === 'from') {
        fromBranchOptions.value = options;
        if (!options.some(o => String(o.id) === String(form.value.from_branch_id))) form.value.from_branch_id = options[0]?.id || '';
    } else {
        toBranchOptions.value = options;
        if (!options.some(o => String(o.id) === String(form.value.to_branch_id))) form.value.to_branch_id = options[0]?.id || '';
    }
}
const fromBranchOptions = ref(props.branches);
const toBranchOptions = ref(props.branches);
watch(() => form.value.currency_to_sell, () => loadBranches('from'));
watch(() => form.value.currency_to_buy, () => loadBranches('to'));

/* ── Balance / Net Balance preview ────────────────────────────────
   Bank→* types show Balance + Net Balance, fed by the From Account
   Number (MoneyReceivedController@updateNetBalanceBasedOnAccountNumber,
   pre-existing, unchanged). Safe→* types show just a Balance, fed by
   the From Branch (MoneyPaymentController@getCashInSafeStatementEndBalance,
   also pre-existing/unchanged). Both read modelId/modelType so an
   edit-in-progress amount is accounted for, same as the old form. */
const bankBalance = ref({ balance: 0, balanceDate: '', netBalance: 0, netBalanceDate: '' });
const cashSafeBalance = ref(0);

async function loadBankBalance() {
    if (!form.value.from_bank_id || !form.value.from_account_type_id || !form.value.from_account_number) return;
    const { data } = await window.axios.get(props.getBankBalanceUrl, {
        params: {
            accountNumber: form.value.from_account_number,
            accountType: form.value.from_account_type_id,
            financialInstitutionId: form.value.from_bank_id,
            balanceDate: form.value.transaction_date,
            modelType: 'BuyOrSellCurrency',
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
watch(() => [form.value.from_bank_id, form.value.from_account_type_id, form.value.from_account_number, form.value.transaction_date], loadBankBalance);

async function loadCashSafeBalance() {
    if (!form.value.from_branch_id || !form.value.currency_to_sell) return;
    const { data } = await window.axios.get(props.getCashSafeBalanceUrl, {
        params: {
            branchId: form.value.from_branch_id,
            currencyName: form.value.currency_to_sell,
            balanceDate: form.value.transaction_date,
            modelType: 'BuyOrSellCurrency',
            modelId: props.model?.id || 0,
        },
    });
    cashSafeBalance.value = data.end_balance || 0;
}
watch(() => [form.value.from_branch_id, form.value.currency_to_sell, form.value.transaction_date], loadCashSafeBalance);

onMounted(() => {
    loadBankBalance();
    loadCashSafeBalance();
});

/* ── Error display ────────────────────────────────────────────── */
function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

function formatNumber(value) {
    return Math.round(Number(value) || 0).toLocaleString('en-US');
}

/* ── Submit ───────────────────────────────────────────────────── */
const submitting = ref(false);
function submit() {
    submitting.value = true;
    // company_id must be sent explicitly — storeBasicForm() (server
    // side, untouched) writes any request field that matches a real
    // column straight onto the model, including company_id. The old
    // Blade form carried this as a hidden <input>; it needs the same
    // treatment here or the row saves with a null company_id and the
    // foreign key constraint on buy_or_sell_currencies rejects it.
    const payload = {
        ...form.value,
        company_id: props.company.id,
        ...(isEdit ? { updated_by: page.props.auth?.user?.id } : { created_by: page.props.auth?.user?.id }),
    };

    // Fields that don't apply to the selected type were left as ''
    // (their v-model default) rather than being removed from `form`,
    // since v-if unmounts the field but form.value keeps its key.
    // storeBasicForm() would otherwise write '' into these columns —
    // for nullable int columns that becomes 0, not NULL, which is a
    // FK-constraint violation waiting to happen exactly like the
    // to_bank_id one below, just quieter because most of these
    // columns are nullable so it wouldn't fail loudly every time.
    if (!isFromBank.value) {
        payload.from_bank_id = null;
        payload.from_account_type_id = null;
        payload.from_account_number = null;
    }
    if (!isFromSafe.value) {
        payload.from_branch_id = null;
    }
    if (!isToBank.value) {
        payload.to_account_type_id = null;
        payload.to_account_number = null;
        // to_bank_id is the one exception: checked against the real
        // schema (schema_full.txt) after this bug report — unlike
        // every other from/to id column on this table, it's declared
        // NOT NULL. It's never read by handleBankToSafeTransfer() or
        // handleSafeToSafeTransfer() (verified in the controller), so
        // this default carries no business meaning for those types —
        // it exists purely to satisfy the column constraint, the same
        // role the old Blade's hidden-but-still-present <select> (with
        // its first bank pre-selected) happened to play without
        // anyone having to think about it.
        payload.to_bank_id = props.financialInstitutionBanks.length ? props.financialInstitutionBanks[0].id : null;
    }
    if (!isToSafe.value) {
        payload.to_branch_id = null;
    }

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
                    {{ $t('← Back to Sell Or Buy Currencies') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">
                {{ isEdit ? $t('Edit') : $t('Add') }} {{ $t('Sell Or Buy Currencies') }}
            </h1>

            <FormErrorSummary />

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card space-y-4">
                    <!-- Balance / Net Balance preview — Bank→* types show both,
                         Safe→* types show just Balance. Read-only, informational. -->
                    <div v-if="isFromBank" class="flex justify-end">
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
                    <div v-else-if="isFromSafe" class="flex justify-end">
                        <div class="w-full max-w-xs">
                            <label class="cvr-form-label">{{ $t('Balance') }}</label>
                            <input :value="formatNumber(cashSafeBalance)" disabled class="cvr-input w-full px-3 py-2 rounded opacity-80" />
                        </div>
                    </div>

                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Type') }} *</label>
                            <select v-model="form.type" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, key) in allTypes" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Transaction Date') }} *</label>
                            <input v-model="form.transaction_date" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('transaction_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('transaction_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency To Sell') }} *</label>
                            <select v-model="form.currency_to_sell" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency To Buy') }} *</label>
                            <select v-model="form.currency_to_buy" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency To Sell Amount') }} *</label>
                            <input v-model="form.currency_to_sell_amount" type="number" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('currency_to_sell_amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor($t('currency_to_sell_amount')) }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Exchange Rate') }} *</label>
                            <input
                                v-model="exchangeRateDisplay"
                                @blur="onExchangeRateBlur"
                                type="text"
                                inputmode="decimal"
                                :placeholder="$t('e.g. 53 or =1/53')"
                                class="cvr-input w-full px-3 py-2 rounded"
                            />
                            <p class="text-xs mt-1 cvr-text-muted">{{ $t('Type a number, or a formula like =1/53 — the result fills in when you leave the field.') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency To Buy Amount') }}</label>
                            <input :value="form.currency_to_buy_amount" readonly class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                    </div>

                    <!-- FROM side: Bank fields — bank name gets double width (6:3:3) since bank names run long -->
                    <div v-if="isFromBank" class="cvr-form-grid-6-3-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('From Bank') }} *</label>
                            <select v-model="form.from_bank_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="bank in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('From Account Type') }} *</label>
                            <select v-model="form.from_account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="at in accountTypes" :key="at.id" :value="at.id">{{ at.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('From Account Number') }} *</label>
                            <select v-model="form.from_account_number" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="num in fromAccountNumberOptions" :key="num.value" :value="num.value">{{ num.label }}</option>
                            </select>
                            <p v-if="errorFor('from_account_number')" class="text-xs mt-1 cvr-num-red">{{ errorFor($t('from_account_number')) }}</p>
                        </div>
                    </div>

                    <!-- FROM side: Branch (Safe) field -->
                    <div v-if="isFromSafe" class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('From Branch') }} *</label>
                            <select v-model="form.from_branch_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="b in fromBranchOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- TO side: Bank fields — bank name gets double width (6:3:3) since bank names run long -->
                    <div v-if="isToBank" class="cvr-form-grid-6-3-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('To Bank') }} *</label>
                            <select v-model="form.to_bank_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="bank in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('To Account Type') }} *</label>
                            <select v-model="form.to_account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="at in accountTypes" :key="at.id" :value="at.id">{{ at.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('To Account Number') }} *</label>
                            <select v-model="form.to_account_number" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="num in toAccountNumberOptions" :key="num.value" :value="num.value">{{ num.label }}</option>
                            </select>
                            <p v-if="errorFor('to_account_number')" class="text-xs mt-1 cvr-num-red">{{ errorFor('to_account_number') }}</p>
                        </div>
                    </div>

                    <!-- TO side: Branch (Safe) field -->
                    <div v-if="isToSafe" class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('To Branch') }} *</label>
                            <select v-model="form.to_branch_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="b in toBranchOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>
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