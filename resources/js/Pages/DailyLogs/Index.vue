<script setup>
/**
 * Daily Logs — every recorded change in the system.
 *
 * Until now a record's history was only reachable from a button on that
 * record's own row, so you had to already know what you were looking
 * for. This page answers the other question: "what happened today?" —
 * and so it opens on today, with nothing to fill in.
 *
 * The record types are stacked, not tabbed: every type that has activity
 * in the chosen period is shown, one section under the next, and the
 * buttons across the top jump to a section rather than reloading the
 * page. A type with no activity in the period has no section and no
 * button — the page only ever shows what actually happened.
 *
 * Filtering is done in SQL (the controller), not here: the counts and
 * the row caps have to agree with the query, and they cannot if the
 * browser is filtering a different set.
 */
import { ref, watch, computed, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    sections: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    events: { type: Object, default: () => ({}) },
    users: { type: Array, default: () => [] },
    rowsPerSection: { type: Number, default: 50 },
    indexUrl: String,
});

const search = ref(props.filters.search || '');
const event = ref(props.filters.event || '');
const user = ref(props.filters.user || '');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');

const totalEntries = computed(() => props.sections.reduce((sum, s) => sum + s.total, 0));

function go(overrides = {}) {
    router.get(props.indexUrl, {
        search: search.value || undefined,
        event: event.value || undefined,
        user: user.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        ...overrides,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => go(), 350);
});

function applyFilters() {
    go();
}

/* Back to the page's own default — today — rather than to "no period at
   all", which would ask the database for every log ever recorded. */
function resetToToday() {
    search.value = '';
    event.value = '';
    user.value = '';
    from.value = '';
    to.value = '';
    router.get(props.indexUrl, {}, { preserveState: true, replace: true });
}

/* The tab buttons scroll; they no longer navigate. */
const activeSection = ref(props.sections[0]?.key || '');

async function jumpTo(key) {
    activeSection.value = key;
    await nextTick();
    document.getElementById(`log-section-${key}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const eventClass = (name) => ({
    created: 'cvr-num-green',
    updated: 'cvr-text-secondary',
    deleted: 'cvr-num-red',
}[name] || 'cvr-text-secondary');
</script>

<template>
    <AppLayout :title="$t('Daily Logs')">
        <div class="p-4 md:p-6">
            <div class="flex items-baseline justify-between flex-wrap gap-2 mb-4">
                <h1 class="text-lg font-medium cvr-text-primary">{{ $t('Daily Logs') }}</h1>
                <p v-if="sections.length" class="text-sm cvr-text-muted">
                    {{ totalEntries }} {{ $t('Results') }} — {{ filters.from }} → {{ filters.to }}
                </p>
            </div>

            <!-- Filters -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-3 mb-4 flex items-end gap-3 flex-wrap">
                <div>
                    <label class="cvr-form-label">{{ $t('From') }}</label>
                    <input v-model="from" type="date" @change="applyFilters" class="cvr-input px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('To') }}</label>
                    <input v-model="to" type="date" @change="applyFilters" class="cvr-input px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('User') }}</label>
                    <select v-model="user" @change="applyFilters" class="cvr-input px-3 py-2 rounded">
                        <option value="">{{ $t('All') }}</option>
                        <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('Event') }}</label>
                    <select v-model="event" @change="applyFilters" class="cvr-input px-3 py-2 rounded">
                        <option value="">{{ $t('All') }}</option>
                        <option v-for="(label, key) in events" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div class="flex-1 min-w-[12rem]">
                    <label class="cvr-form-label">{{ $t('Search') }}</label>
                    <input v-model="search" type="text" class="cvr-input w-full px-3 py-2 rounded" :placeholder="$t('User, description or record id')" />
                </div>
                <button @click="resetToToday" class="cvr-btn-secondary px-3 py-2 rounded border text-sm whitespace-nowrap">
                    {{ $t('Today') }}
                </button>
            </div>

            <!-- Jump links: only the types that actually have activity -->
            <div v-if="sections.length" class="flex items-center gap-2 flex-wrap mb-4">
                <button
                    v-for="section in sections"
                    :key="section.key"
                    @click="jumpTo(section.key)"
                    class="px-3 py-1.5 rounded text-sm border"
                    :class="section.key === activeSection ? 'cvr-btn-copper' : 'cvr-btn-secondary'"
                >{{ $t(section.label) }} <span class="opacity-70">({{ section.total }})</span></button>
            </div>

            <div v-if="sections.length === 0" class="py-10 text-center cvr-text-muted">
                {{ $t('No activity recorded for this selection.') }}
            </div>

            <!-- One section per record type, stacked -->
            <section
                v-for="section in sections"
                :key="section.key"
                :id="`log-section-${section.key}`"
                class="mb-8 scroll-mt-4"
            >
                <div class="flex items-baseline justify-between flex-wrap gap-2 mb-2">
                    <h2 class="text-base font-medium cvr-text-primary">{{ $t(section.label) }}</h2>
                    <p v-if="section.hasMore" class="text-xs cvr-text-muted">
                        {{ $t('Showing') }} {{ section.shown }} {{ $t('of') }} {{ section.total }} —
                        {{ $t('narrow the period or the filters to see the rest') }}
                    </p>
                    <p v-else class="text-xs cvr-text-muted">{{ section.total }} {{ $t('Results') }}</p>
                </div>

                <div class="overflow-auto cvr-card-bg cvr-border border rounded-lg">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="cvr-table-head">
                                <th class="px-3 py-2 text-start">{{ $t('When') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Record') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Event') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('User') }}</th>
                                <th class="px-3 py-2 text-start">{{ $t('Details') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in section.entries" :key="entry.id" class="cvr-table-row align-top">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div>{{ entry.at }}</div>
                                    <div class="text-xs cvr-text-muted">{{ entry.at_human }}</div>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ entry.record }}</td>
                                <td class="px-3 py-2" :class="eventClass(entry.event)">{{ events[entry.event] || entry.event }}</td>
                                <td class="px-3 py-2">{{ entry.actor }}</td>
                                <td class="px-3 py-2">
                                    <div>{{ entry.sentence }}</div>
                                    <!-- An update lists what actually changed, field by field -->
                                    <ul v-if="entry.changes && entry.changes.length" class="mt-1 space-y-0.5">
                                        <li v-for="(change, i) in entry.changes" :key="i" class="text-xs cvr-text-muted">
                                            {{ change.label }}: {{ change.from }} → {{ change.to }}
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
