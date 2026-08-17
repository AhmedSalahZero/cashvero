<?php

namespace App\Support\Activity;

use App\Models\RecordActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ActivityLogger
 * ==================================================================
 * The single writer of the per-record audit trail.
 *
 * Nothing else inserts into `record_activities` — so muting, company
 * resolution and the diff rules live in one place instead of being
 * re-derived at each call site.
 */
class ActivityLogger
{
    /**
     * Depth counter for withoutLogging(). A counter rather than a bool
     * so nested suppression (an import that internally calls a service
     * which also suppresses) unwinds correctly instead of the inner
     * block switching logging back on for the outer one.
     */
    private static int $muted = 0;

    /**
     * Stack of capture buffers used by asUpdate(). While a buffer is
     * open, log() diverts entries into it instead of writing them, so
     * the delete+create pair a "replace the row" edit produces can be
     * collapsed into the single update it really was.
     *
     * @var array<int, array<int, array{event:string, model:Model}>>
     */
    private static array $capture = [];

    /**
     * Run a callback without writing any activity.
     *
     * For bulk work where per-row history is noise rather than
     * information: invoice imports, company data imports, statement
     * rebuilds, seeders.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public static function withoutLogging(callable $callback): mixed
    {
        self::$muted++;

        try {
            return $callback();
        } finally {
            self::$muted--;
        }
    }

    /**
     * Mute for the current scope, releasing automatically when the
     * returned guard goes out of scope — including on an exception.
     *
     * Use this in queue jobs, where a bare mute would leak into every
     * later job handled by the same long-lived worker:
     *
     *     $mute = ActivityLogger::mute();
     */
    public static function mute(): ActivityMuteGuard
    {
        return new ActivityMuteGuard;
    }

    /** @internal used by ActivityMuteGuard */
    public static function incrementMute(): void
    {
        self::$muted++;
    }

    /** @internal used by ActivityMuteGuard */
    public static function decrementMute(): void
    {
        self::$muted = max(0, self::$muted - 1);
    }

    public static function isMuted(): bool
    {
        return self::$muted > 0 || ! config('activity.enabled', true);
    }

