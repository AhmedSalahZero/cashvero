<script setup>
/**
 * Daily Logs — every recorded change in the system, one tab per record
 * type.
 *
 * Until now a record's history was only reachable from a button on that
 * record's own row, so you had to already know what you were looking
 * for. This page answers the other question: "what happened today?"
 *
 * Paging is done in SQL (the controller paginates), not here — the
 * activity table only ever grows, and loading all of it to show 25 rows
 * would get slower every day.
 */
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    tabs: { type: Array, default: () => [] },
    activeTab: String,
    filters: { type: Object, default: () => ({}) },
    events: { type: Object, default: () => ({}) },
    entries: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({}) },
    indexUrl: String,
});

const search = ref(props.filters.search || '');
const event = ref(props.filters.event || '');
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');

/* Every navigation goes back to the server: the filters are applied in
   SQL, so filtering client-side would silently disagree with the count
   and the paging. */
function go(overrides = {}) {
    router.get(props.indexUrl, {
        tab: props.activeTab,
        search: search.value || undefined,
        event: event.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        ...overrides,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => go({ page: 1 }), 350);
});

function selectTab(key) {
    go({ tab: key, page: 1 });
}

function applyFilters() {
    go({ page: 1 });
}

function clearFilters() {
    search.value = '';
    event.value = '';
    from.value = '';
    to.value = '';
    go({ page: 1, search: undefined, event: undefined, from: undefined, to: undefined });
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
            <h1 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Daily Logs') }}</h1>

            <!-- Tabs: one per record type the viewer may see -->
            <div class="flex items-center gap-2 flex-wrap mb-4">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="selectTab(tab.key)"
                    class="px-3 py-1.5 rounded text-sm border"
                    :class="tab.key === activeTab ? 'cvr-btn-copper' : 'cvr-btn-secondary'"
                >{{ $t(tab.label) }}</button>
            </div>

            <!-- Filters -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-3 mb-4 flex items-end gap-3 flex-wrap">
                <div class="flex-1 min-w-[12rem]">
                    <label class="cvr-form-label">{{ $t('Search') }}</label>
                    <input v-model="search" type="text" class="cvr-input w-full px-3 py-2 rounded" :placeholder="$t('User, description or record id')" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('Event') }}</label>
                    <select v-model="event" @change="applyFilters" class="cvr-input px-3 py-2 rounded">
                        <option value="">{{ $t('All') }}</option>
                        <option v-for="(label, key) in events" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('From') }}</label>
                    <input v-model="from" type="date" @change="applyFilters" class="cvr-input px-3 py-2 rounded" />
                </div>
                <div>
                    <label class="cvr-form-label">{{ $t('To') }}</label>
                    <input v-model="to" type="date" @change="applyFilters" class="cvr-input px-3 py-2 rounded" />
                </div>
                <button @click="clearFilters" class="cvr-btn-secondary px-3 py-2 rounded border text-sm">{{ $t('Clear') }}</button>
            </div>

            <div v-if="entries.length === 0" class="py-10 text-center cvr-text-muted">
                {{ $t('No activity recorded for this selection.') }}
            </div>

            <div v-else class="overflow-auto">
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
                        <tr v-for="entry in entries" :key="entry.id" class="cvr-table-row align-top">
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

            <!-- Paging: server-driven, so the numbers always match the query -->
            <div v-if="pagination.lastPage > 1" class="flex items-center justify-between gap-3 flex-wrap mt-4">
                <p class="text-sm cvr-text-muted">
                    {{ $t('Showing') }} {{ pagination.from }}–{{ pagination.to }} {{ $t('of') }} {{ pagination.total }}
                </p>
                <div class="flex items-center gap-1 flex-wrap">
                    <Link
                        v-for="(link, i) in pagination.links"
                        :key="i"
                        :href="link.url || ''"
                        :class="[
                            'px-2.5 py-1 rounded text-sm border',
                            link.active ? 'cvr-btn-copper' : 'cvr-btn-secondary',
                            !link.url ? 'opacity-40 pointer-events-none' : '',
                        ]"
                        preserve-scroll
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
