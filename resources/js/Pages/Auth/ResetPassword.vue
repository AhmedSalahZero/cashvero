<script setup>
/**
 * Auth/ResetPassword.vue
 * ------------------------------------------------------------------
 * Served by ResetPasswordController@showResetForm(Request $request,
 * $token = null) — same signature Laravel's default trait already
 * uses, UNCHANGED. `token` comes from the URL path, `email` from the
 * `?email=` query string the reset-link email itself carries — both
 * pre-filled here exactly as the original Blade view did (email
 * field read-only, since it's what the emailed link was generated
 * for, not user-editable).
 */
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    token: String,
    email: String,
    passwordUpdateUrl: String,
    loginUrl: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(props.passwordUpdateUrl, {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
</script>

<template>
    <Head :title="$t('Reset Password — CashVero')" />
    <AuthLayout>
        <Link :href="loginUrl" class="zav-back-link">{{ $t('← Back to sign in') }}</Link>

        <div v-if="Object.keys(form.errors).length" class="zav-alert zav-alert-danger" role="alert">
            <ul>
                <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
            </ul>
        </div>

        <div class="zav-form-header">
            <h2 class="zav-welcome">{{ $t('Set a new password') }}</h2>
            <div class="zav-welcome-line"></div>
            <p class="zav-welcome-sub">{{ $t('Choose a new password for your account') }}</p>
        </div>

        <form class="zav-form" @submit.prevent="submit">
            <div class="zav-field">
                <label class="zav-label" for="email">{{ $t('Email Address') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="zav-input"
                    :class="{ 'is-invalid': form.errors.email }"
                    disabled
                />
            </div>

            <div class="zav-field">
                <label class="zav-label" for="password">{{ $t('New Password') }}</label>
                <div class="zav-input-wrap">
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="••••••••"
                        autocomplete="new-password"
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

            <div class="zav-field">
                <label class="zav-label" for="password_confirmation">{{ $t('Confirm New Password') }}</label>
                <div class="zav-input-wrap">
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="showPasswordConfirmation ? 'text' : 'password'"
                        placeholder="••••••••"
                        autocomplete="new-password"
                        class="zav-input zav-input-pw"
                        :class="{ 'is-invalid': form.errors.password_confirmation }"
                        required
                    />
                    <button type="button" class="zav-eye-btn" tabindex="-1" @click="showPasswordConfirmation = !showPasswordConfirmation">
                        <svg v-if="!showPasswordConfirmation" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                {{ form.processing ? $t('Resetting…') : $t('Reset Password') }}
            </button>
        </form>
    </AuthLayout>
</template>
