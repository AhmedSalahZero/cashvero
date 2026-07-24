<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessUnitRequest;
use App\Models\CashVeroBusinessUnit;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Business Units — Vue SimpleMasterList. Renaming cascades into
 * customer_invoices.business_unit.
 */
class BusinessUnitsController
{
    public function index(Company $company)
    {
        $items = $company->businessUnits()->orderBy('name')->get();

        return \Inertia\Inertia::render('Settings/SimpleMasterList', [
            'company' => ['id' => $company->id],
            'title' => 'Business Units',
            'subtitle' => 'Used to categorize customer invoices by business unit',
            'itemLabel' => 'Business Unit',
            'items' => $items->map(fn (CashVeroBusinessUnit $item) => [
                'id' => $item->id,
                'name' => $item->getName(),
                'update_url' => route('business.units.update', ['company' => $company->id, 'businessUnit' => $item->id]),
                'delete_url' => route('business.units.destroy', ['company' => $company->id, 'businessUnit' => $item->id]),
            ])->values(),
            'createUrl' => route('business.units.store', ['company' => $company->id]),
        ]);
    }

    public function store(Company $company, StoreBusinessUnitRequest $request)
    {
        CashVeroBusinessUnit::create([
            'name' => $request->get('name'),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('business.units.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, StoreBusinessUnitRequest $request, CashVeroBusinessUnit $businessUnit)
    {
        $newName = $request->get('name');
        $oldName = $businessUnit->getName();
        DB::table('customer_invoices')->where('company_id', $company->id)->where('business_unit', $oldName)->update([
            'business_unit' => $newName,
        ]);
        $businessUnit->update([
            'name' => $newName,
        ]);

        return redirect()
            ->route('business.units.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, CashVeroBusinessUnit $businessUnit)
    {
        $businessUnit->delete();

        return redirect()
            ->route('business.units.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Delete Successfully'));
    }
}
