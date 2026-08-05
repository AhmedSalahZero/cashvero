<?php

namespace App\Http\Controllers;

use App\Exports\Statements\BankStatementExport;
use App\Models\AccountType;
use App\Models\CleanOverdraft;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\FullySecuredOverdraft;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;
use App\Models\TimeOfDeposit;
use App\Services\Api\CashExpenseOdooService;
use App\Services\Api\LetterOfGuaranteeService;
use App\Traits\GeneralFunctions;
use App\Traits\PaginatesStatementQueries;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BankStatementController
 * ------------------------------------------------------------------
 * Renders the "Bank Statement" report (Statements sidebar section) —
 * a heavy, transaction-by-transaction ledger for ONE specific
 * bank account/facility (current account, or one of the 4 overdraft
 * types), for a chosen date range and currency. Every column
 * (beginning balance, debit, credit, end balance, and for overdraft
 * types: limit/room/calculated interest) is already computed and
 * stored per-row by the account's own bank-statement-writing trigger
 * logic elsewhere in the app — this controller only reads and
 * presents it, never recalculates it.
 *
 * Two rows can be edited inline from this report (both wired to
 * real, live Odoo journal entries): a Letter of Guarantee's
 * commission fees row, and an end-of-month interest row. Those two
 * update methods (updateCommissionFees / updateBankStatementRow) are
 * UNCHANGED, deliberately — this migration only touches how the data
 * reaches the screen, never the financial calculation or Odoo sync
 * logic underneath it.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   - index()               → ✅ Migrated to Inertia (Statements/BankStatement/Index)
 *   - result()               → ✅ Migrated to Inertia (Statements/BankStatement/Result).
 *                               Query logic per account type lives in
 *                               fetchStatementData() and is BYTE-FOR-BYTE
 *                               UNCHANGED from the original Blade-era
 *                               controller — only the pagination (now via
 *                               PaginatesStatementQueries — only the current
 *                               page is read from the database, filters
 *                               preserved across pages) and the row/KPI
 *                               shaping for Vue are new.
 *   - getAccountNumbers()     → ✅ New. Small JSON lookup powering the Bank → Account
 *                               Type → Account Number cascading dropdown on the Vue
 *                               form, mirroring the existing pattern already used by
 *                               MoneyReceivedController::getAccountNumbersForAccountType().
 *   - exportExcel()           → ✅ New (project-owner requested). Reuses the exact
 *                               same fetchStatementData()/mapStatementRow() as the
 *                               on-screen report — the export is guaranteed to match
 *                               what's on screen, never a second, drifting query.
 *                               Built on Maatwebsite\Excel (already used elsewhere in
 *                               the app, e.g. SalesGatheringController@export) via the
 *                               new App\Exports\Statements\BankStatementExport, which
 *                               adds real styling (colored header, banded rows,
 *                               End-Balance sign coloring, a formula-based totals row)
 *                               — no new export library introduced.
 *   - updateCommissionFees()  → Still plain Blade-era logic, UNCHANGED. Real Odoo
 *                               journal entry side effects — not touched.
 *   - updateBankStatementRow()→ Still plain Blade-era logic, UNCHANGED. Same reason.
 */
class BankStatementController
{
    use GeneralFunctions;
    use PaginatesStatementQueries;

    private const ROWS_PER_PAGE = 50;

