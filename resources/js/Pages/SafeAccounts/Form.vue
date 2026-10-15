<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const props = defineProps({
    mode: String, // 'create' | 'edit'
    company: Object,
    currencies: Object,
    hasOdoo: Boolean,
    model: Object, // null in create mode
    submitUrl: String,
    backUrl: String,
    navUrls: Object,
});

const isEdit = props.mode === 'edit';

let rowIdCounter = 0;
function newRow(name = '', currency = 'egp', odooCode = '') {
    rowIdCounter += 1;
    return { key: rowIdCounter, name, currency, odoo_code: odooCode };
}

// Edit mode only ever shows one row — no "Repeat" affordance, matching
// the original exactly (the Blade repeater partial hides its "Repeat"
// button entirely when editing).
const rows = ref(
    isEdit
        ? [newRow(props.model.name, props.model.currency, props.model.odoo_code)]
        : [newRow()]
);

function addRow() {
    rows.value.push(newRow());
}
function removeRow(index) {
    if (rows.value.length <= 1) return;
    if (!confirm(t('Are you sure you want to delete this element?'))) return;
    rows.value.splice(index, 1);
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const safePayload = rows.value.map(r => ({
        name: r.name,
        currency: r.currency,
        ...(props.hasOdoo ? { odoo_code: r.odoo_code } : {}),
    }));
    const payload = { safe: safePayload };
    if (isEdit) {
        router.put(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    }
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Safe Accounts') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">
                {{ isEdit ? $t('Edit') : $t('Add') }} {{ $t('Safe') }}
            </h1>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Safe Information') }}</h2>

                    <div v-for="(row, index) in rows" :key="row.key" class="flex items-end gap-3 mb-3">
                        <div class="w-56">
                            <label class="cvr-form-label">{{ $t('Name') }} *</label>
                            <input v-model="row.name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div class="w-48">
                            <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                            <select v-model="row.currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div v-if="hasOdoo" class="w-56">
                            <label class="cvr-form-label">{{ $t('Chart Of Account Number') }} *</label>
                            <input v-model="row.odoo_code" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <button
                            v-if="!isEdit && rows.length > 1"
                            type="button"
                            @click="removeRow(index)"
                            class="cvr-btn-danger px-3 py-2 rounded border text-xs"
                        >
                            {{ $t('Delete') }}
                        </button>
                    </div>

                    <button v-if="!isEdit" type="button" @click="addRow" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                        {{ $t('+ Repeat') }}
                    </button>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Cancel') }}</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? $t('Saving...') : $t('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
