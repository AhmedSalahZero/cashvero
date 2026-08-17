<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessSectorRequest;
use App\Models\CashVeroBusinessSector;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Business Sectors — Vue SimpleMasterList. Renaming cascades into
 * customer_invoices.business_sector.
 */
class BusinessSectorsController
{
    public function index(Company $company)
    {
        $items = $company->businessSectors()->orderBy('name')->get();

        return \Inertia\Inertia::render('Settings/SimpleMasterList', [
            // Shared master-list page — each controller supplies its own
            // module's rights so one screen cannot leak another's.
            'permissions' => [
                'canCreate' => hasAuthFor('business_sector.create'),
                'canUpdate' => hasAuthFor('business_sector.update'),
                'canDelete' => hasAuthFor('business_sector.delete'),
            ],
            'company' => ['id' => $company->id],
            'title' => 'Business Sectors',
            'subtitle' => 'Used to categorize customer invoices by sector',
            'itemLabel' => 'Business Sector',
            'items' => $items->map(fn (CashVeroBusinessSector $item) => [
                'id' => $item->id,
                'name' => $item->getName(),
                'update_url' => route('business.sectors.update', ['company' => $company->id, 'businessSector' => $item->id]),
                'delete_url' => route('business.sectors.destroy', ['company' => $company->id, 'businessSector' => $item->id]),
            ])->values(),
            'createUrl' => route('business.sectors.store', ['company' => $company->id]),
        ]);
    }

    public function store(Company $company, StoreBusinessSectorRequest $request)
    {
        CashVeroBusinessSector::create([
            'name' => $request->get('name'),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('business.sectors.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, StoreBusinessSectorRequest $request, CashVeroBusinessSector $businessSector)
    {
        $newName = $request->get('name');
        $oldName = $businessSector->getName();
        DB::table('customer_invoices')->where('company_id', $company->id)->where('business_sector', $oldName)->update([
            'business_sector' => $newName,
        ]);
        $businessSector->update([
            'name' => $newName,
        ]);

        return redirect()
            ->route('business.sectors.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, CashVeroBusinessSector $businessSector)
    {
        $businessSector->delete();

        return redirect()
            ->route('business.sectors.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Delete Successfully'));
    }
}
