<?php

namespace App\Http\Controllers;

use App\Events\NewMessageSent;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
 * table/model, and MESSAGING_SETUP.md for what still needs configuring
 * (a real-time broadcasting driver) before messages appear live.
 */
class MessagingController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

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
            'conversations' => [
                'direct' => $this->formatConversationList($direct, $user),
                'support' => $this->formatConversationList($support, $user),
            ],
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
                // {id} is a literal placeholder the frontend swaps out —
                // simplest way to hand over a whole family of per-conversation
                // URLs without listing one for every conversation up front.
                'show' => route('messages.show', ['conversation' => '__ID__']),
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
     * JSON: full message history for one conversation (used by the Vue
     * page when a conversation is opened, and to poll as a fallback if
     * no real-time broadcasting driver is configured).
     */
    public function show(Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, Auth::user()),
            'messages' => $conversation->messages()
                ->with('user:id,name')
                ->orderBy('created_at')
                ->get()
                ->map(fn (Message $m) => $this->formatMessage($m)),
        ]);
    }

    /** Start (or reuse) a direct chat with another user, with an opening message. */
    public function startDirect(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', 'different:' . Auth::id()],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $other = User::findOrFail($data['user_id']);
        $conversation = Conversation::findOrCreateDirect(Auth::user(), $other);

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

        $conversation->participants()->syncWithoutDetaching([
            Auth::id() => ['last_read_at' => now()],
        ]);

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
        $conversation->participants()->syncWithoutDetaching([
            Auth::id() => ['last_read_at' => now()],
        ]);

        broadcast(new NewMessageSent($message))->toOthers();

        return $message;
    }

    private function authorizeConversation(Conversation $conversation): void
    {
        if (! $conversation->isVisibleTo(Auth::user())) {
            throw new AccessDeniedHttpException();
        }
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
