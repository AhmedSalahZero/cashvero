<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object, // null if not scoped to a company
    user: Object, // { id, name, email }
    groups: Array, // [{ name, permissions: [{name, label, checked}] }]
    submitUrl: String,
    backUrl: String,
});

const page = usePage();

const allChecked = computed(() =>
    props.groups.every(g => g.permissions.every(p => p.checked))
);
function toggleAll(checked) {
    props.groups.forEach(g => g.permissions.forEach(p => { p.checked = checked; }));
}

function groupAllChecked(group) {
    return group.permissions.every(p => p.checked);
}
function toggleGroup(group, checked) {
    group.permissions.forEach(p => { p.checked = checked; });
}

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const permissions = {};
    props.groups.forEach(g => g.permissions.forEach(p => {
        if (p.checked) permissions[p.name] = 1;
    }));
    router.post(props.submitUrl, { permissions }, { onFinish: () => { submitting.value = false; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    ← Back to Users
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">
                Edit Permission For {{ user.name }} [ {{ user.email }} ]
            </h1>

            <form @submit.prevent="submit" class="space-y-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" :checked="allChecked" @change="toggleAll($event.target.checked)" />
                    <span class="font-semibold cvr-text-primary">Select All</span>
                </label>

                <div class="cvr-card">
                    <table class="min-w-full text-sm">
                        <thead class="cvr-table-head">
                            <tr>
                                <th class="px-3 py-2 text-left">Name</th>
                                <th class="px-3 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in groups" :key="group.name" class="cvr-table-row align-top">
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <label class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            :checked="groupAllChecked(group)"
                                            @change="toggleGroup(group, $event.target.checked)"
                                        />
                                        <span class="font-semibold capitalize cvr-text-primary">{{ group.name }}</span>
                                    </label>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                                        <label v-for="perm in group.permissions" :key="perm.name" class="flex items-center gap-2">
                                            <input type="checkbox" v-model="perm.checked" />
                                            <span class="cvr-text-secondary text-sm capitalize">{{ perm.label }}</span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
