<script setup>
/**
 * Auth/Register.vue
 * ------------------------------------------------------------------
 * Served by RegisterController@showRegistrationForm. Same shell as
 * Login.vue (via Layouts/AuthLayout.vue), same form-field styling —
 * this is a genuinely new page (the original had no custom Blade
 * design for this one, just Laravel's stock auth.register scaffold
 * view), built to match the rest of the auth flow rather than the
 * plain unstyled original.
 *
 * RegisterController::validator()/create() are UNCHANGED — this only
 * replaces how the form reaches the browser. Note the same "Access is
 * by invitation only" messaging shown on Login.vue: self-registration
 * is technically still wired up (Auth::routes() default), but this
 * page is reachable only by someone who already knows the /register
 * URL — nothing in the app links to it.
 */
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    registerUrl: String,
    loginUrl: String,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(props.registerUrl, {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
</script>

<template>
    <Head title="Create Account — CashVero" />
    <AuthLayout>
        <Link :href="loginUrl" class="zav-back-link">← Back to sign in</Link>

        <div v-if="Object.keys(form.errors).length" class="zav-alert zav-alert-danger" role="alert">
            <ul>
                <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
            </ul>
        </div>

        <div class="zav-form-header">
            <h2 class="zav-welcome">Create your account</h2>
            <div class="zav-welcome-line"></div>
            <p class="zav-welcome-sub">Set up access to your CashVero workspace</p>
        </div>

        <form class="zav-form" @submit.prevent="submit">
            <div class="zav-field">
                <label class="zav-label" for="name">Full Name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    placeholder="Your name"
                    autocomplete="name"
                    class="zav-input"
                    :class="{ 'is-invalid': form.errors.name }"
                    required
                    autofocus
                />
            </div>

            <div class="zav-field">
                <label class="zav-label" for="email">Email Address</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    placeholder="your@email.com"
                    autocomplete="username"
                    class="zav-input"
                    :class="{ 'is-invalid': form.errors.email }"
                    required
                />
            </div>

            <div class="zav-field">
                <label class="zav-label" for="password">Password</label>
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
                <label class="zav-label" for="password_confirmation">Confirm Password</label>
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
                {{ form.processing ? 'Creating Account…' : 'Create Account' }}
            </button>
        </form>
    </AuthLayout>
</template>