    /**
     * Filter form: pick Bank → Account Type → Account Number → Currency → date range.
     * Renders Statements/BankStatement/Index.vue.
     */
    public function index(Company $company, Request $request)
    {
        $selectedAccountTypeName = $request->get('accountType');
        $selectedCurrency  = $request->get('currency');
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        $accountTypes = AccountType::onlyCashAccounts()->get();

        return \Inertia\Inertia::render('Statements/BankStatement/Index', [
            'company' => ['id' => $company->id],
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($bank) => [
                'id' => $bank->id,
                'name' => $bank->getName(),
            ])->values(),
            'accountTypes' => $accountTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->getName(),
                'modelName' => $type->getModelName(),
            ])->values(),
            'currencies' => getCurrency(),
            'selectedAccountTypeName' => $selectedAccountTypeName,
            'selectedCurrency' => $selectedCurrency,
            'urls' => [
                'result' => route('result.bank.statement', ['company' => $company->id]),
                'accountNumbers' => route('bank.statement.account.numbers', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * JSON lookup for the Account Number dropdown, once Bank + Account Type
     * (+ Currency, when already chosen) are selected. Same underlying
     * per-model getAllAccountNumberForCurrency() every other cascading
     * account-number dropdown in the app already uses (see
     * MoneyReceivedController@getAccountNumbersForAccountType) — not new
     * business logic, just a dedicated endpoint for this page instead of
     * reusing another feature's route.
     */
    public function getAccountNumbers(Company $company, Request $request)
    {
        $accountTypeId = $request->get('account_type');
        $financialInstitutionId = $request->get('financial_institution_id');
        $currencyName = $request->get('currency');

        $accountType = AccountType::find($accountTypeId);
        if (! $accountType || ! $financialInstitutionId) {
            return response()->json(['status' => true, 'data' => []]);
        }

        $modelClass = '\\App\\Models\\'.$accountType->getModelName();
        $accountNumbers = $modelClass::getAllAccountNumberForCurrency($company->id, $currencyName, $financialInstitutionId);

        return response()->json([
            'status' => true,
            'data' => $accountNumbers,
        ]);
    }

    /**
     * Builds the raw result set for the current filters. Which table gets
     * queried, and which columns exist on each row, depends entirely on the
     * account type chosen on the form — every branch here is BYTE-FOR-BYTE
     * UNCHANGED from the original controller (only extracted into its own
     * method so result() and exportExcel() can share it instead of running
     * two copies of the same query that could drift apart).
     *
     * Returns null when no rows match (caller decides how to respond).
     */
    private function fetchStatementData(Company $company, Request $request): ?array
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $financialInstitutionId = $request->get('financial_institution_id');
        $financialInstitution = FinancialInstitution::find($financialInstitutionId);
        $financialInstitutionName = $financialInstitution->getName();
        $accountTypeId = $request->get('account_type');
        $accountNumber = $request->get('account_number');
        $currencyName = $request->get('currency');
        $results = [];
        $accountType = AccountType::find($accountTypeId);

        /**
         * @var AccountType $accountType
         */

        $accountTypeName = $accountType->getName();
        $isCurrentAccount = $accountType->isCurrentAccount();
        $statementModelName = null;
        $statementTable = null;
        $freshQuery = null;
        if ($isCurrentAccount) {
            $statementModelName = 'CurrentAccountBankStatement';
            $statementTable = 'current_account_bank_statements';
            $financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
            $freshQuery = fn () => DB::table('current_account_bank_statements')
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            /**
             * * is_active =1
             * * علشان الكوميشن ما تجيش في حاله ال
             * * lg issuance
             */
            ->where('current_account_bank_statements.is_active', 1)
            ->where('current_account_bank_statements.financial_institution_account_id', $financialInstitutionAccount->id)
            ->where('current_account_bank_statements.company_id', $company->id)
            ->join('financial_institution_accounts', 'financial_institution_account_id', '=', 'financial_institution_accounts.id')
            ->where('financial_institution_accounts.currency', $currencyName)
            ->where('current_account_bank_statements.date', '>=', $startDate)
            ->where('current_account_bank_statements.date', '<=', $endDate)
            ->leftJoin('money_received', 'current_account_bank_statements.money_received_id', '=', 'money_received.id')
            ->selectRaw('current_account_bank_statements.*,financial_institution_accounts.*,money_received.is_reviewed,money_received.reviewed_by,current_account_bank_statements.id as id,current_account_bank_statements.full_date as full_date,current_account_bank_statements.date as date')
            ->orderByRaw('current_account_bank_statements.full_date desc , current_account_bank_statements.id desc');


        } elseif ($accountType->isCleanOverdraftAccount()) {
            $statementModelName = 'CleanOverdraftBankStatement';
            $statementTable = 'clean_overdraft_bank_statements';
            $cleanOverdraft  = CleanOverdraft::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);

            $freshQuery = fn () => DB::table('clean_overdraft_bank_statements')
                 ->where('clean_overdraft_bank_statements.company_id', $company->id)
                 ->where('date', '>=', $startDate)
                 ->where('date', '<=', $endDate)
                 ->where('clean_overdraft_id', $cleanOverdraft->id)
                 ->join('clean_overdrafts', 'clean_overdraft_bank_statements.clean_overdraft_id', '=', 'clean_overdrafts.id')
                 ->where('clean_overdrafts.currency', '=', $currencyName)
                //  ->leftJoin('money_received','current_account_bank_statements.money_received_id','=','money_received.id')
                ->orderByRaw('clean_overdraft_bank_statements.full_date desc , clean_overdraft_bank_statements.id desc')
                ->selectRaw('*,clean_overdraft_bank_statements.id as id');

        } elseif ($accountType->isFullySecuredOverdraftAccount()) {
            $fullySecuredOverdraft  = FullySecuredOverdraft::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
            $statementModelName = 'FullySecuredOverdraftBankStatement';
            $statementTable = 'fully_secured_overdraft_bank_statements';
            $freshQuery = fn () => DB::table('fully_secured_overdraft_bank_statements')
                 ->where('fully_secured_overdraft_bank_statements.company_id', $company->id)
                 ->where('date', '>=', $startDate)
                 ->where('date', '<=', $endDate)
                 ->where('fully_secured_overdraft_id', $fullySecuredOverdraft->id)
                 ->join('fully_secured_overdrafts', 'fully_secured_overdraft_bank_statements.fully_secured_overdraft_id', '=', 'fully_secured_overdrafts.id')
                 ->where('fully_secured_overdrafts.currency', '=', $currencyName)
                 ->selectRaw('*,fully_secured_overdraft_bank_statements.id as id')
                 ->orderByRaw('fully_secured_overdraft_bank_statements.full_date desc, fully_secured_overdraft_bank_statements.id desc');
        } elseif ($accountType->isOverdraftAgainstCommercialPaperAccount()) {
            $statementModelName = 'OverdraftAgainstCommercialPaperBankStatement';
            $statementTable = 'overdraft_against_commercial_paper_bank_statements';

            $overdraftAgainstCommercialPaper  = OverdraftAgainstCommercialPaper::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
            $freshQuery = fn () => DB::table('overdraft_against_commercial_paper_bank_statements')
                 ->where('overdraft_against_commercial_paper_bank_statements.company_id', $company->id)
                 ->where('date', '>=', $startDate)
                 ->where('date', '<=', $endDate)
                 ->where('overdraft_against_commercial_paper_id', $overdraftAgainstCommercialPaper->id)
                 ->join('overdraft_against_commercial_papers', 'overdraft_against_commercial_paper_bank_statements.overdraft_against_commercial_paper_id', '=', 'overdraft_against_commercial_papers.id')
                 ->where('overdraft_against_commercial_papers.currency', '=', $currencyName)
                 ->orderByRaw('overdraft_against_commercial_paper_bank_statements.full_date desc, overdraft_against_commercial_paper_bank_statements.id desc')
                 ->selectRaw('* , overdraft_against_commercial_paper_bank_statements.limit as statement_limit,overdraft_against_commercial_paper_bank_statements.id as id');
        } elseif ($accountType->isOverdraftAgainstAssignmentOfContractAccount()) {
            $statementModelName = 'OverdraftAgainstAssignmentOfContractBankStatement';
            $statementTable = 'overdraft_against_assignment_of_contract_bank_statements';
            $overdraftAgainstAgainstAssignmentOfContract  = OverdraftAgainstAssignmentOfContract::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
            $odaId = $overdraftAgainstAgainstAssignmentOfContract ? $overdraftAgainstAgainstAssignmentOfContract->id:0;
            $freshQuery = fn () => DB::table('overdraft_against_assignment_of_contract_bank_statements')
                 ->where('overdraft_against_assignment_of_contract_bank_statements.company_id', $company->id)
                 ->where('date', '>=', $startDate)
                 ->where('date', '<=', $endDate)
                 ->where('overdraft_against_assignment_of_contract_id', $odaId)
                 ->join('overdraft_against_assignment_of_contracts', 'overdraft_against_assignment_of_contract_bank_statements.overdraft_against_assignment_of_contract_id', '=', 'overdraft_against_assignment_of_contracts.id')
                 ->where('overdraft_against_assignment_of_contracts.currency', '=', $currencyName)
                 ->orderByRaw('overdraft_against_assignment_of_contract_bank_statements.full_date desc, overdraft_against_assignment_of_contract_bank_statements.id desc')
                 ->selectRaw('* , overdraft_against_assignment_of_contract_bank_statements.limit as statement_limit,overdraft_against_assignment_of_contract_bank_statements.id as id');
        }

        if (is_null($freshQuery) || ! $freshQuery()->exists()) {
            return null;
        }

        return [
            'query' => $freshQuery,
            'statementTable' => $statementTable,
            /**
             * * كل فروع الاستعلام فوق بترتّب بـ full_date مش date، وده نفس
             * * الترتيب اللي الرصيد الجاري (beginning_balance/end_balance)
             * * بيتسلسل بيه في CurrentAccountBankStatement و
             * * RepairStatementBalancesCommand ('full_date asc, id asc')
             * * فالكروت لازم تقرا آخر/أول صف بنفس العمود ده
             */
            'orderColumn' => 'full_date',
            'statementModelName' => $statementModelName,
            'accountType' => $accountType,
            'accountTypeName' => $accountTypeName,
            'isCurrentAccount' => $isCurrentAccount,
            'isAgainstCommercialPaper' => $accountType->isOverdraftAgainstCommercialPaperAccount(),
            'isAgainstAssignmentOfContract' => $accountType->isOverdraftAgainstAssignmentOfContractAccount(),
            'financialInstitutionName' => $financialInstitutionName,
            'accountNumber' => $accountNumber,
            'currencyName' => $currencyName,
        ];
    }

