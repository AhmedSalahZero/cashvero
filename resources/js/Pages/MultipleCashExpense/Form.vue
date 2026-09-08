<script setup>
/**
 * Multiple Cash Expenses — one payment covering several expense lines.
 *
 * The repeater holds only what differs per line (category, expense name,
 * amount). Everything else — date, payment method, bank/safe, currency —
 * is entered once for the whole payment, and the Total under the repeater
 * is what actually leaves the safe or the bank.
 *
 * Each line still lands on the statement on its own (the server writes one
 * statement row per line), so the total here is a sum for the person
 * filling the form, never a single lumped movement.
 */
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { mapAccountNumberOptions, accountNumberOption, hasAccountNumber } from '@/composables/useAccountNumberOptions';

const props = defineProps({
    company: Object,
    mode: String,
    types: Object,
    currencies: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    categoryNames: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    financialInstitutionBanks: { type: Array, default: () => [] },
    accountTypes: { type: Array, default: () => [] },
    clientsWithContracts: { type: Array, default: () => [] },
    locale: { type: String, default: 'en' },
    getContractsForCustomerUrl: String,
    model: { type: Object, default: null },
    submitUrl: String,
    backUrl: String,
});

const emptyItem = () => ({ category_id: '', cash_expense_category_name_id: '', paid_amount: 0 });
/* Same allocation repeater as the single Cash Expense form: pick a
   customer, their contracts load from the same endpoint, Contract Code
   and Amount fill themselves in. The only addition is the expense
   selector, so the allocation says which line it belongs to. */
const emptyAllocation = () => ({
    item_index: '',
    partner_id: '',
    contract_id: '',
    contract_code: '',
    contract_amount: null,
    contract_currency: '',
    amount: 0,
});

const form = useForm({
    type: props.model?.type || 'cash_payment',
    payment_date: props.model?.payment_date || '',
    currency: props.model?.currency || props.company?.mainFunctionalCurrency || '',
    exchange_rate: props.model?.exchange_rate || 1,
    user_comment: props.model?.user_comment || '',
    delivery_bank_id: props.model?.delivery_bank_id || '',
    account_type: props.model?.account_type || '',
    account_number: props.model?.account_number || '',
    delivery_branch_id: props.model?.delivery_branch_id || '',
    receipt_number: props.model?.receipt_number || '',
    cheque_number: props.model?.cheque_number || '',
    due_date: props.model?.due_date || '',
    items: props.model?.items?.length ? props.model.items.map(i => ({ ...i })) : [emptyItem()],
    allocations: props.model?.allocations?.length ? props.model.allocations.map(a => ({ ...a })) : [],
});

/* Expense names are filtered by the category chosen on that same line. */
function namesFor(categoryId) {
    return props.categoryNames.filter(n => String(n.category_id) === String(categoryId));
}

function addItem() {
    form.items.push(emptyItem());
}

function removeItem(index) {
    form.items.splice(index, 1);
    /* An allocation points at a line by position, so removing a line has
       to take its allocations with it and shift the ones after it — or
       they would silently attach to the wrong expense. */
    form.allocations = form.allocations
        .filter(a => Number(a.item_index) !== index)
        .map(a => (Number(a.item_index) > index ? { ...a, item_index: Number(a.item_index) - 1 } : a));
}

const contractsByPartner = ref({}); // partnerId -> [{id, name, code, amount, currency}]

async function loadContractsForPartner(partnerId) {
    if (!partnerId || contractsByPartner.value[partnerId]) return;
    const { data } = await window.axios.get(props.getContractsForCustomerUrl, {
        params: { partnerId, model: 'CashExpense', inEditMode: props.mode === 'edit' ? 1 : 0 },
    });
    contractsByPartner.value = { ...contractsByPartner.value, [partnerId]: data.contracts || [] };
}

/* A saved allocation opens with its contract list already loaded, so the
   Contract dropdown is not empty when editing. */
form.allocations.forEach(row => { if (row.partner_id) loadContractsForPartner(row.partner_id); });

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

