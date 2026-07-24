<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

/*
 * Contracts/Form.vue
 * ------------------------------------------------------------------
 * ONE shared page for Add + Edit, and for Customer + Supplier —
 * mirroring both this project's `mode` convention (see
 * TimeOfDeposits/Form.vue) and the controller's own `type` convention.
 *
 * Each contract has one or more Sales Orders (Customer) / Purchase
 * Orders (Supplier) under it. Each of those, in turn, has up to 5
 * fixed "execution phases" (percentage of the order's amount, a start
 * date, an end date, and a collection-days figure) — edited through a
 * per-row "Execution Details" modal, matching the old page's
 * x-modal.execution-percentage popup exactly rather than cramming 5
 * phases worth of fields into the visible table.
 */

const props = defineProps({
    company: Object,
    type: String, // 'Customer' | 'Supplier'
    mode: String, // 'create' | 'edit'
    formTitle: String,
    clients: Array, // [{id, name}]
    currencies: Object, // {code: label}
    salesOrderOrPurchaseOrderInformationText: String,
    salesOrderOrPurchaseNumberText: String, // e.g. "Sales Order Number"
    salesOrderOrPurchaseNoText: String, // 'so_number' | 'po_number'
    salesOrderOrPurchaseOrderRelationName: String, // 'salesOrders' | 'purchasesOrders'
    model: Object, // null in create mode
    submitUrl: String,
    backUrl: String,
    generateCodeUrl: String,
    addNewPartnerUrl: String,
});

const page = usePage();
const isEdit = props.mode === 'edit';

function emptyPhase() {
    return { percentage: 0, start_date: '', end_date: '', collection_days: 0 };
}
function emptyOrder() {
    return { id: 0, number: '', amount: 0, phases: Array.from({ length: 5 }, emptyPhase) };
}

const form = ref({
    name: props.model?.name ?? '',
    code: props.model?.code ?? '',
    partner_id: props.model?.partner_id ?? '',
    start_date: props.model?.start_date ?? '',
    end_date: props.model?.end_date ?? '',
    amount: props.model?.amount ?? 0,
    currency: props.model?.currency ?? 'EGP',
    exchange_rate: props.model?.exchange_rate ?? 1,
});

const orders = ref(
    props.model?.orders?.length
        ? props.model.orders.map(o => ({
            id: o.id,
            number: o.number,
            amount: o.amount,
            phases: o.phases.map(p => ({ ...p })),
        }))
        : [emptyOrder()]
);

function addOrderRow() {
    orders.value.push(emptyOrder());
}
function removeOrderRow(index) {
    if (orders.value.length === 1) return; // matches old repeater's isFirstItemUndeletable
    orders.value.splice(index, 1);
}

/* Orders total, shown next to Contract Amount so the required
   "totals must match" rule (TwoNumericsAreEqual, enforced server-side
   in StoreContractRequest) is visible before submitting, not just
   after a failed save. */
const ordersTotal = computed(() =>
    orders.value.reduce((sum, o) => sum + (Number(o.amount) || 0), 0)
);
const totalsMismatch = computed(() =>
    Math.round(ordersTotal.value * 100) !== Math.round((Number(form.value.amount) || 0) * 100)
);

/* ── Add New Customer/Supplier modal ─────────────────────────────
   Matches the old page's inline "Add New" button + modal, posting to
   the same existing AddNewCustomerController endpoint. */
const localClients = ref([...props.clients]);
const showAddPartnerModal = ref(false);
const newPartnerName = ref('');
const addingPartner = ref(false);
async function submitNewPartner() {
    if (!newPartnerName.value.trim()) return;
    addingPartner.value = true;
    try {
        const { data } = await window.axios.post(props.addNewPartnerUrl, {
            customerName: newPartnerName.value,
            type: props.type,
        });
        if (data.status) {
            localClients.value.push({ id: data.customer.id, name: newPartnerName.value });
            form.value.partner_id = data.customer.id;
            showAddPartnerModal.value = false;
            newPartnerName.value = '';
        } else {
            alert(data.message || 'Could not add partner.');
        }
    } finally {
        addingPartner.value = false;
    }
}

