<?php

namespace App\Http\Controllers;

use App\Events\NewMessageSent;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Chat + "Super Message" support tickets.
 * ------------------------------------------------------------------
 * One inbox page (Messaging/Inbox.vue) serves three audiences:
 *   - a regular user's direct chats with colleagues
 *   - that same user's own support tickets ("Super Message")
 *   - the Super Admin's inbox of every open/in-progress/resolved ticket
 *
 * See App\Models\Conversation for why both chat and tickets share one
 * table/model.
 *
 * Live updates are driven by polling, not by broadcasting: no driver is
 * configured (BROADCAST_DRIVER=log), so Laravel Echo never comes up in
 * the browser. Two endpoints exist purely for that loop — updates() for
 * the open thread and poll() for the inbox — both built to answer
 * "nothing changed" as cheaply as possible, since that's what they
 * mostly answer. The broadcast() calls are left in place so configuring
 * Pusher later upgrades the experience without touching this code.
 */
class MessagingController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Contacts a user can start a direct chat with.
        //
        // ⚠️ Real bug fixed here (reported 2026-09-20): this used to show
        // ALL companies a user belongs to (or, for a Super Admin, every
        // user in the whole system) rather than just the ONE company the
        // person currently has open. Now it's scoped to that single
        // company — passed in via ?company=… on the Messages link (see
        // SidebarMenu::build()) — for everyone, Super Admin included.
        // Falls back to the broader behaviour only when there's truly no
        // company context at all (e.g. visiting /messages directly with
        // no company ever selected).
        $companyId = $request->query('company');
        $company = $companyId ? Company::find($companyId) : null;

        // A non-Super-Admin may only scope to a company they actually
        // belong to — never trust the query string blindly.
        if ($company && ! $user->isSuperAdmin() && ! $user->companies->contains('id', $company->id)) {
            $company = null;
        }

        if ($company) {
            $contacts = User::whereHas('companies', fn ($q) => $q->where('companies.id', $company->id))
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        } elseif ($user->isSuperAdmin()) {
            $contacts = User::where('id', '!=', $user->id)->orderBy('name')->get(['id', 'name', 'email']);
        } else {
            $contacts = User::whereHas('companies', fn ($q) => $q->whereIn('companies.id', $user->companies->pluck('id')))
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        return Inertia::render('Messaging/Inbox', [
            'isSuperAdmin' => $user->isSuperAdmin(),
            'conversations' => $this->inboxLists($user),
            'contacts' => $contacts,
            /**
             * ⚠️ Real bug fixed here (reported 2026-09-20, "Could not load
             * that conversation"): the Vue page used hand-typed paths like
             * '/messages/${id}', which silently 404'd on any locale other
             * than an unprefixed default — this app puts the locale in
             * every URL (e.g. /en/messages/...), via LaravelLocalization.
             * route() already knows the correct current-locale prefix, so
             * generate every URL here, server-side, once, instead of the
             * frontend trying to reconstruct it.
             */
            'routes' => [
                'start' => route('messages.start'),
                'support' => route('messages.support.start'),
                // Inbox-wide background refresh: one request that covers
                // both lists plus the sidebar badge.
                'poll' => route('messages.poll'),
                // {id} is a literal placeholder the frontend swaps out —
                // simplest way to hand over a whole family of per-conversation
                // URLs without listing one for every conversation up front.
                'show' => route('messages.show', ['conversation' => '__ID__']),
                'updates' => route('messages.updates', ['conversation' => '__ID__']),
                'send' => route('messages.send', ['conversation' => '__ID__']),
                'read' => route('messages.read', ['conversation' => '__ID__']),
                'status' => route('messages.status', ['conversation' => '__ID__']),
                // Message-level route: needs BOTH the conversation id and the
                // message id, so it carries two placeholders.
                'deleteMessage' => route('messages.delete', ['conversation' => '__ID__', 'message' => '__MSG_ID__']),
            ],
        ]);
    }

    /**
     * JSON: full message history for one conversation — the first paint
     * when a thread is opened. Everything after that arrives through
     * updates() below, which only ships what changed.
     *
     * `server_time` is the handoff to that delta: the frontend sends it
     * straight back as `since` on its first poll, so the two can't
     * disagree about where "already seen" ends even if the browser
     * clock is wrong.
     */
    public function show(Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $messages = $conversation->messages()
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Message $m) => $this->formatMessage($m));

        return response()->json([
            'conversation' => $this->formatConversation($conversation, Auth::user()),
            'messages' => $messages,
            'last_id' => (int) ($conversation->messages()->max('id') ?? 0),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * JSON: what changed in one conversation since the caller last
     * looked. Replaces re-downloading the entire thread every few
     * seconds, which is what the old polling fallback did.
     *
     *   after_id  — highest message id the caller already holds.
     *   since     — server_time from its previous response.
     *   mark_read — advance this user's read watermark in the same trip.
     *
     * Two kinds of change come back:
     *   messages — anything newer than after_id, in full.
     *   changed  — messages the caller already has whose body/deleted
     *              flag moved since `since`. Tiny by construction, and
     *              the only way a deletion can reach a client that
     *              isn't re-reading the whole history.
     */
    public function updates(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'since' => ['nullable', 'date'],
            'mark_read' => ['nullable', 'boolean'],
        ]);

        $afterId = (int) ($data['after_id'] ?? 0);

        $new = $conversation->messages()
            ->with('user:id,name')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get()
            ->map(fn (Message $m) => $this->formatMessage($m));

        $changed = collect();

        if ($afterId > 0 && ! empty($data['since'])) {
            /**
             * The 2-second overlap is deliberate. `updated_at` has no
             * fractional seconds, so a change written in the same second
             * the previous response was generated would otherwise fall
             * exactly on the boundary and be missed forever. Re-sending a
             * change once or twice costs nothing — applying it is
             * idempotent (a message only ever goes to deleted, never back).
             */
            $changed = $conversation->messages()
                ->where('id', '<=', $afterId)
                ->where('updated_at', '>=', Carbon::parse($data['since'])->subSeconds(2))
                ->get(['id', 'body', 'deleted_at'])
                ->map(fn (Message $m) => [
                    'id' => $m->id,
                    'body' => $m->isDeleted() ? null : $m->body,
                    'deleted' => $m->isDeleted(),
                ]);
        }

        /**
         * Reading happens in the same round trip as fetching. The old
         * code only marked a thread read once, at the moment it was
         * opened, so anything that arrived while you sat there reading
         * it stayed "unread" on the server and kept the badge lit.
         */
        if ($request->boolean('mark_read') && $new->isNotEmpty()) {
            $this->touchLastReadAt($conversation);
        }

        return response()->json([
            // Carries the ticket status so a Super Admin flipping it to
            // Resolved shows up for the reporter without a reload.
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'subject' => $conversation->subject,
            ],
            'messages' => $new->values(),
            'changed' => $changed->values(),
            'last_id' => (int) ($conversation->messages()->max('id') ?? $afterId),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * JSON: the whole inbox as the background poller sees it — both
     * lists plus the sidebar's unread total, in one request.
     *
     * `version` is a cheap fingerprint of everything this response
     * depends on. The caller echoes back the one it already has, and the
     * overwhelmingly common "nothing happened" answer costs three
     * aggregate queries instead of hydrating every conversation with its
     * participants and last message.
     */
    public function poll(Request $request)
    {
        $user = Auth::user();
        $version = $this->inboxVersion($user);

        if ($request->query('version') === $version) {
            return response()->json(['changed' => false, 'version' => $version]);
        }

        return response()->json([
            'changed' => true,
            'version' => $version,
            'unread_total' => Conversation::unreadCountFor($user),
            'conversations' => $this->inboxLists($user),
        ]);
    }

    /** Start (or reuse) a direct chat with another user, with an opening message. */
    public function startDirect(Request $request)
    {
        $data = $request->validate([
            /**
             * Rule::notIn, not `different`. The `different` rule takes
             * the NAME OF ANOTHER FIELD in the same request, not a
             * value — so "different:7" compared user_id against a field
             * literally named "7", which never exists, so the rule
             * always passed. Messaging yourself then reached
             * findOrCreateDirect($me, $me), which tries to attach the
             * same participant twice and dies on the unique key: a 500
             * where a 422 belongs.
             */
            'user_id' => ['required', 'exists:users,id', Rule::notIn([Auth::id()])],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $me = Auth::user();
        $other = User::findOrFail($data['user_id']);

        /**
         * Colleagues only. index() already limits the contacts dropdown
         * to people sharing a company with you, but that is the UI, not
         * a guard: this endpoint takes a raw user_id, so without the
         * check below anyone could open a chat with anyone in any other
         * client's company just by knowing their id.
         *
         * A Super Admin is exempt on either side — they support every
         * company and belong to none in particular.
         */
        if (! $me->isSuperAdmin() && ! $other->isSuperAdmin()) {
            $shared = $me->companies->pluck('id')->intersect($other->companies->pluck('id'));

            if ($shared->isEmpty()) {
                throw new AccessDeniedHttpException();
            }
        }

        $conversation = Conversation::findOrCreateDirect($me, $other);

        $message = $this->postMessage($conversation, $data['body']);

        return response()->json([
            'conversation_id' => $conversation->id,
            'message' => $this->formatMessage($message),
        ]);
    }

    /** Raise a new "Super Message" support ticket. */
    public function startSupport(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $conversation = Conversation::createSupportTicket(Auth::user(), $data['subject']);
        $message = $this->postMessage($conversation, $data['body']);

        return response()->json([
            'conversation_id' => $conversation->id,
            'message' => $this->formatMessage($message),
        ]);
    }

    /** Send a message into an existing conversation (chat or ticket reply). */
    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        // A Super Admin replying to a ticket they weren't yet a
        // participant of (e.g. it predates their account) is added now,
        // so it starts showing up in their unread counts too.
        if ($conversation->isSupport() && Auth::user()->isSuperAdmin()) {
            $conversation->participants()->syncWithoutDetaching([Auth::id()]);
        }

        $message = $this->postMessage($conversation, $data['body']);

        return response()->json([
            'message' => $this->formatMessage($message),
        ]);
    }

    /** Mark a conversation as read up to now for the current user. */
    public function markRead(Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $this->touchLastReadAt($conversation);

        return response()->json(['ok' => true]);
    }

    /** Super Admin only: change a ticket's status. */
    public function updateStatus(Request $request, Conversation $conversation)
    {
        if (! Auth::user()->isSuperAdmin() || ! $conversation->isSupport()) {
            throw new AccessDeniedHttpException();
        }

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,resolved'],
        ]);

        $conversation->update(['status' => $data['status']]);

        return response()->json(['ok' => true, 'status' => $conversation->status]);
    }

    /** Sender deletes their own message. Leaves a placeholder in its place (see migration docblock). */
    public function deleteMessage(Conversation $conversation, Message $message)
    {
        $this->authorizeConversation($conversation);

        if ($message->conversation_id !== $conversation->id) {
            throw new AccessDeniedHttpException();
        }

        if ($message->user_id !== Auth::id()) {
            throw new AccessDeniedHttpException();
        }

        // Direct property set + save(), not update(['deleted_at' => ...]) —
        // that goes through mass-assignment, which silently no-ops on any
        // column not in $fillable. Doing it this way can never quietly
        // fail again even if $fillable changes later.
        $message->deleted_at = now();
        $message->save();

        broadcast(new \App\Events\MessageDeleted($message))->toOthers();

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------

    private function postMessage(Conversation $conversation, string $body): Message
    {
        $message = $conversation->messages()->create([
            'user_id' => Auth::id(),
            'body' => $body,
        ]);
        $message->load('user:id,name');

        // Sending counts as reading your own new message.
        $this->touchLastReadAt($conversation);

        broadcast(new NewMessageSent($message))->toOthers();

        return $message;
    }

    /**
     * Advance the current user's read watermark on this conversation.
     * Also bumps the pivot's updated_at, which is one of the inputs to
     * inboxVersion() — so reading a thread in one tab invalidates the
     * poller's fingerprint in the others.
     */
    private function touchLastReadAt(Conversation $conversation): void
    {
        $conversation->participants()->syncWithoutDetaching([
            Auth::id() => ['last_read_at' => now()],
        ]);
    }

    private function authorizeConversation(Conversation $conversation): void
    {
        if (! $conversation->isVisibleTo(Auth::user())) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     * The two inbox lists, already formatted. Shared by index() (first
     * paint) and poll() (background refresh) so the two can never drift
     * into disagreeing about ordering, titles or unread flags.
     *
     * @return array{direct: array, support: array}
     */
    private function inboxLists(User $user): array
    {
        $conversations = $user->conversations()
            ->with(['participants:id,name,email', 'lastMessage.user:id,name'])
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->latest()
                    ->limit(1)
            )
            ->get();

        // Super Admin also sees every support ticket, including ones
        // raised before their account existed (see isVisibleTo()).
        if ($user->isSuperAdmin()) {
            $extraTickets = Conversation::query()
                ->where('type', Conversation::TYPE_SUPPORT)
                ->whereDoesntHave('participants', fn ($q) => $q->where('users.id', $user->id))
                ->with(['participants:id,name,email', 'lastMessage.user:id,name'])
                ->get();

            $conversations = $conversations->concat($extraTickets);
        }

        $direct = $conversations->where('type', Conversation::TYPE_DIRECT)->values();
        $support = $conversations->where('type', Conversation::TYPE_SUPPORT)
            ->sortBy(fn ($c) => $c->status === Conversation::STATUS_RESOLVED ? 1 : 0)
            ->values();

        return [
            'direct' => $this->formatConversationList($direct, $user),
            'support' => $this->formatConversationList($support, $user),
        ];
    }

    /**
     * Ids of every conversation $user may see — the scope both the
     * fingerprint below and inboxLists() above work over.
     *
     * @return Collection<int, int>
     */
    private function visibleConversationIds(User $user): Collection
    {
        $ids = DB::table('conversation_participants')
            ->where('user_id', $user->id)
            ->pluck('conversation_id');

        if ($user->isSuperAdmin()) {
            $ids = $ids->concat(
                DB::table('conversations')
                    ->where('type', Conversation::TYPE_SUPPORT)
                    ->pluck('id')
            );
        }

        return $ids->unique()->values();
    }

    /**
     * A short string that changes whenever anything the inbox displays
     * changes, and otherwise stays byte-identical. Covers:
     *
     *   - a new message anywhere       -> MAX(messages.id), COUNT(*)
     *   - a deletion                   -> COUNT(messages.deleted_at)
     *   - a ticket status change,
     *     or a brand new conversation  -> conversations aggregates
     *   - a read in another tab        -> pivot updated_at
     *
     * All aggregates, no model hydration — that's the whole point.
     *
     * ⚠️ The deleted count is what actually catches deletions, not
     * MAX(updated_at): timestamps have no fractional seconds, so sending
     * a message and deleting it inside the same second produced a
     * byte-identical fingerprint and the list preview never updated.
     * Counting deleted rows can't collide that way.
     */
    private function inboxVersion(User $user): string
    {
        $ids = $this->visibleConversationIds($user);

        if ($ids->isEmpty()) {
            return 'empty';
        }

        $messages = DB::table('messages')
            ->whereIn('conversation_id', $ids)
            ->selectRaw('COUNT(*) as total, COUNT(deleted_at) as deleted_total, MAX(id) as max_id, MAX(updated_at) as max_updated')
            ->first();

        $threads = DB::table('conversations')
            ->whereIn('id', $ids)
            ->selectRaw('COUNT(*) as total, MAX(updated_at) as max_updated')
            ->first();

        $read = DB::table('conversation_participants')
            ->where('user_id', $user->id)
            ->max('updated_at');

        return implode('|', [
            $messages->total ?? 0,
            $messages->deleted_total ?? 0,
            $messages->max_id ?? 0,
            $messages->max_updated ?? '-',
            $threads->total ?? 0,
            $threads->max_updated ?? '-',
            $read ?? '-',
        ]);
    }

    private function formatConversationList($conversations, User $viewer): array
    {
        return $conversations->map(fn (Conversation $c) => $this->formatConversation($c, $viewer))->values()->all();
    }

    private function formatConversation(Conversation $conversation, User $viewer): array
    {
        $pivot = $conversation->participants->firstWhere('id', $viewer->id)?->pivot;
        $lastReadAt = $pivot?->last_read_at;
        $lastMessage = $conversation->lastMessage;

        $unread = $lastMessage
            && $lastMessage->user_id !== $viewer->id
            && (! $lastReadAt || $lastMessage->created_at->gt($lastReadAt));

        // For a direct chat, "the other person" — used as the list label.
        $other = $conversation->type === Conversation::TYPE_DIRECT
            ? $conversation->participants->firstWhere('id', '!=', $viewer->id)
            : null;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'subject' => $conversation->subject,
            'status' => $conversation->status,
            'title' => $conversation->type === Conversation::TYPE_DIRECT
                ? ($other->name ?? 'Deleted user')
                : ($conversation->subject ?? 'Support ticket'),
            'other_user' => $other ? ['id' => $other->id, 'name' => $other->name] : null,
            // Lets the frontend merge a polled snapshot into the list it
            // already has without guessing whether the preview moved.
            'last_message_id' => $lastMessage?->id,
            'last_message' => $lastMessage ? [
                'body' => $lastMessage->isDeleted() ? __('This message was deleted') : $lastMessage->body,
                'created_at' => $lastMessage->created_at->toIso8601String(),
                'user_name' => $lastMessage->user->name ?? '',
            ] : null,
            'unread' => $unread,
        ];
    }

    private function formatMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->isDeleted() ? null : $message->body,
            'deleted' => $message->isDeleted(),
            'created_at' => $message->created_at->toIso8601String(),
            'user' => [
                'id' => $message->user->id,
                'name' => $message->user->name,
            ],
        ];
    }
}
