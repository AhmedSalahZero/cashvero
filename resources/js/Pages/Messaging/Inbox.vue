<script setup>
/**
 * One inbox, two tabs:
 *   - "Messages": ordinary chat with colleagues (route: messages.start / messages.send)
 *   - "Support" : "Super Message" tickets to the Super Admin
 *                 (route: messages.support.start), shown ticket-style
 *                 with an Open / In Progress / Resolved status.
 *
 * ── How this page stays live ──────────────────────────────────────
 * By polling, not by broadcasting. No broadcast driver is configured
 * (BROADCAST_DRIVER=log with no Pusher key), so window.Echo doesn't
 * exist in the browser and the Echo listeners below never fire. They're
 * kept so that configuring Pusher later is a config change rather than a
 * rewrite — see MessagingController.
 *
 * Two loops, both built on createPoller (composables/usePoller.js — the
 * response-ordering, abort and backoff guarantees live there):
 *
 *   inboxPoller  — the conversation lists and the sidebar badge, so a
 *                  message in a thread you don't have open still shows
 *                  up. Previously nothing refreshed these at all: the
 *                  only code that flagged a list row unread hung off the
 *                  Echo listener, which never runs.
 *   threadPoller — a delta for the open thread only: messages newer than
 *                  the highest id we hold, plus any message we already
 *                  hold whose deleted flag moved. This used to
 *                  re-download the whole history every 5 seconds and
 *                  replace the entire array with it.
 */
import { ref, computed, nextTick, watch, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToasts } from '@/composables/useToasts';
import { createPoller } from '@/composables/usePoller';
import { useUnreadMessages } from '@/composables/useUnreadMessages';

const props = defineProps({
    isSuperAdmin: Boolean,
    conversations: Object, // { direct: [...], support: [...] }
    contacts: Array,       // [{ id, name, email }]
    routes: Object,        // server-built, locale-correct URLs — see MessagingController::index()
});

// routes.show / send / read / status come from the server with a literal
// "__ID__" placeholder swapped in per-conversation (see props.routes docblock
// in the controller) — this fills it in with a real conversation id.
function urlFor(template, id) {
    return template.replace('__ID__', id);
}
function deleteMessageUrl(conversationId, messageId) {
    return props.routes.deleteMessage.replace('__ID__', conversationId).replace('__MSG_ID__', messageId);
}

const { pushToast } = useToasts();
const { setUnreadMessages, clearUnreadMessages } = useUnreadMessages();
const page = usePage();

const currentUserId = computed(() => page.props.auth?.user?.id);
const currentUserName = computed(() => page.props.auth?.user?.name ?? '');

const activeTab = ref('direct'); // 'direct' | 'support'
const lists = ref({
    direct: props.conversations.direct ?? [],
    support: props.conversations.support ?? [],
});

const currentList = computed(() => lists.value[activeTab.value]);

const selected = ref(null);       // the selected conversation summary object
const messages = ref([]);
const loadingMessages = ref(false);
const messageBody = ref('');
const messagesEnd = ref(null);
const messagesPane = ref(null);
/** A message landed while the user was reading further up the thread. */
const newMessagesBelow = ref(false);
let currentChannelName = null;
let pendingSeed = 0;

/**
 * Where the open thread's delta picks up from: the highest message id we
 * hold, and the server's own clock reading from the last response (used
 * as `since` for deletions — never the browser's clock, which is
 * compared against nothing on the server side).
 */
let threadCursor = { id: 0, since: null };

/** Fingerprint of the last inbox snapshot, echoed back so an unchanged inbox is a cheap answer. */
const inboxVersion = ref(null);

function statusLabel(status) {
    return { open: 'Open', in_progress: 'In Progress', resolved: 'Resolved' }[status] ?? status;
}
function statusBadgeClass(status) {
    return {
        open: 'cvr-badge cvr-badge-pending',
        in_progress: 'cvr-badge cvr-badge-info',
        resolved: 'cvr-badge cvr-badge-active',
    }[status] ?? 'cvr-badge';
}

