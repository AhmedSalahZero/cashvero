<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FactoringCompany;
use App\Models\FactoringContract;
use App\Models\FactoringStatement;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FactoringStatementController
{
    public function index(Company $company)
    {
        return view('factoring-statement.form', [
            'company' => $company,
            'factoringCompanies' => $company->factoringCompanies()->orderBy('name')->get(),
        ]);
    }

    public function result(Company $company, Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $factoringCompany = FactoringCompany::where('company_id', $company->id)
            ->findOrFail($request->integer('factoring_company_id'));
        $contract = FactoringContract::where('company_id', $company->id)
            ->where('factoring_company_id', $factoringCompany->id)
            ->findOrFail($request->integer('factoring_contract_id'));

        abort_unless($contract->currency === $request->get('currency'), 422);

        $statements = FactoringStatement::query()
            ->where('company_id', $company->id)
            ->where('factoring_contract_id', $contract->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($statements->isEmpty()) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $rows = [];
        $runningBalance = 0.0;

        foreach ($statements as $statement) {
            $debit = (float) $statement->debit;
            $credit = (float) $statement->credit;
            $runningBalance = round($runningBalance + $debit - $credit, 2);

            $rows[] = [
                'date' => Carbon::make($statement->date)->format('d-m-Y'),
                'debit' => $debit,
                'credit' => $credit,
                'end_balance' => $runningBalance,
                'comment' => $statement->getComment(),
            ];
        }

        return view('factoring-statement.result', [
            'company' => $company,
            'rows' => $rows,
            'factoringCompany' => $factoringCompany,
            'contract' => $contract,
            'currency' => strtoupper((string) $contract->currency),
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function getCurrencies(Company $company, FactoringCompany $factoringCompany)
    {
        abort_unless($factoringCompany->company_id === $company->id, 404);

        $currencies = $company->factoringContracts()
            ->where('factoring_company_id', $factoringCompany->id)
            ->pluck('currency')
            ->unique()
            ->filter()
            ->mapWithKeys(function ($currency) {
                $allCurrencies = getCurrencies();

                return [$currency => $allCurrencies[$currency] ?? strtoupper($currency)];
            });

        return response()->json(['status' => true, 'currencies' => $currencies]);
    }

    public function getContracts(Company $company, FactoringCompany $factoringCompany, string $currency, Request $request)
    {
        abort_unless($factoringCompany->company_id === $company->id, 404);

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $contracts = $company->factoringContracts()
            ->where('factoring_company_id', $factoringCompany->id)
            ->where('currency', $currency)
            ->when($startDate, fn ($query) => $query->where('contract_end_date', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->where('contract_start_date', '<=', $endDate))
            ->get()
            ->map(fn (FactoringContract $contract) => [
                'id' => $contract->id,
                'label' => $contract->getContractStartDateFormatted() . ' — ' . $contract->getContractEndDateFormatted()
                    . ' | ' . strtoupper($contract->getCurrency() ?? '')
                    . ' | ' . $contract->getLimitFormatted(),
            ]);

        return response()->json(['status' => true, 'contracts' => $contracts]);
    }
}