/* ── Auto-generate Contract Code ──────────────────────────────────
   Mirrors the old .regenerate-code-ajax handler — fires when either
   Partner or Start Date changes, create mode only (an existing
   contract's code is never regenerated once saved, matching the old
   readonly-on-edit code field). */
async function regenerateCode() {
    if (isEdit || !form.value.partner_id || !form.value.start_date) return;
    const { data } = await window.axios.get(props.generateCodeUrl, {
        params: { partnerId: form.value.partner_id, startDate: form.value.start_date },
    });
    form.value.code = data.code;
}
watch(() => [form.value.partner_id, form.value.start_date], regenerateCode);

/* ── Execution Details modal (per order row) ─────────────────────
   Amount per phase is always derived, never typed directly —
   percentage% of that order's own amount — matching the old page's
   readonly .amount-js field exactly. */
const executionModalIndex = ref(null);
function openExecutionModal(index) { executionModalIndex.value = index; }
function closeExecutionModal() { executionModalIndex.value = null; }
function phaseAmount(order, phase) {
    const pct = Number(phase.percentage) || 0;
    return ((pct / 100) * (Number(order.amount) || 0)).toFixed(2);
}
function totalPhasePercentage(order) {
    return order.phases.reduce((sum, p) => sum + (Number(p.percentage) || 0), 0);
}

/* ── Error display ────────────────────────────────────────────── */
function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

