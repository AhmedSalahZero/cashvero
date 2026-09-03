<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpeningBalanceRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CashInSafeStatement;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\OpeningBalance;
use App\Models\Partner;
use App\Models\PayableCheque;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * OpeningBalancesController
 * ------------------------------------------------------------------
 * Manages the company's "Cash in Safe & Cheque Balance" opening
 * balance — a SINGLETON per company (Company::openingBalance() is a
 * HasOne, not HasMany), holding four repeaters: Cash In Safe entries,
 * Cheques In Safe, Cheques Under Collection, and Payable Cheques.
 * There is exactly one of these per company, ever — which is why the
 * original app never had a separate list/index page; visiting the
 * section always meant "show me the one record for this company,
 * create it if it doesn't exist yet."
 *
 * `store()` / `update()`'s actual SAVE LOGIC is completely UNCHANGED —
 * this is genuinely heavy, trigger-adjacent logic (cheque pivot data,
 * bank-statement credit/debit handling via `handleCreditStatement()` /
 * `handleFullDateAfterDateEdit()`) and this migration deliberately
 * does not touch either method's business logic — only what BUILDS
 * the request they receive changed, not what they do with it.
 *
 * ⚠️ ONE further fix was required after the form went live: both
 * methods used to `return response()->json(['redirectTo'=>...])` —
 * correct for the OLD jQuery-AJAX Blade form, which read that JSON
 * and redirected itself client-side. Once the caller became a real
 * Inertia page, that raw JSON response broke Inertia entirely
 * ("All Inertia requests must receive a valid Inertia response").
 * Fixed by returning a real `redirect()->route(...)->with('success', ...)`
 * instead — same fix already applied everywhere else in this project
 * (see roadmap §11 item 19); only the response TYPE changed, not the
 * save logic above it.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() → REPURPOSED (see previous update) — read-only Vue
 *      summary page.
 *   ✅ manage() → MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/OpeningBalance/Form.vue instead of the
 *      1,390-line Blade form. Submits the EXACT SAME field/array
 *      names the untouched store()/update() already expect
 *      (`cash-in-safe`, `cheque`, `cheque-under-collection`,
 *      `payable_cheque`, each row keeping its `id` — 0 for new rows,
 *      matching `StoreOpeningBalanceRequest`'s validation keys and
 *      the update() diff-by-id logic exactly) — so store()/update()
 *      needed ZERO changes.
 *   ⚠️ Two deliberate, flagged simplifications (not silent drops —
 *      confirmed acceptable trade-offs, see chat):
 *      1. The original's "Drawee Bank" dropdown only listed banks
 *         already used by this company's cheques, with a modal to
 *         search all banks and inject a new one into the list (no
 *         traceable server route for that modal's search in this
 *         codebase). Replaced with the FULL bank list directly —
 *         strictly more capable, no modal needed.
 *      2. The "Account Number" dropdown (Cheque Under Collection /
 *         Payable Cheque) was populated by client-side JS with no
 *         traceable AJAX route in this codebase either. Replaced
 *         with the same "fetch every account up front, filter
 *         client-side by bank + account type" pattern already used
 *         for Fully Secured Overdraft's CD/TD picker — every Current
 *         Account, Fully Secured/Clean/Commercial-Paper/Assignment-
 *         of-Contract Overdraft account for this company is fetched
 *         once via `AccountType::onlyCashAccounts()` +
 *         `getModelName()`, tagged with its account type and
 *         financial institution, and Vue narrows it down as the user
 *         picks a bank and account type.
 *   ⚠️ store() / update() → save logic NOT touched; response type
 *      fixed from raw JSON to a real redirect (see above), required
 *      once the caller became Inertia.
 */
class OpeningBalancesController
{
    use GeneralFunctions;