function isMine(message) {
    return message.user?.id === currentUserId.value;
}

function bubbleStyle(message) {
    if (message.deleted) {
        return 'background: transparent; border: 1px dashed var(--cvr-border); color: var(--cvr-text-muted); font-style: italic;';
    }
    if (message.failed) {
        return 'background: var(--cvr-bg-hover); color: var(--cvr-text-primary); border: 1px solid var(--cvr-num-red);';
    }
    const base = isMine(message)
        ? 'background: var(--cvr-green-hover); color: #fff;'
        : 'background: var(--cvr-bg-hover); color: var(--cvr-text-primary);';

    return message.pending ? `${base} opacity: 0.55;` : base;
}

/* ── Scroll position ─────────────────────────────────────────────
   Auto-scrolling on every refresh used to yank the view down while
   someone was reading older messages. Only follow the conversation if
   they were already at the bottom (or it's their own message); otherwise
   offer them the jump instead of taking it. ───────────────────────── */

/** Within ~80px of the bottom counts as "following along". */
function isNearBottom() {
    const pane = messagesPane.value;
    if (!pane) return true;
    return pane.scrollHeight - pane.scrollTop - pane.clientHeight < 80;
}

function scrollToBottom() {
    newMessagesBelow.value = false;
    nextTick(() => messagesEnd.value?.scrollIntoView({ block: 'end' }));
}

function onPaneScroll() {
    if (isNearBottom()) newMessagesBelow.value = false;
}

/* ── Merging polled data into what's on screen ───────────────────── */

function markListItemUnread(conversationId) {
    for (const key of ['direct', 'support']) {
        const item = lists.value[key].find(c => c.id === conversationId);
        if (item) item.unread = true;
    }
}

function markListItemRead(conversationId) {
    for (const key of ['direct', 'support']) {
        const item = lists.value[key].find(c => c.id === conversationId);
        if (item) item.unread = false;
    }
}

/**
 * Add messages we don't already hold. Shared by the delta poll and the
 * Echo listener so the two paths can't drift apart, and id-based so a
 * message can never appear twice — which matters because our own POST
 * response and the next poll can both carry the same message.
 *
 * Returns whether anything was actually new (drives the poller's
 * adaptive interval).
 */
function appendMessages(incoming, conversationId) {
    const fresh = incoming.filter(m => !messages.value.some(held => held.id === m.id));
    if (fresh.length === 0) return false;

    // Read before mutating: pushing changes scrollHeight.
    const following = isNearBottom();

    // A poll can beat our own send response back, in which case our
    // optimistic placeholder is still on screen. Retire it here rather
    // than briefly show the same message twice; deliver() copes with
    // finding it already gone.
    for (const message of fresh) {
        if (!isMine(message)) continue;
        const placeholder = messages.value.findIndex(held => held.pending && held.body === message.body);
        if (placeholder !== -1) messages.value.splice(placeholder, 1);
    }

    messages.value.push(...fresh);
    threadCursor.id = Math.max(threadCursor.id, ...fresh.map(m => m.id));

    if (following || fresh.every(isMine)) scrollToBottom();
    else newMessagesBelow.value = true;

    // We're looking at this thread, so it isn't unread however the list
    // snapshot was computed a moment ago.
    markListItemRead(conversationId);

    return true;
}

function applyThreadUpdates(data) {
    if (!data) return false;
    // createPoller guarantees ordering; this guards identity — the user
    // may have switched threads while the request was in flight.
    if (selected.value?.id !== data.conversationId) return false;

    threadCursor.since = data.server_time ?? threadCursor.since;

    let changed = false;

    // Deletions (and any future edit) to messages we already hold. A
    // message only ever goes to deleted, so re-applying is harmless.
    for (const patch of data.changed ?? []) {
        const held = messages.value.find(m => m.id === patch.id);
        if (held && held.deleted !== patch.deleted) {
            held.deleted = patch.deleted;
            held.body = patch.body;
            changed = true;
        }
    }

    if (appendMessages(data.messages ?? [], data.conversationId)) changed = true;

    if (typeof data.last_id === 'number' && data.last_id > threadCursor.id) {
        threadCursor.id = data.last_id;
    }

    // A Super Admin moving a ticket to Resolved reaches the reporter here.
    if (data.conversation && data.conversation.status !== selected.value.status) {
        selected.value.status = data.conversation.status;
        const item = lists.value.support.find(c => c.id === data.conversationId);
        if (item) item.status = data.conversation.status;
        changed = true;
    }

    // Whatever moved in here moved the list previews and the badge too.
    if (changed) inboxPoller.poke();

    return changed;
}

