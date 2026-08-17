<?php
namespace App\Http\Controllers;

use App\Models\CashInSafeStatement;
use App\Models\Company;
use App\Models\FinancialInstitutionAccount;
use App\Models\ForeignExchangeRate;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * ForeignExchangeRateController
 * ------------------------------------------------------------------
 * One tab per currency the company deals in; each tab lists that
 * currency's historical rates against the main functional currency,
 * newest first. Only the single most recent rate for a currency can
 * be edited or deleted — older rows are locked, since other modules
 * (invoices, statements, etc.) already used them in past calculations.
 *
 * This table is also read from directly by getExchangeRate() /
 * ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate() —
 * used across many other unrelated features to look up "the rate on
 * date X". None of that lookup logic is touched here.
 *
 * ── Frontend migration status ───────────────────────────────────────
 *   ✅ index() → MIGRATED to Vue + Inertia
 *      (resources/js/Pages/ForeignExchangeRate/Index.vue).
 *
 *      Two real changes were made here, both requested explicitly
 *      after reviewing the old page with hundreds of rows in mind —
 *      this is NOT a silent behavior change:
 *
 *      1. SCALING FIX: the old index() loaded every row for every
 *         currency into memory on every page load (via ->get()), and
 *         filtered/searched the ACTIVE tab's rows with PHP array
 *         filtering (Collection::filter/where) rather than SQL. With
 *         hundreds of rows per currency this only gets worse over
 *         time. It's now a real, indexed SQL query — only the active
 *         tab's rows are fetched, filtered with `where()` clauses,
 *         and paginate()'d — and switching tabs makes a fresh request
 *         rather than shipping every currency's full history on every
 *         load.
 *
 *      2. CORRECTNESS FIX: the old Blade view marked whichever row
 *         happened to be first in the (possibly filtered/searched)
 *         list as editable ($loop->first). That meant if a search was
 *         active, edit/delete could end up on an arbitrary row instead
 *         of the company's actual latest rate for that currency. This
 *         version instead looks up the true latest rate for the
 *         currency directly and flags only that specific row as
 *         editable, regardless of what filters are active — closing
 *         what looks like an unintended edge case in the old page
 *         rather than reproducing it.
 *
 *      Everything else — which currencies show as tabs, the default
 *      18-month date window, store()/update()/destroy()'s actual
 *      field-level logic, and getExchangeRate() — is UNCHANGED.
 *   🔲 create()/edit() have no separate old routes — adding a rate
 *      was always an inline form on the list page (_form.blade.php).
 *      The new Vue page keeps that same inline-form pattern rather
 *      than introducing a separate page.
 */
class ForeignExchangeRateController
{
    use GeneralFunctions;

