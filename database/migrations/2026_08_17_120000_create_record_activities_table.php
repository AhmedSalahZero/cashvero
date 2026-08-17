<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-record audit trail — "who changed what, on which record, when".
 *
 * ⚠️ Deliberately NOT the existing `logs` table. That one is a session
 * trail (logged in / logged out / entered section): it has no subject
 * columns at all, and its `created_at` is a string while `updated_at`
 * is an integer. It answers a different question and could not carry
 * this data.
 *
 * Rows here are immutable — written once, never edited — so there is no
 * `updated_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_activities', function (Blueprint $table) {
            $table->bigIncrements('id');

            // The audited record. Polymorphic: `subject_type` holds the
            // model class so one table serves every module.
            $table->string('subject_type', 191);
            $table->unsignedBigInteger('subject_id');

            // Scoping. Kept even though it is derivable from the subject,
            // because every read is company-scoped and joining out to the
            // subject for that would be pointless work.
            $table->unsignedBigInteger('company_id')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();

            /**
             * Snapshot of who acted, taken at write time. A log that says
             * "user #38 deleted this" is useless once #38 is gone, and
             * users here are soft-deletable. The id stays for linking
             * when the account still exists.
             */
            $table->string('user_name', 191)->nullable();

            // created | updated | deleted | restored | custom
            $table->string('event', 40);

            /**
             * Only set for `custom` events, where the sentence cannot be
             * derived from a field diff (e.g. "resent to Odoo"). Ordinary
             * events are rendered at READ time from `event` + `changes`,
             * so the log follows the viewer's language instead of being
             * frozen in whatever locale the actor happened to use.
             */
            $table->string('description', 255)->nullable();

            /**
             * [{ "field": "type", "from": "cheque", "to": "cheque-under-collection" }, …]
             * Raw values only — field and value LABELS are resolved on
             * read from ActivityRegistry, for the same i18n reason.
             *
             * ⚠️ NOT named `changes`. Eloquent declares its own
             * `protected $changes` on every model (HasAttributes:70) to
             * track dirty state, so an attribute of that name is
             * shadowed by the property: writes appear to succeed and
             * reads come back empty, silently. Do not rename this back.
             */
            $table->json('field_changes')->nullable();

            $table->timestamp('created_at')->nullable();

            // The timeline query: one record's history, newest first.
            $table->index(['subject_type', 'subject_id', 'id'], 'ra_subject_idx');
            // Company-wide activity feeds and retention pruning.
            $table->index(['company_id', 'created_at'], 'ra_company_created_idx');
            $table->index('user_id', 'ra_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_activities');
    }
};
