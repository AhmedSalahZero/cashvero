<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeasingCompanyRequest;
use App\Models\Company;
use App\Models\LeasingCompany;

/**
 * Leasing companies — create/edit via inline modal on
 * FinancialInstitutions/Index.vue (store/update/destroy only).
 */
class LeasingCompanyController
{
    public function store(Company $company, StoreLeasingCompanyRequest $request)
    {
        LeasingCompany::create([
            'company_id' => $company->id,
            'name' => $request->get('name'),
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('view.financial.institutions', ['company' => $company->id, 'active' => 'leasing_companies'])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, StoreLeasingCompanyRequest $request, LeasingCompany $leasingCompany)
    {
        $leasingCompany->update([
            'name' => $request->get('name'),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('view.financial.institutions', ['company' => $company->id, 'active' => 'leasing_companies'])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, LeasingCompany $leasingCompany)
    {
        $leasingCompany->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }
}
