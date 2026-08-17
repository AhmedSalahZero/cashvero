<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    | Master switch for App\Http\Middleware\EnforcePermission. Turning it
    | off degrades the middleware to log-only, which is useful when
    | rehearsing a rollout on staging. Backend authorization applied
    | directly in controllers (authorizeAction / RequirePermission) is
    | NOT affected by this flag.
    */
    'enforce' => env('PERMISSIONS_ENFORCE', true),

    /*
    |--------------------------------------------------------------------------
    | Unmapped route policy
    |--------------------------------------------------------------------------
    | What to do when a route carries a name that appears in neither
    | RoutePermissionMap::MAP nor its PUBLIC_ROUTES list.
    |
    |   'allow'  → let it through (fail-open) and log it. Chosen for this
    |              rollout so a route this map missed can never take a
    |              working page offline.
    |   'deny'   → 403 (fail-closed). Switch to this once the log has
    |              been quiet for a full release cycle.
    |
    | Either way tests/Feature/Permissions/RouteCoverageTest.php fails
    | the build when anything is unmapped, so this is a runtime safety
    | net rather than a licence to skip the map.
    */
    'unmapped' => env('PERMISSIONS_UNMAPPED', 'allow'),

    /*
    |--------------------------------------------------------------------------
    | Log unmapped routes
    |--------------------------------------------------------------------------
    | Each distinct unmapped route name is logged once per request cycle
    | at this level. Set to null to silence.
    */
    'log_unmapped' => env('PERMISSIONS_LOG_UNMAPPED', 'warning'),

    /*
    |--------------------------------------------------------------------------
    | Models that generic destructive endpoints may target
    |--------------------------------------------------------------------------
    | `truncate` and `multipleRowsDelete` resolve an Eloquent class from
    | a URL segment. Before this system they accepted ANY model name —
    | a GET request could mass-delete any table (2026-08 audit, F-02).
    | Only the classes listed here are now permitted.
    */
    'bulk_deletable_models' => [
        'CustomerInvoice',
        'SupplierInvoice',
        'LoanSchedule',
        'BankStatement',
        'SalesGathering',
    ],

];
