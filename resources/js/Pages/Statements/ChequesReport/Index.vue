<script setup>
/**
 * Statements/ChequesReport/Index.vue
 * ------------------------------------------------------------------
 * Served by ChequesReportController@index. Filter form: the due date the
 * cheques fall on or after, Received vs Payable, and one or more
 * currencies (none picked = every currency).
 */
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';

const props = defineProps({
    company: Object,
    types: Array,       // [{ value, label }] - only the kinds this user may open
    currencies: Array,  // [{ value, label }]
    urls: Object,       // { result }
});

const type = ref(props.types[0]?.value || '');
const dueDate = ref(new Date().toISOString().slice(0, 10));
const selectedCurrencies = ref([]);

const canSubmit = computed(() => type.value && dueDate.value);

function submit() {
    if (!canSubmit.value) return;
    router.get(props.urls.result, {
        type: type.value,
        due_date: dueDate.value,
        currencies: selectedCurrencies.value,
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Cheques Report') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ $t('Received or payable cheques whose due date is on or after the date you pick.') }}
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                <div class="cvr-form-grid-3 mb-5">
                    <div>
                        <label class="cvr-form-label">{{ $t('Type') }} *</label>
                        <select v-model="type" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Due Date From') }} *</label>
                        <input v-model="dueDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">
                            {{ $t('Currency') }} <span class="cvr-text-muted font-normal">{{ $t('(leave empty for all)') }}</span>
                        </label>
                        <MultiSelectDropdown
                            v-model="selectedCurrencies"
                            :options="currencies"
                            :placeholder="$t('All currencies')"
                        />
                    </div>
                </div>

                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="cvr-btn-primary px-4 py-1.5 rounded text-sm"
                    :class="{ 'opacity-40 cursor-not-allowed': !canSubmit }"
                >
                    {{ $t('View Report') }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
