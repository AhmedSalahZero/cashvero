<script setup>
/**
 * SearchableSelect.vue
 * ------------------------------------------------------------------
 * Single-select dropdown with a type-to-filter search box. Same visual
 * language as MultiSelectDropdown (cvr-input trigger, cvr-modal panel)
 * so it sits next to native selects without looking like a third-party
 * widget.
 *
 * Usage:
 *   <SearchableSelect
 *     v-model="bankId"
 *     :options="[{ value, label }, ...]"
 *     placeholder="Select bank"
 *   />
 */
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: { default: '' },
    options: { type: Array, default: () => [] }, // [{ value, label }]
    placeholder: { type: String, default: 'Select' },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const rootRef = ref(null);
const searchInputRef = ref(null);
const search = ref('');

const selectedLabel = computed(() => {
    if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) {
        return null;
    }
    const match = props.options.find((o) => String(o.value) === String(props.modelValue));
    return match ? match.label : null;
});

const filteredOptions = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((o) => String(o.label ?? '').toLowerCase().includes(q));
});

function toggleOpen() {
    if (props.disabled) return;
    open.value = !open.value;
}

function close() {
    open.value = false;
    search.value = '';
}

function handleClickOutside(e) {
    if (rootRef.value && !rootRef.value.contains(e.target)) {
        close();
    }
}

document.addEventListener('click', handleClickOutside, true);
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside, true));

watch(open, async (isOpen) => {
    if (!isOpen) return;
    await nextTick();
    searchInputRef.value?.focus();
});

function selectOption(value) {
    emit('update:modelValue', value);
    close();
}
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            type="button"
            @click="toggleOpen"
            :disabled="disabled"
            class="cvr-input w-full px-3 py-2 rounded flex items-center justify-between text-start gap-2"
            :class="{ 'opacity-40 cursor-not-allowed': disabled }"
        >
            <span class="truncate text-sm" :class="selectedLabel ? 'cvr-text-primary' : 'cvr-text-placeholder'">
                {{ selectedLabel || $t(placeholder) }}
            </span>
            <span class="cvr-text-muted text-xs shrink-0 transition-transform" :class="{ 'rotate-180': open }">▾</span>
        </button>

        <div
            v-if="open"
            class="cvr-modal absolute z-40 mt-1 w-full rounded-lg overflow-hidden flex flex-col"
            style="max-height: 280px"
        >
            <div class="px-2 py-2 border-b cvr-border shrink-0">
                <input
                    ref="searchInputRef"
                    v-model="search"
                    type="text"
                    class="cvr-input w-full px-2 py-1.5 rounded text-sm"
                    :placeholder="$t('Search…')"
                    @keydown.esc.prevent="close"
                />
            </div>
            <div class="overflow-y-auto py-1">
                <button
                    v-for="opt in filteredOptions"
                    :key="opt.value"
                    type="button"
                    class="w-full text-start px-3 py-1.5 text-sm cursor-pointer hover:bg-white/5 truncate"
                    :class="String(opt.value) === String(modelValue) ? 'cvr-text-primary font-medium' : 'cvr-text-primary'"
                    @click="selectOption(opt.value)"
                >
                    {{ opt.label }}
                </button>
                <p v-if="!filteredOptions.length" class="px-3 py-4 text-xs cvr-text-muted text-center">{{ $t('No matches') }}</p>
            </div>
        </div>
    </div>
</template>