    /**
     * Shapes one raw statement row (stdClass from the query above) into the
     * plain array both the on-screen table (via result()) and the Excel
     * export (via exportExcel()) read from — one formula for "what a row
     * means," used in both places, so they can never silently drift apart.
     */
    private function mapStatementRow($row, string $lang, bool $isCurrentAccount, bool $isAgainstCommercialPaper, bool $isAgainstAssignmentOfContract): array
    {
        $reviewedArr = getBankStatementReviewed($row);

        return [
            'id' => $row->id,
            'date' => Carbon::make($row->date)->format('d-m-Y'),
            'limit' => ! $isCurrentAccount ? (float) ($row->limit ?? 0) : null,
            'statementLimit' => ($isAgainstCommercialPaper || $isAgainstAssignmentOfContract) ? (float) ($row->statement_limit ?? 0) : null,
            'beginningBalance' => (float) ($row->beginning_balance ?? 0),
            'debit' => (float) ($row->debit ?? 0),
            'credit' => (float) ($row->credit ?? 0),
            'endBalance' => (float) ($row->end_balance ?? 0),
            'room' => ! $isCurrentAccount ? (float) ($row->room ?? 0) : null,
            'interestAmount' => ! $isCurrentAccount ? (float) ($row->interest_amount ?? 0) : null,
            'reviewedText' => getReviewedText($reviewedArr),
            'comment' => (isset($row->{'comment_'.$lang}) ? $row->{'comment_'.$lang} : null) ?: getBankStatementComment($row),
            'userComment' => \App\Helpers\HVero::getUserCommentFromModel($row),
            'isCommissionFees' => (bool) ($row->is_commission_fees ?? false),
            'interestType' => $row->interest_type ?? null,
            'letterOfGuaranteeIssuanceId' => $row->letter_of_guarantee_issuance_id ?? null,
            'rawDate' => $row->date,
        ];
    }

