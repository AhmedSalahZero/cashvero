<script setup>
/**
 * CollectionEffectiveness/Form.vue
 * ------------------------------------------------------------------
 * Served by CollectionEffectivenessIndexController@index — shared by
 * "Collection Effectiveness Index" (customers) and "Payment
 * Effectiveness Index" (suppliers), same page, $modelType differs.
 *
 * Structurally almost identical to Aging/Form.vue (same
 * Business Unit/Sales Person/Business Sector/Currency filter shape,
 * same live-refresh-on-change behavior) — reuses MultiSelectDropdown.
 * One real difference: the Clients field here submits by NAME, not
 * ID (result() reads it via Partner::getPartnerFromName) — matches
 * what the backend actually expects, not copied blindly from Aging.
 *
 * Unlike Aging's form, result() is ALSO migrated in this same pass,
 * so this submits as a real Inertia form (smooth transition) rather
 * than a native browser POST.
 */
import { ref, computed, onMounted, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
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
    defaultStartDate: String,
    defaultEndDate: String,
    clientOptions: Array, // [name, name, ...]
    resultUrl: String,
    ajaxCustomersUrl: String,
});

const form = useForm({
    start_date: props.defaultStartDate,
    end_date: props.defaultEndDate,
    business_units: [],
    sales_persons: [],
    business_sectors: [],
    currency: '',
    clients: [],
    model_type: props.modelType,
});

const currencyOptions = ref(props.currencies || []);
// The initial client list came from the server unfiltered; it also
// refreshes live via the same endpoint Aging uses, same as there.
const clientNameOptions = ref((props.clientOptions || []).map(name => ({ value: name, label: name })));

const businessUnitOptions = computed(() => (props.businessUnits || []).map(v => ({ value: v, label: v })));
const salesPersonOptions = computed(() => (props.salesPersons || []).map(v => ({ value: v, label: v })));
const businessSectorOptions = computed(() => (props.businessSectors || []).map(v => ({ value: v, label: v })));

async function refreshOptions() {
    const { data } = await window.axios.get(props.ajaxCustomersUrl, {
        params: {
            business_units: form.business_units,
            sales_persons: form.sales_persons,
            business_sectors: form.business_sectors,
            currencies: form.currency,
        },
    });
    currencyOptions.value = data.data.currencies_names || [];
    // customer_names arrives as { id: name } — this form only cares
    // about the name (see the field-shape note above).
    clientNameOptions.value = Object.values(data.data.customer_names || {}).map(name => ({ value: name, label: name }));
}

onMounted(refreshOptions);
watch(() => [form.business_units, form.sales_persons, form.business_sectors], refreshOptions, { deep: true });

const validationError = ref('');
function submit() {
    if (!form.currency || !form.clients.length) {
        validationError.value = `Currency and ${props.customersOrSupplierText} are required.`;
        return;
    }
    validationError.value = '';
    form.post(props.resultUrl);
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ title }}</h1>

            <div class="cvr-card-bg cvr-border border rounded-lg p-4 space-y-4">
                <p v-if="validationError" class="text-sm text-red-500">{{ validationError }}</p>

                <div class="cvr-form-grid-4">
                    <div>
                        <label class="cvr-form-label">{{ $t('Start Date') }}</label>
                        <input v-model="form.start_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('End Date') }}</label>
                        <input v-model="form.end_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>

                    <div v-if="businessUnits.length" class="relative">
                        <label class="cvr-form-label">{{ $t('Business Unit') }}</label>
                        <MultiSelectDropdown v-model="form.business_units" :options="businessUnitOptions" :placeholder="$t('Select')" />
                    </div>

                    <div v-if="salesPersons.length" class="relative">
                        <label class="cvr-form-label">{{ $t('Sales Person') }}</label>
                        <MultiSelectDropdown v-model="form.sales_persons" :options="salesPersonOptions" :placeholder="$t('Select')" />
                    </div>

                    <div v-if="businessSectors.length" class="relative">
                        <label class="cvr-form-label">{{ $t('Business Sector') }}</label>
                        <MultiSelectDropdown v-model="form.business_sectors" :options="businessSectorOptions" :placeholder="$t('Select')" />
                    </div>

                    <div>
                        <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                        <select v-model="form.currency" @change="refreshOptions" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>{{ $t('Select') }}</option>
                            <option v-for="c in currencyOptions" :key="c" :value="c">{{ c.toUpperCase() }}</option>
                        </select>
                    </div>

                    <div class="relative">
                        <label class="cvr-form-label">{{ customersOrSupplierText }} *</label>
                        <MultiSelectDropdown v-model="form.clients" :options="clientNameOptions" :placeholder="$t('Select')" />
                    </div>
                </div>

                <button @click="submit" :disabled="form.processing" class="cvr-btn-primary px-4 py-1.5 rounded text-sm">{{ $t('Submit') }}</button>
            </div>
        </div>
    </AppLayout>
</template>
