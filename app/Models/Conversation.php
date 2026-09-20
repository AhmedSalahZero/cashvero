<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A conversation is either:
 *  - type = 'direct'  : an ordinary chat between two users.
 *  - type = 'support' : a "Super Message" ticket a user raised with the
 *                        Super Admin, carrying a subject + status.
 *
 * Both are just "a thread of messages between some participants" under
 * the hood, so they share this one model/table instead of two parallel
 * implementations.
 */
class Conversation extends Model
{
    use HasFactory;

    const TYPE_DIRECT = 'direct';
    const TYPE_SUPPORT = 'support';

    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'type', 'subject', 'status', 'created_by', 'company_id',
    ];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSupport(): bool
    {
        return $this->type === self::TYPE_SUPPORT;
    }

    /**
     * Can $user open this conversation?
     * - Direct chat: must be one of the two participants.
     * - Support ticket: the reporting user, OR any Super Admin (so a new
     *   Super Admin can still see tickets raised before their account
     *   existed, not just ones created after).
     */
    public function isVisibleTo(User $user): bool
    {
        if ($this->isSupport() && $user->isSuperAdmin()) {
            return true;
        }

        return $this->participants()->where('users.id', $user->id)->exists();
    }

    /**
     * Find the existing 1-to-1 direct conversation between these two
     * users, or start a new one. Keeps the inbox from filling up with a
     * duplicate thread every time two people say hello again.
     */
    public static function findOrCreateDirect(User $a, User $b): self
    {
        $existing = self::query()
            ->where('type', self::TYPE_DIRECT)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $a->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $b->id))
            ->withCount('participants')
            ->having('participants_count', 2)
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = self::create([
            'type' => self::TYPE_DIRECT,
            'created_by' => $a->id,
        ]);

        $conversation->participants()->attach([$a->id, $b->id]);

        return $conversation;
    }

    /**
     * Start a new "Super Message" support ticket. Every current Super
     * Admin is added as a participant up front (so unread counts work
     * for them immediately, and they don't have to be discovered via
     * the isVisibleTo() role fallback alone).
     */
    public static function createSupportTicket(User $reporter, string $subject): self
    {
        $conversation = self::create([
            'type' => self::TYPE_SUPPORT,
            'subject' => $subject,
            'status' => self::STATUS_OPEN,
            'created_by' => $reporter->id,
        ]);

        $superAdminIds = User::role(User::SUPER_ADMIN)->pluck('users.id');
        $participantIds = $superAdminIds->push($reporter->id)->unique();

        $conversation->participants()->attach($participantIds);

        return $conversation;
    }

    /**
     * How many of $user's conversations have a message they haven't
     * read yet — drives the sidebar "Messages" badge.
     */
    public static function unreadCountFor(User $user): int
    {
        return \DB::table('conversations as c')
            ->join('conversation_participants as cp', function ($join) use ($user) {
                $join->on('cp.conversation_id', '=', 'c.id')
                    ->where('cp.user_id', '=', $user->id);
            })
            ->join('messages as m', 'm.conversation_id', '=', 'c.id')
            ->where('m.user_id', '!=', $user->id)
            ->whereNull('m.deleted_at') // a deleted message shouldn't keep a conversation flagged unread
            ->where(function ($query) {
                $query->whereNull('cp.last_read_at')
                    ->orWhereColumn('m.created_at', '>', 'cp.last_read_at');
            })
            ->distinct()
            ->count('c.id');
    }
}
