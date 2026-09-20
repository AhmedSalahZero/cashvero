<script setup>
/**
 * One inbox, two tabs:
 *   - "Messages": ordinary chat with colleagues (route: messages.start / messages.send)
 *   - "Support" : "Super Message" tickets to the Super Admin
 *                 (route: messages.support.start), shown ticket-style
 *                 with an Open / In Progress / Resolved status.
 *
 * Real-time updates go through Laravel Echo (window.Echo, set up in
 * resources/js/bootstrap.js) on a private channel per conversation.
 * If no broadcasting driver is configured yet, the page still works —
 * you just won't see the other person's messages appear live without
 * reopening the conversation. See MESSAGING_SETUP.md.
 */
import { ref, computed, nextTick, watch, onBeforeUnmount } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useToasts } from '@/composables/useToasts';

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
let currentChannelName = null;

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

function scrollToBottom() {
    nextTick(() => messagesEnd.value?.scrollIntoView({ block: 'end' }));
}

function leaveChannel() {
    if (currentChannelName && window.Echo) {
        window.Echo.leave(currentChannelName);
    }
    currentChannelName = null;
}

/**
 * Fallback for when no live broadcasting driver (Pusher) is configured
 * (see MESSAGING_SETUP.md): without it, joinChannel() below has nothing
 * to listen to, so a new message or a deletion made by the other person
 * would otherwise sit unnoticed until you manually reopen the
 * conversation. This quietly re-checks every few seconds instead, so
 * both still show up on their own — just not instantly.
 */
let pollTimer = null;

function startPolling(conversationId) {
    stopPolling();
    if (window.Echo) return; // live updates already cover this
    pollTimer = setInterval(async () => {
        if (!selected.value || selected.value.id !== conversationId) return;
        try {
            const { data } = await window.axios.get(urlFor(props.routes.show, conversationId));
            const hadCount = messages.value.length;
            // ⚠️ Real bug fixed here ("delete a message, it comes back"):
            // this background check can be mid-flight from BEFORE you
            // deleted a message, and its response would then land AFTER
            // your delete and overwrite it with the old, pre-deletion
            // copy. A deletion made locally always wins over a stale
            // background read — it can only ever go from not-deleted to
            // deleted, never the other way.
            messages.value = data.messages.map(fresh => {
                const local = messages.value.find(m => m.id === fresh.id);
                return local?.deleted ? local : fresh;
            });
            if (messages.value.length > hadCount) scrollToBottom();
        } catch (e) {
            // Silent — this is just a background refresh, not a user action.
        }
    }, 5000);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function joinChannel(conversationId) {
    if (!window.Echo) return; // no broadcasting driver configured yet
    currentChannelName = `conversation.${conversationId}`;
    window.Echo.private(currentChannelName).listen('.message.sent', (payload) => {
        if (selected.value?.id === conversationId) {
            messages.value.push(payload);
            scrollToBottom();
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

function markListItemUnread(conversationId) {
    for (const key of ['direct', 'support']) {
        const item = lists.value[key].find(c => c.id === conversationId);
        if (item) item.unread = true;
    }
}

async function selectConversation(item) {
    leaveChannel();
    stopPolling();
    selected.value = item;
    messages.value = [];
    loadingMessages.value = true;
    try {
        const { data } = await window.axios.get(urlFor(props.routes.show, item.id));
        messages.value = data.messages;
        selected.value = { ...item, ...data.conversation };
        scrollToBottom();
        await window.axios.post(urlFor(props.routes.read, item.id));
        item.unread = false;
        joinChannel(item.id);
        startPolling(item.id);
    } catch (e) {
        pushToast('error', 'Could not load that conversation.');
    } finally {
        loadingMessages.value = false;
    }
}

async function sendMessage() {
    const body = messageBody.value.trim();
    if (!body || !selected.value) return;
    messageBody.value = '';
    try {
        const { data } = await window.axios.post(urlFor(props.routes.send, selected.value.id), { body });
        messages.value.push(data.message);
        scrollToBottom();
    } catch (e) {
        pushToast('error', 'Message could not be sent.');
        messageBody.value = body;
    }
}

async function deleteMessage(message) {
    if (!confirm('Delete this message? This cannot be undone.')) return;
    try {
        await window.axios.delete(deleteMessageUrl(selected.value.id, message.id));
        message.deleted = true;
        message.body = null;
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

// After creating something new, re-pull the inbox list (Inertia partial
// reload keeps this cheap — sidebarMenu badge, etc. refresh too) and
// open the new thread.
async function refreshAndOpen(tab, conversationId) {
    const { router } = await import('@inertiajs/vue3');
    activeTab.value = tab;
    router.reload({
        only: ['conversations'],
        onSuccess: (page) => {
            lists.value.direct = page.props.conversations.direct;
            lists.value.support = page.props.conversations.support;
            const item = lists.value[tab].find(c => c.id === conversationId);
            if (item) selectConversation(item);
        },
    });
}

onBeforeUnmount(() => {
    leaveChannel();
    stopPolling();
});
</script>

<template>
    <AppLayout>
        <div class="p-4 md:p-6 h-[calc(100vh-4rem)] flex flex-col">
            <h1 class="text-xl font-bold cvr-text-primary mb-4">{{ $t('Messages') }}</h1>

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

                        <div class="flex-1 overflow-y-auto p-4 space-y-3">
                            <div v-if="loadingMessages" class="text-sm cvr-text-muted text-center">{{ $t('Loading…') }}</div>
                            <div v-for="m in messages" :key="m.id" class="max-w-[75%] group" :class="m.user.id === $page.props.auth.user.id ? 'ms-auto text-end' : ''">
                                <div class="inline-flex items-center gap-1" :class="m.user.id === $page.props.auth.user.id ? 'flex-row-reverse' : ''">
                                    <div
                                        class="inline-block rounded-lg px-3 py-2 text-sm"
                                        :style="m.deleted
                                            ? 'background: transparent; border: 1px dashed var(--cvr-border); color: var(--cvr-text-muted); font-style: italic;'
                                            : (m.user.id === $page.props.auth.user.id
                                                ? 'background: var(--cvr-green-hover); color: #fff;'
                                                : 'background: var(--cvr-bg-hover); color: var(--cvr-text-primary);')"
                                    >
                                        <div v-if="!m.deleted && m.user.id !== $page.props.auth.user.id" class="text-[11px] font-semibold mb-0.5 opacity-70">{{ m.user.name }}</div>
                                        <div v-if="m.deleted" class="whitespace-pre-wrap break-words">{{ $t('This message was deleted') }}</div>
                                        <div v-else class="whitespace-pre-wrap break-words">{{ m.body }}</div>
                                    </div>
                                    <button
                                        v-if="!m.deleted && m.user.id === $page.props.auth.user.id"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity text-xs cvr-text-muted hover:text-red-400 px-1"
                                        :title="$t('Delete message')"
                                        @click="deleteMessage(m)"
                                    >🗑</button>
                                </div>
                                <div class="text-[10px] cvr-text-muted mt-0.5">{{ new Date(m.created_at).toLocaleString() }}</div>
                            </div>
                            <div ref="messagesEnd"></div>
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
