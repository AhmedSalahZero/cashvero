<script setup>
import { ref, computed, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

/**
 * Per-user permissions — the screen where access is actually decided.
 *
 * This application is user-based: nothing here is inherited from the
 * role, so every box is editable. The role only supplied a starting
 * point when the account was created; "Start from a template" re-fills
 * the form from a role on demand, but changes nothing until Save.
 */
const props = defineProps({
    company: Object,
    user: Object,             // { id, name, email, role, is_super_admin, permissions_count }
    tree: Array,              // [{ key, label, modules: [{ key, label, permissions: [{key, action, label}] }] }]
    selected: Array,          // permission keys this user currently holds
    grantable: Array,         // keys the editor may toggle
    isSuperAdminEditor: Boolean,
    totalPermissions: Number,
    templates: Array,         // [{ name, label, keys: [...] }]
    submitUrl: String,
    backUrl: String,
});

/* ── state ──────────────────────────────────────────────────────── */

const checked = reactive(new Set(props.selected ?? []));
const grantableSet = computed(() => new Set(props.grantable ?? []));
const search = ref('');
const collapsed = reactive({});
const submitting = ref(false);
const appliedTemplate = ref('');

/* ── permission helpers ─────────────────────────────────────────── */

function canToggle(key) {
    if (props.user.is_super_admin) return false;
    if (props.isSuperAdminEditor) return true;
    // May grant only what the editor holds; may always leave alone
    // something this user already has.
    return grantableSet.value.has(key) || checked.has(key);
}

function isChecked(key) { return checked.has(key); }

function toggle(key, value) {
    if (!canToggle(key)) return;
    value ? checked.add(key) : checked.delete(key);
}

/* ── search / filtering ─────────────────────────────────────────── */

const query = computed(() => search.value.trim().toLowerCase());

function moduleMatches(module, group) {
    if (!query.value) return true;
    const haystack = [
        group.label, module.label, module.key,
        ...module.permissions.map((p) => `${p.label} ${p.key}`),
    ].join(' ').toLowerCase();
    return haystack.includes(query.value);
}

const filteredTree = computed(() =>
    props.tree
        .map((group) => ({ ...group, modules: group.modules.filter((m) => moduleMatches(m, group)) }))
        .filter((group) => group.modules.length > 0),
);

/* ── bulk selection ─────────────────────────────────────────────── */

function keysOf(scope) {
    if (Array.isArray(scope)) return scope.flatMap(keysOf);
    if (scope.modules) return scope.modules.flatMap((m) => m.permissions.map((p) => p.key));
    if (scope.permissions) return scope.permissions.map((p) => p.key);
    return [];
}

function setMany(keys, value) { keys.forEach((k) => toggle(k, value)); }
function allSelected(keys) { return keys.length > 0 && keys.every((k) => checked.has(k)); }
function someSelected(keys) { return keys.some((k) => checked.has(k)) && !allSelected(keys); }
function countSelected(keys) { return keys.filter((k) => checked.has(k)).length; }

const visibleKeys = computed(() => keysOf(filteredTree.value));
const totalKeys = computed(() => keysOf(props.tree));

/* ── templates ──────────────────────────────────────────────────── */

/**
 * Fill the form from a role's template. Replaces the current selection
 * so the result is exactly that template — anything the editor may not
 * grant is skipped by toggle().
 */
function applyTemplate(name) {
    const template = props.templates.find((t) => t.name === name);
    if (!template) return;
    setMany(totalKeys.value, false);
    template.keys.forEach((k) => toggle(k, true));
    appliedTemplate.value = name;
}

/* ── collapse ───────────────────────────────────────────────────── */

function isCollapsed(groupKey) {
    // Searching expands everything so matches are never hidden.
    if (query.value) return false;
    return Boolean(collapsed[groupKey]);
}
function toggleCollapse(groupKey) { collapsed[groupKey] = !collapsed[groupKey]; }
function expandAll() { Object.keys(collapsed).forEach((k) => { collapsed[k] = false; }); }
function collapseAll() { props.tree.forEach((g) => { collapsed[g.key] = true; }); }

/* ── submit ─────────────────────────────────────────────────────── */

function submit() {
    submitting.value = true;
    router.post(props.submitUrl, {
        permissions: Array.from(checked),
        back_url: props.backUrl,
    }, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-4">
                ← Back to Users
            </Link>

            <h1 class="text-xl font-semibold cvr-text-primary mb-1">
                Permissions for {{ user.name }}
            </h1>
            <p class="text-sm cvr-text-muted mb-1">{{ user.email }}</p>
            <p class="text-sm cvr-text-muted mb-6">
                Role:
                <strong class="cvr-text-primary capitalize">{{ user.role ? user.role.replace('-', ' ') : 'none' }}</strong>
                — a label and a starting template only. What this person can do is decided entirely by the boxes below.
            </p>

            <div v-if="user.is_super_admin" class="cvr-card p-4 mb-6">
                <p class="text-sm cvr-text-secondary">
                    <strong class="cvr-text-primary">This user is a Super Admin</strong> and passes every permission check
                    through a centralised bypass, so nothing selected here changes what they can do. Give them a different
                    role to limit them.
                </p>
            </div>

            <form v-else @submit.prevent="submit" class="space-y-5">
                <!-- ── Toolbar ── -->
                <div class="cvr-card p-4 flex flex-wrap items-center gap-3">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search modules and actions…"
                        class="cvr-input flex-1 min-w-[14rem] px-3 py-2 rounded border"
                    />
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" @click="setMany(visibleKeys, true)" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                            Select {{ query ? 'shown' : 'all' }}
                        </button>
                        <button type="button" @click="setMany(visibleKeys, false)" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                            Clear {{ query ? 'shown' : 'all' }}
                        </button>
                        <button type="button" @click="expandAll" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">Expand</button>
                        <button type="button" @click="collapseAll" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">Collapse</button>
                    </div>
                    <span class="text-sm cvr-text-muted tabular-nums whitespace-nowrap">
                        {{ countSelected(totalKeys) }} / {{ totalKeys.length }} selected
                    </span>
                </div>

                <!-- ── Template shortcut ── -->
                <div v-if="templates?.length" class="cvr-card p-4 flex flex-wrap items-center gap-3">
                    <span class="text-sm cvr-text-secondary">Start from a template:</span>
                    <button
                        v-for="t in templates"
                        :key="t.name"
                        type="button"
                        @click="applyTemplate(t.name)"
                        class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm"
                        :class="{ 'cvr-filter-pill-active': appliedTemplate === t.name }"
                    >
                        {{ t.label }}
                        <span class="cvr-text-muted tabular-nums">({{ t.keys.length }})</span>
                    </button>
                    <span class="text-xs cvr-text-muted basis-full">
                        Fills the boxes below from that role. Nothing is saved until you press Save, and this user stays
                        unlinked from the template afterwards.
                    </span>
                </div>

                <!-- ── Permission matrix ── -->
                <div class="space-y-3">
                    <div v-for="group in filteredTree" :key="group.key" class="cvr-card overflow-hidden">
                        <div class="flex items-center gap-3 px-4 py-3 cvr-table-head">
                            <button
                                type="button"
                                @click="toggleCollapse(group.key)"
                                class="cvr-action-btn"
                                :aria-expanded="!isCollapsed(group.key)"
                                :title="isCollapsed(group.key) ? 'Expand' : 'Collapse'"
                            >{{ isCollapsed(group.key) ? '▸' : '▾' }}</button>

                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :checked="allSelected(keysOf(group))"
                                    :indeterminate.prop="someSelected(keysOf(group))"
                                    @change="setMany(keysOf(group), $event.target.checked)"
                                />
                                <span class="font-semibold cvr-text-primary">{{ group.label }}</span>
                            </label>

                            <span class="text-xs cvr-text-muted tabular-nums ms-auto">
                                {{ countSelected(keysOf(group)) }} / {{ keysOf(group).length }}
                            </span>
                        </div>

                        <table v-show="!isCollapsed(group.key)" class="min-w-full text-sm">
                            <tbody>
                                <tr v-for="module in group.modules" :key="module.key" class="cvr-table-row align-top">
                                    <td class="px-4 py-3 w-64">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                :checked="allSelected(keysOf(module))"
                                                :indeterminate.prop="someSelected(keysOf(module))"
                                                @change="setMany(keysOf(module), $event.target.checked)"
                                            />
                                            <span class="font-medium cvr-text-primary">{{ module.label }}</span>
                                        </label>
                                        <!-- Only modules whose meaning is not
                                             self-evident carry a hint. -->
                                        <p v-if="module.hint" class="text-xs cvr-text-muted mt-1 leading-snug">
                                            {{ module.hint }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                                            <label
                                                v-for="perm in module.permissions"
                                                :key="perm.key"
                                                class="flex items-center gap-2"
                                                :class="canToggle(perm.key) ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed'"
                                                :title="canToggle(perm.key) ? perm.key : `You cannot grant ${perm.key} — you do not hold it yourself.`"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :checked="isChecked(perm.key)"
                                                    :disabled="!canToggle(perm.key)"
                                                    @change="toggle(perm.key, $event.target.checked)"
                                                />
                                                <span class="cvr-text-secondary">{{ perm.label }}</span>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="filteredTree.length === 0" class="cvr-card p-8 text-center cvr-text-muted">
                        Nothing matches “{{ search }}”.
                    </div>
                </div>

                <p v-if="!isSuperAdminEditor" class="text-xs cvr-text-muted">
                    Greyed-out actions are ones you do not hold yourself, so you cannot grant them.
                </p>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">Cancel</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? 'Saving…' : 'Save Permissions' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