    public function index(Company $company, Request $request)
    {
        $numberOfMonthsBetweenEndDateAndStartDate = 18;
        $mainFunctionalCurrency = $company->getMainFunctionalCurrency();
        $filterDates = [];
        $searchFields = [];
        $existingCurrencies = FinancialInstitutionAccount::getAllCurrentAccountCurrenciesForCompany($company->id, [$mainFunctionalCurrency]);
        $existingCurrencies = array_values(array_unique(array_merge($existingCurrencies, CashInSafeStatement::getCurrencies($company->id, [$mainFunctionalCurrency]))));
        $isMainFunctionCurrencyExistInHisCurrency = in_array($mainFunctionalCurrency, $existingCurrencies);
        $activeType = $isMainFunctionCurrencyExistInHisCurrency ? $mainFunctionalCurrency : Arr::first($existingCurrencies);
        $activeType = $request->get('active', $activeType);

        $searchFieldOptions = [
            'from_currency' => __('From Currency'),
            'to_currency' => __('To Currency'),
            'date' => __('Date'),
        ];

        $startDate = $request->get('startDate') ?: now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
        $endDate = $request->get('endDate') ?: now()->format('Y-m-d');
        $searchField = $request->get('field');
        $searchValue = $request->get('value');

        $query = ForeignExchangeRate::where('company_id', $company->id)
            ->where('from_currency', $activeType)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->when($searchField && $searchValue, function ($query) use ($searchField, $searchValue) {
                return $query->where($searchField, 'like', '%' . $searchValue . '%');
            })
            ->orderByDesc('date')
            ->orderByDesc('id');

        $paginated = $query->paginate(20)->withQueryString();

        // See "CORRECTNESS FIX" in the class docblock above — computed
        // separately from any active filter so edit/delete never lands
        // on the wrong row.
        $latestRate = ForeignExchangeRate::where('company_id', $company->id)
            ->where('from_currency', $activeType)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        $rates = $paginated->through(function (ForeignExchangeRate $rate) use ($latestRate) {
            $exchangeRate = $rate->getExchangeRate();
            return [
                'id' => $rate->id,
                'date_formatted' => $rate->getDateFormatted(),
                'from_currency' => $rate->getFromCurrency(),
                'to_currency' => $rate->getToCurrency(),
                'exchange_rate_formatted' => number_format($exchangeRate, 4),
                'reciprocal_exchange_rate_formatted' => number_format($exchangeRate ? 1 / $exchangeRate : 0, 4),
                'is_editable' => $latestRate && $rate->id === $latestRate->id,
                'edit_url' => route('edit.foreign.exchange.rate', ['company' => $rate->company_id, 'foreignExchangeRate' => $rate->id]),
                'delete_url' => route('delete.foreign.exchange.rate', ['company' => $rate->company_id, 'foreignExchangeRate' => $rate->id]),
            ];
        });

        $editingRateId = $request->get('edit');
        $editingRate = null;
        if ($editingRateId) {
            $model = ForeignExchangeRate::where('company_id', $company->id)->find($editingRateId);
            if ($model) {
                $editingRate = [
                    'id' => $model->id,
                    'date' => $model->getDate(),
                    'from_currency' => $model->getFromCurrency(),
                    'to_currency' => $model->getToCurrency(),
                    'exchange_rate' => $model->getExchangeRate(),
                    'update_url' => route('update.foreign.exchange.rate', ['company' => $company->id, 'foreignExchangeRate' => $model->id]),
                ];
            }
        }

        return \Inertia\Inertia::render('ForeignExchangeRate/Index', [
            'company' => ['id' => $company->id],
            'mainFunctionalCurrency' => $mainFunctionalCurrency,
            'existingCurrencies' => $existingCurrencies,
            'activeTab' => $activeType,
            'canCreate' => hasAuthFor('foreign_exchange_rate.create'),
            'canUpdate' => hasAuthFor('foreign_exchange_rate.update'),
            'canDelete' => hasAuthFor('foreign_exchange_rate.delete'),
            'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
            'currencies' => getCurrencies(),
            'searchFieldOptions' => $searchFieldOptions,
            'filters' => [
                'field' => $searchField,
                'value' => $searchValue,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ],
            'rates' => $rates,
            'editingRate' => $editingRate,
            'indexUrl' => route('view.foreign.exchange.rate', ['company' => $company->id]),
            'storeUrl' => route('store.foreign.exchange.rate', ['company' => $company->id]),
        ]);
    }
    public function store(Request $request, Company $company)
    {
        $data = [
            'company_id'=>$company->id ,
            'exchange_rate'=>$request->get('exchange_rate'),
            'date'=>$request->get('date'),
            'from_currency'=>$request->get('from_currency'),
            'to_currency'=>$request->get('to_currency'),
        ] ;
        
        
        ForeignExchangeRate::create($data);
        
        
        
        return redirect()->route('view.foreign.exchange.rate', ['company'=>$company->id,'active'=>$request->get('from_currency')]);
    }
    public function edit(Request $request, Company $company, $foreignExchangeRateId)
    {
        $foreignExchangeRate = ForeignExchangeRate::find($foreignExchangeRateId);
        return redirect()->route('view.foreign.exchange.rate', ['company' => $company->id, 'active' => $foreignExchangeRate->getFromCurrency(), 'edit' => $foreignExchangeRateId]);
    }
    public function update(Request $request, Company $company, $foreignExchangeRateId)
    {
        $date = $request->get('date') ;
        $foreignExchangeRate = ForeignExchangeRate::find($foreignExchangeRateId);
        $data = [
            'exchange_rate'=>$request->get('exchange_rate'),
            'date'=>$request->get('date'),
            'from_currency'=>$request->get('from_currency'),
            'to_currency'=>$request->get('to_currency'),
        ] ;
        $foreignExchangeRate->update($data);
        
        return redirect()->route('view.foreign.exchange.rate', ['company'=>$company->id,'active'=>$request->get('from_currency')]);
        
    }
    public function destroy(Request $request, Company $company, $foreignExchangeRateId)
    {
        $foreignExchangeRate = ForeignExchangeRate::find($foreignExchangeRateId);
        $foreignExchangeRate->delete();
        
        /**
         * * لو معدش فاضل غيرها دا معناه انه حذف تاني عنصر وبالتالي العنصر الاول اللي معتش فاضل غيره هو الديو ديت الاصلي ففي الحاله
         * * دي هنحذفه معتش ليه لزمة
         */
        // if(ForeignExchangeRate::where('company_id',$company->id)->count() == 1){
        // 	ForeignExchangeRate::where('company_id',$company->id)->delete();
        // }
        return redirect()->route('view.foreign.exchange.rate', ['company'=>$company->id]);
    }
    public function getExchangeRate(Request $request, Company $company)
    {
        $date = $request->get('date') ;
        if (!$date) {
            return response()->json([
               'exchange_rate'=> 1
            ]);
        }
        $date = Carbon::make($date)->format('Y-m-d') ;
        $fromCurrency = $request->get('fromCurrency') ;
        $toCurrency = $request->get('toCurrency');
        $isReverse = false ;
        $mainFunctionalCurrency = $company->getMainFunctionalCurrency();
        if ($fromCurrency == $mainFunctionalCurrency && $toCurrency != $fromCurrency) {
            $fromCurrency = $request->get('toCurrency');
            $toCurrency = $request->get('fromCurrency');
            $isReverse = true ;
        }
        if ($fromCurrency != $toCurrency && $fromCurrency != $mainFunctionalCurrency  && $toCurrency != $mainFunctionalCurrency) {
            $exchangeRateRow = ForeignExchangeRate::where('company_id', $company->id)
                                ->where('from_currency', $fromCurrency)
                                ->where('to_currency', $mainFunctionalCurrency)
                                ->where('date', '<=', $date)
                                ->orderByDesc('date')
                                ->first() ;
            $firstExchangeRate = $exchangeRateRow ? $exchangeRateRow->exchange_rate : 1 ;
            $exchangeRateRow2 = ForeignExchangeRate::where('company_id', $company->id)
                                ->where('from_currency', $toCurrency)
                                ->where('to_currency', $mainFunctionalCurrency)
                                ->where('date', '<=', $date)
                                ->orderByDesc('date')
                                ->first() ;
            $secondExchangeRate = $exchangeRateRow2 ? $exchangeRateRow2->exchange_rate : 1;
            // Guard stored zero rates — same class of bug as CashExpenseController.
            if (! $secondExchangeRate || (float) $secondExchangeRate == 0) {
                return response()->json(['exchange_rate' => 0]);
            }
            return response()->json([
       	     'exchange_rate'=>$firstExchangeRate/$secondExchangeRate
  		      ]);
        }
        $exchangeRateRow = ForeignExchangeRate::where('company_id', $company->id)
                                ->where('from_currency', $fromCurrency)
                                ->where('to_currency', $toCurrency)
                                ->where('date', '<=', $date)
                                ->orderByDesc('date')
                                ->first() ;
                                
        
        // if(){
        // 	$isReverse = true ;
        // 	$exchangeRateRow = ForeignExchangeRate::where('company_id', $company->id)
        // 						->where('from_currency', $toCurrency)
        // 						->where('to_currency', $fromCurrency)
        // 						->where('date', '<=', $date)
        // 						->orderByDesc('date')
        // 						->first() ;
                                
        // }
        $exchangeRate = $exchangeRateRow ? $exchangeRateRow->exchange_rate : 1;
        if ($isReverse) {
            $exchangeRate = ($exchangeRate && (float) $exchangeRate != 0) ? 1/$exchangeRate : 0;
        }
        return response()->json([
            'exchange_rate'=>$exchangeRate
        ]);
    }
}