/* ── Submit ───────────────────────────────────────────────────── */
const submitting = ref(false);
function submit() {
    submitting.value = true;
    const ordersPayload = orders.value.map(o => {
        const row = {
            id: o.id || 0,
            amount: o.amount,
            [props.salesOrderOrPurchaseNoText]: o.number,
        };
        o.phases.forEach((p, i) => {
            const n = i + 1;
            row[`execution_percentage_${n}`] = p.percentage;
            row[`start_date_${n}`] = p.start_date;
            row[`end_date_${n}`] = p.end_date;
            row[`collection_days_${n}`] = p.collection_days;
        });
        return row;
    });

    const payload = {
        ...form.value,
        company_id: props.company.id,
        model_type: props.type,
        [props.salesOrderOrPurchaseOrderRelationName]: ordersPayload,
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
                    ← Back to {{ type }} Contracts
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ formTitle }}</h1>

            <div v-if="Object.keys(page.props.errors || {}).length" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
                Please fix the highlighted field(s) below before saving.
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Contract Information -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Contract Information</h2>

                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">Name *</label>
                            <input v-model="form.name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('name')" class="text-xs mt-1 cvr-num-red">{{ errorFor('name') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Code *</label>
                            <input v-model="form.code" type="text" :readonly="isEdit" class="cvr-input w-full px-3 py-2 rounded" :class="{ 'opacity-70': isEdit }" />
                            <p v-if="errorFor('code')" class="text-xs mt-1 cvr-num-red">{{ errorFor('code') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Partner Name *</label>
                            <div class="flex gap-2">
                                <select v-model="form.partner_id" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="">Select...</option>
                                    <option v-for="c in localClients" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <button type="button" @click="showAddPartnerModal = true" class="cvr-btn-secondary px-3 py-2 rounded border text-sm whitespace-nowrap">
                                    + Add New
                                </button>
                            </div>
                            <p v-if="errorFor('partner_id')" class="text-xs mt-1 cvr-num-red">{{ errorFor('partner_id') }}</p>
                        </div>

                        <div>
                            <label class="cvr-form-label">Start Date *</label>
                            <input v-model="form.start_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('start_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('start_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">End Date *</label>
                            <input v-model="form.end_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('end_date')" class="text-xs mt-1 cvr-num-red">{{ errorFor('end_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Amount *</label>
                            <input v-model="form.amount" type="number" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('amount')" class="text-xs mt-1 cvr-num-red">{{ errorFor('amount') }}</p>
                        </div>

                        <div>
                            <label class="cvr-form-label">Currency *</label>
                            <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Exchange Rate *</label>
                            <input v-model="form.exchange_rate" type="number" step="0.0001" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Sales/Purchase Orders -->
                <div class="cvr-card">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide">{{ salesOrderOrPurchaseOrderInformationText }}</h2>
                        <p class="text-xs" :class="totalsMismatch ? 'cvr-num-red' : 'cvr-num-green'">
                            Orders Total: {{ ordersTotal.toFixed(2) }} / Contract Amount: {{ Number(form.amount || 0).toFixed(2) }}
                        </p>
                    </div>

                    <table class="min-w-full text-sm mb-3">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-left">{{ salesOrderOrPurchaseNumberText }}</th>
                                <th class="px-3 py-2 text-left">Amount</th>
                                <th class="px-3 py-2 text-left">Execution Details</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(order, index) in orders" :key="index" class="cvr-table-row">
                                <td class="px-3 py-2">
                                    <input v-model="order.number" type="text" class="cvr-input w-full px-2 py-1.5 rounded" />
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="order.amount" type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded" />
                                </td>
                                <td class="px-3 py-2">
                                    <button type="button" @click="openExecutionModal(index)" class="cvr-btn-secondary px-2 py-1.5 rounded border text-xs">
                                        Insert Execution Details
                                    </button>
                                </td>
                                <td class="px-3 py-2">
                                    <button type="button" @click="removeOrderRow(index)" class="cvr-btn-danger px-2 py-1 rounded border text-xs">✕</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <button type="button" @click="addOrderRow" class="cvr-btn-secondary px-3 py-1.5 rounded border text-xs">
                        + Add {{ salesOrderOrPurchaseNumberText }}
                    </button>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">Cancel</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </form>

            <!-- Add New Customer/Supplier modal -->
            <div v-if="showAddPartnerModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Add New {{ type }}</h2>
                    <input v-model="newPartnerName" type="text" placeholder="Enter new partner name" class="cvr-input w-full px-3 py-2 rounded mb-4" @keyup.enter="submitNewPartner" />
                    <div class="flex justify-end gap-2">
                        <button @click="showAddPartnerModal = false" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button :disabled="addingPartner" @click="submitNewPartner" class="cvr-btn-primary px-3 py-1.5 rounded">
                            {{ addingPartner ? 'Saving...' : 'Save' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Execution Details modal (per order row) -->
            <div v-if="executionModalIndex !== null" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-5xl max-h-[85vh] overflow-y-auto">
                    <div class="flex items-center justify-between mb-1">
                        <h2 class="text-lg font-medium cvr-text-primary">
                            Execution Details — {{ orders[executionModalIndex].number || '(unnamed order)' }}
                        </h2>
                        <button @click="closeExecutionModal" class="cvr-btn-secondary px-2 py-1 rounded border text-xs">✕</button>
                    </div>
                    <p class="text-xs mb-4" :class="totalPhasePercentage(orders[executionModalIndex]) > 100 ? 'cvr-num-red' : 'cvr-text-muted'">
                        Total across the 5 phases: {{ totalPhasePercentage(orders[executionModalIndex]) }}% (must not exceed 100%)
                    </p>

                    <table class="min-w-full text-xs">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-2 py-2 text-left">Execution %</th>
                                <th class="px-2 py-2 text-left">Amount</th>
                                <th class="px-2 py-2 text-left">Start Date</th>
                                <th class="px-2 py-2 text-left">End Date</th>
                                <th class="px-2 py-2 text-left">Collection Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(phase, pIndex) in orders[executionModalIndex].phases" :key="pIndex" class="cvr-table-row">
                                <td class="px-2 py-2">
                                    <input v-model="phase.percentage" type="number" step="0.1" class="cvr-input w-24 px-2 py-1.5 rounded" />
                                </td>
                                <td class="px-2 py-2 cvr-num">
                                    {{ phaseAmount(orders[executionModalIndex], phase) }}
                                </td>
                                <td class="px-2 py-2">
                                    <input v-model="phase.start_date" type="date" :min="form.start_date" :max="form.end_date" class="cvr-input px-2 py-1.5 rounded" />
                                </td>
                                <td class="px-2 py-2">
                                    <input v-model="phase.end_date" type="date" :min="form.start_date" :max="form.end_date" class="cvr-input px-2 py-1.5 rounded" />
                                </td>
                                <td class="px-2 py-2">
                                    <input v-model="phase.collection_days" type="number" step="1" class="cvr-input w-24 px-2 py-1.5 rounded" />
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-end mt-4">
                        <button @click="closeExecutionModal" class="cvr-btn-primary px-3 py-1.5 rounded">Save</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
