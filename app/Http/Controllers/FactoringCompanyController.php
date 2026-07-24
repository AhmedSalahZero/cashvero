<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFactoringCompanyRequest;
use App\Models\Company;
use App\Models\FactoringCompany;
use App\Models\FactoringContract;

/**
 * Factoring companies — create/edit via inline modal on
 * FinancialInstitutions/Index.vue (store/update/destroy only).
 */
class FactoringCompanyController
{
    public function store(Company $company, StoreFactoringCompanyRequest $request)
    {
        FactoringCompany::create([
            'company_id' => $company->id,
            'name' => $request->get('name'),
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('view.financial.institutions', ['company' => $company->id, 'active' => 'factoring_companies'])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, StoreFactoringCompanyRequest $request, FactoringCompany $factoringCompany)
    {
        $factoringCompany->update([
            'name' => $request->get('name'),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('view.financial.institutions', ['company' => $company->id, 'active' => 'factoring_companies'])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, FactoringCompany $factoringCompany)
    {
        $factoringCompany->contracts->each(function (FactoringContract $contract) {
            $contract->deleteRelations();
            $contract->delete();
        });
        $factoringCompany->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }
}
