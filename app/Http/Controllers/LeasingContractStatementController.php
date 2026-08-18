<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LeasingCompany;
use App\Models\LeasingContract;
use App\Support\LeasingContractStatementData;
use Illuminate\Http\Request;

/**
 * LeasingContractStatementController
 * ------------------------------------------------------------------
 * The "Leasing Contract Statement" entry in the Statements sidebar
 * section — the same statement the 📄 button on a leasing company's
 * contract list opens, but reachable without knowing which leasing
 * company the contract belongs to first.
 *
 * Shaped after FactoringStatementController, the closest sibling in
 * this section: a filter form (index) with a date range that cascades
 * Leasing Company → Currency → Contract through JSON lookups, then a
 * result page.
 *
 * ⚠️ The date range restricts which rows are LISTED, never how they are
 * calculated — see LeasingContractStatementData. A period is a window
 * onto the contract, not a reset: a ledger row inside the window still
 * opens at the balance it genuinely had, and the facility figures are
 * read as of the end date rather than as of today.
 *
 * result() renders the SAME Vue page as LeasingContractController@statement
 * with the SAME props, both built by LeasingContractStatementData, so
 * the two routes can never disagree about a contract's numbers.
 */
class LeasingContractStatementController
{
    /**
     * Filter form: Leasing Company → Contract. Renders
     * Statements/LeasingContractStatement/Index.vue.
     */
    public function index(Company $company, Request $request)
    {
        return \Inertia\Inertia::render('Statements/LeasingContractStatement/Index', [
            'company' => ['id' => $company->id],
            'leasingCompanies' => $company->leasingCompanies()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (LeasingCompany $lc) => ['id' => $lc->id, 'name' => $lc->getName()])
                ->values(),
            'urls' => [
                'currencies' => route('leasing.contract.statement.currencies', ['company' => $company->id]),
                'contracts' => route('leasing.contract.statement.contracts', ['company' => $company->id]),
                'result' => route('result.leasing.contract.statement', ['company' => $company->id]),
            ],
            // Coming back from a statement re-fills the form with what
            // produced it, so "Back to Filters" lands on the filters the
            // user actually used rather than an empty form.
            'filters' => [
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'leasing_company_id' => $request->get('leasing_company_id'),
                'currency' => $request->get('currency'),
                'leasing_contract_id' => $request->get('leasing_contract_id'),
            ],
            'navUrls' => $this->navUrls($company),
        ]);
    }

    /**
     * JSON lookup for the Currency dropdown, once a Leasing Company is
     * chosen — the distinct currencies its contracts are written in.
     */
    public function getCurrencies(Company $company, Request $request)
    {
        $leasingCompany = LeasingCompany::where('company_id', $company->id)
            ->findOrFail($request->integer('leasing_company_id'));

        $currencies = LeasingContract::where('company_id', $company->id)
            ->where('leasing_company_id', $leasingCompany->id)
            ->pluck('currency')
            ->unique()
            ->filter()
            ->mapWithKeys(function ($currency) {
                $allCurrencies = getCurrencies();

                return [$currency => $allCurrencies[$currency] ?? strtoupper($currency)];
            });

        return response()->json(['status' => true, 'currencies' => $currencies]);
    }

    /**
     * JSON lookup for the Contract dropdown, once a Leasing Company and
     * a Currency are chosen.
     *
     * ⚠️ Deliberately NOT LeasingContract::payableFor(): that one answers
     * "which contracts can this payment be made from" and therefore hides
     * anything not running. A statement is a record, so a finished
     * contract belongs here — it is exactly the kind somebody looks up
     * afterwards.
     *
     * The date range narrows the list to contracts whose own life
     * OVERLAPS the period, the same rule FactoringStatementController
     * applies: a contract that ended before the period started has
     * nothing to show inside it.
     */
    public function getContracts(Company $company, Request $request)
    {
        $leasingCompany = LeasingCompany::where('company_id', $company->id)
            ->findOrFail($request->integer('leasing_company_id'));

        $currency = (string) $request->get('currency');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $contracts = LeasingContract::where('company_id', $company->id)
            ->where('leasing_company_id', $leasingCompany->id)
            ->when($currency, fn ($q) => $q->where('currency', $currency))
            ->when($startDate, fn ($q) => $q->where(fn ($w) => $w->whereNull('end_date')->orWhere('end_date', '>=', $startDate)))
            ->when($endDate, fn ($q) => $q->where(fn ($w) => $w->whereNull('start_date')->orWhere('start_date', '<=', $endDate)))
            ->orderBy('name')
            ->get()
            ->map(fn (LeasingContract $contract) => [
                'id' => $contract->id,
                'name' => $contract->getName(),
                'label' => $contract->getName()
                    .' | '.strtoupper((string) $contract->getCurrency())
                    .' | '.number_format((float) $contract->getLimit()),
            ])
            ->values();

        return response()->json(['status' => true, 'contracts' => $contracts]);
    }

    /**
     * The statement itself, for the contract and period chosen on the
     * filter form.
     */
    public function result(Company $company, Request $request)
    {
        $contract = LeasingContract::where('company_id', $company->id)
            ->findOrFail($request->integer('leasing_contract_id'));

        $leasingCompany = $contract->leasingCompany;
        $startDate = $request->get('start_date') ?: null;
        $endDate = $request->get('end_date') ?: null;

        return \Inertia\Inertia::render('LeasingContract/Statement', array_merge(
            LeasingContractStatementData::for($contract, $startDate, $endDate),
            [
                'company' => ['id' => $company->id],
                'leasingCompany' => [
                    'id' => $leasingCompany?->id,
                    'name' => $leasingCompany?->getName() ?? __('N/A'),
                ],
                // Back to the filter form, not to the contract list — the
                // user came from the sidebar and expects to pick another
                // contract, not to land in a screen they never opened.
                'backUrl' => route('view.leasing.contract.statement', array_filter([
                    'company' => $company->id,
                    'leasing_company_id' => $contract->leasing_company_id,
                    'leasing_contract_id' => $contract->id,
                    'currency' => $contract->getCurrency(),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ])),
                'backLabel' => __('Back to Filters'),
                'navUrls' => $this->navUrls($company),
            ]
        ));
    }

    private function navUrls(Company $company): array
    {
        return [
            'home' => route('home', ['company' => $company->id]),
            'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
            'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
            'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
            'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
        ];
    }
}
