<script setup>
/**
 * Cash in Safe & Cheque Balance — create/edit form.
 *
 * Submits the exact same field/array names the untouched
 * OpeningBalancesController::store()/update() already expect:
 *   date, cash-in-safe[], cheque[], cheque-under-collection[], payable_cheque[]
 * Every row keeps its `id` (0 = new row) so the server's diff-by-id
 * update logic works unmodified.
 */
import { ref, computed } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    submitUrl: String,
    backUrl: String,
    isEdit: Boolean,
    model: { type: Object, default: null },
    currencies: Object, // { EGP: 'EGP', USD: 'USD', ... }
    branches: { type: Array, default: () => [] },      // [{id, name, currency}]
    customers: { type: Array, default: () => [] },     // [{id, name}]
    suppliers: { type: Array, default: () => [] },      // [{id, name}]
    financialInstitutionBanks: { type: Array, default: () => [] }, // [{id, name}] - our own bank accounts
    draweeBanks: { type: Array, default: () => [] },     // [{id, name}] - full bank list (cheque issuer)
    accountTypes: { type: Array, default: () => [] },    // [{id, name}]
    cashAccounts: { type: Array, default: () => [] },    // [{account_type_id, financial_institution_id, account_number, currency}]
});

const page = usePage();

const currencyOptions = Object.entries(props.currencies || {});

/* ── Date ─────────────────────────────────────────────────────────── */
const date = ref(props.model?.date ?? new Date().toISOString().slice(0, 10));

/* ── Row id counters (client-side only, for :key) ───────────────────
   Real ids come from the server (0 for new rows, matching the
   original's hidden id=0 input). These are just Vue :key helpers. */
let nextRowKey = 1;

/* ── Cash In Safe ─────────────────────────────────────────────────── */
function blankCashInSafe() {
    return { _key: nextRowKey++, id: 0, received_branch_id: props.branches[0]?.id ?? '', received_amount: '', currency: 'EGP', exchange_rate: 1 };
}
const cashInSafe = ref(
    (props.model?.cashInSafe ?? []).map(r => ({ _key: nextRowKey++, ...r }))
);
function addCashInSafe() { cashInSafe.value.push(blankCashInSafe()); }
function removeCashInSafe(key) {
    cashInSafe.value = cashInSafe.value.filter(r => r._key !== key);
}

/* ── Cheques In Safe ──────────────────────────────────────────────── */
function blankCheque() {
    return { _key: nextRowKey++, id: 0, customer_id: props.customers[0]?.id ?? '', currency: 'EGP', due_date: new Date().toISOString().slice(0, 10), drawee_bank_id: props.draweeBanks[0]?.id ?? '', received_amount: '', cheque_number: '', exchange_rate: 1 };
}
const cheque = ref(
    (props.model?.cheque ?? []).map(r => ({ _key: nextRowKey++, ...r }))
);
function addCheque() { cheque.value.push(blankCheque()); }
function removeCheque(key) {
    cheque.value = cheque.value.filter(r => r._key !== key);
}

/* ── Cheques Under Collection ─────────────────────────────────────── */
function blankChequeUnderCollection() {
    return {
        _key: nextRowKey++, id: 0, customer_id: props.customers[0]?.id ?? '', currency: 'EGP',
        due_date: new Date().toISOString().slice(0, 10), drawee_bank_id: props.draweeBanks[0]?.id ?? '',
        received_amount: '', cheque_number: '', exchange_rate: 1,
        deposit_date: new Date().toISOString().slice(0, 10),
        drawl_bank_id: props.financialInstitutionBanks[0]?.id ?? '',
        account_type: '', account_number: '', clearance_days: 0,
    };
}
const chequeUnderCollection = ref(
    (props.model?.chequeUnderCollection ?? []).map(r => ({ _key: nextRowKey++, ...r }))
);
function addChequeUnderCollection() { chequeUnderCollection.value.push(blankChequeUnderCollection()); }
function removeChequeUnderCollection(key) {
    chequeUnderCollection.value = chequeUnderCollection.value.filter(r => r._key !== key);
}
// Account Number options depend on the row's chosen bank + account type —
// same cascading behavior as the original, rebuilt from a fetched-up-front
// list since there's no traceable AJAX route for the original JS version.
function accountNumberOptions(financialInstitutionId, accountTypeId) {
    if (!financialInstitutionId || !accountTypeId) return [];
    return props.cashAccounts.filter(a =>
        String(a.financial_institution_id) === String(financialInstitutionId) &&
        String(a.account_type_id) === String(accountTypeId)
    );
}