    /**
     * Record a lifecycle event produced by the observer.
     *
     * @param  array<int, array{field:string, from:mixed, to:mixed}>  $changes
     */
    public static function log(Model $subject, string $event, array $changes = [], ?string $description = null): ?RecordActivity
    {
        if (self::isMuted() || ! ActivityRegistry::tracks($subject)) {
            return null;
        }

        // An update that changed nothing worth showing is not an event.
        if ($event === RecordActivity::EVENT_UPDATED && $changes === []) {
            return null;
        }

        // Inside asUpdate(): remember what happened, write nothing yet.
        if (self::$capture !== []) {
            self::$capture[array_key_last(self::$capture)][] = ['event' => $event, 'model' => $subject];

            return null;
        }

        $user = self::actor();

        return RecordActivity::create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'company_id' => self::companyId($subject),
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'event' => $event,
            'description' => $description,
            'field_changes' => $changes ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Record something the field diff cannot express on its own —
     * an action whose meaning is not "this column changed".
     *
     *   ActivityLogger::custom($moneyReceived, 'resent the payment to Odoo');
     *
     * Prefer letting the observer describe ordinary edits; reach for
     * this only when the sentence would otherwise be wrong or empty.
     */
    public static function custom(Model $subject, string $description, array $changes = []): ?RecordActivity
    {
        return self::log($subject, RecordActivity::EVENT_CUSTOM, $changes, $description);
    }

    /**
     * Record a "replace the row" edit as a single update.
     *
     * ⚠️ Twelve controllers in this app implement update as DELETE the
     * old row + CREATE a new one (MoneyReceivedController::update()
     * says so outright: "التعديل معمول كـ حذف ثم إنشاء"). Left alone the
     * observer records that literally — a `deleted` on the old id and a
     * `created` on a brand-new id — so an edit reads as two unrelated
     * events and, worse, the record's history does not follow it: open
     * the row you just edited and it claims to have been created a
     * second ago with no past.
     *
     * This wraps that pattern so it reads the way a person means it:
     *
     *     $new = ActivityLogger::asUpdate($moneyReceived, function () {
     *         … delete + recreate …
     *         return $newMoneyReceived;
     *     });
     *
     * The callback runs with logging muted (no delete/create noise),
     * the old row's history is carried onto the new id, and one
     * `updated` entry is written with the real before/after diff.
     *
     * @template T of Model
     *
     * @param  callable():?T  $callback  must return the replacement model
     * @return ?T
     */
    public static function asUpdate(Model $old, callable $callback): mixed
    {
        if (self::isMuted() || ! ActivityRegistry::tracks($old)) {
            return $callback();
        }

        $class = $old::class;
        $oldId = $old->getKey();
        $before = self::auditableAttributes($old);

        /**
         * Capture rather than mute.
         *
         * The controllers doing this do not hand back the row they
         * created — their storeWithinTransaction() returns a redirect,
         * and several are shared with store(). Rather than change eleven
         * of them, listen: the observer still fires inside the block, so
         * the replacement announces itself and asUpdate() picks it up.
         */
        self::$capture[] = [];

        try {
            $result = $callback();
        } finally {
            $captured = array_pop(self::$capture);
        }

        $new = $result instanceof Model && $result::class === $class
            ? $result
            : self::replacementFrom($captured, $class);

        // Nothing comparable was created — record the block literally
        // rather than inventing an edit that did not happen.
        if (! $new instanceof Model) {
            self::flush($captured);

            return $result;
        }

        $newId = $new->getKey();

        /**
         * Carry the history forward. The old id no longer exists, so
         * nothing else points at these rows, and a person asking "what
         * happened to this cheque" means the thing — not the row id it
         * happens to occupy today.
         */
        if ($newId !== null && $oldId !== null && $newId != $oldId) {
            RecordActivity::where('subject_type', $class)
                ->where('subject_id', $oldId)
                ->update(['subject_id' => $newId]);
        }

        self::log($new, RecordActivity::EVENT_UPDATED, self::diffAttributes(
            $class, $before, self::auditableAttributes($new->refresh())
        ));

        return $result;
    }

    /**
     * The row that replaced the edited one: the last record of the same
     * class created inside the block.
     *
     * @param  array<int, array{event:string, model:Model}>  $captured
     */
    private static function replacementFrom(array $captured, string $class): ?Model
    {
        foreach (array_reverse($captured) as $entry) {
            if ($entry['event'] === RecordActivity::EVENT_CREATED && $entry['model']::class === $class) {
                return $entry['model'];
            }
        }

        return null;
    }

    /**
     * Write out a capture buffer unchanged — used when the block turned
     * out not to be a replacement after all, so its events are still
     * recorded rather than silently dropped.
     *
     * @param  array<int, array{event:string, model:Model}>  $captured
     */
    private static function flush(array $captured): void
    {
        foreach ($captured as $entry) {
            self::log($entry['model'], $entry['event']);
        }
    }

    /**
     * A record's attributes, minus bookkeeping and its own key — the key
     * always differs across a delete+create and would otherwise show up
     * as a change in every such edit.
     *
     * @return array<string, mixed>
     */
    private static function auditableAttributes(Model $model): array
    {
        $ignored = ActivityRegistry::ignoredFields($model::class);
        $ignored[$model->getKeyName()] = true;

        return array_diff_key($model->getAttributes(), $ignored);
    }

    /**
     * Diff two attribute snapshots.
     *
     * @return array<int, array{field:string, from:mixed, to:mixed}>
     */
    private static function diffAttributes(string $class, array $before, array $after): array
    {
        $changes = [];

        foreach (array_keys($before + $after) as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            // Loose, for the same reason as diff(): MySQL returns "0"
            // and "1500.00" where PHP holds 0 and 1500.
            if ($old == $new) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'from' => self::scalar($old),
                'to' => self::scalar($new),
            ];
        }

        return $changes;
    }

    /**
     * The diff for an update, filtered to fields worth showing.
     *
     * @return array<int, array{field:string, from:mixed, to:mixed}>
     */
    public static function diff(Model $subject): array
    {
        $ignored = ActivityRegistry::ignoredFields($subject::class);
        $changes = [];

        foreach ($subject->getDirty() as $field => $new) {
            if (isset($ignored[$field])) {
                continue;
            }

            $old = $subject->getOriginal($field);

            /**
             * Loose comparison on purpose. MySQL hands back "0" where
             * PHP holds 0, and decimals come back as "1500.00" against
             * a submitted 1500 — strict comparison would report a change
             * on every save and bury the real ones.
             */
            if ($old == $new) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'from' => self::scalar($old),
                'to' => self::scalar($new),
            ];
        }

        return $changes;
    }

    /**
     * Values go into JSON, so reduce them to something storable and
     * readable. Casts can hand back Carbon instances or arrays.
     */
    private static function scalar(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : null;
        }

        return $value;
    }

    private static function actor(): ?User
    {
        // auth() is unavailable in some console contexts; a queued job
        // or a scheduled command legitimately has no actor.
        try {
            $user = auth()->user();
        } catch (\Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    /**
     * The company this activity belongs to: the record's own, falling
     * back to the request's current company for models that carry no
     * company_id of their own (e.g. User).
     */
    private static function companyId(Model $subject): ?int
    {
        $own = $subject->getAttribute('company_id');

        if ($own !== null) {
            return (int) $own;
        }

        try {
            $current = currentCompany();

            return $current?->id;
        } catch (\Throwable) {
            return null;
        }
    }
}
