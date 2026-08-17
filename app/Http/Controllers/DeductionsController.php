<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreDeductionRequest;
use App\Models\Company;
use App\Models\Deduction;

/**
 * Deductions — Vue SimpleMasterList (inline add / modal edit).
 * No separate create/edit pages.
 */
class DeductionsController
{
    public function index(Company $company)
    {
        $items = $company->deductions()->orderBy('name')->get();

        return \Inertia\Inertia::render('Settings/SimpleMasterList', [
            // Shared master-list page — each controller supplies its own
            // module's rights so one screen cannot leak another's.
            'permissions' => [
                'canCreate' => hasAuthFor('deduction.create'),
                'canUpdate' => hasAuthFor('deduction.update'),
                'canDelete' => hasAuthFor('deduction.delete'),
            ],
            'company' => ['id' => $company->id],
            'title' => 'Deductions',
            'subtitle' => 'Deduction types available across the app',
            'itemLabel' => 'Deduction',
            'items' => $items->map(fn (Deduction $item) => [
                'id' => $item->id,
                'name' => $item->getName(),
                'update_url' => route('deductions.update', ['company' => $company->id, 'deduction' => $item->id]),
                'delete_url' => route('deductions.destroy', ['company' => $company->id, 'deduction' => $item->id]),
            ])->values(),
            'createUrl' => route('deductions.store', ['company' => $company->id]),
        ]);
    }

    public function store(Company $company, StoreDeductionRequest $request)
    {
        Deduction::create([
            'name' => $request->get('name'),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('deductions.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, StoreDeductionRequest $request, Deduction $deduction)
    {
        $deduction->update([
            'name' => $request->get('name'),
        ]);

        return redirect()
            ->route('deductions.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, Deduction $deduction)
    {
        $deduction->delete();

        return redirect()
            ->route('deductions.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Delete Successfully'));
    }
}
