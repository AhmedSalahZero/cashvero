<?php

namespace App\Http\Controllers\Helpers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Http\Request;

/**
 * Deletes ONE row from a data table (invoice, supplier invoice, loan schedule row).
 *
 * ⚠️ Security fix (2026 audit, SEC-08): this used to build the class name straight from
 * the request ((\App\Models\ . $modelName)::find($id)->delete()), so anyone holding the
 * right to delete one kind of row could delete ANY model (a User, a Company ...) with ANY
 * id, in ANY company. It now
 *   1. accepts only a fixed list of models,
 *   2. demands the delete right of THAT model (not just any of the three),
 *   3. finds the row inside the current company only.
 * The success response is unchanged.
 */
class DeleteSingleRecordController extends Controller
{
    /** model name => permission module that guards its delete right */
    private const ALLOWED = [
        'CustomerInvoice' => 'customer_invoice_data',
        'SupplierInvoice' => 'supplier_invoice_data',
        'LoanSchedule' => 'loan_schedule_data',
    ];

    public function __invoke(Request $request)
    {
        $modelName = (string) $request->get('modelName');

        abort_unless(array_key_exists($modelName, self::ALLOWED), 404);

        abort_unless(
            PermissionResolver::allows($request->user(), self::ALLOWED[$modelName].'.delete'),
            403,
            __('You do not have permission to perform this action.')
        );

        $company = $request->route('company');
        $companyId = $company instanceof Company ? $company->id : (int) $company;

        $class = 'App\\Models\\'.$modelName;
        $record = $class::query()
            ->where('company_id', $companyId)
            ->whereKey($request->get('recordId'))
            ->first();

        if ($record) {
            try {
                $record->delete();
            } catch (\InvalidArgumentException $e) {
                return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
            }
        }

        return response()->json([
            'status' => true,
            'tableId' => $request->get('tableId'),
        ]);
    }
}
