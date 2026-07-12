<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FactoringCompany;
use App\Models\FactoringContract;
use App\Models\FactoringTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FactoringChargesStatementController
{
    public function index(Company $company)
    {
        return view('factoring-charges-statement.form', [
            'company' => $company,
            'factoringCompanies' => $company->factoringCompanies()->orderBy('name')->get(),
        ]);
    }

    public function result(Company $company, Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'factoring_company_id' => 'required|integer',
            'currency' => 'required|string',
            'factoring_contract_id' => 'nullable|integer',
        ]);

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $currency = $request->get('currency');
        $contractId = $request->integer('factoring_contract_id') ?: null;

        $factoringCompany = FactoringCompany::where('company_id', $company->id)
            ->findOrFail($request->integer('factoring_company_id'));

        $contract = null;
        if ($contractId) {
            $contract = FactoringContract::where('company_id', $company->id)
                ->where('factoring_company_id', $factoringCompany->id)
                ->findOrFail($contractId);

            abort_unless($contract->currency === $currency, 422);
        }

        $transactions = FactoringTransaction::query()
            ->with(['customer', 'customerInvoice', 'factoringContract'])
            ->where('company_id', $company->id)
            ->where('factoring_company_id', $factoringCompany->id)
            ->where('invoice_currency', $currency)
            ->when($contractId, fn ($query) => $query->where('factoring_contract_id', $contractId))
            ->get();

        $rows = $this->buildChargeRows($transactions, $startDate, $endDate);

        if (empty($rows)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $runningTotal = 0.0;
        foreach ($rows as &$row) {
            $runningTotal = round($runningTotal + $row['amount'], 2);
            $row['running_total'] = $runningTotal;
            $row['date'] = Carbon::make($row['raw_date'])->format('d-m-Y');
            unset($row['raw_date'], $row['sort_order']);
        }
        unset($row);

        return view('factoring-charges-statement.result', [
            'company' => $company,
            'rows' => $rows,
            'factoringCompany' => $factoringCompany,
            'contract' => $contract,
            'currency' => strtoupper((string) $currency),
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

    protected function buildChargeRows($transactions, string $startDate, string $endDate): array
    {
        $rows = [];

        foreach ($transactions as $transaction) {
            $comment = $this->buildRowComment($transaction);

            if (
                (float) $transaction->factoring_interest_amount > 0
                && $this->dateInRange($transaction->factoring_date, $startDate, $endDate)
            ) {
                $rows[] = [
                    'raw_date' => $transaction->factoring_date,
                    'factoring_transaction_id' => $transaction->id,
                    'sort_order' => 1,
                    'charge_type' => __('Factoring Interest'),
                    'amount' => (float) $transaction->factoring_interest_amount,
                    'comment' => $comment,
                ];
            }

            if (
                (float) $transaction->other_charges > 0
                && $this->dateInRange($transaction->factoring_date, $startDate, $endDate)
            ) {
                $rows[] = [
                    'raw_date' => $transaction->factoring_date,
                    'factoring_transaction_id' => $transaction->id,
                    'sort_order' => 2,
                    'charge_type' => __('Other Charges'),
                    'amount' => (float) $transaction->other_charges,
                    'comment' => $comment,
                ];
            }

            if (
                $transaction->recourse_type === FactoringTransaction::WITH_RECOURSE
                && $transaction->isRejected()
                && (float) $transaction->uncollected_invoice_charges > 0
                && $transaction->rejection_date
                && $this->dateInRange($transaction->rejection_date, $startDate, $endDate)
            ) {
                $rows[] = [
                    'raw_date' => $transaction->rejection_date,
                    'factoring_transaction_id' => $transaction->id,
                    'sort_order' => 3,
                    'charge_type' => __('Uncollected Invoices Charges'),
                    'amount' => (float) $transaction->uncollected_invoice_charges,
                    'comment' => $comment,
                ];
            }
        }

        usort($rows, function (array $a, array $b) {
            $dateCompare = strcmp($a['raw_date'], $b['raw_date']);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            $transactionCompare = $a['factoring_transaction_id'] <=> $b['factoring_transaction_id'];
            if ($transactionCompare !== 0) {
                return $transactionCompare;
            }

            return $a['sort_order'] <=> $b['sort_order'];
        });

        return $rows;
    }

    protected function buildRowComment(FactoringTransaction $transaction): string
    {
        $invoiceNumber = $transaction->customerInvoice?->invoice_number ?? '';
        $customerName = $transaction->customer?->getName() ?? '';
        $contract = $transaction->factoringContract;
        $contractLabel = $contract
            ? $contract->getContractStartDateFormatted() . ' — ' . $contract->getContractEndDateFormatted()
            : '';
        $recourseLabel = $transaction->recourse_type === FactoringTransaction::WITH_RECOURSE
            ? __('With Recourse')
            : __('Without Recourse');

        return __('Invoice #:invoice | Customer: :customer | Contract: :contract | :recourse', [
            'invoice' => $invoiceNumber,
            'customer' => $customerName,
            'contract' => $contractLabel,
            'recourse' => $recourseLabel,
        ]);
    }

    protected function dateInRange(?string $date, string $startDate, string $endDate): bool
    {
        if (!$date) {
            return false;
        }

        $value = Carbon::make($date)->format('Y-m-d');

        return $value >= $startDate && $value <= $endDate;
    }
}
