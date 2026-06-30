<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFactoringContractRequest;
use App\Http\Requests\UpdateFactoringContractRequest;
use App\Models\Company;
use App\Models\FactoringCompany;
use App\Models\FactoringContract;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FactoringContractController
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
                    $currentValue = $searchFieldName === 'recourse_type'
                        ? $item->getRecourseTypeLabel()
                        : $item->{$searchFieldName};

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

    public function index(Company $company, Request $request, FactoringCompany $factoringCompany)
    {
        $contracts = $company->factoringContracts->where('factoring_company_id', $factoringCompany->id);
        $contracts = $this->applyFilter($request, $contracts);

        $searchFields = [
            'contract_start_date' => __('Contract Start Date'),
            'contract_end_date' => __('Contract End Date'),
            'recourse_type' => __('Recourse Type'),
            'currency' => __('Currency'),
            'limit' => __('Limit'),
            'outstanding_balance' => __('Outstanding Balance'),
        ];

        return view('factoring-contracts.index', [
            'company' => $company,
            'factoringCompany' => $factoringCompany,
            'searchFields' => $searchFields,
            'contracts' => $contracts,
        ]);
    }

    public function create(Company $company, FactoringCompany $factoringCompany)
    {
        return view('factoring-contracts.form', [
            'company' => $company,
            'factoringCompany' => $factoringCompany,
        ]);
    }

    public function store(Company $company, FactoringCompany $factoringCompany, StoreFactoringContractRequest $request)
    {
        $data = $request->only($this->getCommonDataArr());
        foreach (['contract_start_date', 'contract_end_date', 'balance_date'] as $dateField) {
            $data[$dateField] = $request->get($dateField)
                ? Carbon::make($request->get($dateField))->format('Y-m-d')
                : null;
        }

        $data['created_by'] = auth()->id();
        $data['company_id'] = $company->id;
        $data['factoring_company_id'] = $factoringCompany->id;

        /** @var FactoringContract $contract */
        $contract = FactoringContract::create($data);
        $contract->storeOutstandingBreakdown($request, $company);
        $contract->storeLimitStatement($company->id);

        return response()->json([
            'redirectTo' => route('factoring.contracts.index', [
                'company' => $company->id,
                'factoringCompany' => $factoringCompany->id,
            ]),
        ]);
    }

    public function edit(Company $company, FactoringCompany $factoringCompany, FactoringContract $factoringContract)
    {
        return view('factoring-contracts.form', [
            'company' => $company,
            'factoringCompany' => $factoringCompany,
            'model' => $factoringContract,
        ]);
    }

    public function update(
        Company $company,
        UpdateFactoringContractRequest $request,
        FactoringCompany $factoringCompany,
        FactoringContract $factoringContract
    ) {
        $data = $request->only($this->getCommonDataArr());
        foreach (['contract_start_date', 'contract_end_date', 'balance_date'] as $dateField) {
            $data[$dateField] = $request->get($dateField)
                ? Carbon::make($request->get($dateField))->format('Y-m-d')
                : null;
        }
        $data['updated_by'] = auth()->id();

        $factoringContract->update($data);
        $factoringContract->storeOutstandingBreakdown($request, $company);

        return response()->json([
            'redirectTo' => route('factoring.contracts.index', [
                'company' => $company->id,
                'factoringCompany' => $factoringCompany->id,
            ]),
        ]);
    }

    public function destroy(Company $company, FactoringCompany $factoringCompany, FactoringContract $factoringContract)
    {
        $factoringContract->deleteRelations();
        $factoringContract->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }

    protected function getCommonDataArr(): array
    {
        return [
            'contract_start_date',
            'contract_end_date',
            'recourse_type',
            'currency',
            'limit',
            'outstanding_balance',
            'balance_date',
            'borrowing_rate',
            'margin_rate',
            'interest_rate',
            'min_interest_rate',
            'highest_debt_balance_rate',
            'admin_fees_rate',
            'to_be_setteled_max_within_days',
        ];
    }
}
