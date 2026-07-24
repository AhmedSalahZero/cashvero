<script setup>
/**
 * MultiSelectDropdown.vue
 * ------------------------------------------------------------------
 * A checkbox-list dropdown standing in for native <select multiple>,
 * which renders as a permanently-open listbox with browser-native
 * (non-themeable) selection highlighting — that's what looked broken
 * in both dark and light mode. This closes by default, opens on
 * click, and includes Select All / Deselect All.
 *
 * Self-contained on purpose — NOT built on top of the shared
 * Dropdown.vue component. That component closes on any click inside
 * its content, which is exactly right for action menus ("click Edit
 * → close") but would close this after every single checkbox click.
 * Changing Dropdown.vue's behavior to suit this would have risked
 * every other action-menu usage across the app; a small dedicated
 * component was the safer fix.
 *
 * Usage:
 *   <MultiSelectDropdown v-model="selectedIds" :options="[{value, label}, ...]" placeholder="Select" />
 */
import { ref, computed, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] }, // [{ value, label }]
    placeholder: { type: String, default: 'Select' },
});
const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const rootRef = ref(null);

function toggleOpen() {
    open.value = !open.value;
}
function close() {
    open.value = false;
}
function handleClickOutside(e) {
    if (rootRef.value && !rootRef.value.contains(e.target)) {
        close();
    }
}
document.addEventListener('click', handleClickOutside, true);
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside, true));

function isChecked(value) {
    return props.modelValue.includes(value);
}
function toggleValue(value) {
    const next = isChecked(value)
        ? props.modelValue.filter(v => v !== value)
        : [...props.modelValue, value];
    emit('update:modelValue', next);
}
function selectAll() {
    emit('update:modelValue', props.options.map(o => o.value));
}
function deselectAll() {
    emit('update:modelValue', []);
}

const summaryText = computed(() => {
    if (!props.modelValue.length) return props.placeholder;
    if (props.options.length && props.modelValue.length === props.options.length) return 'All selected';
    if (props.modelValue.length === 1) {
        const match = props.options.find(o => o.value === props.modelValue[0]);
        return match ? match.label : '1 selected';
    }
    return `${props.modelValue.length} selected`;
});
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            type="button"
            @click="toggleOpen"
            class="cvr-input w-full px-3 py-2 rounded flex items-center justify-between text-left gap-2"
        >
            <span class="truncate text-sm" :class="modelValue.length ? 'cvr-text-primary' : 'cvr-text-placeholder'">{{ summaryText }}</span>
            <span class="cvr-text-muted text-xs shrink-0 transition-transform" :class="{ 'rotate-180': open }">▾</span>
        </button>

        <div
            v-if="open"
            class="cvr-modal absolute z-40 mt-1 w-full rounded-lg overflow-hidden flex flex-col"
            style="max-height: 280px"
        >
            <div class="flex items-center justify-between gap-2 px-3 py-2 border-b cvr-border shrink-0">
                <button type="button" @click="selectAll" class="text-xs font-medium" style="color: var(--cvr-green-bright)">Select All</button>
                <button type="button" @click="deselectAll" class="text-xs font-medium cvr-text-muted hover:cvr-text-primary">Deselect All</button>
            </div>
            <div class="overflow-y-auto py-1">
                <label
                    v-for="opt in options"
                    :key="opt.value"
                    class="flex items-center gap-2 px-3 py-1.5 text-sm cvr-text-primary cursor-pointer hover:bg-white/5"
                >
                    <input
                        type="checkbox"
                        :checked="isChecked(opt.value)"
                        @change="toggleValue(opt.value)"
                        style="accent-color: var(--cvr-green-bright)"
                    />
                    <span class="truncate">{{ opt.label }}</span>
                </label>
                <p v-if="!options.length" class="px-3 py-4 text-xs cvr-text-muted text-center">No options</p>
            </div>
        </div>
    </div>
</template>
