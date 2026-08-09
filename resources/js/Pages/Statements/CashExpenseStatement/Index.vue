<script setup>
/**
 * Statements/CashExpenseStatement/Index.vue
 * ------------------------------------------------------------------
 * Served by CashExpenseStatementController@index. Filter form: date
 * range, Currency, and one or more expense sub-categories — matches
 * result()'s real filter field, cash_expense_category_name_id[].
 *
 * Sub-categories are flattened into one "Category — Sub-category"
 * list and picked via the shared MultiSelectDropdown component (the
 * same one used on the Aging report's form) — not a hand-rolled
 * checkbox tree.
 *
 * ⚠️ This page's original Blade template doesn't exist in the project
 * backup (see the controller's docblock) — rebuilt from what the
 * result() query actually filters by, not copied from a missing file.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import { todayDate, clampDateToToday } from '@/composables/today';

const maxDate = todayDate();

const props = defineProps({
    company: Object,
    categories: Array, // [{ id, name, subCategories: [{ id, name }] }]
    currencies: Object, // { code: label }
    urls: Object, // { result }
});

const currency = ref('');
const startDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().slice(0, 10));
const endDate = ref(todayDate());
const selectedSubCategoryIds = ref([]);

watch(endDate, (value) => {
    const clamped = clampDateToToday(value);
    if (clamped !== value) {
        endDate.value = clamped;
    }
});

const subCategoryOptions = computed(() =>
    props.categories.flatMap(category =>
        category.subCategories.map(sub => ({ value: sub.id, label: `${category.name} — ${sub.name}` }))
    )
);

const canSubmit = computed(() => currency.value && startDate.value && endDate.value && endDate.value <= maxDate && selectedSubCategoryIds.value.length > 0);

function submit() {
    if (!canSubmit.value) return;
    endDate.value = clampDateToToday(endDate.value);
    router.get(props.urls.result, {
        start_date: startDate.value,
        end_date: endDate.value,
        currency: currency.value,
        cash_expense_category_name_id: selectedSubCategoryIds.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Cash Expense Statement</h1>
            <p class="text-sm cvr-text-muted mb-6">
                A transaction-by-transaction list of cash expenses, for a chosen date range, currency, and category.
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

                <div class="max-w-xl">
                    <label class="cvr-form-label">Categories * <span class="cvr-text-muted font-normal">(pick one or more)</span></label>
                    <MultiSelectDropdown v-model="selectedSubCategoryIds" :options="subCategoryOptions" placeholder="Select categories" />
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
                    <li v-if="selectedSubCategoryIds.length === 0">— No category is selected yet (open the Categories dropdown and pick at least one, or Select All).</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>