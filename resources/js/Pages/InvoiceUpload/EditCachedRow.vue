<script setup>
/**
 * InvoiceUpload/EditCachedRow.vue
 * ------------------------------------------------------------------
 * Served by SalesGatheringTestController@editCachedRow — fix a
 * mistake in one not-yet-saved row before committing the import.
 * Reached from the "Edit" link on a non-duplicate row in
 * InvoiceUpload/Import.vue's review table.
 *
 * updateCachedRow() (the submit target) already returns a plain
 * redirect back to the import/review page — Inertia-compatible
 * as-is, no backend response-shape fix was needed here.
 *
 * Contract Loan Schedule's Drawee Bank → Account Number cascading
 * dropdown: picking a bank scopes Account Number to just that
 * bank's real accounts (a genuine data-integrity guard, confirmed
 * with the project owner — not incidental). Uses the existing,
 * unchanged contract.loan.schedule.account.numbers lookup endpoint.
 */
import { ref, onMounted, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    modelName: String,
    modelDisplayName: String,
    fields: Array, // [{ field, label, type, value, options }]
    accountNumbersUrl: String, // null unless modelName is ContractLoanSchedule
    updateUrl: String,
    backUrl: String,
});

const form = useForm(
    Object.fromEntries(props.fields.map(f => [f.field, f.value]))
);

/* ── Drawee Bank → Account Number cascade ────────────────────────── */
const accountNumberOptions = ref([]);
const initialAccountNumber = props.fields.find(f => f.field === 'account_number')?.value || '';
let firstLoad = true;

// Same fix as InvoiceUpload/ScheduleForm.vue — seed with the row's own
// saved account number immediately so it's displayable before the
// async lookup resolves (see that file for the full explanation).
if (initialAccountNumber) {
    accountNumberOptions.value = [initialAccountNumber];
}

async function loadAccountNumbers() {
    if (!props.accountNumbersUrl) return;
    const draweeBank = form.drawee_bank || '';
    if (!draweeBank) {
        // Same fix as ScheduleForm.vue — a blank Drawee Bank should
        // never erase an already-known, independently-saved Account
        // Number from the dropdown.
        accountNumberOptions.value = initialAccountNumber ? [initialAccountNumber] : [];
        return;
    }
    const { data } = await window.axios.get(props.accountNumbersUrl, { params: { drawee_bank: draweeBank } });
    const fetched = data.data || [];
    accountNumberOptions.value = (firstLoad && initialAccountNumber && !fetched.includes(initialAccountNumber))
        ? [initialAccountNumber, ...fetched]
        : fetched;
    // Preserve the row's existing account number on first load only —
    // a later bank change should force a fresh, explicit choice
    // (matches the original: changing bank clears the old account
    // number, since it belonged to a different bank).
    if (firstLoad && initialAccountNumber && accountNumberOptions.value.includes(initialAccountNumber)) {
        form.account_number = initialAccountNumber;
    }
}

onMounted(() => {
    loadAccountNumbers().then(() => { firstLoad = false; });
});

watch(() => form.drawee_bank, () => {
    if (firstLoad) return;
    form.account_number = '';
    loadAccountNumbers();
});

function submit() {
    form.put(props.updateUrl);
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                {{ $t('← Back to') }} {{ modelDisplayName }} {{ $t('Review') }}
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ modelDisplayName }} {{ $t('— Edit Row') }}</h1>

            <div class="cvr-card-bg cvr-border border rounded-lg p-4">
                <div class="cvr-form-grid-2">
                    <div v-for="f in fields" :key="f.field">
                        <label class="cvr-form-label">{{ f.label }}</label>

                        <select v-if="f.type === 'bank_select'" v-model="form[f.field]" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="bank in f.options" :key="bank" :value="bank">{{ bank }}</option>
                        </select>

                        <select v-else-if="f.type === 'account_number_select'" v-model="form[f.field]" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">{{ $t('Select') }}</option>
                            <option v-for="acc in accountNumberOptions" :key="acc" :value="acc">{{ acc }}</option>
                        </select>

                        <input v-else v-model="form[f.field]" :type="f.type" class="cvr-input w-full px-3 py-2 rounded" />

                        <p v-if="form.errors[f.field]" class="text-xs text-red-500 mt-1">{{ form.errors[f.field] }}</p>
                    </div>
                </div>
                <button @click="submit" :disabled="form.processing" class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-4">{{ $t('Save') }}</button>
            </div>
        </div>
    </AppLayout>
</template>
