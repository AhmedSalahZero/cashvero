<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

/**
 * Shown when a signed-in account holds no permissions at all.
 *
 * With user-based permissions this is a normal state for a newly
 * created user — the account exists, but an administrator has not
 * configured what it may do yet. Without this page they would be
 * redirected into a dashboard they cannot open and meet a bare 403.
 */
defineProps({
    userName: String,
    roleName: String,
});

const page = usePage();

function logout() {
    router.post(page.props.logoutUrl);
}
</script>

<template>
    <AppLayout>
        <div class="p-6 flex justify-center">
            <div class="cvr-card p-8 max-w-xl w-full">
                <h1 class="text-xl font-semibold cvr-text-primary mb-3">
                    {{ $t('No permissions have been set for your account yet') }}
                </h1>

                <p class="text-sm cvr-text-secondary mb-4">
                    {{ $t('You are signed in as') }} <strong class="cvr-text-primary">{{ userName }}</strong
                    ><span v-if="roleName"> {{ $t('with the') }} <strong class="cvr-text-primary capitalize">{{ roleName.replace('-', ' ') }}</strong> {{ $t('role') }}</span>{{ $t(', but no permissions are assigned to you, so there is nothing you can open.') }}
                </p>

                <p class="text-sm cvr-text-muted mb-6">
                    {{ $t('Ask an administrator to open') }}
                    <strong class="cvr-text-primary">{{ $t('Users → your account → the permissions icon') }}</strong>
                    {{ $t('and select what you should be able to do. Access takes effect on your next page load.') }}
                </p>

                <div class="flex gap-2">
                    <Link href="/" class="cvr-btn-primary px-4 py-2 rounded">{{ $t('Try again') }}</Link>
                    <button @click="logout" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Sign out') }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