/* ── Payable Cheques ──────────────────────────────────────────────── */
function blankPayableCheque() {
    return {
        _key: nextRowKey++, id: 0, supplier_id: props.suppliers[0]?.id ?? '', currency: 'EGP',
        due_date: new Date().toISOString().slice(0, 10), paid_amount: '', cheque_number: '', exchange_rate: 1,
        delivery_bank_id: props.financialInstitutionBanks[0]?.id ?? '', account_type: '', account_number: '',
    };
}
const payableCheque = ref(
    (props.model?.payableCheque ?? []).map(r => ({ _key: nextRowKey++, ...r }))
);
function addPayableCheque() { payableCheque.value.push(blankPayableCheque()); }
function removePayableCheque(key) {
    payableCheque.value = payableCheque.value.filter(r => r._key !== key);
}

/* ── Submit ───────────────────────────────────────────────────────── */
const submitting = ref(false);
function stripKey(rows) {
    return rows.map(({ _key, ...rest }) => rest);
}
function submit() {
    submitting.value = true;
    const payload = {
        date: date.value,
        'cash-in-safe': stripKey(cashInSafe.value),
        cheque: stripKey(cheque.value),
        'cheque-under-collection': stripKey(chequeUnderCollection.value),
        payable_cheque: stripKey(payableCheque.value),
    };
    const method = props.isEdit ? 'put' : 'post';
    router[method](props.submitUrl, payload, {
        onFinish: () => { submitting.value = false; },
    });
}

