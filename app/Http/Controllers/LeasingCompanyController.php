<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeasingCompanyRequest;
use App\Models\Company;
use App\Models\LeasingCompany;
use App\Models\LeasingContract;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LeasingCompanyController
{
    use GeneralFunctions;

    protected function applyFilter(Request $request, Collection $collection): Collection
    {
        if (!count($collection)) {
            return $collection;
        }

        $searchFieldName = $request->get('field');
        $dateFieldName = 'created_at';
        $from = $request->get('from');
        $to = $request->get('to');
        $value = $request->query('value');

        return $collection
            ->when($request->has('value'), function ($collection) use ($value, $searchFieldName) {
                return $collection->filter(function ($item) use ($value, $searchFieldName) {
                    $currentValue = $item->{$searchFieldName};

                    return false !== stristr((string) $currentValue, (string) $value);
                });
            })
            ->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
                return $collection->where($dateFieldName, '>=', $from);
            })
            ->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
                return $collection->where($dateFieldName, '<=', $to);
            });
    }

    public function create(Company $company)
    {
        return view('leasing-companies.form', [
            'company' => $company,
        ]);
    }

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

    public function edit(Company $company, LeasingCompany $leasingCompany)
    {
        return view('leasing-companies.form', [
            'company' => $company,
            'model' => $leasingCompany,
        ]);
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
        $leasingCompany->contracts->each(function (LeasingContract $contract) {
            $contract->deleteRelations();
            $contract->delete();
        });
        $leasingCompany->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }
}
