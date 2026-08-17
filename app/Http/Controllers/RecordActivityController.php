<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\RecordActivity;
use App\Support\Activity\ActivityRegistry;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves one record's history to the timeline modal.
 *
 * Authorization is derived, not declared separately: you may read the
 * history of a record you are allowed to VIEW. ActivityRegistry already
 * names the permission module for each model, so a new audited model
 * gets the right gate automatically instead of needing its own entry
 * in RoutePermissionMap.
 */
class RecordActivityController extends Controller
{
    public function __invoke(Request $request, Company $company, string $subject, int $id): JsonResponse
    {
        $class = $this->resolveSubjectClass($subject);

        $module = ActivityRegistry::moduleFor($class);
        abort_unless($module !== null, 404);

        abort_unless(
            PermissionResolver::allows($request->user(), "{$module}.view"),
            403,
            __('You do not have permission to perform this action.')
        );

        $activities = RecordActivity::query()
            ->where('subject_type', $class)
            ->where('subject_id', $id)
            /**
             * Company scoping on the log itself, so a forged subject id
             * cannot surface another company's history. The subject row
             * is not loaded here — it may well have been deleted, and a
             * deletion is exactly the entry someone comes looking for.
             */
            ->where(function ($q) use ($company) {
                $q->where('company_id', $company->id)->orWhereNull('company_id');
            })
            ->orderByDesc('id')
            ->limit((int) config('activity.timeline_limit', 100))
            ->get();

        return response()->json([
            'subject' => ActivityRegistry::labelFor($class),
            'entries' => $activities->map(fn (RecordActivity $a) => $a->toTimelineArray())->values(),
        ]);
    }

    /**
     * Turn the URL's short model name into a real audited class.
     *
     * Only names the registry declares are accepted — the segment never
     * reaches `new $class` or a table name, so an arbitrary value 404s
     * rather than being instantiated. (Same lesson as the `truncate`
     * endpoint in the 2026-08 audit.)
     */
    private function resolveSubjectClass(string $subject): string
    {
        foreach (ActivityRegistry::models() as $class) {
            if (class_basename($class) === $subject) {
                return $class;
            }
        }

        abort(404);
    }
}
