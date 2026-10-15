<?php
namespace App\Http\Controllers;

use App\Support\Instructions\PageInstructions;
use App\Http\Requests\StoreSalesPersonRequest;
use App\Models\CashVeroSalesPerson;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Sales Persons — Vue SimpleMasterList. Renaming cascades into
 * customer_invoices.sales_person.
 */
class SalesPersonsController
{
    public function index(Company $company)
    {
        $items = $company->salesPersons()->orderBy('name')->get();

        return \Inertia\Inertia::render('Settings/SimpleMasterList', [
            'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::SETTINGS]),
            // Shared master-list page — each controller supplies its own
            // module's rights so one screen cannot leak another's.
            'permissions' => [
                'canCreate' => hasAuthFor('sales_person.create'),
                'canUpdate' => hasAuthFor('sales_person.update'),
                'canDelete' => hasAuthFor('sales_person.delete'),
            ],
            'company' => ['id' => $company->id],
            'title' => 'Sales Persons',
            'subtitle' => 'Used to categorize customer invoices by sales person',
            'itemLabel' => 'Sales Person',
            'items' => $items->map(fn (CashVeroSalesPerson $item) => [
                'id' => $item->id,
                'name' => $item->getName(),
                'update_url' => route('sales.persons.update', ['company' => $company->id, 'salesPerson' => $item->id]),
                'delete_url' => route('sales.persons.destroy', ['company' => $company->id, 'salesPerson' => $item->id]),
            ])->values(),
            'createUrl' => route('sales.persons.store', ['company' => $company->id]),
        ]);
    }

    public function store(Company $company, StoreSalesPersonRequest $request)
    {
        CashVeroSalesPerson::create([
            'name' => $request->get('name'),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('sales.persons.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, StoreSalesPersonRequest $request, CashVeroSalesPerson $salesPerson)
    {
        $newName = $request->get('name');
        $oldName = $salesPerson->getName();
        DB::table('customer_invoices')->where('company_id', $company->id)->where('sales_person', $oldName)->update([
            'sales_person' => $newName,
        ]);
        $salesPerson->update([
            'name' => $newName,
        ]);

        return redirect()
            ->route('sales.persons.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, CashVeroSalesPerson $salesPerson)
    {
        $salesPerson->delete();

        return redirect()
            ->route('sales.persons.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Delete Successfully'));
    }
}
