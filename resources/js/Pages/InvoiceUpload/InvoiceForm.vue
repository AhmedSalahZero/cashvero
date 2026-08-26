<script setup>
/**
 * InvoiceUpload/InvoiceForm.vue
 * ------------------------------------------------------------------
 * Served by SalesGatheringTestController@createModel / @editModel —
 * the REAL single-record Customer/Supplier Invoice form, scoped down
 * from the original's 865-line generic shared template (which also
 * serves several unrelated model types). Shared for both add and
 * edit, same pattern used throughout this migration.
 *
 * The cascade, exactly matching the original:
 *   Customer/Supplier → Project Name (their contracts, via
 *   get.projects.for.customer.or.supplier) → Sales/Purchase Order
 *   Number (that contract's orders, via get.po.or.so.from.contract)
 * Selecting a Project auto-fills Contract Code/Date (still editable
 * afterward, same as the original — not disabled fields). Selecting
 * a Sales/Purchase Order auto-fills its date the same way.
 *
 * Field keys: the controller remaps a few fields (Customer/Supplier
 * Name, Project Name, Sales/Purchase Order Number) to the exact
 * field names storeModel()/updateModel() actually expect
 * (customer_id, supplier_id, contract_id, sales_order_id /
 * purchases_order_id) — those methods explicitly except() these
 * names from the raw pass-through data and read them separately, so
 * the form MUST submit under those exact names, not the invoice's
 * own raw column names (e.g. customer_name).
 *
 * storeModel()/updateModel() (submit targets) are UNCHANGED — the
 * actual invoice-saving business logic was never touched, only this
 * presentation layer.
 */
import { ref, computed, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    modelName: String,
    modelDisplayName: String,
    fields: Array, // [{ field, label, type, value, options }]
    projectsUrl: String,
    poOrSoUrl: String,
    submitUrl: String,
    isEdit: Boolean,
    backUrl: String,
    /* { target, add: [...], subtract: [...] } — which fields make up
       "Total Invoice Amount". Comes from the server because the set of
       columns a company shows is configurable. */
    totalInvoiceFormula: { type: Object, default: () => ({ target: '', add: [], subtract: [] }) },
});

const isCustomer = props.modelName === 'CustomerInvoice';
const customerOrSupplierField = isCustomer ? 'customer_id' : 'supplier_id';
const orderField = isCustomer ? 'sales_order_id' : 'purchases_order_id';

const form = useForm(
    Object.fromEntries(props.fields.map(f => [f.field, f.value]))
);

/* ── Total Invoice Amount ──────────────────────────────────────────
   invoice amount + VAT − withholding, kept in step with its inputs
   instead of typed. A term the company does not show on the form is
   simply absent from `form` and counts as 0 — the same thing typing
   nothing into it would have meant.

   The value still submits under its own field name, so nothing about
   how the invoice is saved changes; the field is just no longer a
   place the three numbers can be contradicted. */
const totalTargetField = props.totalInvoiceFormula?.target || '';

function sumOf(fieldNames) {
    return (fieldNames || []).reduce((total, name) => {
        const raw = form[name];
        const value = Number(String(raw ?? '').replace(/,/g, ''));
        return total + (Number.isFinite(value) ? value : 0);
    }, 0);
}

const computedTotalInvoiceAmount = computed(() => {
    const total = sumOf(props.totalInvoiceFormula?.add) - sumOf(props.totalInvoiceFormula?.subtract);
    // Money, not a float artefact: 100 + 14.5 must not become 114.49999.
    return Math.round(total * 100) / 100;
});

if (totalTargetField) {
    watch(computedTotalInvoiceAmount, (total) => { form[totalTargetField] = total; }, { immediate: true });
}

/* Spelled out with this company's own field labels, so the number is
   checkable on the spot rather than taken on trust. */
const totalFormulaHint = computed(() => {
    const labelFor = (name) => props.fields.find(f => f.field === name)?.label;
    const added = (props.totalInvoiceFormula?.add || []).map(labelFor).filter(Boolean);
    const subtracted = (props.totalInvoiceFormula?.subtract || []).map(labelFor).filter(Boolean);
    if (!added.length) return '';
    return added.join(' + ') + subtracted.map(l => ` − ${l}`).join('');
});

/* ── Cascade state — auto-fill targets matched by partial field
   name, mirroring the original's [name*="..."] selector approach. ── */
const projectOptions = ref([]); // [{ id, name, code, start_date }]
const orderOptions = ref([]); // [{ id, number, date }]

const contractCodeField = props.fields.find(f => f.field.includes('contract_code'))?.field;
const contractDateField = props.fields.find(f => f.field.includes('contract_date'))?.field;
const orderDateField = props.fields.find(f => f.field.includes(isCustomer ? 'sales_order_date' : 'purchases_order_date'))?.field;

