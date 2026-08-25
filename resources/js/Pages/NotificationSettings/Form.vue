<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    model: Object, // null if no settings row yet
    defaults: Object,
    submitUrl: String,
});

/*
 * Mirror Blade x-form.input: when $model is present use $model->{$name}
 * (even if null); when absent use the default-value constants.
 */
function initialValue(field) {
    if (props.model) {
        return props.model[field] ?? '';
    }
    return props.defaults[field] ?? '';
}

const sections = [
    {
        title: 'Customer Invoices Notifications',
        fields: [
            ['customer_coming_dues_invoices_notifications_days', 'Coming Dues Invoices Notifications Days'],
            ['customer_past_dues_invoices_notifications_days', 'Past Dues Invoices Notifications Days'],
        ],
    },
    {
        title: 'Cheques In Hand Notifications',
        fields: [
            ['cheques_in_safe_notifications_days', 'Cheques In Safe Notifications Days'],
            ['coming_receivable_cheques_notifications_days', 'Coming Receivable Cheques'],
        ],
    },
    {
        title: 'Suppliers Invoices Notifications',
        fields: [
            ['supplier_coming_dues_invoices_notifications_days', 'Coming Dues Invoices Notifications Days'],
            ['supplier_past_dues_invoices_notifications_days', 'Past Dues Invoices Notifications Days'],
            ['coming_payable_cheques_notifications_days', 'Coming Payable Cheques Notifications Days'],
        ],
    },
];

const form = reactive(
    Object.fromEntries(
        sections.flatMap(s => s.fields).map(([name]) => [name, initialValue(name)])
    )
);

const submitting = ref(false);
function submit() {
    submitting.value = true;
    router.post(props.submitUrl, { ...form }, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-5xl mx-auto">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Notifications Settings') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ $t('Days before/after due dates used for customer, supplier, and cheque notifications.') }}
            </p>

            <form @submit.prevent="submit" class="space-y-6">
                <div v-for="section in sections" :key="section.title" class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
                        {{ section.title }}
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="[name, label] in section.fields" :key="name">
                            <label class="cvr-form-label">
                                {{ label }} <span class="cvr-num-red">*</span>
                            </label>
                            <input
                                v-model="form[name]"
                                type="text"
                                required
                                :placeholder="label"
                                class="cvr-input w-full px-3 py-2 rounded"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="cvr-btn-copper px-5 py-2 rounded-lg text-sm font-medium"
                        :disabled="submitting"
                    >
                        {{ submitting ? $t('Saving…') : $t('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