function formatNumber(value) {
    if (value === null || value === undefined || value === '') return '';
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function addAllocation() {
    form.allocations.push(emptyAllocation());
}

function removeAllocation(index) {
    form.allocations.splice(index, 1);
}

const total = computed(() =>
    form.items.reduce((sum, item) => sum + (Number(item.paid_amount) || 0), 0)
);

const formattedTotal = computed(() =>
    total.value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
);

/* The label shown in the allocation's expense picker — the same line the
   user typed above, so they can tell the rows apart. */
function itemLabel(item, index) {
    const name = props.categoryNames.find(n => String(n.id) === String(item.cash_expense_category_name_id));
    return `${index + 1}. ${name ? name.name : '—'}`;
}

/* ── Cascading: Account Numbers, from Bank + Account Type + Currency ──
   The account number is a choice, not free text — it must be one of the
   accounts that actually exist on that bank for that type and currency.
   Same read-only endpoint the single Cash Expense form calls. */
const accountNumberOptions = ref(accountNumberOption(props.model?.account_number));

async function loadAccountNumbers() {
    if (!form.delivery_bank_id || !form.account_type || !form.currency) {
        accountNumberOptions.value = [];
        return;
    }

    const url = `/${props.locale}/${props.company.id}/cash-expense/get-account-numbers-based-on-account-type/${form.account_type}/${form.currency}/${form.delivery_bank_id}`;
    const { data } = await window.axios.get(url);
    const options = mapAccountNumberOptions(data.data);
    accountNumberOptions.value = options;

    if (!hasAccountNumber(options, form.account_number)) {
        form.account_number = options[0]?.value || '';
    }
}

watch(() => [form.delivery_bank_id, form.account_type, form.currency], loadAccountNumbers);

if (props.mode === 'edit') {
    loadAccountNumbers();
}

const isCash = computed(() => form.type === 'cash_payment');
const isCheque = computed(() => form.type === 'payable_cheque');

function submit() {
    if (props.mode === 'edit') {
        form.put(props.submitUrl);
    } else {
        form.post(props.submitUrl);
    }
}
</script>

<template>
    <AppLayout :title="$t('Multiple Cash Expenses')">
        <div class="p-4 md:p-6">
            <h1 class="text-lg font-medium cvr-text-primary mb-4">
                {{ mode === 'edit' ? $t('Edit') : $t('Create') }} — {{ $t('Multiple Cash Expenses') }}
            </h1>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Everything that applies to the whole payment -->
                <div class="cvr-card-bg cvr-border border rounded-lg p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Type') }}</label>
                        <select v-model="form.type" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <p v-if="form.errors.type" class="text-xs cvr-text-danger mt-1">{{ form.errors.type }}</p>
                    </div>

                    <div>
                        <label class="cvr-form-label">{{ $t('Payment Date') }}</label>
                        <input v-model="form.payment_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="form.errors.payment_date" class="text-xs cvr-text-danger mt-1">{{ form.errors.payment_date }}</p>
                    </div>

                    <div>
                        <label class="cvr-form-label">{{ $t('Currency') }}</label>
                        <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                        </select>
                        <p v-if="form.errors.currency" class="text-xs cvr-text-danger mt-1">{{ form.errors.currency }}</p>
                    </div>

                    <div v-if="isCash">
                        <label class="cvr-form-label">{{ $t('Branch') }}</label>
                        <select v-model="form.delivery_branch_id" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>

                    <template v-if="!isCash">
                        <div>
                            <label class="cvr-form-label">{{ $t('Bank') }}</label>
                            <select v-model="form.delivery_bank_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="b in financialInstitutionBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Type') }}</label>
                            <select v-model="form.account_type" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="a in accountTypes" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Number') }}</label>
                            <select v-model="form.account_number" class="cvr-input w-full px-3 py-2 rounded" :disabled="!accountNumberOptions.length">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="o in accountNumberOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                            <p v-if="form.errors.account_number" class="text-xs cvr-text-danger mt-1">{{ form.errors.account_number }}</p>
                        </div>
                    </template>

                    <div v-if="isCheque">
                        <label class="cvr-form-label">{{ $t('Cheque Number') }}</label>
                        <input v-model="form.cheque_number" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div v-if="isCheque">
                        <label class="cvr-form-label">{{ $t('Due Date') }}</label>
                        <input v-model="form.due_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>

                    <!--
                        Receipt Number belongs to a cash payment, exactly as on
                        the single Cash Expense form.
                    -->
                    <div v-if="isCash">
                        <label class="cvr-form-label">{{ $t('Receipt Number') }}</label>
                        <input v-model="form.receipt_number" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        <p v-if="form.errors.receipt_number" class="text-xs cvr-text-danger mt-1">{{ form.errors.receipt_number }}</p>
                    </div>

                    <div class="md:col-span-3">
                        <label class="cvr-form-label">{{ $t('User Comment') }}</label>
                        <textarea v-model="form.user_comment" rows="2" class="cvr-input w-full px-3 py-2 rounded"></textarea>
                    </div>
                </div>

                <!-- The repeater: only what differs per expense line -->
                <div class="cvr-card-bg cvr-border border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-medium cvr-text-primary">{{ $t('Expenses') }}</h2>
                        <button type="button" @click="addItem" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                            + {{ $t('Add') }}
                        </button>
                    </div>

                    <p v-if="form.errors.items" class="text-xs cvr-text-danger mb-2">{{ form.errors.items }}</p>

                    <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3 items-end">
                        <div>
                            <label class="cvr-form-label">{{ $t('Expense Category') }}</label>
                            <select v-model="item.category_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Expense Name') }}</label>
                            <select v-model="item.cash_expense_category_name_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="n in namesFor(item.category_id)" :key="n.id" :value="n.id">{{ n.name }}</option>
                            </select>
                            <p v-if="form.errors[`items.${index}.cash_expense_category_name_id`]" class="text-xs cvr-text-danger mt-1">
                                {{ form.errors[`items.${index}.cash_expense_category_name_id`] }}
                            </p>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Paid Amount') }} *</label>
                            <input v-model="item.paid_amount" type="number" step="0.01" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="form.errors[`items.${index}.paid_amount`]" class="text-xs cvr-text-danger mt-1">
                                {{ form.errors[`items.${index}.paid_amount`] }}
                            </p>
                        </div>

                        <div>
                            <button
                                type="button"
                                @click="removeItem(index)"
                                :disabled="form.items.length === 1"
                                class="cvr-action-btn"
                                :title="$t('Delete')"
                            >🗑️</button>
                        </div>
                    </div>

                    <!-- This total is what actually leaves the safe or the bank -->
                    <div class="flex items-center justify-end gap-3 border-t cvr-border pt-3 mt-2">
                        <span class="cvr-text-muted">{{ $t('Total') }}:</span>
                        <span class="font-medium cvr-text-primary">{{ formattedTotal }} {{ form.currency }}</span>
                    </div>
                </div>

                <!--
                    Allocating With Customer Contracts — the same repeater as
                    the single Cash Expense form (customer → contract, with
                    Contract Code and Amount filled in from the chosen
                    contract). The one addition is the Expense selector, so
                    each allocation says which line above it belongs to.
                -->
                <div class="cvr-card-bg cvr-border border rounded-lg p-4">
                    <h2 class="font-medium cvr-text-primary mb-3">{{ $t('Allocating With Customer Contracts') }}</h2>

                    <div v-if="form.allocations.length" class="overflow-auto">
                        <table class="min-w-full text-xs mb-3">
                            <thead class="cvr-table-head">
                                <tr>
                                    <th class="px-2 py-2 text-start">{{ $t('Expense Name') }}</th>
                                    <th class="px-2 py-2 text-start">{{ $t('Customer') }}</th>
                                    <th class="px-2 py-2 text-start">{{ $t('Contract Name') }}</th>
                                    <th class="px-2 py-2 text-start">{{ $t('Contract Code') }}</th>
                                    <th class="px-2 py-2 text-start">{{ $t('Contract Amount') }}</th>
                                    <th class="px-2 py-2 text-start">{{ $t('Allocate Amount') }}</th>
                                    <th class="px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in form.allocations" :key="index" class="cvr-table-row">
                                    <!-- which expense line this allocation belongs to -->
                                    <td class="px-2 py-2">
                                        <select v-model="row.item_index" class="cvr-input px-2 py-1 rounded w-full">
                                            <option value="">{{ $t('Select') }}</option>
                                            <option v-for="(item, i) in form.items" :key="i" :value="i">{{ itemLabel(item, i) }}</option>
                                        </select>
                                        <p v-if="form.errors[`allocations.${index}.item_index`]" class="text-xs cvr-text-danger mt-1">
                                            {{ form.errors[`allocations.${index}.item_index`] }}
                                        </p>
                                    </td>
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
                                        <button type="button" @click="removeAllocation(index)" class="cvr-btn-danger px-2 py-1 rounded border text-xs">✕</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-xs cvr-text-muted mb-3">
                        {{ $t('No contract allocations yet — optional, only needed if this expense should be settled against a customer\'s contract.') }}
                    </p>

                    <button type="button" @click="addAllocation" class="cvr-btn-secondary px-3 py-1.5 rounded border text-xs">
                        {{ $t('+ Add Allocation') }}
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" :disabled="form.processing" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ $t('Save') }}
                    </button>
                    <a :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Back') }}</a>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
