<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Http\Request;

/**
 * DeletingClass
 * ==================================================================
 * Generic bulk-delete endpoints for the data-upload screens.
 *
 * ⚠️ REAL BUG FIXED HERE (2026-08 permissions audit, F-02 — the most
 * severe finding in that report):
 *
 *   • `truncate` resolved an Eloquent class straight from a URL
 *     segment — `new ('App\Models\' . $model)` — and mass-deleted
 *     every row of it scoped to the company. Any model name worked.
 *   • It had NO permission check whatsoever; `auth` was the only gate.
 *   • It was a GET route, so it could be triggered by an <img> tag or
 *     a link on any external page, with no CSRF token involved.
 *
 * Three things changed, and no business logic did:
 *   1. The {model} segment is checked against
 *      config('permissions.bulk_deletable_models') — an unknown or
 *      hostile class name now 404s instead of being instantiated.
 *   2. A bulk-delete permission for that specific dataset is required.
 *      EnforcePermission already demands *a* bulk-delete right to reach
 *      the route; this narrows it to the right one for this model.
 *   3. The route is now DELETE (see routes/web.php), so it carries CSRF.
 */
class DeletingClass
{
    /**
     * Dataset model → the permission module governing it.
     */
    private const MODEL_PERMISSION_MODULE = [
        'CustomerInvoice' => 'customer_invoice_data',
        'SupplierInvoice' => 'supplier_invoice_data',
        'LoanSchedule' => 'loan_schedule_data',
        'BankStatement' => 'customer_invoice_data',
        'SalesGathering' => 'customer_invoice_data',
    ];

    public function truncate(Company $company, $model)
    {
        $modelClass = $this->resolveModel($model, 'bulk_delete');

        $model_obj = new $modelClass();
        $all_model_data = $model_obj->company()->get();
        if (count($all_model_data) > 0) {
            $all_model_data->each->delete();
        }

        toastr()->success('All Rows Were Deleted  Successfully');

        return redirect()->back();
    }

    public function multipleRowsDeleting(Request $request, Company $company, $model)
    {

        if ($request->rows === null || count($request->rows) == 0) {
            return redirect()->back()->with('fail', __('No Rows Were Selected'));
        }

        $modelClass = $this->resolveModel($model, 'bulk_delete');

        $model_obj = new $modelClass();
        $all_model_data = null ;
        if ($request->has('delete_date_from')) {
			$deleteColumnName = method_exists($model_obj,'getDeleteByDateColumnName') ?  $model_obj->getDeleteByDateColumnName() : 'date';
            $all_model_data = $model_obj->company()->whereBetween($deleteColumnName, [$request->get('delete_date_from'), $request->get('delete_date_to')])->get();
        } elseif ($request->has('delete_serial_from')) {
            $all_model_data = $model_obj->company()->get()->filter(function ($element, $index) use ($request) {
                return $index + 1 >= $request->get('delete_serial_from') && $index + 1 <= $request->get('delete_serial_to') ;
            });
        } else {
            $all_model_data = $model_obj->company()->whereIn('id', is_array($request->rows) ? $request->rows : [$request->rows])->get();
        }
        if (count($all_model_data) > 0) {
            $all_model_data->each->delete();
        }

        if ($request->ajax() && ! $request->header('X-Inertia')) {
            return response()->json([
                'status' => true
            ]);
        }
        return redirect()->back()->with('success', __('Deleted Selected Rows Successfully'));
    }

    /**
     * Turn the {model} URL segment into a real, allowed model class and
     * verify the caller may perform $action on that specific dataset.
     */
    private function resolveModel(string $model, string $action): string
    {
        $allowed = config('permissions.bulk_deletable_models', []);

        // 404 rather than 403: an arbitrary class name isn't a resource
        // the caller was denied, it's one that doesn't exist here.
        abort_unless(in_array($model, $allowed, true), 404);

        $module = self::MODEL_PERMISSION_MODULE[$model] ?? null;
        abort_unless($module !== null, 404);

        abort_unless(
            PermissionResolver::allows(request()->user(), "{$module}.{$action}"),
            403,
            __('You do not have permission to perform this action.')
        );

        $class = 'App\\Models\\'.$model;
        abort_unless(class_exists($class), 404);

        return $class;
    }
}
