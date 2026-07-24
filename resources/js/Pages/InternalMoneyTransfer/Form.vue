<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

/*
 * InternalMoneyTransfer/Form.vue
 * ------------------------------------------------------------------
 * Close sibling of BuyOrSellCurrencies/Form.vue. Real differences:
 *   - Single Currency + Amount (no sell/buy pair, no exchange rate —
 *     there's no conversion in an internal transfer).
 *   - `type` is fixed by the route (which "+ Add" button you clicked
 *     on the list page), not a dropdown in the form — one page still
 *     covers all four types, it just doesn't offer to change type
 *     mid-form the way Buy Or Sell Currencies does.
 *   - Transfer Days (Bank→Bank only) and Cheque Number (Bank→Safe and
 *     Safe→Safe only) — matching the old page's per-type fields.
 *
 * IMPORTANT (see controller docblock, bug #2): the old
 * safe-to-safe-form.blade.php had a copy-paste bug where its hidden
 * `type` field said "bank-to-safe" instead of "safe-to-safe", silently
 * mislabeling every Safe→Safe transfer ever saved through it. This
 * page always sends the CORRECT type — taken from the `type` prop
 * (itself from the route), never hand-typed per form — so that bug
 * can't recur here.
 *
 * The Balance / Net Balance preview boxes at the top work exactly
 * like Buy Or Sell Currencies' — a Bank→* type shows Balance + Net
 * Balance (fed by the From Account Number), a Safe→* type shows just
 * Balance (fed by the From Branch), both against the same
 * pre-existing AJAX endpoints the old jQuery forms called.
 */

