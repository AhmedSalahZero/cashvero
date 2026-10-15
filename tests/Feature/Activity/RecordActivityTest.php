<?php

namespace Tests\Feature\Activity;

use App\Models\RecordActivity;
use App\Support\Activity\ActivityLogger;
use App\Support\Activity\ActivityRegistry;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * Structural guarantees for the per-record audit trail.
 *
 * These need no database — they check the declaration every other part
 * derives from.
 */
class RecordActivityTest extends TestCase
{
    public function test_every_audited_model_class_exists_and_is_eloquent(): void
    {
        $bad = [];

        foreach (ActivityRegistry::models() as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                $bad[] = $class;
            }
        }

        $this->assertSame([], $bad, "Audited entries that are not Eloquent models:\n  ".implode("\n  ", $bad));
    }

    /**
     * Reading a record's history is gated by the permission that governs
     * viewing the record. If a model's module has no `.view` permission
     * the controller would deny everyone, so catch it here instead.
     */
    public function test_every_audited_model_maps_to_a_real_view_permission(): void
    {
        $bad = [];

        foreach (ActivityRegistry::models() as $class) {
            $module = ActivityRegistry::moduleFor($class);

            if (! $module || ! PermissionRegistry::has("{$module}.view")) {
                $bad[] = class_basename($class).' => '.($module ?? 'NULL');
            }
        }

        $this->assertSame([], $bad, "Audited models with no valid view permission:\n  ".implode("\n  ", $bad));
    }

    /**
     * Derived tables must stay out. They are rewritten wholesale by
     * nearly every transaction and partly by DB triggers that bypass
     * Eloquent, so auditing them yields noise that is also incomplete.
     */
    public function test_derived_tables_are_not_audited(): void
    {
        /**
         * Names that end in a derived-looking suffix but are genuinely
         * user-maintained. ForeignExchangeRate is a rate people type in,
         * not an interest-rate chapter recalculated by the system —
         * the `*OverdraftRate` models are the derived ones.
         */
        $intentional = ['ForeignExchangeRate'];

        $leaked = array_values(array_filter(
            array_map('class_basename', ActivityRegistry::models()),
            fn ($name) => preg_match('/(Statement|Limit|Withdrawal|Rate)$/', $name)
                && ! in_array($name, $intentional, true)
        ));

        $this->assertSame([], $leaked,
            "Derived/recalculated models must not be audited — they are rewritten by every\n"
            ."transaction and partly by DB triggers that bypass Eloquent entirely:\n  "
            .implode("\n  ", $leaked)
        );
    }

    public function test_secrets_are_never_recorded(): void
    {
        foreach ([\App\Models\User::class] as $class) {
            $ignored = ActivityRegistry::ignoredFields($class);

            foreach (['password', 'remember_token'] as $secret) {
                $this->assertArrayHasKey($secret, $ignored,
                    "{$secret} must never appear in a diff for ".class_basename($class)
                );
            }
        }
    }

    public function test_value_labels_render_slugs_as_words(): void
    {
        $this->assertSame('Cheque Under Collection', ActivityRegistry::valueLabel(
            \App\Models\MoneyReceived::class, 'type', 'cheque-under-collection'
        ));

        // A null reads as an em dash, not the word "null".
        $this->assertSame('—', ActivityRegistry::valueLabel(
            \App\Models\MoneyReceived::class, 'type', null
        ));

        // Flag columns read as Yes/No rather than 1/0.
        $this->assertSame('Yes', ActivityRegistry::valueLabel(
            \App\Models\MoneyReceived::class, 'is_reviewed', 1
        ));
    }

    public function test_field_labels_fall_back_to_a_readable_column_name(): void
    {
        $this->assertSame('Financial Institution', ActivityRegistry::fieldLabel(
            \App\Models\MoneyReceived::class, 'financial_institution_id'
        ));
    }

    /**
     * ⚠️ Regression guard. `changes` is a real protected property on
     * every Eloquent model (HasAttributes::$changes, used for dirty
     * tracking), so a column of that name is shadowed by the property:
     * writes look fine and reads come back empty, with no error. The
     * column is therefore `field_changes`.
     */
    public function test_the_diff_column_does_not_collide_with_eloquent(): void
    {
        $activity = new RecordActivity;

        $this->assertArrayHasKey('field_changes', $activity->getCasts(),
            'The diff column must be `field_changes` — `changes` is shadowed by Eloquent.'
        );
        $this->assertArrayNotHasKey('changes', $activity->getCasts());
    }

    /**
     * ⚠️ Twelve controllers implement update as DELETE + CREATE
     * (MoneyReceivedController::update() says so in its own comment).
     * Every one of those must be wrapped in asUpdate(), or an edit
     * records as an unrelated delete and create and the record's
     * history does not follow it — the row you just edited claims to
     * have been created seconds ago with no past.
     */
    public function test_delete_then_create_updates_are_wrapped(): void
    {
        $unwrapped = [];

        foreach (glob(app_path('Http/Controllers/*.php')) as $file) {
            $source = file_get_contents($file);

            if (! preg_match('/public function update\(/', $source)) {
                continue;
            }

            $updateAt = strpos($source, 'public function update(');

            /**
             * Read only update()'s own body. A fixed-size window bleeds
             * into the method that follows — destroy() legitimately
             * deletes, which made FactoringCompanyController look like a
             * replace-on-update when its update() is a plain ->update().
             */
            /**
             * Terminator must accept BOTH indent styles — this codebase
             * mixes tabs and spaces, and matching only spaces let the
             * window run to end of file and pick up destroy(), which
             * legitimately deletes.
             */
            $rest = substr($source, $updateAt + 10);
            $body = preg_match('/\n[ \t]+(public|protected|private) function /', $rest, $m, PREG_OFFSET_CAPTURE)
                ? substr($source, $updateAt, $m[0][1] + 10)
                : substr($source, $updateAt);

            /**
             * The pattern that matters is update() deleting ITS OWN
             * route-bound model. Merely containing a ->delete() is not
             * enough: the opening-balance controllers delete child
             * statement rows (which are not audited at all) while the
             * parent gets a real ->update(), and are correctly handled
             * by the observer.
             */
            if (! preg_match('/public function update\([^)]*\)/s', $body, $sig)) {
                continue;
            }

            // The last model-typed parameter is the record being edited.
            if (! preg_match_all('/\b[A-Z][A-Za-z]*\s+(\$\w+)/', $sig[0], $params) || ! $params[1]) {
                continue;
            }

            $subject = end($params[1]);

            /**
             * Commented-out code is not code. SalesGatheringTestController
             * keeps a disabled update() in comments that otherwise reads
             * as a replace-on-update.
             */
            $live = preg_replace('/^\s*\/\/.*$/m', '', $body);

            $deletesItself = str_contains($live, $subject.'->delete();');

            if ($deletesItself && ! str_contains($body, 'ActivityLogger::asUpdate')) {
                $unwrapped[] = basename($file);
            }
        }

        sort($unwrapped);

        $this->assertSame([], $unwrapped, sprintf(
            "%d controller(s) replace the row on update without wrapping it in\n".
            "ActivityLogger::asUpdate(), so edits there will log as delete+create\n".
            "and lose the record's history:\n\n  %s\n",
            count($unwrapped),
            implode("\n  ", $unwrapped)
        ));
    }

    public function test_muting_is_reentrant(): void
    {
        $this->assertFalse(ActivityLogger::isMuted());

        ActivityLogger::withoutLogging(function () {
            $this->assertTrue(ActivityLogger::isMuted());

            // A nested block must not switch logging back on when it ends.
            ActivityLogger::withoutLogging(function () {
                $this->assertTrue(ActivityLogger::isMuted());
            });

            $this->assertTrue(ActivityLogger::isMuted());
        });

        $this->assertFalse(ActivityLogger::isMuted());
    }

    /**
     * Queue workers are long-lived: a mute with no matching unmute
     * would silence the audit trail for every job that ran afterwards
     * in the same worker. The guard must release on scope exit.
     */
    public function test_the_mute_guard_releases_when_it_goes_out_of_scope(): void
    {
        $this->assertFalse(ActivityLogger::isMuted());

        (function () {
            $mute = ActivityLogger::mute();
            $this->assertTrue(ActivityLogger::isMuted());
        })();

        $this->assertFalse(ActivityLogger::isMuted(),
            'The mute must not outlive the scope that took it — otherwise one import job '
            .'silences every later job in the same worker.'
        );
    }

    public function test_the_mute_guard_releases_when_the_scope_throws(): void
    {
        try {
            (function () {
                $mute = ActivityLogger::mute();
                throw new \RuntimeException('boom');
            })();
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertFalse(ActivityLogger::isMuted());
    }

    public function test_muting_unwinds_even_when_the_callback_throws(): void
    {
        try {
            ActivityLogger::withoutLogging(function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertFalse(ActivityLogger::isMuted(),
            'A throwing callback must not leave logging muted for the rest of the request.'
        );
    }
}
