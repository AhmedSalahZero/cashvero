<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const locale = computed(() => page.props.locale || 'en');
const urls = computed(() => page.props.languageUrls || {});

function switchTo(target) {
    const url = urls.value[target];
    if (!url || target === locale.value) {
        return;
    }
    window.location.href = url;
}
</script>

<template>
    <div
        class="inline-flex items-center rounded border overflow-hidden text-xs font-semibold"
        style="border-color: var(--cvr-border, #1B3558);"
        role="group"
        :aria-label="$t('Language')"
    >
        <button
            type="button"
            class="px-2 py-1 transition-colors"
            :class="locale === 'en' ? 'cvr-lang-active' : 'cvr-lang-idle'"
            :aria-pressed="locale === 'en'"
            @click="switchTo('en')"
        >
            EN
        </button>
        <button
            type="button"
            class="px-2 py-1 transition-colors"
            :class="locale === 'ar' ? 'cvr-lang-active' : 'cvr-lang-idle'"
            :aria-pressed="locale === 'ar'"
            @click="switchTo('ar')"
        >
            ع
        </button>
    </div>
</template>

<style scoped>
.cvr-lang-active {
    background: var(--cvr-nav-active-bg, rgba(29, 154, 108, 0.14));
    color: var(--cvr-text-primary, #E7EDF3);
}
.cvr-lang-idle {
    color: var(--cvr-text-muted, #5B6B7A);
}
.cvr-lang-idle:hover {
    color: var(--cvr-text-primary, #E7EDF3);
}
</style>
