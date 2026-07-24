<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    mode: String, // 'create' | 'edit'
    company: Object, // null if not scoped to a company
    model: Object, // null in create mode
    companies: Array, // [{id, name}]
    roleOptions: Array, // [{value, label}] — already gated server-side
    submitUrl: String,
    backUrl: String,
});

const page = usePage();
const isEdit = props.mode === 'edit';

const form = ref({
    name: props.model?.name ?? '',
    email: props.model?.email ?? '',
    password: '',
    password_confirmation: '',
    companies: props.model?.company_ids?.length ? [...props.model.company_ids] : [],
    role: props.model?.role ?? '',
    max_users: props.model?.max_users ?? 10,
});

const avatarFile = ref(null);
function onAvatarChange(event) {
    avatarFile.value = event.target.files[0] ?? null;
}

// "Max Users Allowed" only matters when Role = Company Admin —
// matches the original's JS fadeIn/fadeOut toggle exactly.
const showMaxUsers = computed(() => form.value.role === 'company-admin');

function toggleCompany(id, checked) {
    if (checked) {
        if (!form.value.companies.includes(id)) form.value.companies.push(id);
    } else {
        form.value.companies = form.value.companies.filter(c => c !== id);
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
        email: form.value.email,
        companies: form.value.companies,
        role: form.value.role,
    };
    if (!isEdit) {
        payload.password = form.value.password;
        payload.password_confirmation = form.value.password_confirmation;
    }
    if (showMaxUsers.value) payload.max_users = form.value.max_users;
    if (avatarFile.value) payload.avatar = avatarFile.value;

    if (isEdit) {
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
                    ← Back to Users
                </Link>
            </div>

            <div v-if="Object.keys(page.props.errors || {}).length" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
                Please fix the highlighted field(s) below before saving.
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Users</h2>
                    <label class="cvr-form-label">Name *</label>
                    <input v-model="form.name" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                    <p v-if="errorFor('name')" class="text-xs mt-1 cvr-num-red">{{ errorFor('name') }}</p>
                </div>

                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">User Information</h2>
                    <div class="cvr-form-grid-2 mb-4">
                        <div>
                            <label class="cvr-form-label">Email *</label>
                            <input v-model="form.email" type="email" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('email')" class="text-xs mt-1 cvr-num-red">{{ errorFor('email') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">User Image *</label>
                            <input type="file" @change="onAvatarChange" class="cvr-input w-full px-3 py-2 rounded" />
                            <img v-if="model?.avatar_url" :src="model.avatar_url" class="w-16 h-16 object-cover rounded-full mt-2" alt="current avatar" />
                        </div>
                    </div>
                    <div v-if="!isEdit" class="cvr-form-grid-2">
                        <div>
                            <label class="cvr-form-label">Password *</label>
                            <input v-model="form.password" type="password" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('password')" class="text-xs mt-1 cvr-num-red">{{ errorFor('password') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Confirm Password *</label>
                            <input v-model="form.password_confirmation" type="password" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>
                </div>

                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Assign Companies To This User</h2>
                    <div class="cvr-form-grid-2">
                        <div>
                            <label class="cvr-form-label">Select Companies — (Multi Selection) *</label>
                            <div class="space-y-1 max-h-48 overflow-y-auto">
                                <label v-for="c in companies" :key="c.id" class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        :checked="form.companies.includes(c.id)"
                                        @change="toggleCompany(c.id, $event.target.checked)"
                                    />
                                    <span class="cvr-text-secondary text-sm">{{ c.name }}</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="cvr-form-label">Role *</label>
                            <select v-model="form.role" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="" disabled>Select</option>
                                <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <p v-if="errorFor('role')" class="text-xs mt-1 cvr-num-red">{{ errorFor('role') }}</p>
                            <div v-if="showMaxUsers" class="mt-3">
                                <label class="cvr-form-label">Max Users Allowed *</label>
                                <input v-model="form.max_users" type="number" class="cvr-input w-full px-3 py-2 rounded" />
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
