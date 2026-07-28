<script setup>
/*
 * Shared pagination bar for Laravel paginators.
 *
 * Before this existed every paginated page hand-rolled its own bar, and
 * the copies had drifted: some rendered one button per page (a company
 * with a few thousand rows got a few hundred buttons), some lost the
 * active filters when you moved to page 2, and one sent a page parameter
 * the backend was not reading at all, so it never left page 1.
 *
 * Two shapes are supported, because both already exist in the app:
 *
 *   - `paginator` — the array Laravel's LengthAwarePaginator::toArray()
 *     produces. Its `links` already carry the full URL including every
 *     filter (that is what withQueryString()/appends() is for), so the
 *     bar just navigates to link.url. This is the preferred form.
 *   - `meta` + `@change` — for tabs fetched over JSON rather than an
 *     Inertia visit, where there are no link URLs and the parent decides
 *     how to load a page.
 */
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    // Laravel paginator array: { current_page, last_page, per_page, total, links }
    paginator: { type: Object, default: null },
    // Fallback for JSON-fetched tabs: { current_page, last_page, total }
    meta: { type: Object, default: null },
    // Rendered next to the page buttons, e.g. "1,204 total records"
    showTotal: { type: Boolean, default: true },
    label: { type: String, default: 'records' },
});

const emit = defineEmits(['change']);

const state = computed(() => props.paginator || props.meta || {});
const currentPage = computed(() => state.value.current_page || 1);
const lastPage = computed(() => state.value.last_page || 1);
const total = computed(() => state.value.total);

/*
 * First page, last page, and a window of two either side of the current
 * one, with an ellipsis wherever that skips a stretch. Keeps the bar a
 * fixed width no matter how many pages there are.
 */
const pages = computed(() => {
    const last = lastPage.value;
    if (last <= 1) return [];

    const wanted = new Set([1, last]);
    for (let page = currentPage.value - 2; page <= currentPage.value + 2; page++) {
        if (page >= 1 && page <= last) wanted.add(page);
    }

    const sorted = [...wanted].sort((a, b) => a - b);
    return sorted.flatMap((page, i) => {
        const previous = sorted[i - 1];
        return previous && page - previous > 1 ? ['gap', page] : [page];
    });
});

/*
 * Laravel emits links as [previous, 1, 2, …, next]; index N in that array
 * is page N. Using the paginator's own URL is what keeps filters attached.
 */
function urlForPage(page) {
    return props.paginator?.links?.[page]?.url ?? null;
}

function goTo(page) {
    if (page < 1 || page > lastPage.value || page === currentPage.value) return;

    const url = urlForPage(page);
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
        return;
    }

    emit('change', page);
}
</script>

<template>
    <div v-if="lastPage > 1" class="flex items-center justify-between gap-4 mt-4 text-sm flex-wrap">
        <p v-if="showTotal && total != null" class="cvr-text-muted">
            {{ Number(total).toLocaleString() }} total {{ label }}
        </p>
        <div v-else></div>

        <div class="flex items-center gap-1">
            <button
                @click="goTo(currentPage - 1)"
                :disabled="currentPage === 1"
                class="cvr-btn-secondary px-3 py-1.5 rounded border text-xs"
                :class="{ 'opacity-40 cursor-not-allowed': currentPage === 1 }"
                aria-label="Previous page"
            >‹</button>

            <template v-for="(page, i) in pages" :key="i">
                <span v-if="page === 'gap'" class="px-2 cvr-text-muted">…</span>
                <button
                    v-else
                    @click="goTo(page)"
                    class="px-3 py-1.5 rounded border text-xs"
                    :class="page === currentPage ? 'cvr-btn-primary' : 'cvr-btn-secondary'"
                    :aria-current="page === currentPage ? 'page' : undefined"
                >{{ page }}</button>
            </template>

            <button
                @click="goTo(currentPage + 1)"
                :disabled="currentPage === lastPage"
                class="cvr-btn-secondary px-3 py-1.5 rounded border text-xs"
                :class="{ 'opacity-40 cursor-not-allowed': currentPage === lastPage }"
                aria-label="Next page"
            >›</button>
        </div>
    </div>
</template>