/**
 * Fold a polled snapshot into the list already on screen, matching on
 * id instead of swapping the array: `selected` and the row the user is
 * hovering both point into these objects, and Vue shouldn't re-render
 * every row because one preview line changed. Rebuilding in the
 * snapshot's order preserves the server's "newest activity first".
 */
function mergeList(key, incoming) {
    const held = new Map(lists.value[key].map(c => [c.id, c]));

    lists.value[key] = incoming.map((fresh) => {
        const current = held.get(fresh.id);
        if (!current) return fresh;

        Object.assign(current, fresh);
        if (selected.value?.id === current.id) current.unread = false;

        return current;
    });
}

function applyInboxSnapshot(data) {
    if (data?.version) inboxVersion.value = data.version;
    if (!data || data.changed === false) return false;

    setUnreadMessages(data.unread_total);
    mergeList('direct', data.conversations?.direct ?? []);
    mergeList('support', data.conversations?.support ?? []);

    return true;
}

/* ── The two polling loops ───────────────────────────────────────── */

const inboxPoller = createPoller({
    interval: 8000,
    maxInterval: 30000,
    request: (signal) => window.axios
        .get(props.routes.poll, { signal, params: { version: inboxVersion.value } })
        .then(response => response.data),
    onResult: applyInboxSnapshot,
});

/**
 * With a broadcasting driver configured, Echo delivers messages
 * instantly and this drops to a slow safety net for anything a dropped
 * socket missed, instead of switching off entirely like the old
 * fallback did.
 */
const liveDriverActive = Boolean(window.Echo);

const threadPoller = createPoller({
    interval: liveDriverActive ? 30000 : 3000,
    maxInterval: liveDriverActive ? 60000 : 15000,
    request: (signal) => {
        const conversationId = selected.value?.id;
        if (!conversationId) return Promise.resolve(null);

        return window.axios.get(urlFor(props.routes.updates, conversationId), {
            signal,
            params: {
                after_id: threadCursor.id,
                since: threadCursor.since,
                // Fetch and mark-read in one trip. The poller only runs
                // while this thread is open and the tab is visible, so
                // anything it returns has genuinely been seen — the old
                // code marked read once, on open, and left everything
                // that arrived afterwards lighting up the badge.
                mark_read: 1,
            },
        }).then(response => ({ conversationId, ...response.data }));
    },
    onResult: applyThreadUpdates,
});

const connectionLost = computed(() => inboxPoller.failing.value || threadPoller.failing.value);

/* ── Optional realtime path (only if Pusher is ever configured) ──── */

function leaveChannel() {
    if (currentChannelName && window.Echo) {
        window.Echo.leave(currentChannelName);
    }
    currentChannelName = null;
}

function joinChannel(conversationId) {
    if (!window.Echo) return; // no broadcasting driver configured yet
    currentChannelName = `conversation.${conversationId}`;
    window.Echo.private(currentChannelName).listen('.message.sent', (payload) => {
        if (selected.value?.id === conversationId) {
            appendMessages([payload], conversationId);
        } else {
            // Message arrived for a conversation that's not open right now —
            // just flag it unread in the list.
            markListItemUnread(conversationId);
        }
    }).listen('.message.deleted', (payload) => {
        if (selected.value?.id === conversationId) {
            const m = messages.value.find(m => m.id === payload.id);
            if (m) {
                m.deleted = true;
                m.body = null;
            }
        }
    });
}