    /**
     * The report itself. Which table gets queried, and which columns exist
     * on each row, depends entirely on the account type chosen on the form
     * — this branching (fetchStatementData()) is UNCHANGED from the
     * original controller. Only the pagination (filters now preserved
     * across pages) and the shaping of each row into a plain array/KPI set
     * for Vue are new.
     */
    public function result(Company $company, Request $request)
    {
        $data = $this->fetchStatementData($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $isCurrentAccount = $data['isCurrentAccount'];
        $isAgainstCommercialPaper = $data['isAgainstCommercialPaper'];
        $isAgainstAssignmentOfContract = $data['isAgainstAssignmentOfContract'];

        /**
         * KPI totals still describe the FULL date range, not the current
         * page — they just no longer require reading the whole range into
         * PHP to get there. Debit/credit are SQL SUMs over the same WHERE
         * clause; the beginning and ending balances are read off the
         * earliest and latest rows, which is what taking last()/first() of
         * the date-desc collection used to mean. Whether a range has 50
         * rows or 50,000, the page costs the same now.
         */
        $paginator = $this->paginateStatement($data['query'], self::ROWS_PER_PAGE);
        $kpis = $this->ledgerStatementKpis($data['query'], $data['statementTable'], $paginator->total(), $data['orderColumn']);

        $lang = app()->getLocale();
        $paginator->getCollection()->transform(
            fn ($row) => $this->mapStatementRow($row, $lang, $isCurrentAccount, $isAgainstCommercialPaper, $isAgainstAssignmentOfContract)
        );

        return \Inertia\Inertia::render('Statements/BankStatement/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currencyName'],
            'isCurrentAccount' => $isCurrentAccount,
            'financialInstitutionName' => $data['financialInstitutionName'],
            'accountTypeName' => $data['accountTypeName'],
            'accountNumber' => $data['accountNumber'],
            'isAgainstCommercialPaper' => $isAgainstCommercialPaper,
            'isAgainstAssignmentOfContract' => $isAgainstAssignmentOfContract,
            'statementModelName' => $data['statementModelName'],
            'kpis' => $kpis,
            // Same shape MoneyReceivedController's tabbed pagination already
            // produces ($paginator->toArray() → data/links/current_page/last_page/total),
            // so Result.vue's pagination controls follow the same proven pattern.
            'paginator' => $paginator->toArray(),
            'urls' => [
                'backUrl' => route('view.bank.statement', ['company' => $company->id]),
                'withdrawalsSettlementReportUrl' => route('view.withdrawals.settlement.report', ['company' => $company->id]),
                'updateCommissionFeesUrl' => route('update.commission.fees', ['company' => $company->id]),
                'updateBankStatementRowUrl' => route('update.bank.statement.debit.or.credit', ['company' => $company->id]),
                // Same filters already in this request, resolved server-side so the
                // "Export to Excel" button is a plain link — no client-side query
                // string building needed (matches the no-Ziggy, pre-resolved-URL
                // convention already used everywhere else in this app).
                'exportUrl' => route('export.bank.statement', array_merge(['company' => $company->id], $request->only([
                    'start_date', 'end_date', 'financial_institution_id', 'account_type', 'account_number', 'currency',
                ]))),
            ],
        ]);
    }

    /**
     * Excel export (project-owner requested) — same filters as result(),
     * reusing fetchStatementData()/mapStatementRow() so the workbook can
     * never drift from what's on screen. Exports the FULL date range in one
     * file (not just the currently-viewed page), since the whole point is
     * offline analysis of a heavy transaction list. Built on the same
     * Maatwebsite\Excel ExportData class SalesGatheringController@export
     * already uses — no new export library introduced.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $data = $this->fetchStatementData($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $isCurrentAccount = $data['isCurrentAccount'];
        $isAgainstCommercialPaper = $data['isAgainstCommercialPaper'];
        $isAgainstAssignmentOfContract = $data['isAgainstAssignmentOfContract'];
        $showActualLimit = $isAgainstCommercialPaper || $isAgainstAssignmentOfContract;
        $lang = app()->getLocale();

        $headings = ['#', 'Date'];
        if (! $isCurrentAccount) {
            $headings[] = 'Limit';
        }
        if ($showActualLimit) {
            $headings[] = 'Actual Limit';
        }
        array_push($headings, 'Beginning Balance', 'Debit', 'Credit', 'End Balance');
        if (! $isCurrentAccount) {
            array_push($headings, 'Room', 'Calculated Interest');
        }
        array_push($headings, 'Reviewed', 'Comment');

        // The workbook is the whole range, not the page on screen, so the
        // export runs the same query unpaginated.
        $rows = $data['query']()->get()->values()->map(function ($row, $index) use ($lang, $isCurrentAccount, $showActualLimit, $isAgainstCommercialPaper, $isAgainstAssignmentOfContract) {
            $mapped = $this->mapStatementRow($row, $lang, $isCurrentAccount, $isAgainstCommercialPaper, $isAgainstAssignmentOfContract);

            $line = ['#' => $index + 1, 'Date' => $mapped['date']];
            if (! $isCurrentAccount) {
                $line['Limit'] = $mapped['limit'];
            }
            if ($showActualLimit) {
                $line['Actual Limit'] = $mapped['statementLimit'];
            }
            $line['Beginning Balance'] = $mapped['beginningBalance'];
            $line['Debit'] = $mapped['debit'];
            $line['Credit'] = $mapped['credit'];
            $line['End Balance'] = $mapped['endBalance'];
            if (! $isCurrentAccount) {
                $line['Room'] = $mapped['room'];
                $line['Calculated Interest'] = $mapped['interestAmount'];
            }
            $line['Reviewed'] = $mapped['reviewedText'];
            $line['Comment'] = trim($mapped['comment'].' '.$mapped['userComment']);

            return $line;
        });

        $fileNameParts = [
            'Bank-Statement',
            $data['financialInstitutionName'],
            $data['accountNumber'],
            strtoupper((string) $data['currencyName']),
        ];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new BankStatementExport($headings, $rows))->download($fileName);
    }

    /**
     * UNCHANGED — real Odoo journal-entry side effects for a Letter of
     * Guarantee's commission fees row. Presentation-layer migration only;
     * this method's logic was deliberately left untouched.
     */
    public function updateCommissionFees(Company $company, Request $request)
    {
        $statementModelName = $request->get('statement_model_name');
        $statementId = $request->get('statement_id');
        $credit = number_unformat($request->get('credit'));
        $date = Carbon::make($request->get('date'))->format('Y-m-d');
        $fullModelClass = 'App\Models\\'.$statementModelName;
        $bankStatementRecord = $fullModelClass::find($statementId) ;
        $letterOfGuaranteeIssuanceId = $bankStatementRecord->letter_of_guarantee_issuance_id;
        $letterOfGuaranteeIssuance  = LetterOfGuaranteeIssuance::find($letterOfGuaranteeIssuanceId);
		 $financialInstitutionAccountForCommissionAndFees = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->getCommissionFeesAccountId());
        /**
         * @var LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance
         */
        $lgType = $letterOfGuaranteeIssuance->getLgTypeFormatted();
        $bankStatementRecord->handleFullDateAfterDateEdit($date, 0, $credit);
		// $bankStatementRecord->lg_commission_fees_journal_entry_id=1;
		// $bankStatementRecord->odoo_lg_commission_fees_reference=2;
		// $bankStatementRecord->save();

        if ($company->hasOdooIntegrationCredentials()) {
            $odooLetterOfGuaranteeIssuanceService = new LetterOfGuaranteeService($company);

                $currentJournalEntryId = $bankStatementRecord->lg_commission_fees_journal_entry_id;
                if ($currentJournalEntryId) {
                    $odooLetterOfGuaranteeIssuanceService->unlink($currentJournalEntryId);
                }




           $commissionFees = $credit;
            $ref = $lgType . ' Commission Fees';
            $message = $ref;
            $odooSetting = $company->odooSetting;
            $debitOdooAccountId = $odooSetting->getLetterOfGuaranteeCommissionFeesId();

            $fromAccountNumber = $financialInstitutionAccountForCommissionAndFees->getAccountNumber();
            $journalId = $financialInstitutionAccountForCommissionAndFees->financialInstitution->getJournalIdForAccount(27, $fromAccountNumber);
            $accountOdooId = $financialInstitutionAccountForCommissionAndFees->financialInstitution->getOdooIdForAccount(27, $fromAccountNumber);
            $currency = $letterOfGuaranteeIssuance->getLgCurrency();
            $odooCurrencyId = Currency::getOdooId($currency);
            $analytic_distribution = $letterOfGuaranteeIssuance->formatAnalysisDistribution();
            $result = $odooLetterOfGuaranteeIssuanceService->createLgIssuanceCashCover($date, $commissionFees, $journalId, $odooCurrencyId, $debitOdooAccountId, $accountOdooId, $letterOfGuaranteeIssuance->getBeneficiaryOdooId(), $ref, $message, $analytic_distribution);
         //   $letterOfGuaranteeIssuance->commission_fees_account_bank_statement_odoo_id=$result['account_bank_statement_line_id'];
	      $bankStatementRecord->lg_commission_fees_journal_entry_id=$result['journal_entry_id'];
	     $bankStatementRecord->odoo_lg_commission_fees_reference=$result['reference'];
		 $bankStatementRecord->save();




        }



        return redirect()->back()->with('success', __('Data Updated Successfully'));
    }

    /**
     * UNCHANGED — real Odoo journal-entry side effects for an
     * end-of-month interest row. Presentation-layer migration only; this
     * method's logic was deliberately left untouched.
     */
    public function updateBankStatementRow(Company $company, Request $request)
    {

        $statementModelName = $request->get('statement_model_name');
        $statementId = $request->get('statement_id');
        $credit = number_unformat($request->get('credit', 0));
        $debit = number_unformat($request->get('debit', 0));
        $date = Carbon::make($request->get('date'))->format('Y-m-d');
        $fullModelClass = 'App\Models\\'.$statementModelName;
        $bankStatementRecord = $fullModelClass::find($statementId) ;
		$financialInstitutionAccountId = $bankStatementRecord->financial_institution_account_id;
		$financialInstitutionAccount = FinancialInstitutionAccount::find($financialInstitutionAccountId);
		$financialInstitution = $financialInstitutionAccount->financialInstitution;
		$financialInstitutionId= $financialInstitution->id;
		if($bankStatementRecord && $bankStatementRecord->interest_journal_entry_id){
			(new CashExpenseOdooService($company))->unlink($bankStatementRecord->interest_journal_entry_id);
		}
		(new TimeOfDeposit())->storePeriodInterestOdooRelations($bankStatementRecord,$date,$debit,$financialInstitutionId , $financialInstitutionAccountId,$company);
        $bankStatementRecord->handleFullDateAfterDateEdit($date, $debit, $credit);
        return redirect()->back()->with('success', __('Data Updated Successfully'));
    }
}
