<script setup>
import { ref } from 'vue';

/**
 * RecordLogButton
 * ==================================================================
 * The 🕘 action button + history modal for one record.
 *
 * Drop it into any action column:
 *
 *   <RecordLogButton subject="MoneyReceived" :id="row.id" :company-id="company.id" />
 *
 * `subject` is the model's short class name as declared in
 * App\Support\Activity\ActivityRegistry. The endpoint resolves it,
 * checks that you may view that module, and returns the timeline —
 * so a page never has to know which permission governs the log.
 *
 * Entries load on open, not with the page: a list of 50 rows would
 * otherwise pull 50 histories nobody asked for.
 */
const props = defineProps({
    subject: { type: String, required: true },
    id: { type: [Number, String], required: true },
    companyId: { type: [Number, String], required: true },
    title: { type: String, default: 'History' },
});

const open = ref(false);
const loading = ref(false);
const error = ref('');
const entries = ref([]);
const subjectLabel = ref('');

async function show() {
    open.value = true;

    // Re-fetch each time: a record's history changes while the list is
    // still on screen, and a stale timeline is worse than a short wait.
    loading.value = true;
    error.value = '';

    try {
        const res = await fetch(`/${props.companyId}/record-activity/${props.subject}/${props.id}`, {
            headers: { Accept: 'application/json' },
        });

        if (res.status === 403) {
            error.value = 'You do not have permission to view this history.';
            return;
        }
        if (!res.ok) {
            error.value = 'Could not load the history.';
            return;
        }

        const data = await res.json();
        entries.value = data.entries ?? [];
        subjectLabel.value = data.subject ?? '';
    } catch {
        error.value = 'Could not load the history.';
    } finally {
        loading.value = false;
    }
}

function close() {
    open.value = false;
}

/** Colour the dot by what kind of event it was. */
function dotClass(event) {
    return {
        created: 'cvr-log-dot-created',
        updated: 'cvr-log-dot-updated',
        deleted: 'cvr-log-dot-deleted',
        restored: 'cvr-log-dot-created',
        custom: 'cvr-log-dot-custom',
    }[event] || 'cvr-log-dot-custom';
}
</script>

<template>
    <button @click="show" class="cvr-action-btn" :title="title">🕘</button>

    <teleport to="body">
        <div v-if="open" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="close">
            <div class="cvr-modal rounded-lg p-6 w-full max-w-2xl max-h-[80vh] flex flex-col mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-medium cvr-text-primary">
                        {{ subjectLabel || title }}
                        <span class="cvr-text-muted text-sm">#{{ id }}</span>
                    </h2>
                    <button @click="close" class="cvr-action-btn" :title="$t('Close')">✕</button>
                </div>

                <div v-if="loading" class="py-10 text-center cvr-text-muted text-sm">{{ $t('Loading…') }}</div>

                <div v-else-if="error" class="py-10 text-center text-sm" style="color: var(--cvr-danger-text);">
                    {{ error }}
                </div>

                <div v-else-if="entries.length === 0" class="py-10 text-center cvr-text-muted text-sm">
                    {{ $t('Nothing has been recorded for this item yet.') }}
                </div>

                <div v-else class="overflow-y-auto flex-1 -mx-1 px-1">
                    <ol class="relative border-s cvr-border ms-3">
                        <li v-for="entry in entries" :key="entry.id" class="mb-5 ms-5">
                            <span
                                class="absolute w-2.5 h-2.5 rounded-full -start-[5px] mt-1.5"
                                :class="dotClass(entry.event)"
                            ></span>

                            <p class="text-sm cvr-text-primary">
                                <strong>{{ entry.actor }}</strong> {{ entry.sentence }}
                            </p>

                            <p class="text-xs cvr-text-muted mt-0.5 tabular-nums">
                                {{ entry.at }} <span v-if="entry.at_human">· {{ entry.at_human }}</span>
                            </p>

                            <div v-if="entry.changes.length" class="mt-2 overflow-x-auto">
                                <table class="text-xs min-w-full">
                                    <tbody>
                                        <tr v-for="(c, i) in entry.changes" :key="i" class="cvr-table-row">
                                            <td class="py-1 pe-4 cvr-text-secondary whitespace-nowrap">{{ c.label }}</td>
                                            <td class="py-1 pe-2 cvr-text-muted">{{ c.from }}</td>
                                            <td class="py-1 pe-2 cvr-text-muted">→</td>
                                            <td class="py-1 cvr-text-primary">{{ c.to }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </teleport>
</template>

<style scoped>
/* Semantic, not decorative: the dot encodes the kind of event so a
   deletion is findable by scanning rather than by reading. */
.cvr-log-dot-created { background: #10b981; }
.cvr-log-dot-updated { background: #3b82f6; }
.cvr-log-dot-deleted { background: #ef4444; }
.cvr-log-dot-custom  { background: #a78bfa; }
</style>
