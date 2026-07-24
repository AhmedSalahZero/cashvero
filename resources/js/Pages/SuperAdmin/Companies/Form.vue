<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    mode: String, // 'create' | 'edit'
    model: Object, // null in create mode
    systemOptions: Array, // ['cash-vero'] today, built generically
    currencies: Object,
    languages: Array, // [{code, name}] — hardcoded to en/ar, matches the original
    submitUrl: String,
    backUrl: String,
});

const page = usePage();
const isEdit = props.mode === 'edit';

const form = ref({
    name: {
        en: props.model?.name?.en ?? '',
        ar: props.model?.name?.ar ?? '',
    },
    main_functional_currency: props.model?.main_functional_currency ?? 'egp',
    systems: props.model?.systems?.length ? [...props.model.systems] : (props.systemOptions[0] ? [props.systemOptions[0]] : []),
    odoo_db_url: props.model?.odoo_db_url ?? '',
    odoo_db_name: props.model?.odoo_db_name ?? '',
    odoo_integration_start_date: props.model?.odoo_integration_start_date ?? '',
});

// Per-user Odoo credentials — only relevant/shown when editing an
// existing company that already has users attached, matching the
// original exactly (a brand-new company has no users yet).
const userCredentials = ref(
    (props.model?.users ?? []).map(u => ({
        id: u.id,
        name: u.name,
        odoo_username: u.odoo_username ?? '',
        odoo_db_password: u.odoo_db_password ?? '',
    }))
);

const imageFile = ref(null);
function onImageChange(event) {
    imageFile.value = event.target.files[0] ?? null;
}

function toggleSystem(systemName, checked) {
    if (checked) {
        if (!form.value.systems.includes(systemName)) form.value.systems.push(systemName);
    } else {
        form.value.systems = form.value.systems.filter(s => s !== systemName);
    }
}

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        name: form.value.name,
        main_functional_currency: form.value.main_functional_currency,
        systems: form.value.systems,
        odoo_db_url: form.value.odoo_db_url,
        odoo_db_name: form.value.odoo_db_name,
        odoo_integration_start_date: form.value.odoo_integration_start_date,
    };
    if (imageFile.value) payload.image = imageFile.value;
    if (isEdit) {
        const odooUsername = {};
        const odooDbPassword = {};
        userCredentials.value.forEach(u => {
            odooUsername[u.id] = u.odoo_username;
            odooDbPassword[u.id] = u.odoo_db_password;
        });
        payload.odoo_username = odooUsername;
        payload.odoo_db_password = odooDbPassword;
        router.put(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
    }
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    ← Back to Companies
                </Link>
            </div>

            <div v-if="Object.keys(page.props.errors || {}).length" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
                Please fix the highlighted field(s) below before saving.
            </div>

            <form @submit.prevent="submit" class="space-y-6" enctype="multipart/form-data">
                <!-- Sections -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Sections</h2>
                    <div class="cvr-form-grid-2">
                        <div v-for="lang in languages" :key="lang.code">
                            <label class="cvr-form-label">Company Name {{ lang.name }} *</label>
                            <input v-model="form.name[lang.code]" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor(`name.${lang.code}`)" class="text-xs mt-1 cvr-num-red">{{ errorFor(`name.${lang.code}`) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Company Information -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Company Information</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">Systems *</label>
                            <div class="space-y-1">
                                <label v-for="sys in systemOptions" :key="sys" class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        :checked="form.systems.includes(sys)"
                                        @change="toggleSystem(sys, $event.target.checked)"
                                    />
                                    <span class="cvr-text-secondary text-sm uppercase">{{ sys }}</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="cvr-form-label">Main Functional Currency *</label>
                            <select v-model="form.main_functional_currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Company Image</label>
                            <input type="file" @change="onImageChange" class="cvr-input w-full px-3 py-2 rounded" />
                            <img v-if="model?.image_url" :src="model.image_url" class="w-20 h-20 object-cover rounded mt-2" alt="current image" />
                        </div>
                    </div>
                </div>

                <!-- Odoo Integration -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Odoo Integration</h2>
                    <div class="cvr-form-grid-3 mb-4">
                        <div>
                            <label class="cvr-form-label">Database URL</label>
                            <input v-model="form.odoo_db_url" type="text" placeholder="Odoo Database URL" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Database Name</label>
                            <input v-model="form.odoo_db_name" type="text" placeholder="Odoo Database Name" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Integration Start Date</label>
                            <input v-model="form.odoo_integration_start_date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>

                    <!-- Per-user Odoo credentials — edit mode only, and
                         only when the company already has users. -->
                    <div v-if="isEdit && userCredentials.length" class="space-y-3">
                        <h3 class="text-xs font-semibold cvr-text-muted uppercase tracking-wide">Per-User Odoo Credentials</h3>
                        <div v-for="u in userCredentials" :key="u.id" class="cvr-form-grid-3">
                            <div>
                                <label class="cvr-form-label">User</label>
                                <input disabled :value="u.name" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">User Name</label>
                                <input v-model="u.odoo_username" type="text" placeholder="Odoo User Name" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Password / API Key</label>
                                <input v-model="u.odoo_db_password" type="text" placeholder="Odoo Database Password / API Key" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">Cancel</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
