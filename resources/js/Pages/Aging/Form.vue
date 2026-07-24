<script setup>
/**
 * Aging/Form.vue
 * ------------------------------------------------------------------
 * Served by AgingController@index — shared by "Customer Aging" and
 * "Suppliers Aging" (same page, $modelType differs). Submits to
 * result(), which is still Blade (the big matrix + charts page,
 * deliberately deferred — see project owner conversation) — so this
 * is a real native HTML form POST, not an Inertia visit, exactly the
 * same way any link to a not-yet-migrated page works elsewhere.
 *
 * The Currency and Customer/Supplier options are refreshed live via
 * a JSON API call (ajaxCustomersUrl) every time a filter changes —
 * that endpoint was never a page visit, just data, so it stays a
 * real axios call here rather than anything Inertia-related.
 *
 * Business Unit / Sales Person / Business Sector / Customers all use
 * MultiSelectDropdown instead of native <select multiple> — a native
 * multiselect renders as a permanently-open listbox with browser-native
 * (non-themeable) highlight colors, which is what looked broken in
 * both dark and light mode. Since that component doesn't produce real
 * <select> form fields, hidden inputs mirror each selection for the
 * native form submission below.
 */
import { ref, computed, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    businessUnits: Array,
    salesPersons: Array,
    businessSectors: Array,
    currencies: Array,
    customersOrSupplierText: String,
    title: String,
    modelType: String,
    submitUrl: String,
    ajaxCustomersUrl: String,
});

const againDate = ref(new Date().toISOString().slice(0, 10));
const selectedBusinessUnits = ref([]);
const selectedSalesPersons = ref([]);
const selectedBusinessSectors = ref([]);
const selectedCurrency = ref('');
const selectedClientIds = ref([]);

const currencyOptions = ref(props.currencies || []);
const customerOptions = ref({}); // { [id]: name } — starts empty, same as the original, until the first refresh

// MultiSelectDropdown wants a uniform [{ value, label }] shape —
// business unit/sales person/sector are plain string arrays (value
// === label); customers arrive as an { id: name } object.
const businessUnitOptions = computed(() => (props.businessUnits || []).map(v => ({ value: v, label: v })));
const salesPersonOptions = computed(() => (props.salesPersons || []).map(v => ({ value: v, label: v })));
const businessSectorOptions = computed(() => (props.businessSectors || []).map(v => ({ value: v, label: v })));
const clientOptions = computed(() => Object.entries(customerOptions.value).map(([id, name]) => ({ value: id, label: name })));

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

/* ── Live-refresh Currency + Customer/Supplier options whenever any
   filter changes — matches the original's ajax-refresh-customers
   behavior, including firing once on load. ─────────────────────── */
async function refreshOptions() {
    const { data } = await window.axios.get(props.ajaxCustomersUrl, {
        params: {
            business_units: selectedBusinessUnits.value,
            sales_persons: selectedSalesPersons.value,
            business_sectors: selectedBusinessSectors.value,
            currencies: selectedCurrency.value,
        },
    });
    currencyOptions.value = data.data.currencies_names || [];
    customerOptions.value = data.data.customer_names || {};
}

onMounted(refreshOptions);
watch([selectedBusinessUnits, selectedSalesPersons, selectedBusinessSectors], refreshOptions, { deep: true });

/* ── Minimal required-field check. Native <select required> handled
   this for free; MultiSelectDropdown doesn't produce a real <select>,
   so this replaces that lost validation rather than silently
   dropping it. ─────────────────────────────────────────────────── */
const validationError = ref('');
function handleSubmit(e) {
    if (!selectedCurrency.value || !selectedClientIds.value.length) {
        e.preventDefault();
        validationError.value = `Currency and ${props.customersOrSupplierText} are required.`;
    } else {
        validationError.value = '';
    }
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ title }}</h1>

            <form method="POST" :action="submitUrl" @submit="handleSubmit" class="cvr-card-bg cvr-border border rounded-lg p-4 space-y-4">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input v-for="v in selectedBusinessUnits" :key="'bu-'+v" type="hidden" name="business_units[]" :value="v" />
                <input v-for="v in selectedSalesPersons" :key="'sp-'+v" type="hidden" name="sales_persons[]" :value="v" />
                <input v-for="v in selectedBusinessSectors" :key="'bs-'+v" type="hidden" name="business_sectors[]" :value="v" />
                <input v-for="v in selectedClientIds" :key="'cl-'+v" type="hidden" name="client_ids[]" :value="v" />

                <p v-if="validationError" class="text-sm text-red-500">{{ validationError }}</p>

                <div class="cvr-form-grid-4">
                    <div>
                        <label class="cvr-form-label">Aging Date *</label>
                        <input v-model="againDate" type="date" name="again_date" required class="cvr-input w-full px-3 py-2 rounded" />
                    </div>

                    <div v-if="businessUnits.length" class="relative">
                        <label class="cvr-form-label">Business Unit</label>
                        <MultiSelectDropdown v-model="selectedBusinessUnits" :options="businessUnitOptions" placeholder="Select" />
                    </div>

                    <div v-if="salesPersons.length" class="relative">
                        <label class="cvr-form-label">Sales Person</label>
                        <MultiSelectDropdown v-model="selectedSalesPersons" :options="salesPersonOptions" placeholder="Select" />
                    </div>

                    <div v-if="businessSectors.length" class="relative">
                        <label class="cvr-form-label">Business Sector</label>
                        <MultiSelectDropdown v-model="selectedBusinessSectors" :options="businessSectorOptions" placeholder="Select" />
                    </div>

                    <div>
                        <label class="cvr-form-label">Currency *</label>
                        <select v-model="selectedCurrency" @change="refreshOptions" name="currency" required class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>Select</option>
                            <option v-for="c in currencyOptions" :key="c" :value="c">{{ c.toUpperCase() }}</option>
                        </select>
                    </div>

                    <div class="relative">
                        <label class="cvr-form-label">{{ customersOrSupplierText }} *</label>
                        <MultiSelectDropdown v-model="selectedClientIds" :options="clientOptions" placeholder="Select" />
                    </div>
                </div>

                <button type="submit" class="cvr-btn-primary px-4 py-1.5 rounded text-sm">Submit</button>
            </form>
        </div>
    </AppLayout>
</template>
