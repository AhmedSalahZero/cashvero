<script setup>
/**
 * ToastStack.vue
 * ------------------------------------------------------------------
 * Replaces the original CashVero app's flash-message pattern, which
 * was genuinely two different, visually inconsistent libraries doing
 * the same job: a SweetAlert2 popup for `session('success')` and a
 * toastr.js toast for `session('fail')` — different shapes, different
 * colors, different positions, neither matching this project's design
 * system at all.
 *
 * This is a single, stacked toast list styled entirely from this
 * project's own design tokens (--cvr-success-* / --cvr-danger-*),
 * matching the exact same colors/borders already used for inline
 * validation banners across the app (e.g. CashExpenses/Form.vue's
 * "Validation errors" block) — so a flash toast and an inline error
 * banner now look like the same product, not two different ones.
 *
 * Each toast auto-dismisses after `duration` ms via a shrinking
 * progress bar (a real affordance the old toastr had that a single
 * static banner didn't), can be dismissed early, and multiple toasts
 * stack vertically instead of one replacing another mid-display.
 *
 * ⚠️ REAL BUG FIXED HERE (2026-07-25, "no data found" toast reported
 * almost invisible in both light and dark mode): the actual problem
 * was never the toast's colors — it was position. `top-4` (16px from
 * the browser viewport top) placed every toast directly on top of
 * AppLayout's header bar (`h-14` = 56px tall), which sits right at the
 * top of the page. The toast was rendering squeezed in among the
 * header's own icons/badges/username, on top of the header's own
 * background color — not on the page background its
 * --cvr-danger-bg/--cvr-danger-text colors were actually designed to
 * be read against. Fixed by pushing the stack below the header
 * (56px + a gap) so it renders where it was always meant to: over the
 * normal page background, clear of any other UI. (Found in passing:
 * there's also an unused `--cvr-header-height: 60px` CSS variable
 * that was never actually wired to the header anywhere and doesn't
 * even match its real height — not used here to avoid inheriting that
 * mismatch; the real 56px header height is used directly instead.)
 *
 * Usage: the parent (AppLayout.vue) owns the `toasts` array and calls
 * `dismiss(id)` — this component is purely presentational.
 */
defineProps({
    toasts: { type: Array, default: () => [] }, // [{ id, type: 'success'|'error', message }]
});
const emit = defineEmits(['dismiss']);
</script>

<template>
    <div class="fixed right-4 z-50 flex flex-col gap-2 w-full max-w-sm pointer-events-none" style="top: calc(var(--cvr-header-height) + 1rem);">
        <TransitionGroup name="cvr-toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto rounded-lg shadow-lg overflow-hidden"
                :style="{
                    background: toast.type === 'success' ? 'var(--cvr-success-bg)' : 'var(--cvr-danger-bg)',
                    border: `1px solid ${toast.type === 'success' ? 'var(--cvr-success-border)' : 'var(--cvr-danger-border)'}`,
                }"
            >
                <div class="px-4 py-3 flex items-start gap-3">
                    <span
                        class="text-base leading-none mt-0.5"
                        :style="{ color: toast.type === 'success' ? 'var(--cvr-success-text)' : 'var(--cvr-danger-text)' }"
                    >{{ toast.type === 'success' ? '✓' : '⚠' }}</span>
                    <p
                        class="text-sm flex-1 leading-snug"
                        :style="{ color: toast.type === 'success' ? 'var(--cvr-success-text)' : 'var(--cvr-danger-text)' }"
                    >{{ toast.message }}</p>
                    <button
                        @click="emit('dismiss', toast.id)"
                        class="text-sm leading-none opacity-60 hover:opacity-100 shrink-0"
                        :style="{ color: toast.type === 'success' ? 'var(--cvr-success-text)' : 'var(--cvr-danger-text)' }"
                    >✕</button>
                </div>
                <div class="h-0.5 w-full" style="background: rgba(0,0,0,0.08)">
                    <div
                        class="h-full cvr-toast-progress"
                        :style="{
                            background: toast.type === 'success' ? 'var(--cvr-success)' : 'var(--cvr-danger)',
                            animationDuration: `${toast.duration}ms`,
                        }"
                    ></div>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.cvr-toast-enter-active,
.cvr-toast-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.cvr-toast-enter-from {
    opacity: 0;
    transform: translateX(24px);
}
.cvr-toast-leave-to {
    opacity: 0;
    transform: translateX(24px);
}
.cvr-toast-leave-active {
    position: absolute;
    width: 100%;
}

.cvr-toast-progress {
    animation-name: cvr-toast-shrink;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
    width: 100%;
}
@keyframes cvr-toast-shrink {
    from { width: 100%; }
    to { width: 0%; }
}
</style>
