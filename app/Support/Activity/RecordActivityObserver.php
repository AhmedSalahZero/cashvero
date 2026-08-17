<?php

namespace App\Support\Activity;

use App\Models\RecordActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns Eloquent lifecycle events into audit entries.
 *
 * Registered once per audited model by ActivityServiceProvider, so no
 * model class needs a trait added to it — 57 files left untouched, and
 * a model joins or leaves the audit by editing ActivityRegistry alone.
 *
 * ⚠️ Eloquent events only fire for Eloquent writes. Anything going
 * through the query builder (`DB::table(...)->update()`), a bulk
 * `Model::query()->update()`, or one of this app's DB triggers is
 * invisible here. That is a deliberate boundary, not an oversight:
 * those paths write derived tables, which the registry excludes anyway.
 */
class RecordActivityObserver
{
    public function created(Model $model): void
    {
        ActivityLogger::log($model, RecordActivity::EVENT_CREATED);
    }

    /**
     * `updating`, not `updated`: the diff has to be taken while the
     * model still knows its original values. By the time `updated`
     * fires, getDirty() is empty and the old values are gone.
     */
    public function updating(Model $model): void
    {
        ActivityLogger::log($model, RecordActivity::EVENT_UPDATED, ActivityLogger::diff($model));
    }

    public function deleted(Model $model): void
    {
        // A soft delete arrives here too; `restored` covers the inverse.
        ActivityLogger::log($model, RecordActivity::EVENT_DELETED);
    }

    public function restored(Model $model): void
    {
        ActivityLogger::log($model, RecordActivity::EVENT_RESTORED);
    }
}
