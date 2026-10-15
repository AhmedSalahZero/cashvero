<script setup>
/**
 * Auth/Login.vue
 * ------------------------------------------------------------------
 * Served by LoginController@showLoginForm. A pixel-faithful rebuild
 * of the existing 'auth-cashvero' Blade design — same two-panel
 * layout, same colors, same copy, nothing restyled.
 *
 * The shared branding panel + shell markup/CSS now live in
 * Layouts/AuthLayout.vue (see that file's own docblock) — this page
 * only contains what's actually specific to login: the form itself.
 *
 * Two small vanilla-JS behaviors from the original (password
 * show/hide toggle, custom checkbox for "Remember me") are rebuilt
 * as plain Vue reactive state instead of DOM query/classList
 * manipulation — same visual result, idiomatic Vue underneath.
 *
 * Submits via Inertia's useForm() (the same pattern already used by
 * every other form on this app, e.g. Balances/InvoiceReport.vue),
 * posting to the untouched `login` route — LoginController@login and
 * everything it calls (validateLogin, attemptLogin, authenticated(),
 * etc.) is completely UNCHANGED by this migration.
 */
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    status: String,
    expiredLogin: String,
    loginUrl: String,
    passwordRequestUrl: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(props.loginUrl, {
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
}

const showPassword = ref(false);
</script>

<template>
    <Head :title="$t('Sign In — CashVero')" />
    <AuthLayout>
        <div v-if="status" class="zav-alert zav-alert-success" role="alert">{{ status }}</div>
        <div v-if="expiredLogin" class="zav-alert zav-alert-danger" role="alert">{{ expiredLogin }}</div>
        <div v-if="Object.keys(form.errors).length" class="zav-alert zav-alert-danger" role="alert">
            <ul>
                <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
            </ul>
        </div>

        <div class="zav-form-header">
            <h2 class="zav-welcome">{{ $t('Welcome back') }}</h2>
            <div class="zav-welcome-line"></div>
            <p class="zav-welcome-sub">{{ $t('Sign in to your workspace to continue') }}</p>
        </div>

        <form class="zav-form" @submit.prevent="submit">
            <div class="zav-field">
                <label class="zav-label" for="email">{{ $t('Email Address') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    placeholder="your@email.com"
                    autocomplete="username"
                    class="zav-input"
                    :class="{ 'is-invalid': form.errors.email }"
                    required
                    autofocus
                />
            </div>

            <div class="zav-field">
                <div class="zav-pw-label-row">
                    <label class="zav-label" for="password">{{ $t('Password') }}</label>
                    <Link :href="passwordRequestUrl" class="zav-forgot">{{ $t('Forgot password?') }}</Link>
                </div>
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

            <div class="zav-remember" @click="form.remember = !form.remember">
                <div class="zav-checkbox" :class="{ checked: form.remember }">
                    <svg viewBox="0 0 24 24" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                </div>
                <span class="zav-remember-text">{{ $t('Remember me for 30 days') }}</span>
            </div>

            <button type="submit" class="zav-btn-submit" :disabled="form.processing">
                {{ form.processing ? $t('Signing In…') : $t('Sign In to CashVero') }}
            </button>
        </form>

        <div class="zav-access-note">
            <div class="zav-access-divider">
                <span class="zav-access-line"></span>
                <span class="zav-access-text">{{ $t('invitation only') }}</span>
                <span class="zav-access-line"></span>
            </div>
            <p class="zav-access-copy">
                {{ $t('Access is by invitation only.') }}<br />
                {{ $t('Contact your administrator if you need access.') }}
            </p>
        </div>
    </AuthLayout>
</template>
