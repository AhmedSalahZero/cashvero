<?php

namespace App\Providers;

use App\Support\Activity\ActivityRegistry;
use App\Support\Activity\RecordActivityObserver;
use Illuminate\Support\ServiceProvider;

/**
 * Attaches the audit observer to every model ActivityRegistry declares.
 *
 * Registering in a loop, rather than adding a trait to each model,
 * means the audit list lives in exactly one file and no model class
 * carries logging concerns.
 */
class ActivityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Nothing to observe if the feature is off — and this also keeps
        // the observer out of the way during a migrate on a database
        // that has no `record_activities` table yet.
        if (! config('activity.enabled', true)) {
            return;
        }

        foreach (ActivityRegistry::models() as $model) {
            $model::observe(RecordActivityObserver::class);
        }
    }
}