    /**
     * NEW — read-only summary. Shows the current state of the
     * company's opening balance (if one exists) with counts and full
     * repeater contents, and a "Manage" button to the real form.
     * Presentation only; does not create, update, or delete anything.
     */
    public function index(Company $company)
    {
        $openingBalance = $company->openingBalance;

        if (!$openingBalance) {
            return \Inertia\Inertia::render('OpeningBalance/Index', [
                'company' => ['id' => $company->id],
                'exists' => false,
                'manageUrl' => route('opening-balance.manage', ['company' => $company->id]),
            ]);
        }

        $cashInSafe = $openingBalance->cashInSafeStatements->map(fn (CashInSafeStatement $row) => [
            'id' => $row->getId(),
            'branch' => $row->branch?->getName(),
            'currency' => $row->getCurrency(),
            'amount' => (float) $row->getDebitAmount(),
            'exchange_rate' => (float) $row->getExchangeRate(),
        ])->values();

        $chequesInSafe = $openingBalance->chequeInSafe->map(fn (MoneyReceived $row) => [
            'id' => $row->getId(),
            'customer' => $row->getCustomerName(),
            'cheque_number' => $row->cheque?->getChequeNumber(),
            'drawee_bank' => $row->cheque?->getDraweeBankName(),
            'currency' => $row->getCurrency(),
            'amount' => (float) $row->getReceivedAmount(),
            'due_date' => $row->cheque?->getDueDateFormatted(),
        ])->values();

        $chequesUnderCollection = $openingBalance->chequeUnderCollections->map(fn (MoneyReceived $row) => [
            'id' => $row->getId(),
            'customer' => $row->getCustomerName(),
            'cheque_number' => $row->cheque?->getChequeNumber(),
            'drawee_bank' => $row->cheque?->getDraweeBankName(),
            'currency' => $row->getCurrency(),
            'amount' => (float) $row->getReceivedAmount(),
            'due_date' => $row->cheque?->getDueDateFormatted(),
            'deposit_date' => $row->cheque?->deposit_date,
            'account_type' => $row->cheque?->getAccountTypeName(),
            'account_number' => AccountNumberLabel::forCurrentAccount($company->id, $row->cheque?->drawl_bank_id, $row->cheque?->getAccountNumber()),
        ])->values();

        $payableCheques = $openingBalance->payableCheques->map(fn (MoneyPayment $row) => [
            'id' => $row->getId(),
            'supplier' => $row->getSupplierName(),
            'cheque_number' => $row->payableCheque?->getChequeNumber(),
            'delivery_bank' => $row->payableCheque?->getDeliveryBankName(),
            'currency' => $row->getCurrency(),
            'amount' => (float) $row->getPaidAmount(),
            'due_date' => $row->payableCheque?->getDueDateFormatted(),
            'account_type' => $row->payableCheque?->getAccountTypeName(),
            'account_number' => AccountNumberLabel::forCurrentAccount($company->id, $row->getPayableChequePaymentBankId(), $row->payableCheque?->getAccountNumber()),
        ])->values();

        return \Inertia\Inertia::render('OpeningBalance/Index', [
            'company' => ['id' => $company->id],
            'exists' => true,
            'date' => $openingBalance->getDate(),
            'manageUrl' => route('opening-balance.manage', ['company' => $company->id]),
            'cashInSafe' => $cashInSafe,
            'chequesInSafe' => $chequesInSafe,
            'chequesUnderCollection' => $chequesUnderCollection,
            'payableCheques' => $payableCheques,
        ]);
    }

    /**
     * MIGRATED — renders the real create/edit form as Vue + Inertia.
     * Gathers reference data (branches, customers, suppliers, banks,
     * account types, and the "cash accounts" list used for the
     * account-number cascading dropdown) plus the existing model (if
     * any) flattened into plain arrays keyed exactly the way
     * store()/update() already expect them back.
     */
    public function manage(Company $company, Request $request)
    {
        $model = $company->openingBalance;

        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)
            ->onlyBanks()
            ->join('banks', 'banks.id', '=', 'financial_institutions.bank_id')
            ->orderBy('banks.view_name')
            ->select('financial_institutions.*')
            ->get()
            ->map(fn (FinancialInstitution $fi) => ['id' => $fi->id, 'name' => $fi->getName()])
            ->values();

        $accountTypes = AccountType::onlyCashAccounts()->get();

        // "Cash accounts" for the Account Number cascading dropdown —
        // see the docblock above for why this replaces the original's
        // client-side-JS-populated select (no traceable route for it).
        $cashAccounts = collect();
        foreach ($accountTypes as $accountType) {
            $modelClass = '\\App\\Models\\'.$accountType->getModelName();
            if (!class_exists($modelClass)) {
                continue;
            }
            foreach ($modelClass::where('company_id', $company->id)->get() as $row) {
                if (!$row->account_number) {
                    continue;
                }
                $cashAccounts->push([
                    'account_type_id' => $accountType->id,
                    'financial_institution_id' => $row->financial_institution_id,
                    'account_number' => $row->account_number,
                    'currency' => $row->currency,
                ]);
            }
        }

        // Full bank list — see docblock: replaces the original's
        // "already-used banks + add-new modal" with the complete list.
        $draweeBanks = Bank::orderBy('view_name')->get()->map(fn (Bank $bank) => [
            'id' => $bank->id,
            'name' => $bank->view_name,
        ])->values();

