<script setup>
/**
 * Auth/ForgotPassword.vue
 * ------------------------------------------------------------------
 * Served by ForgotPasswordController@showLinkRequestForm. Same shell
 * as Login.vue. ForgotPasswordController itself is UNCHANGED — the
 * untouched SendsPasswordResetEmails trait handles sending the reset
 * link and flashes `status` on success, same as Laravel's default.
 */
import { useForm, Head, Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    status: String,
    passwordEmailUrl: String,
    loginUrl: String,
});

const form = useForm({
    email: '',
});

function submit() {
    form.post(props.passwordEmailUrl, { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('Forgot Password — CashVero')" />
    <AuthLayout>
        <Link :href="loginUrl" class="zav-back-link">{{ $t('← Back to sign in') }}</Link>

        <div v-if="status" class="zav-alert zav-alert-success" role="alert">{{ status }}</div>
        <div v-if="Object.keys(form.errors).length" class="zav-alert zav-alert-danger" role="alert">
            <ul>
                <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
            </ul>
        </div>

        <div class="zav-form-header">
            <h2 class="zav-welcome">{{ $t('Reset your password') }}</h2>
            <div class="zav-welcome-line"></div>
            <p class="zav-welcome-sub">{{ $t('We\'ll email you a link to reset it') }}</p>
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

            <button type="submit" class="zav-btn-submit" :disabled="form.processing">
                {{ form.processing ? $t('Sending…') : $t('Send Password Reset Link') }}
            </button>
        </form>
    </AuthLayout>
</template>
