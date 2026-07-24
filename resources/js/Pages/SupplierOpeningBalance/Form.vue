<script setup>
/**
 * Suppliers Opening Balance — create/edit form. Mirror of
 * CustomerOpeningBalance/Form.vue with two field-name differences
 * confirmed from the original Blade: `paid_amount` (not
 * `received_amount`) and `purchases_order_number` (not
 * `sales_order_number`).
 *
 * Submits the exact same field/array names the untouched
 * SupplierOpeningBalancesController::store()/update() already expect:
 *   date, opening-balances[], advanced-opening-balances[]
 * Every row keeps its `id` (0 = new row).
 *
 * Contract Name (invoice rows) and the down-payment Contract picker
 * use the SAME real, still-wired AJAX routes as the Customers form,
 * called with is_lc=1 so ContractsController's existing
 * `forSupplier()` branch is used.
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
    currencies: Object,
    suppliers: { type: Array, default: () => [] }, // [{id, name}]
    contractsForSupplierUrl: String,
    salesOrdersForContractUrl: String,
});

const page = usePage();
const currencyOptions = Object.entries(props.currencies || {});
const date = ref(props.model?.date ?? new Date().toISOString().slice(0, 10));
let nextRowKey = 1;

/* ── Contract/sales-order lookups ────────────────────────────────── */
const contractsCache = ref({}); // supplierId -> { contractName: {id, currency, code} }
async function loadContracts(supplierId) {
    if (!supplierId) return {};
    if (contractsCache.value[supplierId]) return contractsCache.value[supplierId];
    const url = new URL(props.contractsForSupplierUrl, window.location.origin);
    url.searchParams.set('customerId', supplierId); // shared endpoint's param name
    url.searchParams.set('is_lc', '1'); // use forSupplier() branch, not forCustomer()
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    const data = await res.json();
    contractsCache.value[supplierId] = data.contracts || {};
    return contractsCache.value[supplierId];
}
const salesOrdersCache = ref({}); // contractId -> { id: so_number }
async function loadSalesOrders(contractId) {
    if (!contractId) return {};
    if (salesOrdersCache.value[contractId]) return salesOrdersCache.value[contractId];
    const url = new URL(props.salesOrdersForContractUrl, window.location.origin);
    url.searchParams.set('contractId', contractId);
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    const data = await res.json();
    salesOrdersCache.value[contractId] = data.purchase_orders || {};
    return salesOrdersCache.value[contractId];
}

/* ── Opening Invoices ─────────────────────────────────────────────── */
function blankInvoice() {
    return {
        _key: nextRowKey++, id: 0, partner_id: props.suppliers[0]?.id ?? '',
        invoice_number: '', contract_name: '', contract_code: '', contract_date: '',
        purchases_order_number: '', paid_amount: '', currency: 'EGP', exchange_rate: 1,
        invoice_due_date: new Date().toISOString().slice(0, 10),
        _contracts: {}, _salesOrders: {},
    };
}
const invoices = ref(
    (props.model?.invoices ?? []).map(r => ({ _key: nextRowKey++, _contracts: {}, _salesOrders: {}, ...r }))
);
function addInvoice() { invoices.value.push(blankInvoice()); }
function removeInvoice(key) { invoices.value = invoices.value.filter(r => r._key !== key); }
async function onInvoiceSupplierChange(row) {
    row.contract_name = '';
    row.contract_code = '';
    row.purchases_order_number = '';
    row._salesOrders = {};
    row._contracts = await loadContracts(row.partner_id);
}
async function onInvoiceContractChange(row) {
    const contract = row._contracts[row.contract_name];
    row.contract_code = contract?.code ?? '';
    row._salesOrders = contract ? await loadSalesOrders(contract.id) : {};
}
// Used only for the initial edit-mode pre-fill — loads the contract
// (and sales order) list without wiping out the row's already-saved values.
async function initInvoiceContracts(row) {
    row._contracts = await loadContracts(row.partner_id);
    const contract = row._contracts[row.contract_name];
    if (contract) {
        row._salesOrders = await loadSalesOrders(contract.id);
    }
}
invoices.value.forEach(row => { if (row.partner_id) initInvoiceContracts(row); });

/* ── Advanced Down Payments ───────────────────────────────────────── */
function blankDownPayment() {
    return {
        _key: nextRowKey++, id: 0, partner_id: props.suppliers[0]?.id ?? '',
        paid_amount: '', currency: 'EGP', exchange_rate: 1,
        down_payment_type: 'general', contract_id: '', _contracts: {},
    };
}
const downPayments = ref(
    (props.model?.downPayments ?? []).map(r => ({ _key: nextRowKey++, _contracts: {}, ...r }))
);
function addDownPayment() { downPayments.value.push(blankDownPayment()); }
function removeDownPayment(key) { downPayments.value = downPayments.value.filter(r => r._key !== key); }
async function onDownPaymentSupplierChange(row) {
    row._contracts = await loadContracts(row.partner_id);
}
downPayments.value.forEach(row => { if (row.partner_id) onDownPaymentSupplierChange(row); });

