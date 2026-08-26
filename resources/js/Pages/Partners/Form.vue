<script setup>
import { ref, computed } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    mode: String, // 'create' | 'edit'
    company: Object,
    companyHasOdoo: Boolean,
    submitUrl: String,
    backUrl: String,
    partner: Object, // present only in edit mode
});

const page = usePage();

const form = ref({
    // The uniqueness validation rule on the server excludes this exact id
    // from its duplicate-name check (0 on create, matching the original
    // Blade form's hidden `id` input) — required, not just informational.
    id: props.partner?.id ?? 0,
    name: props.partner?.name ?? '',
    is_customer: props.partner?.is_customer ?? false,
    is_supplier: props.partner?.is_supplier ?? false,
    is_employee: props.partner?.is_employee ?? false,
    is_subsidiary_company: props.partner?.is_subsidiary_company ?? false,
    is_other_partner: props.partner?.is_other_partner ?? false,
    is_shareholder: props.partner?.is_shareholder ?? false,
});

/* ── Partner type chips — a proper segmented multi-select instead of
   bare browser checkboxes, using the same pill visual language as the
   filter bar elsewhere in the app so the whole product feels of a
   piece. Each type is independently toggleable (a partner can be both
   a Customer and a Shareholder, for example). */
const typeOptions = [
    { key: 'is_customer', label: 'Customer', icon: '🧾' },
    { key: 'is_supplier', label: 'Supplier', icon: '🚚' },
    { key: 'is_employee', label: 'Employee', icon: '🧑‍💼' },
    { key: 'is_shareholder', label: 'Shareholder', icon: '📈' },
    { key: 'is_subsidiary_company', label: 'Subsidiary Company', icon: '🏢' },
    { key: 'is_other_partner', label: 'Other Partner', icon: '🔗' },
];

function toggleType(key) {
    form.value[key] = !form.value[key];
}

const initials = computed(() => {
    const n = (form.value.name || '?').trim();
    return n ? n.slice(0, 2).toUpperCase() : '?';
});

const submitting = ref(false);

function submit() {
    submitting.value = true;
    const method = props.mode === 'edit' ? 'put' : 'post';
    router[method](props.submitUrl, form.value, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-3xl mx-auto">
            <Link :href="backUrl" class="cvr-back-link inline-flex items-center gap-1 text-xs cvr-text-muted mb-4">
                {{ $t('← Back to Partners') }}
            </Link>

            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <div class="cvr-avatar" style="width: 3rem; height: 3rem; font-size: 1rem;">{{ initials }}</div>
                <div>
                    <h1 class="text-xl font-semibold cvr-text-primary">
                        {{ mode === 'edit' ? $t('Edit Partner') : $t('Add Partner') }}
                    </h1>
                    <p class="text-sm cvr-text-muted">
                        {{ mode === 'edit' ? $t('Update this partner\'s name and roles') : $t('Create a new customer, supplier, or other partner record') }}
                    </p>
                </div>
            </div>

            <!-- Validation errors -->
            <div
                v-if="Object.keys(page.props.errors || {}).length"
                class="mb-5 px-4 py-3 rounded-lg text-sm flex items-start gap-2"
                style="background: var(--cvr-danger-bg); border: 1px solid var(--cvr-danger-border); color: var(--cvr-danger-text);"
            >
                <span class="text-base leading-none">⚠</span>
                <div>
                    <p v-for="(msg, field) in page.props.errors" :key="field">{{ msg }}</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="cvr-card">
                <!-- Section: Basic Information -->
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-base">👤</span>
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">{{ $t('Basic Information') }}</h2>
                </div>

                <div class="mb-2">
                    <label class="cvr-form-label">
                        {{ $t('Partner Name') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        :readonly="companyHasOdoo"
                        :placeholder="$t('e.g. Acme Trading Co.')"
                        class="cvr-input w-full px-3 py-2.5 rounded-lg text-sm"
                        :class="{ 'opacity-70 cursor-not-allowed': companyHasOdoo }"
                    />
                    <p v-if="companyHasOdoo" class="text-xs cvr-text-muted mt-1.5 flex items-center gap-1">
                        <span>🔗</span> {{ $t('Synced from Odoo — the name can\'t be edited here.') }}
                    </p>
                    <p v-if="page.props.errors?.name" class="text-xs mt-1.5" style="color: var(--cvr-danger-text);">
                        {{ page.props.errors.name }}
                    </p>
                </div>

                <hr class="cvr-divider my-6" />

                <!-- Section: Partner Type -->
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-base">🏷️</span>
                    <h2 class="text-sm font-semibold uppercase tracking-wide cvr-text-secondary">
                        {{ $t('Partner Type') }} <span class="text-red-500">*</span>
                    </h2>
                </div>
                <p class="text-xs cvr-text-muted mb-4">{{ $t('Select every role that applies — a partner can hold more than one.') }}</p>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="opt in typeOptions"
                        :key="opt.key"
                        type="button"
                        @click="toggleType(opt.key)"
                        class="cvr-filter-pill inline-flex items-center gap-1.5 !px-3.5 !py-2"
                        :class="{ 'cvr-filter-pill-active': form[opt.key] }"
                        style="border-width: 1px;"
                    >
                        <span>{{ opt.icon }}</span>
                        <span>{{ $t(opt.label) }}</span>
                        <span v-if="form[opt.key]" class="text-xs">✓</span>
                    </button>
                </div>
                <p v-if="page.props.errors?.partner_type" class="text-xs mt-2" style="color: var(--cvr-danger-text);">
                    {{ page.props.errors.partner_type }}
                </p>

                <hr class="cvr-divider my-6" />

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded-lg border text-sm">{{ $t('Cancel') }}</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-copper px-5 py-2 rounded-lg text-sm font-medium disabled:opacity-60">
                        {{ submitting ? $t('Saving…') : (mode === 'edit' ? $t('Save Changes') : $t('Create Partner')) }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.cvr-back-link {
    transition: var(--cvr-transition);
}
.cvr-back-link:hover {
    color: var(--cvr-text-primary);
}
</style>