async function loadProjects(preserveCurrent = false) {
    const currentProject = form.contract_id;
    const partnerId = form[customerOrSupplierField];
    if (!partnerId) { projectOptions.value = []; return; }
    const { data } = await window.axios.get(props.projectsUrl, { params: { customerOrSupplierId: partnerId } });
    projectOptions.value = (data.projects || []).map(c => ({ id: c.id, name: c.name, code: c.code, start_date: c.start_date }));
    if (preserveCurrent && currentProject && projectOptions.value.some(p => String(p.id) === String(currentProject))) {
        form.contract_id = currentProject;
        applyProjectAutoFill(currentProject, false);
    }
}

function applyProjectAutoFill(projectId, resetOrder = true) {
    const project = projectOptions.value.find(p => String(p.id) === String(projectId));
    if (contractCodeField) form[contractCodeField] = project?.code ?? '';
    if (contractDateField) form[contractDateField] = project?.start_date ?? '';
    if (resetOrder) {
        form[orderField] = '';
        orderOptions.value = [];
    }
}

async function loadOrders(preserveCurrent = false) {
    const currentOrder = form[orderField];
    const projectId = form.contract_id;
    if (!projectId) { orderOptions.value = []; return; }
    const { data } = await window.axios.get(props.poOrSoUrl, { params: { contractId: projectId } });
    const list = isCustomer ? (data.sales_orders || []) : (data.purchase_orders || []);
    orderOptions.value = list.map(o => ({ id: o.id, number: isCustomer ? o.so_number : o.po_number, date: o.start_date_1 }));
    if (preserveCurrent && currentOrder && orderOptions.value.some(o => String(o.id) === String(currentOrder))) {
        form[orderField] = currentOrder;
        applyOrderAutoFill(currentOrder);
    }
}

function applyOrderAutoFill(orderId) {
    const order = orderOptions.value.find(o => String(o.id) === String(orderId));
    if (orderDateField) form[orderDateField] = order?.date ?? '';
}

function onCustomerOrSupplierChange() {
    form.contract_id = '';
    form[orderField] = '';
    if (contractCodeField) form[contractCodeField] = '';
    if (contractDateField) form[contractDateField] = '';
    orderOptions.value = [];
    loadProjects();
}
function onProjectChange() {
    applyProjectAutoFill(form.contract_id);
    loadOrders();
}
function onOrderChange() {
    applyOrderAutoFill(form[orderField]);
}

// Bootstrap the cascade on load (edit mode: preserve the model's
// existing project/order selections, same as the original's
// $(function(){ trigger('change') }) bootstrap).
loadProjects(true).then(() => loadOrders(true));

function submit() {
    form.post(props.submitUrl);
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                ← Back to {{ modelDisplayName }} Table
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ modelDisplayName }} {{ isEdit ? $t('— Edit') : $t('— Create') }}</h1>
            <p v-if="!isEdit" class="text-sm cvr-text-muted -mt-4 mb-6">{{ $t('If you can\'t find your customer or supplier in the dropdown, create them first from the Partners section.') }}</p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-4">
                <div class="cvr-form-grid-4">
                    <div v-for="f in fields" :key="f.field">
                        <label class="cvr-form-label">{{ f.label }}</label>

                        <select v-if="f.type === 'customer_select' || f.type === 'supplier_select'" v-model="form[f.field]" @change="onCustomerOrSupplierChange" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="(name, id) in f.options" :key="id" :value="id">{{ name }}</option>
                        </select>

                        <select v-else-if="f.type === 'business_sector_select'" v-model="form[f.field]" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="(name, id) in f.options" :key="id" :value="id">{{ name }}</option>
                        </select>

                        <select v-else-if="f.type === 'project_select'" v-model="form[f.field]" @change="onProjectChange" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="p in projectOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>

                        <select v-else-if="f.type === 'sales_order_select' || f.type === 'purchase_order_select'" v-model="form[f.field]" @change="onOrderChange" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="o in orderOptions" :key="o.id" :value="o.id">{{ o.number }}</option>
                        </select>

                        <select v-else-if="f.type === 'currency_select'" v-model="form[f.field]" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="(name, code) in f.options" :key="code" :value="code">{{ name }}</option>
                        </select>

                        <!-- Computed, not typed — see the script's
                             "Total Invoice Amount" note. Shown disabled so it
                             reads as a result rather than an empty box someone
                             forgot to fill. -->
                        <template v-else-if="f.type === 'computed_total'">
                            <input :value="computedTotalInvoiceAmount" type="number" disabled class="cvr-input w-full px-3 py-2 rounded opacity-70 cursor-not-allowed" />
                            <p class="text-xs cvr-text-muted mt-1">{{ totalFormulaHint }}</p>
                        </template>

                        <input v-else v-model="form[f.field]" :type="f.type" class="cvr-input w-full px-3 py-2 rounded" />

                        <p v-if="form.errors[f.field]" class="text-xs text-red-500 mt-1">{{ form.errors[f.field] }}</p>
                    </div>
                </div>
                <button @click="submit" :disabled="form.processing" class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-4">{{ $t('Save') }}</button>
            </div>
        </div>
    </AppLayout>
</template>