/* ── Submit ───────────────────────────────────────────────────────── */
const submitting = ref(false);
function stripInternal(rows) {
    return rows.map(({ _key, _contracts, _salesOrders, ...rest }) => rest);
}
function submit() {
    submitting.value = true;
    const payload = {
        date: date.value,
        'opening-balances': stripInternal(invoices.value),
        'advanced-opening-balances': stripInternal(downPayments.value),
    };
    const method = props.isEdit ? 'put' : 'post';
    router[method](props.submitUrl, payload, {
        onFinish: () => { submitting.value = false; },
    });
}

const errors = computed(() => page.props.errors || {});
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-back-link inline-flex items-center gap-1 text-xs cvr-text-muted mb-4">
                ← Back to Suppliers Opening Balance
            </Link>

            <div class="flex items-center gap-3 mb-6">
                <div class="cvr-avatar" style="width: 3rem; height: 3rem; font-size: 1.1rem;">🚚</div>
                <div>
                    <h1 class="text-xl font-semibold cvr-text-primary">
                        {{ isEdit ? 'Manage Suppliers Opening Balance' : 'Set Up Suppliers Opening Balance' }}
                    </h1>
                    <p class="text-sm cvr-text-muted">Opening invoices and advanced down payments</p>
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

            <!-- Opening Invoices -->
            <div class="cvr-card mb-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">🧾</span>
                        <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">Suppliers Opening Balance</h2>
                    </div>
                    <button type="button" @click="addInvoice" class="cvr-btn-secondary px-3 py-1.5 rounded-lg border text-sm">+ Add Row</button>
                </div>
                <p v-if="invoices.length === 0" class="text-sm cvr-text-muted py-4 text-center cvr-card-bg cvr-border border rounded-lg">No opening invoices. Click "+ Add Row" if there are any.</p>
                <div class="space-y-3">
                    <div v-for="row in invoices" :key="row._key" class="cvr-card-bg cvr-border border rounded-lg p-3">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="cvr-form-label">Supplier</label>
                                <select v-model="row.partner_id" @change="onInvoiceSupplierChange(row)" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Invoice No</label>
                                <input v-model="row.invoice_number" type="text" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Contract Name</label>
                                <select v-model="row.contract_name" @change="onInvoiceContractChange(row)" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option value="">—</option>
                                    <option v-for="name in Object.keys(row._contracts)" :key="name" :value="name">{{ name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Contract Code</label>
                                <input v-model="row.contract_code" type="text" readonly class="cvr-input w-full px-2 py-1.5 rounded text-sm opacity-70 cursor-not-allowed" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Contract Date</label>
                                <input v-model="row.contract_date" type="date" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Purchase Order Number</label>
                                <select v-model="row.purchases_order_number" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option value="">—</option>
                                    <option v-for="(poNumber, poId) in row._salesOrders" :key="poId" :value="poNumber">{{ poNumber }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Amount</label>
                                <input v-model="row.paid_amount" type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Currency</label>
                                <select v-model="row.currency" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option v-for="[code, label] in currencyOptions" :key="code" :value="code">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Exchange Rate</label>
                                <input v-model="row.exchange_rate" type="number" step="0.0001" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Due Date</label>
                                <input v-model="row.invoice_due_date" type="date" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                        </div>
                        <div class="flex justify-end mt-3">
                            <button type="button" @click="removeInvoice(row._key)" class="cvr-btn-remove-row">🗑 Remove Row</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Down Payments -->
            <div class="cvr-card mb-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">💸</span>
                        <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">Suppliers Advanced Opening Balance</h2>
                    </div>
                    <button type="button" @click="addDownPayment" class="cvr-btn-secondary px-3 py-1.5 rounded-lg border text-sm">+ Add Row</button>
                </div>
                <p v-if="downPayments.length === 0" class="text-sm cvr-text-muted py-4 text-center cvr-card-bg cvr-border border rounded-lg">No advanced down payments. Click "+ Add Row" if there are any.</p>
                <div class="space-y-3">
                    <div v-for="row in downPayments" :key="row._key" class="cvr-card-bg cvr-border border rounded-lg p-3">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="cvr-form-label">Supplier</label>
                                <select v-model="row.partner_id" @change="onDownPaymentSupplierChange(row)" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Amount</label>
                                <input v-model="row.paid_amount" type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Currency</label>
                                <select v-model="row.currency" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option v-for="[code, label] in currencyOptions" :key="code" :value="code">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">Exchange Rate</label>
                                <input v-model="row.exchange_rate" type="number" step="0.0001" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Type</label>
                                <select v-model="row.down_payment_type" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option value="general">General</option>
                                    <option value="over_contract">Over Contract</option>
                                </select>
                            </div>
                            <div v-if="row.down_payment_type === 'over_contract'">
                                <label class="cvr-form-label">Contract</label>
                                <select v-model="row.contract_id" class="cvr-input w-full px-2 py-1.5 rounded text-sm">
                                    <option value="">—</option>
                                    <option v-for="(c, name) in row._contracts" :key="name" :value="c.id">{{ name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end mt-3">
                            <button type="button" @click="removeDownPayment(row._key)" class="cvr-btn-remove-row">🗑 Remove Row</button>
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