/* ── Opening a conversation ──────────────────────────────────────── */

async function selectConversation(item) {
    leaveChannel();
    threadPoller.stop();
    selected.value = item;
    messages.value = [];
    threadCursor = { id: 0, since: null };
    newMessagesBelow.value = false;
    loadingMessages.value = true;

    try {
        const { data } = await window.axios.get(urlFor(props.routes.show, item.id));
        // Clicked away while this was loading — the newer click owns the pane.
        if (selected.value?.id !== item.id) return;

        messages.value = data.messages;
        threadCursor = { id: data.last_id ?? 0, since: data.server_time ?? null };
        selected.value = { ...item, ...data.conversation };
        scrollToBottom();

        await window.axios.post(urlFor(props.routes.read, item.id));
        if (selected.value?.id !== item.id) return;

        item.unread = false;
        // The badge just dropped; don't make anyone wait out the idle interval.
        inboxPoller.poke();
        joinChannel(item.id);
        threadPoller.start();
    } catch (e) {
        pushToast('error', 'Could not load that conversation.');
    } finally {
        if (selected.value?.id === item.id) loadingMessages.value = false;
    }
}

/* ── Sending ─────────────────────────────────────────────────────── */

function sendMessage() {
    const body = messageBody.value.trim();
    if (!body || !selected.value) return;
    messageBody.value = '';
    deliver(selected.value.id, body);
}

/**
 * Optimistic: the bubble appears immediately, greyed out, and either
 * settles into the real message or turns into something retryable. The
 * old version silently put the text back in the box on failure, which
 * read as "nothing happened".
 */
async function deliver(conversationId, body, retryId = null) {
    const placeholderId = retryId ?? `pending-${++pendingSeed}`;

    if (retryId) {
        const held = messages.value.find(m => m.id === retryId);
        if (held) {
            held.pending = true;
            held.failed = false;
        }
    } else {
        messages.value.push({
            id: placeholderId,
            body,
            deleted: false,
            pending: true,
            failed: false,
            created_at: new Date().toISOString(),
            user: { id: currentUserId.value, name: currentUserName.value },
        });
        scrollToBottom();
    }

    try {
        const { data } = await window.axios.post(urlFor(props.routes.send, conversationId), { body });

        const index = messages.value.findIndex(m => m.id === placeholderId);
        if (index !== -1) {
            // A poll can occasionally beat our own response to the real
            // message; drop the placeholder rather than show it twice.
            const already = messages.value.some(m => m.id === data.message.id);
            messages.value.splice(index, 1, ...(already ? [] : [data.message]));
        }

        threadCursor.id = Math.max(threadCursor.id, data.message.id);
        scrollToBottom();
        // Sending is activity: pull both loops back to their base rate.
        threadPoller.poke(false);
        inboxPoller.poke();
    } catch (e) {
        const held = messages.value.find(m => m.id === placeholderId);
        if (held) {
            held.pending = false;
            held.failed = true;
        }
        pushToast('error', 'Message could not be sent.');
    }
}

function retrySend(message) {
    if (!selected.value) return;
    deliver(selected.value.id, message.body, message.id);
}

function discardMessage(message) {
    messages.value = messages.value.filter(m => m.id !== message.id);
}

/* ── Deleting / ticket status ────────────────────────────────────── */

async function deleteMessage(message) {
    if (!confirm('Delete this message? This cannot be undone.')) return;
    try {
        await window.axios.delete(deleteMessageUrl(selected.value.id, message.id));
        message.deleted = true;
        message.body = null;
        // The list preview may have been this message.
        inboxPoller.poke();
    } catch (e) {
        pushToast('error', 'Could not delete that message.');
    }
}

async function updateStatus(status) {
    if (!selected.value) return;
    try {
        await window.axios.patch(urlFor(props.routes.status, selected.value.id), { status });
        selected.value.status = status;
        const item = lists.value.support.find(c => c.id === selected.value.id);
        if (item) item.status = status;
        pushToast('success', 'Ticket status updated.');
    } catch (e) {
        pushToast('error', 'Could not update the status.');
    }
}

