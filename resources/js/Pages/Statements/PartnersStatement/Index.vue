<script setup>
/**
 * Statements/PartnersStatement/Index.vue
 * ------------------------------------------------------------------
 * Served by PartnersStatementController@index. Filter form: Partner
 * Type (Subsidiary Company / Shareholder / Employee / Other Partner /
 * Taxes & Insurance), then Partners of that type — picked via the
 * shared MultiSelectDropdown component (same one used on the Aging
 * report's form), reloaded via
 * PartnersStatementController@getPartnersByType whenever the type
 * changes — then Currency and a date range.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import { todayDate, clampDateToToday } from '@/composables/today';

const maxDate = todayDate();

const props = defineProps({
    company: Object,
    partnerTypes: Array, // [{ value, title }]
    currencies: Object, // { code: label }
    urls: Object, // { result, partnersByType }
});

const partnerType = ref('');
const selectedPartnerIds = ref([]);
const currency = ref('');
const startDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().slice(0, 10));
const endDate = ref(todayDate());

watch(endDate, (value) => {
    const clamped = clampDateToToday(value);
    if (clamped !== value) {
        endDate.value = clamped;
    }
});

const partnerOptions = ref([]);
const loadingPartners = ref(false);
const loadError = ref('');

async function loadPartners() {
    selectedPartnerIds.value = [];
    partnerOptions.value = [];
    loadError.value = '';
    if (!partnerType.value) return;
    loadingPartners.value = true;
    try {
        const { data } = await window.axios.get(props.urls.partnersByType, {
            params: { partner_type: partnerType.value },
        });
        partnerOptions.value = (data.data || []).map(p => ({ value: p.id, label: p.name }));
    } catch (error) {
        loadError.value = 'Could not load partners for this type. Please try again.';
        console.error('Failed to load partners by type:', error);
    } finally {
        loadingPartners.value = false;
    }
}
watch(partnerType, loadPartners);

const canSubmit = computed(() =>
    partnerType.value && selectedPartnerIds.value.length > 0 && currency.value && startDate.value && endDate.value && endDate.value <= maxDate
);

function submit() {
    if (!canSubmit.value) return;
    endDate.value = clampDateToToday(endDate.value);
    router.get(props.urls.result, {
        start_date: startDate.value,
        end_date: endDate.value,
        partner_type: partnerType.value,
        partner_id: selectedPartnerIds.value,
        currency: currency.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Partner Statement</h1>
            <p class="text-sm cvr-text-muted mb-6">
                A running-balance ledger for one or more partners at once, for a chosen date range.
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-3 mb-5">
                    <div>
                        <label class="cvr-form-label">Start Date *</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">End Date *</label>
                        <input v-model="endDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Currency *</label>
                        <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>Select currency</option>
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ String(label).toUpperCase() }}</option>
                        </select>
                    </div>
                </div>

                <div class="cvr-form-grid-2">
                    <div>
                        <label class="cvr-form-label">Partner Type *</label>
                        <select v-model="partnerType" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>Select partner type</option>
                            <option v-for="type in partnerTypes" :key="type.value" :value="type.value">{{ type.title }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">
                            Partners * <span class="cvr-text-muted font-normal">(pick one or more)</span>
                        </label>
                        <MultiSelectDropdown
                            v-model="selectedPartnerIds"
                            :options="partnerOptions"
                            :placeholder="!partnerType ? 'Select a partner type first' : (loadingPartners ? 'Loading…' : 'Select partners')"
                        />
                        <p v-if="loadError" class="text-xs mt-1" style="color: var(--cvr-danger-text);">{{ loadError }}</p>
                        <p v-else-if="partnerType && !loadingPartners && partnerOptions.length === 0" class="text-xs cvr-text-muted mt-1">
                            No partners of this type yet.
                        </p>
                    </div>
                </div>

                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-5"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit }"
                >
                    View Statement
                </button>
                <ul v-if="!canSubmit" class="text-xs mt-2 space-y-0.5" style="color: var(--cvr-danger-text);">
                    <li v-if="!startDate">— Start Date is not set.</li>
                    <li v-if="!endDate">— End Date is not set.</li>
                    <li v-if="!currency">— Currency is not selected.</li>
                    <li v-if="!partnerType">— Partner Type is not selected.</li>
                    <li v-if="selectedPartnerIds.length === 0">— No partner is selected yet (open the Partners dropdown and pick at least one, or Select All).</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>