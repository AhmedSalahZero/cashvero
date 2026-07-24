<script setup>
/**
 * Auth/ConfirmPassword.vue
 * ------------------------------------------------------------------
 * Served by ConfirmPasswordController@showConfirmForm. Shown when a
 * password-confirmation-protected action is attempted and the
 * confirmation has expired/never happened (Laravel's `password.confirm`
 * middleware, untouched). No "back to login" link here — the user is
 * already authenticated, just re-proving their password mid-session.
 */
import { ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    passwordConfirmUrl: String,
});

const form = useForm({
    password: '',
});

function submit() {
    form.post(props.passwordConfirmUrl, {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
}

const showPassword = ref(false);
</script>

<template>
    <Head title="Confirm Password — CashVero" />
    <AuthLayout>
        <div v-if="Object.keys(form.errors).length" class="zav-alert zav-alert-danger" role="alert">
            <ul>
                <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
            </ul>
        </div>

        <div class="zav-form-header">
            <h2 class="zav-welcome">Confirm your password</h2>
            <div class="zav-welcome-line"></div>
            <p class="zav-welcome-sub">For your security, please confirm your password to continue</p>
        </div>

        <form class="zav-form" @submit.prevent="submit">
            <div class="zav-field">
                <label class="zav-label" for="password">Password</label>
                <div class="zav-input-wrap">
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        class="zav-input zav-input-pw"
                        :class="{ 'is-invalid': form.errors.password }"
                        required
                        autofocus
                    />
                    <button type="button" class="zav-eye-btn" tabindex="-1" @click="showPassword = !showPassword">
                        <svg v-if="!showPassword" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg v-else viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" />
                            <line x1="1" y1="1" x2="23" y2="23" />
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="zav-btn-submit" :disabled="form.processing">
                {{ form.processing ? 'Confirming…' : 'Confirm Password' }}
            </button>
        </form>
    </AuthLayout>
</template>