/* ── Start a new direct chat ────────────────────────────────────── */
const showNewChat = ref(false);
const newChatUserId = ref('');
const newChatText = ref('');

async function startDirectChat() {
    if (!newChatUserId.value) {
        pushToast('error', 'Please choose a person to message.');
        return;
    }
    if (!newChatText.value.trim()) {
        pushToast('error', 'Please write a message.');
        return;
    }
    try {
        const { data } = await window.axios.post(props.routes.start, {
            user_id: newChatUserId.value,
            body: newChatText.value.trim(),
        });
        showNewChat.value = false;
        newChatUserId.value = '';
        newChatText.value = '';
        await refreshAndOpen('direct', data.conversation_id);
    } catch (e) {
        pushToast('error', 'Could not start the conversation.');
    }
}

/* ── Raise a new Super Message support ticket ───────────────────── */
const showNewTicket = ref(false);
const ticketSubject = ref(''); // optional — auto-filled from the message if left blank
const ticketBody = ref('');

async function startSupportTicket() {
    if (!ticketBody.value.trim()) {
        pushToast('error', 'Please describe the issue before sending.');
        return;
    }
    // Subject is a nice-to-have, not something the person must stop and
    // fill in — unlike Chat, there's no name to pick here at all, since
    // it always goes to the Super Admin.
    const subject = ticketSubject.value.trim() || ticketBody.value.trim().slice(0, 60);
    try {
        const { data } = await window.axios.post(props.routes.support, {
            subject,
            body: ticketBody.value.trim(),
        });
        showNewTicket.value = false;
        ticketSubject.value = '';
        ticketBody.value = '';
        await refreshAndOpen('support', data.conversation_id);
    } catch (e) {
        pushToast('error', 'Could not send your message to support.');
    }
}

/**
 * After creating something new, pull the inbox once — the same endpoint
 * the background loop uses — and open the new thread. This used to be an
 * Inertia partial reload, which re-rendered the whole page just to
 * refresh one list.
 */
async function refreshAndOpen(tab, conversationId) {
    activeTab.value = tab;

    try {
        // No `version`: we want the full snapshot, not "nothing changed".
        const { data } = await window.axios.get(props.routes.poll);
        applyInboxSnapshot(data);
    } catch (e) {
        // Not worth an error toast — the poller catches up on its own.
    }

    const item = lists.value[tab].find(c => c.id === conversationId);
    if (item) await selectConversation(item);
}

/* ── Lifecycle ───────────────────────────────────────────────────── */

watch(selected, (next, previous) => {
    if (next && previous && next.id === previous.id) return;
    // Also covers the mobile "Back" button (selected = null), which used
    // to leave the old timer firing every few seconds against a thread
    // nobody had open any more.
    threadPoller.stop();
    newMessagesBelow.value = false;
});

onMounted(() => inboxPoller.start());

onBeforeUnmount(() => {
    leaveChannel();
    threadPoller.stop();
    inboxPoller.stop();
    // Nothing polls off this page, so the server-rendered badge on the
    // next navigation is the fresher of the two numbers.
    clearUnreadMessages();
});
</script>

