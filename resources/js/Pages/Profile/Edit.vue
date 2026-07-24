<script setup>
/**
 * Profile/Edit.vue
 * ------------------------------------------------------------------
 * Served by ProfileController@edit. Previously view('profile.form',
 * ...). update()'s validation/business logic (name/email/avatar,
 * conditional Odoo credentials, Odoo id refresh) is UNCHANGED — only
 * the presentation layer moved to Inertia/Vue, same as every other
 * migrated page in this app.
 *
 * File upload follows the exact same pattern already established in
 * SuperAdmin/Users/Form.vue: a plain <input type="file"> read via
 * @change into a ref, included in the payload only if a new file was
 * actually chosen, and router.put() (which detects the File object
 * and switches to multipart/form-data + method-spoofing
 * automatically — no manual FormData handling needed).
 */
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    user: Object, // { name, email, avatar_url, odoo_username, odoo_db_password }
    hasOdooCredentials: Boolean,
    submitUrl: String,
});

const page = usePage();

const name = ref(props.user.name);
const email = ref(props.user.email);
const odooUsername = ref(props.user.odoo_username ?? '');
const odooDbPassword = ref(props.user.odoo_db_password ?? '');

const avatarFile = ref(null);
const avatarPreview = ref(props.user.avatar_url);
function onAvatarChange(event) {
    const file = event.target.files[0] ?? null;
    avatarFile.value = file;
    avatarPreview.value = file ? URL.createObjectURL(file) : props.user.avatar_url;
}

function errorFor(field) {
    return page.props.errors?.[field] ?? null;
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        name: name.value,
        email: email.value,
    };
    if (hasOdooCredentials) {
        payload.odoo_username = odooUsername.value;
        payload.odoo_db_password = odooDbPassword.value;
    }
    if (avatarFile.value) payload.avatar = avatarFile.value;

    router.put(props.submitUrl, payload, { onFinish: () => { submitting.value = false; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-3xl mx-auto">
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">My Profile</h1>

            <div v-if="Object.keys(page.props.errors || {}).length" class="mb-5 px-4 py-3 rounded-lg text-sm flex items-start gap-2"
                style="background: var(--cvr-danger-bg); border: 1px solid var(--cvr-danger-border); color: var(--cvr-danger-text);">
                <span class="text-base leading-none">⚠</span>
                <div>
                    <p v-for="(msg, field) in page.props.errors" :key="field">{{ msg }}</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                    <label class="cvr-form-label">Name *</label>
                    <input v-model="name" type="text" required class="cvr-input w-full px-3 py-2 rounded" />
                    <p v-if="errorFor('name')" class="text-xs mt-1 cvr-num-red">{{ errorFor('name') }}</p>
                </div>

                <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">User Information</h2>
                    <div class="cvr-form-grid-2">
                        <div>
                            <label class="cvr-form-label">Email *</label>
                            <input v-model="email" type="email" required class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('email')" class="text-xs mt-1 cvr-num-red">{{ errorFor('email') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">User Image</label>
                            <img v-if="avatarPreview" :src="avatarPreview" class="w-20 h-20 object-cover rounded-full mb-2" alt="current avatar" />
                            <input type="file" accept="image/*" @change="onAvatarChange" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('avatar')" class="text-xs mt-1 cvr-num-red">{{ errorFor('avatar') }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="hasOdooCredentials" class="cvr-card-bg cvr-border border rounded-lg p-5">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Odoo Credentials</h2>
                    <div class="cvr-form-grid-2">
                        <div>
                            <label class="cvr-form-label">Odoo User Name</label>
                            <input v-model="odooUsername" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('odoo_username')" class="text-xs mt-1 cvr-num-red">{{ errorFor('odoo_username') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Odoo Database Password / API Key</label>
                            <input v-model="odooDbPassword" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errorFor('odoo_db_password')" class="text-xs mt-1 cvr-num-red">{{ errorFor('odoo_db_password') }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded disabled:opacity-50">
                        {{ submitting ? 'Saving…' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
