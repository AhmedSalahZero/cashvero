<script setup>
import { ref, onBeforeUnmount, nextTick } from 'vue';

/*
 * Reusable dropdown menu — use this anywhere a button needs a small
 * popup menu (table row actions, "Add X" buttons, etc.), instead of
 * building one-off absolute-positioned <div>s.
 *
 * WHY THIS EXISTS: dropdowns built with plain `position: absolute`
 * inside a <table> get clipped/mispositioned by the table's own
 * layout rules — this caused real overlapping-text bugs on the
 * Financial Institutions page. This component sidesteps that
 * entirely by teleporting the menu to <body> and positioning it
 * with fixed coordinates based on the trigger button's real
 * on-screen location.
 *
 * Usage:
 *   <Dropdown>
 *     <template #trigger="{ toggle }">
 *       <button @click="toggle" class="cvr-tag">{{ $t('Options ▾') }}</button>
 *     </template>
 *     <template #content>
 *       <Link href="..." class="block px-3 py-2 text-xs cvr-nav-item">{{ $t('Item') }}</Link>
 *     </template>
 *   </Dropdown>
 */

const open = ref(false);
const triggerRef = ref(null);
const menuRef = ref(null);
const position = ref({ top: 0, left: 0 });

/*
 * Opens left-aligned to the trigger by default (same as before). But
 * once the menu actually renders, if its right edge would run past
 * the window's right edge — forcing the user to scroll horizontally
 * to see items like "Delete" — we flip it to right-align instead, so
 * it opens leftward. This is a root-cause fix in the shared
 * component, so every dropdown in the app (not just this one page)
 * benefits automatically.
 */
async function toggle() {
    if (!open.value && triggerRef.value) {
        const rect = triggerRef.value.getBoundingClientRect();
        const isRtl = document.documentElement.dir === 'rtl';
        position.value = {
            top: rect.bottom + window.scrollY + 4,
            left: isRtl
                ? rect.right + window.scrollX - 200
                : rect.left + window.scrollX,
        };
        open.value = true;
        await nextTick();
        if (menuRef.value) {
            const menuRect = menuRef.value.getBoundingClientRect();
            if (!isRtl && menuRect.right > window.innerWidth) {
                position.value.left = rect.right + window.scrollX - menuRect.width;
            }
            if (isRtl && menuRect.left < 0) {
                position.value.left = rect.left + window.scrollX;
            }
        }
        return;
    }
    open.value = !open.value;
}

function close() {
    open.value = false;
}

function handleClickOutside(e) {
    if (triggerRef.value && !triggerRef.value.contains(e.target)) {
        // also ignore clicks inside the teleported menu itself
        if (!e.target.closest('[data-dropdown-menu]')) {
            close();
        }
    }
}

document.addEventListener('click', handleClickOutside, true);
onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside, true);
});
</script>

<template>
    <span ref="triggerRef" class="inline-block">
        <slot name="trigger" :toggle="toggle" :open="open" />
    </span>

    <Teleport to="body">
        <div
            v-if="open"
            ref="menuRef"
            data-dropdown-menu
            class="cvr-modal rounded shadow-lg py-1 absolute z-50"
            :style="{ top: position.top + 'px', left: position.left + 'px', minWidth: '200px' }"
            @click="close"
        >
            <slot name="content" />
        </div>
    </Teleport>
</template>