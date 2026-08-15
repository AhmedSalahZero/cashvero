<script setup>
/**
 * InvoiceUpload/ScheduleForm.vue
 * ------------------------------------------------------------------
 * Create / edit a single LoanSchedule or ContractLoanSchedule row.
 * Replaces admin/create-excel-by-form.blade.php for those two models.
 * Field typing + Drawee Bank → Account Number cascade match
 * EditCachedRow.vue (import review editor).
 */
import { ref, onMounted, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    modelName: String,
    modelDisplayName: String,
    fields: Array, // [{ field, label, type, value, options }]
    accountNumbersUrl: String,
    submitUrl: String,
    isEdit: Boolean,
    backUrl: String,
    leasingContractId: [String, Number],
    mediumTermLoanId: [String, Number],
});

const form = useForm({
    ...Object.fromEntries(props.fields.map(f => [f.field, f.value])),
    ...(props.leasingContractId ? { leasing_contract_id: props.leasingContractId } : {}),
    ...(props.mediumTermLoanId ? { medium_term_loan_id: props.mediumTermLoanId } : {}),
});

const accountNumberOptions = ref([]);
const initialAccountNumber = props.fields.find(f => f.field === 'account_number')?.value || '';
let firstLoad = true;

/**
 * ⚠️ REAL BUG FIXED HERE (client-flagged): "Drawee Bank and Account
 * Number does not get fetched in edit mode." The Drawee Bank field
 * itself is fine (its options come straight from props, available
 * immediately) — the actual problem is Account Number. It starts
 * with an EMPTY options list, only filled in once the async
 * loadAccountNumbers() lookup resolves. Until then (or if that
 * lookup is slow or fails), there is no matching <option> for the
 * row's own saved account number, so the native <select> can't show
 * it as selected — even though form.account_number already holds
 * the correct value internally. Fixed by seeding the options list
 * with the row's own saved account number right away, so it's
 * always displayable from the very first render; the real fetch
 * still runs and supersedes this with the full, authoritative list.
 */
if (initialAccountNumber) {
    accountNumberOptions.value = [initialAccountNumber];
}

async function loadAccountNumbers() {
    if (!props.accountNumbersUrl) return;
    const draweeBank = form.drawee_bank || '';
    if (!draweeBank) {
        // ⚠️ REAL BUG FIXED HERE (client-flagged): this used to reset
        // accountNumberOptions to an empty array whenever Drawee Bank
        // was blank — wiping out the row's own saved account number
        // (seeded above) even though Account Number is a perfectly
        // valid, independently-saved value. A blank Drawee Bank no
        // longer erases an already-known Account Number.
        accountNumberOptions.value = initialAccountNumber ? [initialAccountNumber] : [];
        return;
    }
    const { data } = await window.axios.get(props.accountNumbersUrl, { params: { drawee_bank: draweeBank } });
    const fetched = data.data || [];
    // Keep the row's own saved account number visible even if the
    // fresh lookup doesn't happen to include it (e.g. an account
    // that's since been deactivated) — better to show a stale-but-
    // correct value than silently drop it from the dropdown.
    accountNumberOptions.value = (firstLoad && initialAccountNumber && !fetched.includes(initialAccountNumber))
        ? [initialAccountNumber, ...fetched]
        : fetched;
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
    form.post(props.submitUrl);
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                ← Back to {{ modelDisplayName }} Table
            </Link>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ modelDisplayName }} {{ isEdit ? '— Edit' : '— Create' }}</h1>

            <div class="cvr-card-bg cvr-border border rounded-lg p-4">
                <div class="cvr-form-grid-2">
                    <div v-for="f in fields" :key="f.field">
                        <label class="cvr-form-label">{{ f.label }}</label>

                        <select v-if="f.type === 'bank_select'" v-model="form[f.field]" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="bank in f.options" :key="bank" :value="bank">{{ bank }}</option>
                        </select>

                        <select v-else-if="f.type === 'account_number_select'" v-model="form[f.field]" class="cvr-input w-full px-3 py-2 rounded">
                            <option value="">Select</option>
                            <option v-for="acc in accountNumberOptions" :key="acc" :value="acc">{{ acc }}</option>
                        </select>

                        <input v-else v-model="form[f.field]" :type="f.type" class="cvr-input w-full px-3 py-2 rounded" />

                        <p v-if="form.errors[f.field]" class="text-xs text-red-500 mt-1">{{ form.errors[f.field] }}</p>
                    </div>
                </div>
                <button @click="submit" :disabled="form.processing" class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-4">Save</button>
            </div>
        </div>
    </AppLayout>
</template>