        $customers = Partner::where('company_id', $company->id)->where('is_customer', 1)->orderBy('name')
            ->get()->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->getName()])->values();
        $suppliers = Partner::where('company_id', $company->id)->where('is_supplier', 1)->orderBy('name')
            ->get()->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->getName()])->values();

        $branches = Branch::where('company_id', $company->id)->get()->map(fn (Branch $b) => [
            'id' => $b->id,
            'name' => $b->getName(),
            'currency' => $b->currency,
        ])->values();

        $modelData = null;
        if ($model) {
            $modelData = [
                'id' => $model->id,
                'date' => $model->getDate(),
                'cashInSafe' => $model->cashInSafeStatements->map(fn (CashInSafeStatement $row) => [
                    'id' => $row->id,
                    'received_branch_id' => $row->getBranchId(),
                    'received_amount' => (float) $row->getDebitAmount(),
                    'currency' => $row->getCurrency(),
                    'exchange_rate' => (float) $row->getExchangeRate(),
                ])->values(),
                'cheque' => $model->chequeInSafe->map(fn (MoneyReceived $row) => [
                    'id' => $row->id,
                    'customer_id' => $row->getPartnerId(),
                    'currency' => $row->getCurrency(),
                    'due_date' => $row->getChequeDueDate(),
                    'drawee_bank_id' => $row->cheque?->getDraweeBankId(),
                    'received_amount' => (float) $row->getReceivedAmount(),
                    'cheque_number' => $row->getChequeNumber(),
                    'exchange_rate' => (float) $row->getExchangeRate(),
                ])->values(),
                'chequeUnderCollection' => $model->chequeUnderCollections->map(fn (MoneyReceived $row) => [
                    'id' => $row->id,
                    'customer_id' => $row->getCustomerId(),
                    'currency' => $row->getCurrency(),
                    'due_date' => $row->getChequeDueDate(),
                    'drawee_bank_id' => $row->cheque?->getDraweeBankId(),
                    'received_amount' => (float) $row->getReceivedAmount(),
                    'cheque_number' => $row->getChequeNumber(),
                    'exchange_rate' => (float) $row->getExchangeRate(),
                    'deposit_date' => $row->getChequeDepositDate(),
                    'drawl_bank_id' => $row->getChequeDrawlBankId(),
                    'account_type' => $row->getChequeAccountType(),
                    'account_number' => $row->getChequeAccountNumber(),
                    'clearance_days' => $row->getChequeClearanceDays(),
                ])->values(),
                'payableCheque' => $model->payableCheques->map(fn (MoneyPayment $row) => [
                    'id' => $row->id,
                    'supplier_id' => $row->getSupplierId(),
                    'currency' => $row->getCurrency(),
                    'due_date' => $row->getPayableChequeDueDate(),
                    'paid_amount' => (float) $row->getPaidAmount(),
                    'cheque_number' => $row->getPayableChequeNumber(),
                    'exchange_rate' => (float) $row->getExchangeRate(),
                    'delivery_bank_id' => $row->getPayableChequePaymentBankId(),
                    'account_type' => $row->getPayableChequeAccountType(),
                    'account_number' => $row->getPayableChequeAccountNumber(),
                ])->values(),
            ];
        }

        return \Inertia\Inertia::render('OpeningBalance/Form', [
            'company' => ['id' => $company->id, 'opening_balance_date' => $company->opening_balance_date],
            'submitUrl' => $model
                ? route('opening-balance.update', ['company' => $company->id, 'opening_balance' => $model->id])
                : route('opening-balance.store', ['company' => $company->id]),
            'backUrl' => route('opening-balance.index', ['company' => $company->id]),
            'isEdit' => (bool) $model,
            'model' => $modelData,
            'currencies' => getCurrencies(),
            'branches' => $branches,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'financialInstitutionBanks' => $financialInstitutionBanks,
            'draweeBanks' => $draweeBanks,
            'accountTypes' => $accountTypes->map(fn (AccountType $t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
            'cashAccounts' => $cashAccounts->values(),
        ]);
    }

    public function store(StoreOpeningBalanceRequest $request, Company $company)
    {
        // The date field is read-only in the form now — it always mirrors
        // the company's own Opening Balance Date (set on company
        // creation), so the source of truth is the company record, not
        // whatever the request happens to send.
        $openingBalanceDate = Carbon::make($company->opening_balance_date)->format('Y-m-d');
        $openingBalance = OpeningBalance::create([
            'date' => $openingBalanceDate,
            'company_id' => $company->id
        ]);
        
        foreach ($request->get('cash-in-safe', []) as $index => $cashInSafeArr) {
            $amount = number_unformat($cashInSafeArr['received_amount'] ?: 0) ;
            $receivingBranchId = $cashInSafeArr['received_branch_id'] ?: null ;
            $exchangeRate = isset($cashInSafeArr['exchange_rate']) ? $cashInSafeArr['exchange_rate'] : 1  ;
        
            $openingBalance->cashInSafeStatements()->create([
                'type'=>OpeningBalance::OPEN_BALANCE,
                'branch_id' => $receivingBranchId,
                'currency' => $cashInSafeArr['currency'],
                'exchange_rate' => $exchangeRate,
                'company_id' => $company->id,
                'debit' =>$amount,
                'credit' => 0,
                'date' => $openingBalanceDate,
            ]);
            
        }
        foreach ($request->get(MoneyReceived::CHEQUE, []) as $index => $cheque) {
            $customer = Partner::find($cheque['customer_id'] ?: null);

            $currentAmount = isset($cheque['received_amount']) ? number_unformat($cheque['received_amount']) : 0 ;
            if ($currentAmount > 0) {
                $moneyReceived = $openingBalance->moneyReceived()->create([
                    'type' => MoneyReceived::CHEQUE,
                    'partner_id' => $customer ? $customer->id : null,
                    'received_amount' => $currentAmount,
                    'amount_in_invoice_currency' => $currentAmount,
                    'currency' => $cheque['currency'],
                    'receiving_currency' => $cheque['currency'],
                    'receiving_date' => $openingBalanceDate,
                    'company_id' => $company->id,
                    'user_id' => auth()->id(),
                    'exchange_rate' => isset($cheque['exchange_rate']) ? $cheque['exchange_rate'] : 1
                ]);
                $moneyReceived->cheque()->create([
                    'cheque_number' => $cheque['cheque_number'] ?: null,
                    'drawee_bank_id' => isset($cheque['drawee_bank_id']) ? $cheque['drawee_bank_id'] : null,
                    'due_date' => $cheque['due_date'] ?: null,
                    'company_id'=>$company->id
                ]);
            }
        }
        
        foreach ($request->get(MoneyReceived::CHEQUE_UNDER_COLLECTION, []) as $index => $chequeUnderCollection) {
            $customer = Partner::find($chequeUnderCollection['customer_id'] ?: null);
            $currentAmount = isset($chequeUnderCollection['received_amount']) ? number_unformat($chequeUnderCollection['received_amount']) :  0 ;
            if ($currentAmount > 0) {
                $moneyReceived = $openingBalance->moneyReceived()->create([
                    'type' => MoneyReceived::CHEQUE,
                    'partner_id' => $customer ? $customer->id : null,
                    'received_amount' => $currentAmount,
                    'amount_in_invoice_currency' => $currentAmount,
                    'currency' => $chequeUnderCollection['currency'],
                    'receiving_currency' => $chequeUnderCollection['currency'],
                    'receiving_date' => $openingBalanceDate,
                    'company_id' => $company->id,
                    'user_id' => auth()->id(),
                    'exchange_rate' => isset($chequeUnderCollection['exchange_rate']) ? $chequeUnderCollection['exchange_rate'] : 1
                ]);
                $dueDate = $chequeUnderCollection['due_date'] ?: null;
                $dueDate = $dueDate?  Carbon::make($dueDate)->format('Y-m-d'): null;
                $currentUnderCollectionCheque = $moneyReceived->cheque()->create([
                    'status' => Cheque::UNDER_COLLECTION,
                    'cheque_number' => $chequeUnderCollection['cheque_number'] ?: null,
                    'drawee_bank_id' => isset($chequeUnderCollection['drawee_bank_id']) ? $chequeUnderCollection['drawee_bank_id'] : null,
                    'due_date' =>$dueDate  ,
                    'expected_collection_date'=>$dueDate,
                    'deposit_date' => $chequeUnderCollection['deposit_date'] ?: null,
                    'drawl_bank_id' => $chequeUnderCollection['drawl_bank_id'] ?: null,
                    'account_type' => $chequeUnderCollection['account_type'] ?: null,
                    'account_number' => $chequeUnderCollection['account_number'] ?: null,
                    'clearance_days' => $chequeUnderCollection['clearance_days'] ?: 0,
                    'company_id'=>$company->id
                ]);
                $currentUnderCollectionCheque->update([
                    'updated_at'=>now()
                ]);
                
            }
        }
        
        
        
        
        foreach ($request->get(MoneyPayment::PAYABLE_CHEQUE, []) as $index => $payableChequeArr) {
            $supplier = Partner::find($payableChequeArr['supplier_id'] ?: null);
            $currentAmount = isset($payableChequeArr['paid_amount']) ? number_unformat($payableChequeArr['paid_amount']) : 0 ;
            if ($currentAmount > 0) {
                $paymentCurrency = $payableChequeArr['currency'] ;
                $moneyPayment = $openingBalance->moneyPayments()->create([
                    'type' => MoneyPayment::PAYABLE_CHEQUE,
                    'partner_id' => $supplier ? $supplier->id : null,
                    'paid_amount' => $currentAmount,
                    'amount_in_invoice_currency' => $currentAmount,
                    'currency' => $paymentCurrency,
                    'delivery_date' => $openingBalanceDate,
                    'company_id' => $company->id,
                    'user_id' => auth()->id(),
                    'exchange_rate' => isset($payableChequeArr['exchange_rate']) ? $payableChequeArr['exchange_rate'] : 1
                ]);
                $financialInstitutionId = isset($payableChequeArr['delivery_bank_id']) ? $payableChequeArr['delivery_bank_id'] : null;
                $accountType = $payableChequeArr['account_type'] ?: null ;
                $accountNumber = $payableChequeArr['account_number'] ?: null ;
                $dueDate = $payableChequeArr['due_date'] ?: null ;
                $dueDate = $dueDate ? Carbon::make($dueDate)->format('Y-m-d') : null  ;
                $statementDate = $dueDate ;
                $moneyType = MoneyPayment::PAYABLE_CHEQUE;
                $amountInPaymentCurrency = $currentAmount ;
                $deliveryBranchId = null;
                $currentPayableCheque = $moneyPayment->payableCheque()->create([
                    'status' => PayableCheque::PENDING,
                    'cheque_number' => $payableChequeArr['cheque_number'] ?: null,
                    'delivery_bank_id' => $financialInstitutionId,
                    'due_date' => $dueDate,
                    'delivery_date' => $openingBalanceDate ,
                    'company_id'=>$company->id,
                    'account_type' => $accountType,
                    'account_number' => $accountNumber ,
                ]);
                $accountType = AccountType::find($accountType);
                $moneyPayment->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInPaymentCurrency, $deliveryBranchId, $paymentCurrency);
                $currentPayableCheque->update([
                    'updated_at'=>now()
                ]);
                
            }
        }
        
        
        
        
        
        
        return redirect()
            ->route('opening-balance.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
      
    }

    public function update(Company $company, StoreOpeningBalanceRequest $request, OpeningBalance $openingBalance)
    {
        // Same as store() — read-only field, always mirrors the company's
        // Opening Balance Date rather than trusting the request.
        $openingBalanceDate = Carbon::make($company->opening_balance_date)->format('Y-m-d');

        $openingBalance->update([
            'date' => $openingBalanceDate,
        ]);

        /**
         * * هنا تحديث ال
         * * cash in safe
         */
        $oldIdsFromDatabase = $openingBalance->cashInSafeStatements->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input(MoneyReceived::CASH_IN_SAFE, []), 'id') ;

        $elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
        // $elementsToUpdate = array_diff($idsFromRequest, $elementsToDelete); // test

        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
        
        CashInSafeStatement::deleteButTriggerChangeOnLastElement($openingBalance->cashInSafeStatements->whereIn('id', $elementsToDelete));
    
        foreach ($elementsToUpdate as $id) {
            $dataToUpdate = findByKey($request->input(MoneyReceived::CASH_IN_SAFE), 'id', $id);
            $openingBalance->cashInSafeStatements()->where('cash_in_safe_statements.id', $id)->first()->update(array_merge($dataToUpdate, [
                'debit'=>number_unformat($dataToUpdate['received_amount']),
                'branch_id'=>$dataToUpdate['received_branch_id'],
                'date'=>$openingBalanceDate
            ]));
            
        }
        foreach ($request->get(MoneyReceived::CASH_IN_SAFE, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0')) {
                unset($data['id']);
                $openingBalance->cashInSafeStatements()->create(array_merge($data, [
                    'company_id' => $company->id,
                    'type' => OpeningBalance::OPEN_BALANCE,
                    'user_id' => auth()->id(),
                    'debit'=>number_unformat($data['received_amount']),
                    'branch_id'=>$data['received_branch_id'],
                    'date'=>$openingBalanceDate
                ]));
                
                
            }
        }
        /**
         * * هنا تحديث الشيكات في الخزنة
         * * ChequeInSafe
         */

        $oldIdsFromDatabase = $openingBalance->chequeInSafe->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input(MoneyReceived::CHEQUE, []), 'id') ;

        $elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
        // $elementsToUpdate = array_diff($idsFromRequest, $elementsToDelete); // test

        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
    
        $openingBalance->chequeInSafe()->whereIn('money_received.id', $elementsToDelete)->delete();
        foreach ($elementsToUpdate as $id) {
            $dataToUpdate = findByKey($request->input(MoneyReceived::CHEQUE), 'id', $id);
    
        
            unset($dataToUpdate['id']);
            $pivotData = [
                'due_date' => $dataToUpdate['due_date'],
                'drawee_bank_id' => isset($dataToUpdate['drawee_bank_id']) ? $dataToUpdate['drawee_bank_id'] : null,
                'cheque_number' => $dataToUpdate['cheque_number'],
                'company_id'=>$company->id
            ];
            unset($dataToUpdate['due_date'], $dataToUpdate['drawee_bank_id'], $dataToUpdate['cheque_number']);
            $dataToUpdate['received_amount'] = isset($dataToUpdate['received_amount']) ? number_unformat($dataToUpdate['received_amount']) : 0;
            $dataToUpdate['partner_id'] = is_numeric($dataToUpdate['customer_id']) ? optional(Partner::find($dataToUpdate['customer_id']))->id : optional(Partner::where('is_customer', 1)->where('name', $dataToUpdate['customer_id'])->first())->id ;
            $dataToUpdate['receiving_date'] =  $openingBalanceDate ;
            $dataToUpdate['company_id'] =  $company->id ;
            $dataToUpdate['receiving_currency'] = $dataToUpdate['currency'] ;
			/**
			 * @var MoneyReceived $currentChequeInSafe
			 */
            $currentChequeInSafe = $openingBalance->chequeInSafe()->where('money_received.id', $id)->first() ;
            $currentChequeInSafe->update($dataToUpdate);
            $currentChequeInSafe->cheque->update($pivotData);
        }
        foreach ($request->get(MoneyReceived::CHEQUE, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0')) {
                unset($data['id']);
                $pivotData = [
                    'due_date' => $data['due_date'],
                    'drawee_bank_id' => isset($data['drawee_bank_id']) ? $data['drawee_bank_id'] : null,
                    'cheque_number' => $data['cheque_number'],
                    'company_id'=>$company->id
                ];
                unset($data['due_date'], $data['drawee_bank_id'], $data['cheque_number']);
                $data['received_amount'] = isset($data['received_amount']) ? number_unformat($data['received_amount']) : 0;
                $data['partner_id'] = is_numeric($data['customer_id']) ? optional(Partner::find($data['customer_id']))->id : optional(Partner::where('is_customer', 1)->where('name', $data['customer_id'])->first())->id ;
                $data['receiving_date'] = $openingBalanceDate ;
                $data['receiving_currency'] = $data['currency'] ;
                $data['company_id'] = $company->id ;
				/**
				 * @var MoneyReceived $moneyReceived
				 */
                $moneyReceived = $openingBalance->chequeInSafe()->create(array_merge($data, [
                    'type' => MoneyReceived::CHEQUE,
                    'user_id' => auth()->id()
                ]));
                $moneyReceived->cheque()->create($pivotData);
            }
        }

        /**
         * * هنا تحديث الشيكات اللي قيد التحصيل
         * * cheques under collection
         */

        $oldIdsFromDatabase = $openingBalance->chequeUnderCollections->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input(MoneyReceived::CHEQUE_UNDER_COLLECTION, []), 'id') ;

        $elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
        // $elementsToUpdate = array_diff($idsFromRequest, $elementsToDelete); // test

        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
        $openingBalance->chequeUnderCollections()->whereIn('money_received.id', $elementsToDelete)->delete();

        
        foreach ($elementsToUpdate as $id) {
            $dataToUpdate = findByKey($request->input(MoneyReceived::CHEQUE_UNDER_COLLECTION), 'id', $id);
            $dataToUpdate['received_amount'] = isset($dataToUpdate['received_amount']) ? number_unformat($dataToUpdate['received_amount']) : 0;
            unset($dataToUpdate['id']);
            $dueDate = $dataToUpdate['due_date'] ?? null ;
            $dueDate = $dueDate ? Carbon::make($dueDate)->format('Y-m-d'):null;
            $pivotData = [
                'due_date' => $dueDate ,
                'expected_collection_date' => $dueDate,
                'drawee_bank_id' => isset($dataToUpdate['drawee_bank_id']) ? $dataToUpdate['drawee_bank_id'] : null,
                'cheque_number' => $dataToUpdate['cheque_number'],
                'deposit_date' => $dataToUpdate['deposit_date'] ?: null,
                'drawl_bank_id' => $dataToUpdate['drawl_bank_id'] ?: null,
                'account_type' => $dataToUpdate['account_type'] ?: null,
                'account_number' => $dataToUpdate['account_number'] ?: null,
                'clearance_days' => $dataToUpdate['clearance_days'] ?: 0,
                'company_id'=>$company->id
            ];
        
            foreach ($pivotData as $key => $val) {
                if ($key != 'company_id') {
                    unset($dataToUpdate[$key]);
                }
            }

            $dataToUpdate['partner_id'] = is_numeric($dataToUpdate['customer_id']) ? optional(Partner::find($dataToUpdate['customer_id']))->id : optional(Partner::where('is_customer', 1)->where('name', $dataToUpdate['customer_id'])->first())->id ;
            $dataToUpdate['receiving_date'] = $openingBalanceDate;
            $dataToUpdate['receiving_currency'] = $dataToUpdate['currency'];
            $dataToUpdate['company_id']=$company->id;
			/**
			 * @var MoneyReceived $currentChequeUnderCollection
			 */
			$currentChequeUnderCollection = $openingBalance->chequeUnderCollections()->where('money_received.id', $id)->first();
            $currentChequeUnderCollection->update(array_merge($dataToUpdate, ['updated_at'=>now()]));
            $currentChequeUnderCollection->cheque->update(array_merge($pivotData, ['updated_at'=>now()]));
        
        }

        foreach ($request->get(MoneyReceived::CHEQUE_UNDER_COLLECTION, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0')) {
                unset($data['id']);
                $dueDate = $data['due_date'] ? Carbon::make($data['due_date'])->format('Y-m-d') : null ;
                $pivotData = [
                    'due_date' => $dueDate,
                    'expected_collection_date' => $dueDate,
                    'status' => Cheque::UNDER_COLLECTION,
                    'drawee_bank_id' => isset($data['drawee_bank_id']) ? $data['drawee_bank_id'] : null,
                    'cheque_number' => $data['cheque_number'],
                    'deposit_date' => $data['deposit_date'] ?: null,
                    'drawl_bank_id' => $data['drawl_bank_id'] ?: null,
                    'account_type' => $data['account_type'] ?: null,
                    'account_number' => $data['account_number'] ?: null,
                    'clearance_days' => $data['clearance_days'] ?: 0,
                    'company_id'=>$company->id
                ];
                foreach ($pivotData as $key => $val) {
                    unset($data[$key]);
                }
                $data['partner_id'] = is_numeric($data['customer_id']) ? optional(Partner::find($data['customer_id']))->id : optional(Partner::where('is_customer', 1)->where('name', $data['customer_id'])->first())->id ;
                $data['receiving_date']=$openingBalanceDate;
                $data['receiving_currency']=$data['currency'];
                $data['company_id']=$company->id;
                $data['received_amount'] = isset($data['received_amount']) ? number_unformat($data['received_amount']) : 0 ;
                $moneyReceived = $openingBalance->chequeUnderCollections()->create(array_merge($data, [
                    'type' => MoneyReceived::CHEQUE,
                    'user_id' => auth()->id(),
                    'company_id'=>$company->id
                ]));
				/**
				 * @var MoneyReceived $moneyReceived
				 */
                $cheque = $moneyReceived->cheque()->create($pivotData);
                $cheque->update(['updated_at'=>now()]);
            }
        }
        
        
    
        /**
        * * هنا تحديث ال payable cheques
        * * payable cheques
        */

        $oldIdsFromDatabase = $openingBalance->payableCheques->pluck('id')->toArray();
        $idsFromRequest = array_column($request->input(MoneyPayment::PAYABLE_CHEQUE, []), 'id') ;
        
        $elementsToDelete = array_diff($oldIdsFromDatabase, $idsFromRequest);
        //  $elementsToUpdate = array_diff($idsFromRequest, $elementsToDelete); // test

        $elementsToUpdate = array_intersect($idsFromRequest, $oldIdsFromDatabase); // origin one
        foreach ($elementsToDelete as $elementToDeleteId) {
			/**
			 * @var MoneyPayment $currentMoneyPayment
			 */
            $currentMoneyPayment = MoneyPayment::find($elementToDeleteId);
			
            $currentMoneyPayment->deleteRelations();
            $currentMoneyPayment->delete();
        }
 
        foreach ($elementsToUpdate as $id) {
            $moneyType= MoneyPayment::PAYABLE_CHEQUE ;
            $dataToUpdate = findByKey($request->input($moneyType), 'id', $id);
            $dataToUpdate['paid_amount'] = isset($dataToUpdate['paid_amount']) ? number_unformat($dataToUpdate['paid_amount']) : 0;
            $dataToUpdate['delivery_date'] = $openingBalanceDate ;
            unset($dataToUpdate['id']);
            $dataToUpdate['amount_in_invoice_currency'] = $dataToUpdate['paid_amount'];
                    
            $pivotData = [
                'due_date' =>$statementDate= $dataToUpdate['due_date'],
                'delivery_bank_id' => $financialInstitutionId = isset($dataToUpdate['delivery_bank_id']) ? $dataToUpdate['delivery_bank_id'] : null,
                'cheque_number' => $dataToUpdate['cheque_number'],
                'account_type' => $accountType = $dataToUpdate['account_type'] ?: null,
                'account_number' => $accountNumber = $dataToUpdate['account_number'] ?: null,
                'company_id'=>$company->id
                 
            ];
            foreach ($pivotData as $key => $val) {
                unset($dataToUpdate[$key]);
            }
            $dataToUpdate['partner_id'] = is_numeric($dataToUpdate['supplier_id']) ? optional(Partner::find($dataToUpdate['supplier_id']))->id : optional(Partner::where('is_supplier', 1)->where('name', $dataToUpdate['supplier_id'])->first())->id ;
            $dataToUpdate['company_id'] = $company->id;
             
            $dataToUpdate['payment_currency'] = $dataToUpdate['currency'];
            $dataToUpdate['amount_in_invoice_currency'] = $dataToUpdate['paid_amount'];
			/**
			 * @var MoneyPayment $currentMoneyPayment
			 */
            $currentMoneyPayment = $openingBalance->payableCheques()->where('money_payments.id', $id)->first() ;
            $currentMoneyPayment->update(array_merge($dataToUpdate, ['updated_at'=>now()]));
            $currentMoneyPayment->payableCheque->update(array_merge($pivotData, ['updated_at'=>now()]));
            $currentStatement = $currentMoneyPayment->getCurrentStatement();

             
            $amountInPaymentCurrency = $dataToUpdate['amount_in_invoice_currency'];
            $deliveryBranchId = null ;
            $paymentCurrency  = $dataToUpdate['currency'];
            $accountType  = AccountType::find($accountType);
            // $fullModelName = 'App\Models\\'.$accountType->model_name;
    
            if ($currentStatement) {
                /**
                 * ! Need To Change To Work With All Other Account Types
                 */
                $financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
                $currentStatement->handleFullDateAfterDateEdit($statementDate, 0, $amountInPaymentCurrency, [
                    'financial_institution_account_id' =>  $financialInstitutionAccount->id
                ]);
            } else {
				/**
				 * @var MoneyPayment $currentMoneyPayment
				 */
                $currentMoneyPayment->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInPaymentCurrency, $deliveryBranchId, $paymentCurrency);
            }

        }
    
        foreach ($request->get(MoneyPayment::PAYABLE_CHEQUE, []) as $data) {
            if (!isset($data['id']) || (isset($data['id']) && $data['id'] == '0')) {
                unset($data['id']);
                $financialInstitutionId =isset($data['delivery_bank_id']) ? $data['delivery_bank_id'] : null;
                $chequeNumber= $data['cheque_number'];
                $accountType = $data['account_type'] ?: null;
                $accountNumber = $data['account_number'] ?: null ;
                $moneyType = MoneyPayment::PAYABLE_CHEQUE;
                $dueDate = $data['due_date'];
                $statementDate = $dueDate ;
                $pivotData = [
                    'due_date' => $dueDate ,
                    'status' => PayableCheque::PENDING,
                    'delivery_bank_id' => $financialInstitutionId,
                    'cheque_number' => $chequeNumber,
                    'account_type' => $accountType,
                    'account_number' => $accountNumber,
                    'company_id'=>$company->id,
                    'delivery_date'=>$openingBalanceDate
                   ];
                foreach ($pivotData as $key => $val) {
                    if ($key != 'delivery_date') {
                        unset($data[$key]);
                    }
                }
                $data['partner_id'] = is_numeric($data['supplier_id']) ? optional(Partner::find($data['supplier_id']))->id : optional(Partner::where('is_supplier', 1)->where('name', $data['supplier_id'])->first())->id ;
                $data['paid_amount'] = isset($data['paid_amount']) ? number_unformat($data['paid_amount']) : 0 ;
                $data['amount_in_invoice_currency'] = $data['paid_amount'];
                $amountInPaymentCurrency = $data['amount_in_invoice_currency'];
				/**
				 * @var MoneyPayment $moneyPayment
				 */
                $moneyPayment = $openingBalance->payableCheques()->create(array_merge($data, [
                    'type' => MoneyPayment::PAYABLE_CHEQUE,
                    'user_id' => auth()->id(),
                    'payment_currency'=>$paymentCurrency = $data['currency'],
                    'company_id'=>$company->id,
                    'delivery_date'=>$openingBalanceDate
                ]));
                $deliveryBranchId = null;
                $accountType  = AccountType::find($accountType);
                $moneyPayment->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInPaymentCurrency, $deliveryBranchId, $paymentCurrency);
				$payableCheque = $moneyPayment->payableCheque()->create($pivotData);
                $payableCheque->update(['updated_at'=>now()]);
            }
        }
        return redirect()
            ->route('opening-balance.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
        
    }
}
