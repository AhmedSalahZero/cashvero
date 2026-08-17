<?php

namespace App\Models;

use App\Support\Activity\ActivityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a record's history.
 *
 * Rows are immutable: written once by ActivityLogger and never edited,
 * which is why `$timestamps` is off (only `created_at` is set, by hand).
 *
 * Sentences are rendered HERE, on read, rather than stored — see the
 * migration for why. That keeps the log in the reader's language and
 * lets a label correction apply retroactively instead of leaving old
 * entries frozen in wording that has since changed.
 */
class RecordActivity extends Model
{
    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_DELETED = 'deleted';

    public const EVENT_RESTORED = 'restored';

    public const EVENT_CUSTOM = 'custom';

    protected $table = 'record_activities';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'field_changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    /**
     * Who acted, preferring the snapshot taken at write time — the
     * account may since have been renamed or soft-deleted, and the log
     * should say who it was then.
     */
    public function actorName(): string
    {
        return $this->user_name ?: ($this->user?->name ?? __('System'));
    }

    /**
     * The headline sentence, e.g.
     *   "moved Money Received from Cheques In Safe to Cheque Under Collection"
     *   "created Cash Expense"
     *   "deleted Contract"
     */
    public function sentence(): string
    {
        $subject = ActivityRegistry::labelFor($this->subject_type);

        if ($this->event === self::EVENT_CUSTOM) {
            return $this->description ?: __('performed an action on :subject', ['subject' => $subject]);
        }

        if ($this->event === self::EVENT_CREATED) {
            return __('created :subject', ['subject' => $subject]);
        }

        if ($this->event === self::EVENT_DELETED) {
            return __('deleted :subject', ['subject' => $subject]);
        }

        if ($this->event === self::EVENT_RESTORED) {
            return __('restored :subject', ['subject' => $subject]);
        }

        /**
         * An update that moved a status field reads as a transition —
         * this is the "moved the cheque from X to Y" case, and it is the
         * whole reason a raw field diff was not good enough.
         */
        $status = $this->statusTransition();

        if ($status !== null) {
            return __('moved :subject from :from to :to', [
                'subject' => $subject,
                'from' => $status['from'],
                'to' => $status['to'],
            ]);
        }

        $count = count($this->field_changes ?? []);

        return trans_choice('updated :subject|updated :subject (:count fields)', $count, [
            'subject' => $subject,
            'count' => $count,
        ]);
    }

    /**
     * The status/type change inside this entry, already labelled — or
     * null when the update did not move the record's state.
     *
     * @return array{field:string, from:string, to:string}|null
     */
    public function statusTransition(): ?array
    {
        foreach ($this->field_changes ?? [] as $change) {
            $field = $change['field'] ?? null;

            if (! in_array($field, ['type', 'status'], true)) {
                continue;
            }

            return [
                'field' => ActivityRegistry::fieldLabel($this->subject_type, $field),
                'from' => ActivityRegistry::valueLabel($this->subject_type, $field, $change['from'] ?? null),
                'to' => ActivityRegistry::valueLabel($this->subject_type, $field, $change['to'] ?? null),
            ];
        }

        return null;
    }

    /**
     * The field diff, labelled for display.
     *
     * @return array<int, array{label:string, from:string, to:string}>
     */
    public function labelledChanges(): array
    {
        $rows = [];

        foreach ($this->field_changes ?? [] as $change) {
            $field = $change['field'] ?? null;

            if (! $field) {
                continue;
            }

            $rows[] = [
                'label' => ActivityRegistry::fieldLabel($this->subject_type, $field),
                'from' => ActivityRegistry::valueLabel($this->subject_type, $field, $change['from'] ?? null),
                'to' => ActivityRegistry::valueLabel($this->subject_type, $field, $change['to'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * Shape sent to the timeline modal.
     */
    public function toTimelineArray(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'actor' => $this->actorName(),
            'sentence' => $this->sentence(),
            'changes' => $this->event === self::EVENT_UPDATED ? $this->labelledChanges() : [],
            // Both forms: absolute for the record, relative for scanning.
            'at' => $this->created_at?->format('Y-m-d H:i'),
            'at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
