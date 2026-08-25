<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
    company: Object,
    roles: Array,            // [{ id, name, label, permissions_count, users_count, is_protected, is_super_admin, edit_url, delete_url }]
    totalPermissions: Number,
    createUrl: String,
});

const { can } = usePermissions();

const deleteTarget = ref(null);
function confirmDelete(role) { deleteTarget.value = role; }
function cancelDelete() { deleteTarget.value = null; }
function destroyRole() {
    router.delete(deleteTarget.value.delete_url, {
        onFinish: () => { deleteTarget.value = null; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center justify-between mb-6 gap-3 flex-wrap">
                <div>
                    <h1 class="text-xl font-semibold cvr-text-primary">{{ $t('Roles & Permission Templates') }}</h1>
                    <p class="text-sm cvr-text-muted mt-1 max-w-2xl">
                        {{ $t('Permissions in this application are set') }} <strong class="cvr-text-primary">{{ $t('per user') }}</strong>{{ $t('. A role is a template: its permissions are copied onto a user when the account is created, or when an admin applies it from that user\'s permission screen.') }}
                        <strong class="cvr-text-primary">{{ $t('Editing a template here does not change anyone who already exists') }}</strong> {{ $t('— it changes what future users start with.') }}
                    </p>
                </div>
                <Link
                    v-if="can('role.create')"
                    :href="createUrl"
                    class="cvr-btn-primary px-4 py-2 rounded"
                >
                    {{ $t('New Role') }}
                </Link>
            </div>

            <div class="cvr-card overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ $t('Role') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Template size') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Users with this role') }}</th>
                            <th v-if="can('role.update') || can('role.delete')" class="px-4 py-3 text-center">{{ $t('Control') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="role in roles" :key="role.id" class="cvr-table-row">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold cvr-text-primary">{{ role.label }}</span>
                                    <span v-if="role.is_protected" class="text-[11px] px-2 py-0.5 rounded cvr-badge-muted">{{ $t('Built-in') }}</span>
                                </div>
                                <div class="text-xs cvr-text-muted mt-0.5 font-mono">{{ role.name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="role.is_super_admin" class="cvr-text-secondary">
                                    {{ $t('All permissions') }} <span class="cvr-text-muted">{{ $t('(bypass)') }}</span>
                                </span>
                                <span v-else class="cvr-text-secondary tabular-nums">
                                    {{ role.permissions_count }} <span class="cvr-text-muted">/ {{ totalPermissions }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 tabular-nums cvr-text-secondary">{{ role.users_count }}</td>
                            <td v-if="can('role.update') || can('role.delete')" class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <Link
                                        v-if="can('role.update')"
                                        :href="role.edit_url"
                                        class="cvr-action-btn"
                                        :title="$t('Edit permissions')"
                                    >✎</Link>
                                    <button
                                        v-if="can('role.delete') && !role.is_protected"
                                        @click="confirmDelete(role)"
                                        class="cvr-action-btn cvr-action-btn-danger"
                                        :title="$t('Delete role')"
                                    >🗑</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="roles.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No roles yet.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-card p-6 max-w-md w-full mx-4">
                    <h2 class="text-lg font-medium cvr-text-primary mb-2">Delete the “{{ deleteTarget.label }}” role?</h2>
                    <p class="text-sm cvr-text-muted mb-4">
                        {{ $t('This cannot be undone. Users must be moved to another role first.') }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <button @click="cancelDelete" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Cancel') }}</button>
                        <button @click="destroyRole" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Delete Role') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
