<script setup>
/**
 * Cash Cover Statement — filters.
 *
 * The cash cover is the money the bank FREEZES behind a letter of
 * guarantee or credit. It is not the instrument, and it is not spendable
 * cash: this report is where you see how much of your balance is locked
 * that way, and when it moved.
 *
 * Bank is deliberately optional — cover for one instrument type is often
 * spread over several banks, and the total across all of them is the
 * figure that matters for cash planning.
 */
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    instrumentTypes: Array,
    banks: Array,
    currencies: Array,
    urls: Object,
});

const form = ref({
    instrument_type: '',
    financial_institution_id: '',
    currency: '',
    start_date: '',
    end_date: '',
});

const missing = computed(() => {
    const gaps = [];
    if (!form.value.instrument_type) gaps.push('Instrument is not selected.');
    if (!form.value.currency) gaps.push('Currency is not selected.');
    if (!form.value.start_date) gaps.push('Start Date is not set.');
    if (!form.value.end_date) gaps.push('End Date is not set.');
    return gaps;
});

function submit() {
    if (missing.value.length) return;
    router.get(props.urls.result, { ...form.value }, { preserveState: true });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Cash Cover Statement') }}</h1>
            <p class="text-sm cvr-text-muted mb-4">
                {{ $t('The money frozen behind letters of guarantee and credit — not the instruments themselves.') }}
            </p>

            <div class="cvr-card rounded-lg p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
                    <div>
                        <label class="cvr-form-label">{{ $t('Instrument') }}</label>
                        <select v-model="form.instrument_type" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="i in instrumentTypes" :key="i.value" :value="i.value">{{ i.label }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="cvr-form-label">{{ $t('Bank') }}</label>
                        <select v-model="form.financial_institution_id" class="cvr-input w-full px-3 py-2 rounded">
                            <!-- Empty means every bank, which is a real choice
                                 here rather than "not chosen yet". -->
                            <option value="">{{ $t('All Banks') }}</option>
                            <option v-for="b in banks" :key="b.value" :value="b.value">{{ b.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="cvr-form-label">{{ $t('Currency') }}</label>
                        <select v-model="form.currency" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="c in currencies" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="cvr-form-label">{{ $t('Start Date') }}</label>
                        <input v-model="form.start_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>

                    <div>
                        <label class="cvr-form-label">{{ $t('End Date') }}</label>
                        <input v-model="form.end_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>

                <ul v-if="missing.length" class="mt-3 text-xs cvr-text-muted">
                    <li v-for="m in missing" :key="m">— {{ $t(m) }}</li>
                </ul>

                <button @click="submit" :disabled="missing.length" class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-4">
                    {{ $t('View Statement') }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