<template>
    <AppLayout>
        <div class="p-4 md:p-6 h-[calc(100vh-4rem)] flex flex-col">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h1 class="text-xl font-bold cvr-text-primary">{{ $t('Messages') }}</h1>
                <span v-if="connectionLost" class="text-xs cvr-text-muted">{{ $t('Reconnecting…') }}</span>
            </div>

            <div class="cvr-card flex-1 min-h-0 flex overflow-hidden">
                <!-- Left: conversation list -->
                <div class="w-full sm:w-80 flex-shrink-0 border-e cvr-border flex flex-col" :class="{ 'hidden sm:flex': selected }">
                    <div class="flex cvr-border border-b">
                        <button
                            class="flex-1 py-3 text-sm font-semibold"
                            :class="activeTab === 'direct' ? 'cvr-nav-item-active' : 'cvr-text-secondary'"
                            @click="activeTab = 'direct'"
                        >{{ $t('Chat') }}</button>
                        <button
                            class="flex-1 py-3 text-sm font-semibold"
                            :class="activeTab === 'support' ? 'cvr-nav-item-active' : 'cvr-text-secondary'"
                            @click="activeTab = 'support'"
                        >{{ isSuperAdmin ? $t('Support Inbox') : $t('Super Message') }}</button>
                    </div>

                    <div class="p-2">
                        <button v-if="activeTab === 'direct'" class="cvr-btn-primary w-full text-sm" @click="showNewChat = true">
                            + {{ $t('New Message') }}
                        </button>
                        <button v-else class="cvr-btn-copper w-full text-sm" @click="showNewTicket = true">
                            + {{ $t('Report an Issue / Bug') }}
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto">
                        <div v-if="currentList.length === 0" class="p-4 text-sm cvr-text-muted text-center">
                            {{ $t('No conversations yet.') }}
                        </div>
                        <button
                            v-for="item in currentList"
                            :key="item.id"
                            class="w-full text-start px-3 py-3 cvr-table-row border-b cvr-border flex items-start gap-2"
                            :class="{ 'cvr-hover-bg': true }"
                            @click="selectConversation(item)"
                        >
                            <span class="cvr-avatar flex-shrink-0">{{ (item.title || '?').charAt(0).toUpperCase() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold cvr-text-primary truncate">{{ item.title }}</span>
                                    <span v-if="item.unread" class="w-2 h-2 rounded-full flex-shrink-0" style="background: var(--cvr-num-red);"></span>
                                </span>
                                <span v-if="item.type === 'support'" :class="statusBadgeClass(item.status)" class="text-[10px] mt-0.5 inline-block">
                                    {{ statusLabel(item.status) }}
                                </span>
                                <span class="block text-xs cvr-text-muted truncate mt-0.5">
                                    {{ item.last_message?.body || $t('No messages yet') }}
                                </span>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Right: selected conversation -->
                <div class="flex-1 min-w-0 flex flex-col" :class="{ 'hidden sm:flex': !selected }">
                    <template v-if="selected">
                        <div class="border-b cvr-border px-4 py-3 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <button class="sm:hidden text-xs cvr-text-muted mb-1" @click="selected = null">&larr; {{ $t('Back') }}</button>
                                <div class="font-semibold cvr-text-primary truncate">{{ selected.title }}</div>
                            </div>
                            <select
                                v-if="isSuperAdmin && selected.type === 'support'"
                                class="cvr-select text-sm"
                                :value="selected.status"
                                @change="updateStatus($event.target.value)"
                            >
                                <option value="open">{{ $t('Open') }}</option>
                                <option value="in_progress">{{ $t('In Progress') }}</option>
                                <option value="resolved">{{ $t('Resolved') }}</option>
                            </select>
                        </div>

                        <div class="flex-1 min-h-0 relative flex flex-col">
                            <div ref="messagesPane" class="flex-1 overflow-y-auto p-4 space-y-3" @scroll.passive="onPaneScroll">
                                <div v-if="loadingMessages" class="text-sm cvr-text-muted text-center">{{ $t('Loading…') }}</div>
                                <div v-for="m in messages" :key="m.id" class="max-w-[75%] group" :class="isMine(m) ? 'ms-auto text-end' : ''">
                                    <div class="inline-flex items-center gap-1" :class="isMine(m) ? 'flex-row-reverse' : ''">
                                        <div class="inline-block rounded-lg px-3 py-2 text-sm" :style="bubbleStyle(m)">
                                            <div v-if="!m.deleted && !isMine(m)" class="text-[11px] font-semibold mb-0.5 opacity-70">{{ m.user.name }}</div>
                                            <div v-if="m.deleted" class="whitespace-pre-wrap break-words">{{ $t('This message was deleted') }}</div>
                                            <div v-else class="whitespace-pre-wrap break-words">{{ m.body }}</div>
                                        </div>
                                        <button
                                            v-if="!m.deleted && !m.pending && !m.failed && isMine(m)"
                                            class="opacity-0 group-hover:opacity-100 transition-opacity text-xs cvr-text-muted hover:text-red-400 px-1"
                                            :title="$t('Delete message')"
                                            @click="deleteMessage(m)"
                                        >🗑</button>
                                    </div>
                                    <div v-if="m.failed" class="text-[10px] mt-0.5 flex items-center gap-2" :class="isMine(m) ? 'justify-end' : ''" style="color: var(--cvr-num-red);">
                                        <span>{{ $t('Not sent') }}</span>
                                        <button class="underline" @click="retrySend(m)">{{ $t('Retry') }}</button>
                                        <button class="underline cvr-text-muted" @click="discardMessage(m)">{{ $t('Discard') }}</button>
                                    </div>
                                    <div v-else class="text-[10px] cvr-text-muted mt-0.5">
                                        {{ m.pending ? $t('Sending…') : new Date(m.created_at).toLocaleString() }}
                                    </div>
                                </div>
                                <div ref="messagesEnd"></div>
                            </div>

                            <!-- Offer the jump instead of yanking the view down mid-read. -->
                            <button
                                v-if="newMessagesBelow"
                                class="absolute bottom-3 left-1/2 -translate-x-1/2 cvr-btn-primary text-xs px-3 py-1 rounded-full shadow"
                                @click="scrollToBottom"
                            >{{ $t('New messages') }} &darr;</button>
                        </div>

                        <form class="border-t cvr-border p-3 flex gap-2" @submit.prevent="sendMessage">
                            <input v-model="messageBody" class="cvr-input flex-1" :placeholder="$t('Type a message…')" />
                            <button type="submit" class="cvr-btn-primary px-4">{{ $t('Send') }}</button>
                        </form>
                    </template>
                    <div v-else class="flex-1 flex items-center justify-center cvr-text-muted text-sm">
                        {{ $t('Select a conversation, or start a new one.') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- New direct message modal -->
        <div v-if="showNewChat" class="cvr-modal-bg fixed inset-0 flex items-center justify-center z-50 p-4" @click.self="showNewChat = false">
            <div class="cvr-modal p-5 w-full max-w-md">
                <h2 class="font-bold cvr-text-primary mb-3">{{ $t('New Message') }}</h2>
                <select v-model="newChatUserId" class="cvr-select w-full mb-3">
                    <option value="" disabled>{{ $t('Choose a person') }}</option>
                    <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <textarea v-model="newChatText" rows="3" class="cvr-input w-full mb-3" :placeholder="$t('Write your message…')"></textarea>
                <div class="flex justify-end gap-2">
                    <button class="cvr-btn-secondary" @click="showNewChat = false">{{ $t('Cancel') }}</button>
                    <button class="cvr-btn-primary" @click="startDirectChat">{{ $t('Send') }}</button>
                </div>
            </div>
        </div>

        <!-- New support ticket modal -->
        <div v-if="showNewTicket" class="cvr-modal-bg fixed inset-0 flex items-center justify-center z-50 p-4" @click.self="showNewTicket = false">
            <div class="cvr-modal p-5 w-full max-w-md">
                <h2 class="font-bold cvr-text-primary mb-1">{{ $t('Report an Issue / Bug') }}</h2>
                <p class="text-xs cvr-text-muted mb-3">{{ $t('This goes straight to the Super Admin.') }}</p>
                <input v-model="ticketSubject" class="cvr-input w-full mb-3" :placeholder="$t('Subject (optional) — e.g. \'Cannot upload invoices\'')" />
                <textarea v-model="ticketBody" rows="4" class="cvr-input w-full mb-3" :placeholder="$t('Describe the problem — what happened, what you expected…')"></textarea>
                <div class="flex justify-end gap-2">
                    <button class="cvr-btn-secondary" @click="showNewTicket = false">{{ $t('Cancel') }}</button>
                    <button class="cvr-btn-copper" @click="startSupportTicket">{{ $t('Submit') }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
