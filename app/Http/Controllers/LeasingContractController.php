<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LeasingCompany;
use App\Models\LeasingContract;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LeasingContractController
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
            })
            ->sortByDesc('id')
            ->values();
    }

    public function index(Company $company, Request $request, LeasingCompany $leasingCompany)
    {
        $currentType = $request->get('active', LeasingContract::RUNNING);
        $filterDates = [];
        foreach (LeasingContract::getAllTypes() as $type) {
            $endDate = $request->has('endDate') ? $request->input('endDate.' . $type) : now()->format('Y-m-d');
            $filterDates[$type] = ['endDate' => $endDate];
        }

        $runningEndDate = $filterDates[LeasingContract::RUNNING]['endDate'] ?? null;
        $contracts = $company->leasingContracts->where('leasing_company_id', $leasingCompany->id);
        $contracts = $contracts->filterByLoanEndDate($runningEndDate);
        $contracts = $currentType == LeasingContract::RUNNING ? $this->applyFilter($request, $contracts) : $contracts;

        $searchFields = [
            LeasingContract::RUNNING => [
                'name' => __('Name'),
                'start_date' => __('Start Date'),
                'end_date' => __('End Date'),
            ],
        ];

        return view('leasing-contracts.index', [
            'company' => $company,
            'leasingCompany' => $leasingCompany,
            'searchFields' => $searchFields,
            'models' => [LeasingContract::RUNNING => $contracts],
            'filterDates' => $filterDates,
        ]);
    }

    public function create(Company $company, LeasingCompany $leasingCompany)
    {
        return view('leasing-contracts.form', $this->getCommonViewVars($company, $leasingCompany));
    }

    public function getCommonViewVars(Company $company, LeasingCompany $leasingCompany, ?LeasingContract $model = null): array
    {
        return [
            'company' => $company,
            'leasingCompany' => $leasingCompany,
            'model' => $model,
        ];
    }

    public function store(Company $company, Request $request, LeasingCompany $leasingCompany)
    {
        $contract = new LeasingContract;
        $contract->status = LeasingContract::RUNNING;
        $contract->storeBasicForm($request);

        return redirect()
            ->route('leasing.contracts.index', [
                'company' => $company->id,
                'leasingCompany' => $leasingCompany->id,
                'active' => LeasingContract::RUNNING,
            ])
            ->with('success', __('Data Store Successfully'));
    }

    public function edit(Company $company, LeasingCompany $leasingCompany, LeasingContract $leasingContract)
    {
        return view('leasing-contracts.form', $this->getCommonViewVars($company, $leasingCompany, $leasingContract));
    }

    public function update(Company $company, Request $request, LeasingCompany $leasingCompany, LeasingContract $leasingContract)
    {
        $leasingContract->deleteRelations();
        $leasingContract->delete();
        $this->store($company, $request, $leasingCompany);

        return redirect()
            ->route('leasing.contracts.index', [
                'company' => $company->id,
                'leasingCompany' => $leasingCompany->id,
                'active' => LeasingContract::RUNNING,
            ])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, LeasingCompany $leasingCompany, LeasingContract $leasingContract)
    {
        $leasingContract->deleteRelations();
        $leasingContract->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }
}
