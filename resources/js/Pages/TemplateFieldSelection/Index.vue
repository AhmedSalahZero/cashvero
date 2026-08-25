<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    model: String,
    modelDisplayName: String,
    view: String,
    isLoanScheduleModel: Boolean,
    fields: Array, // [{field_name, label, checked, locked}]
    banks: { type: Array, default: () => [] }, // [{id, name}] — ContractLoanSchedule only
    submitUrl: String,
    redirectUrl: String,
    navUrls: Object,
});

const formRef = ref(null);
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const checkedState = ref(Object.fromEntries(props.fields.map(f => [f.field_name, f.checked])));

// Drawee Bank picker — only shown for ContractLoanSchedule. Nothing
// checked here means "include every company bank" (previous behavior),
// so leaving this untouched is a safe default, not a way to end up with
// an accidentally-empty dropdown.
const showBankPicker = computed(() => props.model === 'ContractLoanSchedule' && props.banks.length > 0);
const bankChecked = ref(Object.fromEntries(props.banks.map(b => [b.id, false])));
const selectedBankIds = computed(() => props.banks.filter(b => bankChecked.value[b.id]).map(b => b.id));

const discountFields = ['quantity_discount', 'cash_discount', 'special_discount', 'other_discounts'];
const hasSalesValueField = computed(() => props.fields.some(f => f.field_name === 'sales_value'));

// sales_value auto-checks whenever any discount field is picked, and
// unchecks when none are — matches the original's live jQuery change
// handler exactly. sales_value itself isn't locked, but this keeps it
// in sync as the user toggles the 4 discount checkboxes.
watch(() => discountFields.map(f => checkedState.value[f]), () => {
    if (!hasSalesValueField.value) return;
    const anyDiscountChecked = discountFields.some(f => checkedState.value[f]);
    checkedState.value.sales_value = anyDiscountChecked;
});

const allChecked = computed(() => props.fields.every(f => checkedState.value[f.field_name]));
function toggleSelectAll(checked) {
    props.fields.forEach(f => {
        if (!f.locked) checkedState.value[f.field_name] = checked;
    });
}

function submit() {
    // Real native browser form submission — NOT intercepted by
    // Inertia's router. The server responds with
    // Content-Disposition: attachment, so the browser downloads the
    // file without navigating away, exactly like the original.
    formRef.value.submit();
    // Per the project owner's decision: redirect immediately
    // client-side instead of the original's 2-second session-flag
    // polling hack (a file-download response can't also carry an
    // HTTP redirect, so the original needed that workaround — this
    // achieves the same result more directly).
    router.visit(props.redirectUrl);
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6 max-w-4xl">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Export Fields — {{ modelDisplayName }}</h1>
            <p class="text-sm cvr-text-muted mb-6">{{ $t('Please choose fields that you need to be in your Excel sheet') }}</p>

            <p v-if="isLoanScheduleModel" class="text-xs cvr-text-muted mb-4">
                {{ $t('All fields are included for this template — selection isn\'t customizable here, matching the original.') }}
            </p>

            <!-- Real native form: triggers a genuine file download,
                 not an Inertia visit. ⚠️ Confirmed bug fix: a native
                 form submission bypasses Inertia/axios's automatic
                 CSRF header entirely, so it needs its own _token
                 field — without it, Laravel's CSRF middleware rejects
                 the request with a 419. Read from the csrf-token meta
                 tag already present in the app's root layout. -->
            <form ref="formRef" :action="submitUrl" method="post" @submit.prevent="submit">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="model_name" :value="model" />

                <div class="cvr-card mb-6">
                    <label class="flex items-center gap-2 mb-4 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="allChecked"
                            :disabled="isLoanScheduleModel"
                            @change="toggleSelectAll($event.target.checked)"
                        />
                        <span class="font-semibold cvr-text-primary">{{ $t('Select All') }}</span>
                    </label>

                    <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                        <label
                            v-for="f in fields"
                            :key="f.field_name"
                            class="flex items-center gap-2"
                            :class="f.locked ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'"
                        >
                            <input
                                type="checkbox"
                                name="fields[]"
                                :value="f.field_name"
                                v-model="checkedState[f.field_name]"
                                :disabled="f.locked"
                            />
                            <span class="cvr-text-secondary text-sm">
                                {{ f.label }}
                                <span v-if="f.field_name === 'document_type'" class="text-xs cvr-text-muted">
                                    {{ $t('(Only allowed content: INV, inv, invoice, INVOICE, فاتوره)') }}
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                <div v-if="showBankPicker" class="cvr-card mb-6">
                    <p class="font-semibold cvr-text-primary mb-1">{{ $t('Drawee Banks') }}</p>
                    <p class="text-xs cvr-text-muted mb-4">
                        {{ $t('Pick the bank(s) the company issues cheques against, in favor of the leasing company. Only these will appear in the Drawee Bank dropdown in the downloaded template — matching the exact name as stored in CashVero, so there\'s no risk of a mismatched English/Arabic spelling. Leave all unchecked to include every bank the company has on file.') }}
                    </p>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                        <label v-for="b in banks" :key="b.id" class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="bankChecked[b.id]" />
                            <span class="cvr-text-secondary text-sm">{{ b.name }}</span>
                        </label>
                    </div>
                    <input v-for="id in selectedBankIds" :key="id" type="hidden" name="bank_ids[]" :value="id" />
                </div>

                <button type="submit" class="cvr-btn-primary px-4 py-2 rounded">{{ $t('Download') }}</button>
            </form>
        </div>
    </AppLayout>
</template>
