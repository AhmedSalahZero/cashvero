<script setup>
/**
 * Statements/SafeStatement/Index.vue
 * ------------------------------------------------------------------
 * Served by SafeStatementController@index. Filter form for the Safe
 * Statement report: Branch, Currency, and a date range. Simpler than
 * Bank Statement's form — no cascading account-number lookup, since a
 * safe is identified directly by its branch.
 *
 * Submits as a GET to SafeStatementController@result (the original
 * Blade form used POST — switched here so the result page's own URL
 * carries every filter, matching Bank Statement's sibling page and
 * needed for bookmarkable/paginable results).
 */
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    branches: Object, // { id: name }
    currencies: Object, // { code: label }
    urls: Object, // { result }
});

const branchId = ref('');
const currency = ref('');
const startDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().slice(0, 10));
const endDate = ref(new Date().toISOString().slice(0, 10));

const canSubmit = computed(() => branchId.value && currency.value && startDate.value && endDate.value);

function submit() {
    if (!canSubmit.value) return;
    router.get(props.urls.result, {
        start_date: startDate.value,
        end_date: endDate.value,
        branch_id: branchId.value,
        currency: currency.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Safe Statement</h1>
            <p class="text-sm cvr-text-muted mb-6">
                A transaction-by-transaction ledger for the cash held in one branch's safe, for a chosen date range.
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-4">
                    <div>
                        <label class="cvr-form-label">Start Date *</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">End Date *</label>
                        <input v-model="endDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">Currency *</label>
                        <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>Select currency</option>
                            <option v-for="(label, code) in currencies" :key="code" :value="code">{{ String(label).toUpperCase() }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">Safe *</label>
                        <select v-model="branchId" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="" disabled>Select Safe</option>
                            <option v-for="(name, id) in branches" :key="id" :value="id">{{ name }}</option>
                        </select>
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
            </div>
        </div>
    </AppLayout>
</template>