const errors = computed(() => page.props.errors || {});
function fieldError(group, index, field) {
    return errors.value[`${group}.${index}.${field}`];
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-back-link inline-flex items-center gap-1 text-xs cvr-text-muted mb-4">
                ← Back to Cash in Safe &amp; Cheque Balance
            </Link>

            <div class="flex items-center gap-3 mb-6">
                <div class="cvr-avatar" style="width: 3rem; height: 3rem; font-size: 1.1rem;">🗄️</div>
                <div>
                    <h1 class="text-xl font-semibold cvr-text-primary">
                        {{ isEdit ? 'Manage Opening Balance' : 'Set Up Opening Balance' }}
                    </h1>
                    <p class="text-sm cvr-text-muted">Cash in safe, cheques in safe, cheques under collection, and payable cheques</p>
                </div>
            </div>

            <div
                v-if="Object.keys(errors).length"
                class="mb-5 px-4 py-3 rounded-lg text-sm"
                style="background: var(--cvr-danger-bg); border: 1px solid var(--cvr-danger-border); color: var(--cvr-danger-text);"
            >
                <p class="font-medium mb-1">⚠ Please fix the following:</p>
                <p v-for="(msg, field) in errors" :key="field">{{ msg }}</p>
            </div>

            <!-- Date -->
            <div class="cvr-card mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-base">📅</span>
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">Opening Balance Date</h2>
                </div>
                <div class="max-w-xs">
                    <input v-model="date" type="date" required class="cvr-input w-full px-3 py-2 rounded-lg text-sm" />
                </div>
            </div>

            <!-- Cash In Safe -->
            <div class="cvr-card mb-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">🗄️</span>
                        <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">Cash In Safe Opening Balance</h2>
                    </div>
                    <button type="button" @click="addCashInSafe" class="cvr-btn-secondary px-3 py-1.5 rounded-lg border text-sm">+ Add Row</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-left">Branch</th>
                                <th class="px-3 py-2 text-left">Amount</th>
                                <th class="px-3 py-2 text-left">Currency</th>
                                <th class="px-3 py-2 text-left">Exchange Rate</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="cashInSafe.length === 0">
                                <td colspan="5" class="px-4 py-6 text-center cvr-text-muted">No cash in safe rows. Click "+ Add Row" if there are any.</td>
                            </tr>
                            <tr v-for="(row, i) in cashInSafe" :key="row._key" class="cvr-table-row">
                                <td class="px-3 py-2">
                                    <select v-model="row.received_branch_id" class="cvr-input px-2 py-1.5 rounded text-sm w-48">
                                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="row.received_amount" type="number" step="0.01" class="cvr-input px-2 py-1.5 rounded text-sm w-32" />
                                    <p v-if="fieldError('cash-in-safe', i, 'received_amount')" class="text-xs mt-0.5" style="color: var(--cvr-danger-text);">{{ fieldError('cash-in-safe', i, 'received_amount') }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    <select v-model="row.currency" class="cvr-input px-2 py-1.5 rounded text-sm w-24">
                                        <option v-for="[code, label] in currencyOptions" :key="code" :value="code">{{ label }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="row.exchange_rate" type="number" step="0.0001" class="cvr-input px-2 py-1.5 rounded text-sm w-24" />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" @click="removeCashInSafe(row._key)" class="cvr-action-btn cvr-action-btn-danger" title="Remove">🗑</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cheques In Safe -->
            <div class="cvr-card mb-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">📄</span>
                        <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">Cheque In Safe Opening Balance</h2>
                    </div>
                    <button type="button" @click="addCheque" class="cvr-btn-secondary px-3 py-1.5 rounded-lg border text-sm">+ Add Row</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-left">Customer</th>
                                <th class="px-3 py-2 text-left">Currency</th>
                                <th class="px-3 py-2 text-left">Due Date</th>
                                <th class="px-3 py-2 text-left">Drawee Bank</th>
                                <th class="px-3 py-2 text-left">Amount</th>
                                <th class="px-3 py-2 text-left">Cheque #</th>
                                <th class="px-3 py-2 text-left">Exchange Rate</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="cheque.length === 0">
                                <td colspan="8" class="px-4 py-6 text-center cvr-text-muted">No cheques in safe. Click "+ Add Row" if there are any.</td>
                            </tr>
                            <tr v-for="(row, i) in cheque" :key="row._key" class="cvr-table-row">
                                <td class="px-3 py-2">
                                    <select v-model="row.customer_id" class="cvr-input px-2 py-1.5 rounded text-sm w-48">
                                        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                    <p v-if="fieldError('cheque', i, 'customer_id')" class="text-xs mt-0.5" style="color: var(--cvr-danger-text);">{{ fieldError('cheque', i, 'customer_id') }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    <select v-model="row.currency" class="cvr-input px-2 py-1.5 rounded text-sm w-24">
                                        <option v-for="[code, label] in currencyOptions" :key="code" :value="code">{{ label }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="row.due_date" type="date" class="cvr-input px-2 py-1.5 rounded text-sm w-40" />
                                </td>
                                <td class="px-3 py-2">
                                    <select v-model="row.drawee_bank_id" class="cvr-input px-2 py-1.5 rounded text-sm w-48">
                                        <option v-for="b in draweeBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="row.received_amount" type="number" step="0.01" class="cvr-input px-2 py-1.5 rounded text-sm w-32" />
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="row.cheque_number" type="text" class="cvr-input px-2 py-1.5 rounded text-sm w-32" />
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="row.exchange_rate" type="number" step="0.0001" class="cvr-input px-2 py-1.5 rounded text-sm w-24" />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" @click="removeCheque(row._key)" class="cvr-action-btn cvr-action-btn-danger" title="Remove">🗑</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cheques Under Collection -->
            <div class="cvr-card mb-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">⏳</span>
                        <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">Cheque Under Collection Opening Balance</h2>
                    </div>
                    <button type="button" @click="addChequeUnderCollection" class="cvr-btn-secondary px-3 py-1.5 rounded-lg border text-sm">+ Add Row</button>
                </div>
                <p v-if="chequeUnderCollection.length === 0" class="text-sm cvr-text-muted py-4 text-center cvr-card-bg cvr-border border rounded-lg">No cheques under collection. Click "+ Add Row" if there are any.</p>
                <div class="space-y-3">
                    <div v-for="(row, i) in chequeUnderCollection" :key="row._key" class="cvr-card-bg cvr-border border rounded-lg p-3">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="cvr-form-label">Customer</label>
                                <select v-model="row.customer_id" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <p v-if="fieldError('cheque-under-collection', i, 'customer_id')" class="text-xs mt-0.5" style="color: var(--cvr-danger-text);">{{ fieldError('cheque-under-collection', i, 'customer_id') }}</p>
                            </div>
                            <div>
                                <label class="cvr-form-label">Currency</label>
                                <select v-model="row.currency" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option v-for="[code, label] in currencyOptions" :key="code" :value="code">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Due Date</label>
                                <input v-model="row.due_date" type="date" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Drawee Bank</label>
                                <select v-model="row.drawee_bank_id" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option v-for="b in draweeBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Amount</label>
                                <input v-model="row.received_amount" type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Cheque #</label>
                                <input v-model="row.cheque_number" type="text" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Exchange Rate</label>
                                <input v-model="row.exchange_rate" type="number" step="0.0001" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Deposit Date</label>
                                <input v-model="row.deposit_date" type="date" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Drawal Bank</label>
                                <select v-model="row.drawl_bank_id" class="cvr-input w-full px-2 py-1.5 rounded text-sm" @change="row.account_number = ''">
                                    <option v-for="b in financialInstitutionBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Account Type</label>
                                <select v-model="row.account_type" class="cvr-input w-full px-2 py-1.5 rounded text-sm" @change="row.account_number = ''">
                                    <option value="">Select</option>
                                    <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Account Number</label>
                                <select v-model="row.account_number" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option value="">Select</option>
                                    <option v-for="a in accountNumberOptions(row.drawl_bank_id, row.account_type)" :key="a.account_number" :value="a.account_number">{{ a.account_number }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Clearance Days</label>
                                <input v-model="row.clearance_days" type="number" min="0" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                        </div>
                        <div class="flex justify-end mt-3">
                            <button type="button" @click="removeChequeUnderCollection(row._key)" class="cvr-btn-remove-row">🗑 Remove Row</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payable Cheques -->
            <div class="cvr-card mb-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">💸</span>
                        <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">Payable Cheques Opening Balance</h2>
                    </div>
                    <button type="button" @click="addPayableCheque" class="cvr-btn-secondary px-3 py-1.5 rounded-lg border text-sm">+ Add Row</button>
                </div>
                <p v-if="payableCheque.length === 0" class="text-sm cvr-text-muted py-4 text-center cvr-card-bg cvr-border border rounded-lg">No payable cheques. Click "+ Add Row" if there are any.</p>
                <div class="space-y-3">
                    <div v-for="(row, i) in payableCheque" :key="row._key" class="cvr-card-bg cvr-border border rounded-lg p-3">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="cvr-form-label">Supplier</label>
                                <select v-model="row.supplier_id" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <p v-if="fieldError('payable_cheque', i, 'supplier_id')" class="text-xs mt-0.5" style="color: var(--cvr-danger-text);">{{ fieldError('payable_cheque', i, 'supplier_id') }}</p>
                            </div>
                            <div>
                                <label class="cvr-form-label">Currency</label>
                                <select v-model="row.currency" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option v-for="[code, label] in currencyOptions" :key="code" :value="code">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Due Date</label>
                                <input v-model="row.due_date" type="date" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Amount</label>
                                <input v-model="row.paid_amount" type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Cheque #</label>
                                <input v-model="row.cheque_number" type="text" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Exchange Rate</label>
                                <input v-model="row.exchange_rate" type="number" step="0.0001" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Payment Bank</label>
                                <select v-model="row.delivery_bank_id" class="cvr-input w-full px-2 py-1.5 rounded text-sm" @change="row.account_number = ''">
                                    <option v-for="b in financialInstitutionBanks" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Account Type</label>
                                <select v-model="row.account_type" class="cvr-input w-full px-2 py-1.5 rounded text-sm" @change="row.account_number = ''">
                                    <option value="">Select</option>
                                    <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Account Number</label>
                                <select v-model="row.account_number" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option value="">Select</option>
                                    <option v-for="a in accountNumberOptions(row.delivery_bank_id, row.account_type)" :key="a.account_number" :value="a.account_number">{{ a.account_number }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end mt-3">
                            <button type="button" @click="removePayableCheque(row._key)" class="cvr-btn-remove-row">🗑 Remove Row</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pb-8">
                <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded-lg border text-sm">Cancel</Link>
                <button type="button" @click="submit" :disabled="submitting" class="cvr-btn-copper px-5 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                    {{ submitting ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.cvr-back-link { transition: var(--cvr-transition); }
.cvr-back-link:hover { color: var(--cvr-text-primary); }
</style>