const props = defineProps({
    company: Object,
    type: String, // fixed for this page load — 'bank-to-bank' | 'safe-to-bank' | 'bank-to-safe' | 'safe-to-safe'
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

/* Which side is Bank vs Branch — fixed for this page load, driven by
   the type prop rather than a user-editable field. */
const isFromBank = props.type.startsWith('bank-to-');
const isFromSafe = props.type.startsWith('safe-to-');
const isToBank = props.type.endsWith('-to-bank');
const isToSafe = props.type.endsWith('-to-safe');
// Cheque Number only applies when money is leaving a bank and landing
// in a safe — matches the old page's bank-to-safe-form.blade.php and
// safe-to-safe-form.blade.php exactly (safe-to-bank and bank-to-bank
// never had this field).
const showChequeNumber = props.type === 'bank-to-safe' || props.type === 'safe-to-safe';
const showTransferDays = props.type === 'bank-to-bank';

const form = ref({
    transfer_date: props.model?.transfer_date ?? new Date().toISOString().slice(0, 10),
    transfer_days: props.model?.transfer_days ?? 0,
    amount: props.model?.amount ?? 0,
    currency: props.model?.currency ?? '',
    cheque_number: props.model?.cheque_number ?? 0,
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

/* ── Cascading: Account Numbers, based on Bank + Account Type + Currency
   Same AJAX endpoint the old jQuery form used
   (MoneyReceivedController@getAccountNumbersForAccountType) — read-only,
   untouched, just called from Vue via axios instead. Single currency
   here (not sell/buy), so both sides key off the same `currency`. */
async function loadAccountNumbers(side) {
    const bankId = side === 'from' ? form.value.from_bank_id : form.value.to_bank_id;
    const accountTypeId = side === 'from' ? form.value.from_account_type_id : form.value.to_account_type_id;
    if (!bankId || !accountTypeId || !form.value.currency) return;
    const url = `/${props.locale}/${props.company.id}/money-received/get-account-numbers-based-on-account-type/${accountTypeId}/${form.value.currency}/${bankId}`;
    const { data } = await window.axios.get(url);
    const options = Object.values(data.data || {});
    if (side === 'from') {
        fromAccountNumberOptions.value = options;
        if (!options.includes(form.value.from_account_number)) form.value.from_account_number = options[0] || '';
    } else {
        toAccountNumberOptions.value = options;
        if (!options.includes(form.value.to_account_number)) form.value.to_account_number = options[0] || '';
    }
}
const fromAccountNumberOptions = ref(props.model?.from_account_number ? [props.model.from_account_number] : []);
const toAccountNumberOptions = ref(props.model?.to_account_number ? [props.model.to_account_number] : []);
watch(() => [form.value.from_bank_id, form.value.from_account_type_id, form.value.currency], () => loadAccountNumbers('from'));
watch(() => [form.value.to_bank_id, form.value.to_account_type_id, form.value.currency], () => loadAccountNumbers('to'));

/* ── Cascading: Branches, based on Currency ────────────────────────
   Same AJAX endpoint the old jQuery form used
   (BranchesController@getBranchesForCurrency) — read-only, untouched.
   Single currency here, so both From Branch and To Branch key off it. */
async function loadBranches(side) {
    if (!form.value.currency) return;
    const { data } = await window.axios.get(props.getBranchesForCurrencyUrl, { params: { currencyName: form.value.currency } });
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
watch(() => form.value.currency, () => {
    loadBranches('from');
    loadBranches('to');
});

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
            balanceDate: form.value.transfer_date,
            modelType: 'InternalMoneyTransfer',
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
watch(() => [form.value.from_bank_id, form.value.from_account_type_id, form.value.from_account_number, form.value.transfer_date], loadBankBalance);

async function loadCashSafeBalance() {
    if (!form.value.from_branch_id || !form.value.currency) return;
    const { data } = await window.axios.get(props.getCashSafeBalanceUrl, {
        params: {
            branchId: form.value.from_branch_id,
            currencyName: form.value.currency,
            balanceDate: form.value.transfer_date,
            modelType: 'InternalMoneyTransfer',
            modelId: props.model?.id || 0,
        },
    });
    cashSafeBalance.value = data.end_balance || 0;
}
watch(() => [form.value.from_branch_id, form.value.currency, form.value.transfer_date], loadCashSafeBalance);

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
    const payload = {
        ...form.value,
        type: props.type, // always the correct, route-driven type — see docblock re: the old Safe→Safe mislabeling bug
        company_id: props.company.id,
        ...(isEdit ? { updated_by: page.props.auth?.user?.id } : { created_by: page.props.auth?.user?.id }),
        // Fields not applicable to this page's fixed type are sent as
        // real null rather than left at '' — same reasoning as the
        // Buy Or Sell Currencies form fix (an empty string on an
        // integer column can silently coerce to 0, which is just as
        // capable of tripping a foreign key check as a true NULL
        // violation, just quieter).
        from_bank_id: isFromBank ? form.value.from_bank_id : null,
        from_account_type_id: isFromBank ? form.value.from_account_type_id : null,
        from_account_number: isFromBank ? form.value.from_account_number : null,
        from_branch_id: isFromSafe ? form.value.from_branch_id : null,
        to_bank_id: isToBank ? form.value.to_bank_id : null,
        to_account_type_id: isToBank ? form.value.to_account_type_id : null,
        to_account_number: isToBank ? form.value.to_account_number : null,
        to_branch_id: isToSafe ? form.value.to_branch_id : null,
        cheque_number: showChequeNumber ? form.value.cheque_number : 0,
        transfer_days: showTransferDays ? form.value.transfer_days : 0,
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
                    ← Back to Internal Money Transfer
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">
                {{ isEdit ? 'Edit' : 'Add' }} {{ allTypes[type] }}
            </h1>

            <div v-if="Object.keys(page.props.errors || {}).length" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
                Please fix the highlighted field(s) below before saving.
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card space-y-4">
                    <!-- Balance / Net Balance preview -->
                    <div v-if="isFromBank" class="flex justify-end">
                        <div class="cvr-form-grid-2 w-full max-w-md">
                            <div>
                                <label class="cvr-form-label">
                                    Balance <span v-if="bankBalance.balanceDate">[ {{ bankBalance.balanceDate }} ]</span>
                                </label>
                                <input :value="formatNumber(bankBalance.balance)" disabled class="cvr-input w-full px-3 py-2 rounded opacity-80" />
                            </div>
                            <div>
                                <label class="cvr-form-label">
                                    Net Balance <span v-if="bankBalance.netBalanceDate">[ {{ bankBalance.netBalanceDate }} ]</span>
                                </label>
                                <input :value="formatNumber(bankBalance.netBalance)" disabled class="cvr-input w-full px-3 py-2 rounded opacity-80" />
                            </div>
                        </div>
                    </div>
                    <div v-else-if="isFromSafe" class="flex justify-end">
                        <div class="w-full max-w-xs">
                            <label class="cvr-form-label">Balance</label>
                            <input :value="formatNumber(cashSafeBalance)" disabled class="cvr-input w-full px-3 py-2 rounded opacity-80" />
                        </div>
                    </div>

                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Transfer Date *</label>
                            <input v-model="form.transfer_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('transfer_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('transfer_date') }}</p>
                        </div>
                        <div v-if="showTransferDays">
                            <label class="cvr-form-label">Transfer Days</label>
                            <input v-model="form.transfer_days" type="number" step="1" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Transfer Amount *</label>
                            <input v-model="form.amount" type="number" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor('amount') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Currency *</label>
                            <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="(clabel, code) in currencies" :key="code" :value="code">{{ clabel }}</option>
                            </select>
                        </div>
                        <div v-if="showChequeNumber">
                            <label class="cvr-form-label">Cheque Number / Cash Withdrawal</label>
                            <input v-model="form.cheque_number" type="text" step="1" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>

                    <!-- FROM side: Bank fields — bank name gets double width (6:3:3) since bank names run long -->
                    <div v-if="isFromBank" class="cvr-form-grid-6-3-3">
                        <div>
                            <label class="cvr-form-label">From Bank *</label>
                            <select v-model="form.from_bank_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="bank in financialInstitutionBanks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">From Account Type *</label>
                            <select v-model="form.from_account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="at in accountTypes" :key="at.id" :value="at.id">{{ at.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">From Account Number *</label>
                            <select v-model="form.from_account_number" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="num in fromAccountNumberOptions" :key="num" :value="num">{{ num }}</option>
                            </select>
                            <p v-if="errorFor('from_account_number')" class="text-xs mt-1 cvr-num-red">{{ errorFor('from_account_number') }}</p>
                        </div>
                    </div>

                    <!-- FROM side: Branch (Safe) field -->
                    <div v-if="isFromSafe" class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">From Branch *</label>
                            <select v-model="form.from_branch_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="b in fromBranchOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- TO side: Bank fields — bank name gets double width (6:3:3) since bank names run long -->
                    <div v-if="isToBank" class="cvr-form-grid-6-3-3">
                        <div>
                            <label class="cvr-form-label">To Bank *</label>
                            <select v-model="form.to_bank_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="bank in financialInstitutionBanks" :key="bank.id" :value="bank.id">{{ bank.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">To Account Type *</label>
                            <select v-model="form.to_account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="at in accountTypes" :key="at.id" :value="at.id">{{ at.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">To Account Number *</label>
                            <select v-model="form.to_account_number" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="num in toAccountNumberOptions" :key="num" :value="num">{{ num }}</option>
                            </select>
                            <p v-if="errorFor('to_account_number')" class="text-xs mt-1 cvr-num-red">{{ errorFor('to_account_number') }}</p>
                        </div>
                    </div>

                    <!-- TO side: Branch (Safe) field -->
                    <div v-if="isToSafe" class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">To Branch *</label>
                            <select v-model="form.to_branch_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="b in toBranchOptions" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">User Comment</h2>
                    <textarea v-model="form.user_comment" rows="3" class="cvr-input w-full px-3 py-2 rounded"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">Cancel</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
