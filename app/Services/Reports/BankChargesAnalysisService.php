<?php

namespace App\Services\Reports;

use App\Models\AccountType;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\ForeignExchangeRate;
use App\Models\LcSettlementInternalMoneyTransfer;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LoanScheduleSettlement;
use App\Support\ShareholderAccounts\ShareholderAccountAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bank Interest, Commissions & Charges Analysis.
 *
 * What the company actually paid its banks over a period, by bank and then
 * by facility. Nothing here is computed afresh: every figure is read from
 * the record the rest of the app already treats as the charge itself, so
 * this report can always be reconciled line by line against the statement
 * it came from.
 *
 *   Interest
 *     - Overdrafts (4 types) and LC overdrafts: the month-end `interest`
 *       row's `credit`. The statement triggers write the month's accrued
 *       interest into that one row; the per-row `interest_amount` is only
 *       the accrual behind it, so summing it as well would double count.
 *     - LC settlement interest: `lc_settlement_internal_money_transfers`,
 *       for the cases handleInterestSettlement() actually posts.
 *     - Medium term loans: the interest due on each installment dated in
 *       the period. An installment closed by early settlement only counts
 *       the interest paid before it — the rest was waived (same rule as
 *       MediumTermLoan::getRepaymentBreakdown()).
 *   Commission
 *     - LG / LC commission rows on the current account.
 *     - Highest debit balance rows on the overdraft statements.
 *   Charges
 *     - LG / LC issuance and renewal fees, LC expenses, loan early
 *       settlement fees, and cash expenses paid as bank charges.
 *
 * ⚠️ LG commission rows carry BOTH is_commission_fees=1 and
 * is_issuance_fees=1 (HasCommissionStatements passes the issuance flag as
 * well), so the commission flag is checked first. Checking issuance first
 * files every commission as an issuance fee.
 *
 * ⚠️ LG / LC commission rows for future quarters are written up front with
 * is_active=0 and switched on by CurrentAccountBankStatementActiveJob as
 * their date arrives. DB::table() skips the model's only_active scope, so
 * the flag is checked here by hand.
 *
 * Currency: one selected currency → that currency, as stored. Several, or
 * none (all of them) → every amount converted to the company's main
 * functional currency at the rate on the line's own date.
 */
class BankChargesAnalysisService
{
    public const INTEREST = 'interest';

    public const COMMISSION = 'commission';

    public const CHARGES = 'charges';

    /** AccountType has no constant for it; this is its slug. */
    public const LETTER_OF_GUARANTEE = 'letter-of-guarantee-lgs';

    /**
     * slug => [facility table, statement table, foreign key, label].
     * Same four as FinancialInstitutionFacilitiesController.
     */
    private const OVERDRAFTS = [
        AccountType::CLEAN_OVERDRAFT => ['clean_overdrafts', 'clean_overdraft_bank_statements', 'clean_overdraft_id', 'Clean Overdraft'],
        AccountType::FULLY_SECURED_OVERDRAFT => ['fully_secured_overdrafts', 'fully_secured_overdraft_bank_statements', 'fully_secured_overdraft_id', 'Fully Secured Overdraft'],
        AccountType::OVERDRAFT_AGAINST_COMMERCIAL_PAPER => ['overdraft_against_commercial_papers', 'overdraft_against_commercial_paper_bank_statements', 'overdraft_against_commercial_paper_id', 'Overdraft Against Commercial Paper'],
        AccountType::OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACTS => ['overdraft_against_assignment_of_contracts', 'overdraft_against_assignment_of_contract_bank_statements', 'overdraft_against_assignment_of_contract_id', 'Overdraft Against Assignment Of Contract'],
    ];

    private int $companyId;

    /** @var array<int, string> financial_institutions.id => display name */
    private array $bankNames = [];

    /** @var array<int> */
    private array $bankIds = [];

    private bool $canViewShareholderAccounts = false;

    /**
     * The facility types the filter offers, in display order.
     *
     * @return array<string, string> slug => label
     */
    public static function facilityTypes(): array
    {
        $types = [];
        foreach (self::OVERDRAFTS as $slug => [, , , $label]) {
            $types[$slug] = __($label);
        }

        return $types + [
            AccountType::MEDIUM_TERM_LOAN => __('Medium Term Loan'),
            self::LETTER_OF_GUARANTEE => __('Letter Of Guarantee'),
            AccountType::LETTER_OF_CREDIT_LCS => __('Letter Of Credit'),
            AccountType::CURRENT_ACCOUNT => __('Current Account'),
        ];
    }

    /** @return array<string, string> category => label */
    public static function categories(): array
    {
        return [
            self::INTEREST => __('Interest'),
            self::COMMISSION => __('Commission'),
            self::CHARGES => __('Charges'),
        ];
    }

    /**
     * Every currency this company holds a facility or a bank account in —
     * the choices the currency filter offers.
     *
     * @return array<int, string>
     */
    public function availableCurrencies(Company $company): array
    {
        // Every place a line of this report takes its currency from — including the charges that
        // carry their own (a bank charge can be paid in a currency the company holds no account in).
        $tables = [
            'financial_institution_accounts', 'medium_term_loans', 'letter_of_guarantee_facilities', 'letter_of_credit_facilities',
            'cash_expenses', 'lc_issuance_expenses', 'lc_settlement_internal_money_transfers',
        ];
        foreach (self::OVERDRAFTS as [$facilityTable]) {
            $tables[] = $facilityTable;
        }

        $currencies = [];
        foreach ($tables as $table) {
            foreach (DB::table($table)->where('company_id', $company->id)->whereNotNull('currency')->distinct()->pluck('currency') as $currency) {
                if (trim((string) $currency) !== '') {
                    $currencies[strtoupper(trim((string) $currency))] = true;
                }
            }
        }

        $currencies = array_keys($currencies);
        sort($currencies);

        return $currencies;
    }

    /**
     * The whole report for one set of filters.
     *
     * @param  array<int>  $bankIds  financial_institutions ids, already checked against the company; empty = every bank
     * @param  array<string>  $facilityTypes  keys of facilityTypes(); empty = every type
     * @param  array<string>  $currencies  empty = every currency
     * @return array{lines: Collection, summary: array, kpis: array, displayCurrency: string, isConverted: bool, missingRates: array<int, string>}
     */
    public function build(Company $company, string $startDate, string $endDate, array $bankIds = [], array $facilityTypes = [], array $currencies = []): array
    {
        $this->companyId = (int) $company->id;
        $this->bankIds = array_values(array_map('intval', $bankIds));
        $this->canViewShareholderAccounts = ShareholderAccountAccess::canView();
        $this->bankNames = FinancialInstitution::with('bank')
            ->where('company_id', $this->companyId)
            ->get()
            ->mapWithKeys(fn (FinancialInstitution $institution) => [$institution->id => (string) $institution->getName()])
            ->all();

        $currencies = array_values(array_unique(array_map(fn ($c) => strtoupper(trim((string) $c)), $currencies)));
        $wanted = fn (string $type) => $facilityTypes === [] || in_array($type, $facilityTypes, true);

        $lines = collect();

        foreach (self::OVERDRAFTS as $slug => $definition) {
            if ($wanted($slug)) {
                $lines = $lines->concat($this->overdraftLines($slug, $definition, $startDate, $endDate));
            }
        }

        if ($wanted(AccountType::MEDIUM_TERM_LOAN)) {
            $lines = $lines
                ->concat($this->loanInterestLines($startDate, $endDate))
                ->concat($this->earlySettlementFeeLines($startDate, $endDate));
        }

        if ($wanted(self::LETTER_OF_GUARANTEE)) {
            $lines = $lines->concat($this->letterOfGuaranteeFeeLines($startDate, $endDate));
        }

        if ($wanted(AccountType::LETTER_OF_CREDIT_LCS)) {
            $lines = $lines
                ->concat($this->lcOverdraftInterestLines($startDate, $endDate))
                ->concat($this->lcSettlementInterestLines($startDate, $endDate))
                ->concat($this->letterOfCreditFeeLines($startDate, $endDate))
                ->concat($this->lcExpenseLines($startDate, $endDate));
        }

        // Bank charges can sit on a current account or on an overdraft, so
        // the type filter is applied per line rather than up front.
        $lines = $lines->concat(
            $this->bankChargeLines($startDate, $endDate)->filter(fn (array $line) => $wanted($line['facility_type']))
        );

        if ($currencies !== []) {
            $lines = $lines->filter(fn (array $line) => in_array($line['currency'], $currencies, true));
        }

        $mainCurrency = $company->getMainFunctionalCurrency();
        $isConverted = count($currencies) !== 1;
        $displayCurrency = $isConverted ? $mainCurrency : $currencies[0];
        [$lines, $missingRates] = $this->convert($lines, $isConverted, $mainCurrency);

        $lines = $lines
            ->sortBy([['date', 'asc'], ['bank_name', 'asc'], ['facility_label', 'asc']])
            ->values();

        [$summary, $kpis] = $this->summarize($lines);

        return [
            'lines' => $lines,
            'summary' => $summary,
            'kpis' => $kpis,
            'displayCurrency' => $displayCurrency,
            'isConverted' => $isConverted,
            'missingRates' => $missingRates,
        ];
    }

    /* ───────────────────────── sources ───────────────────────── */

    /** Month-end interest and highest debit balance rows on one overdraft type. */
    private function overdraftLines(string $slug, array $definition, string $startDate, string $endDate): Collection
    {
        [$facilityTable, $statementTable, $foreignKey, $typeLabel] = $definition;

        $rows = DB::table($statementTable.' as s')
            ->join($facilityTable.' as f', 'f.id', '=', 's.'.$foreignKey)
            ->where('s.company_id', $this->companyId)
            ->whereBetween('s.date', [$startDate, $endDate])
            ->where(function (Builder $query) {
                $query->where(fn (Builder $q) => $q->where('s.type', 'interest')->where('s.interest_type', 'end_of_month'))
                    ->orWhere('s.type', 'highest_debit_balance');
            })
            ->where('s.credit', '!=', 0)
            ->tap(fn (Builder $query) => $this->whereBank($query, 'f.financial_institution_id'))
            ->get(['s.date', 's.type', 's.credit', 's.comment_en', 's.comment_ar', 'f.financial_institution_id', 'f.account_number', 'f.currency']);

        return $rows->map(function ($row) use ($slug, $typeLabel) {
            $isInterest = $row->type === 'interest';

            return $this->line(
                date: $row->date,
                bankId: (int) $row->financial_institution_id,
                facilityType: $slug,
                facilityTypeLabel: __($typeLabel),
                facilityKey: $slug.'|'.$row->financial_institution_id.'|'.$row->account_number,
                facilityLabel: (string) $row->account_number,
                category: $isInterest ? self::INTEREST : self::COMMISSION,
                item: $isInterest ? __('End Of Month Interest') : __('Highest Debit Balance Commission'),
                currency: $row->currency,
                amount: (float) $row->credit,
                comment: $this->comment($row),
            );
        });
    }

    /** Interest due on each MTL installment dated in the period. */
    private function loanInterestLines(string $startDate, string $endDate): Collection
    {
        $paid = DB::table('loan_schedule_settlements')
            ->selectRaw('loan_schedule_id, SUM(amount) as paid_amount')
            ->groupBy('loan_schedule_id');

        $rows = DB::table('loan_schedules as ls')
            ->join('medium_term_loans as m', 'm.id', '=', 'ls.medium_term_loan_id')
            ->leftJoinSub($paid, 'paid', 'paid.loan_schedule_id', '=', 'ls.id')
            ->where('m.company_id', $this->companyId)
            ->whereBetween('ls.date', [$startDate, $endDate])
            ->where('ls.interest_amount', '>', 0)
            ->tap(fn (Builder $query) => $this->whereBank($query, 'm.financial_institution_id'))
            ->tap(fn (Builder $query) => $this->whereCompanyOwned($query, 'm'))
            ->get(['ls.date', 'ls.interest_amount', 'ls.medium_term_loan_early_settlement_id', 'paid.paid_amount', 'm.financial_institution_id', 'm.name', 'm.account_number', 'm.currency']);

        return $rows->map(function ($row) {
            $isEarlySettled = (bool) $row->medium_term_loan_early_settlement_id;
            $amount = $isEarlySettled
                ? LoanScheduleSettlement::interestPaidFor((float) $row->paid_amount, (float) $row->interest_amount)
                : (float) $row->interest_amount;

            return $this->line(
                date: $row->date,
                bankId: (int) $row->financial_institution_id,
                facilityType: AccountType::MEDIUM_TERM_LOAN,
                facilityTypeLabel: __('Medium Term Loan'),
                facilityKey: $this->loanKey($row),
                facilityLabel: $this->loanLabel($row),
                category: self::INTEREST,
                item: __('Installment Interest'),
                currency: $row->currency,
                amount: $amount,
                comment: $isEarlySettled ? __('Early settled — remaining interest waived') : null,
            );
        })->filter(fn (array $line) => $line['amount'] != 0);
    }

    private function earlySettlementFeeLines(string $startDate, string $endDate): Collection
    {
        $rows = DB::table('medium_term_loan_early_settlements as e')
            ->join('medium_term_loans as m', 'm.id', '=', 'e.medium_term_loan_id')
            ->where('e.company_id', $this->companyId)
            ->whereBetween('e.date', [$startDate, $endDate])
            ->where('e.fee_amount', '>', 0)
            ->tap(fn (Builder $query) => $this->whereBank($query, 'm.financial_institution_id'))
            ->tap(fn (Builder $query) => $this->whereCompanyOwned($query, 'm'))
            ->get(['e.date', 'e.fee_amount', 'm.financial_institution_id', 'm.name', 'm.account_number', 'm.currency']);

        return $rows->map(fn ($row) => $this->line(
            date: $row->date,
            bankId: (int) $row->financial_institution_id,
            facilityType: AccountType::MEDIUM_TERM_LOAN,
            facilityTypeLabel: __('Medium Term Loan'),
            facilityKey: $this->loanKey($row),
            facilityLabel: $this->loanLabel($row),
            category: self::CHARGES,
            item: __('Early Settlement Fees'),
            currency: $row->currency,
            amount: (float) $row->fee_amount,
            comment: null,
        ));
    }

    /** LG commission / issuance / renewal rows on the current account. */
    private function letterOfGuaranteeFeeLines(string $startDate, string $endDate): Collection
    {
        $rows = $this->feeRowsQuery('letter_of_guarantee_issuance_id', 'letter_of_guarantee_issuances', 'letter_of_guarantee_facilities', 'lg_facility_id', $startDate, $endDate)
            ->get(['s.date', 's.credit', 's.is_commission_fees', 's.is_renewal_fees', 's.is_issuance_fees', 's.comment_en', 's.comment_ar', 'a.currency', 'i.financial_institution_id', 'i.source', 'i.lg_facility_id as facility_id', 'f.name as facility_name']);

        $sources = LetterOfGuaranteeIssuance::lgSources();

        return $rows->map(fn ($row) => $this->feeLine($row, self::LETTER_OF_GUARANTEE, __('Letter Of Guarantee'), 'lg', $sources[$row->source ?: LetterOfGuaranteeIssuance::LG_FACILITY] ?? __('N/A')));
    }

    /** LC commission / issuance rows on the current account. */
    private function letterOfCreditFeeLines(string $startDate, string $endDate): Collection
    {
        $rows = $this->feeRowsQuery('letter_of_credit_issuance_id', 'letter_of_credit_issuances', 'letter_of_credit_facilities', 'lc_facility_id', $startDate, $endDate)
            ->get(['s.date', 's.credit', 's.is_commission_fees', 's.is_renewal_fees', 's.is_issuance_fees', 's.comment_en', 's.comment_ar', 'a.currency', 'i.financial_institution_id', 'i.source', 'i.lc_facility_id as facility_id', 'f.name as facility_name']);

        $sources = LetterOfCreditIssuance::lcSources();

        return $rows->map(fn ($row) => $this->feeLine($row, AccountType::LETTER_OF_CREDIT_LCS, __('Letter Of Credit'), 'lc', $sources[$row->source ?: LetterOfCreditIssuance::LC_FACILITY] ?? __('N/A')));
    }

    private function feeRowsQuery(string $issuanceColumn, string $issuanceTable, string $facilityTable, string $facilityColumn, string $startDate, string $endDate): Builder
    {
        return DB::table('current_account_bank_statements as s')
            ->join('financial_institution_accounts as a', 'a.id', '=', 's.financial_institution_account_id')
            ->join($issuanceTable.' as i', 'i.id', '=', 's.'.$issuanceColumn)
            ->leftJoin($facilityTable.' as f', 'f.id', '=', 'i.'.$facilityColumn)
            ->where('s.company_id', $this->companyId)
            ->where('s.is_active', 1)
            ->whereBetween('s.date', [$startDate, $endDate])
            ->where(function (Builder $query) {
                $query->where('s.is_commission_fees', 1)
                    ->orWhere('s.is_renewal_fees', 1)
                    ->orWhere('s.is_issuance_fees', 1);
            })
            ->where('s.credit', '>', 0)
            ->tap(fn (Builder $query) => $this->whereBank($query, 'i.financial_institution_id'))
            ->tap(fn (Builder $query) => $this->whereCompanyOwned($query, 'a'));
    }

    /** Commission first — see the class docblock for why the order matters. */
    private function feeLine(object $row, string $facilityType, string $facilityTypeLabel, string $prefix, string $sourceLabel): array
    {
        if ($row->is_commission_fees) {
            [$category, $item] = [self::COMMISSION, $prefix === 'lg' ? __('LG Commission') : __('LC Commission')];
        } elseif ($row->is_renewal_fees) {
            [$category, $item] = [self::CHARGES, __('Renewal Fees')];
        } else {
            [$category, $item] = [self::CHARGES, __('Issuance Fees')];
        }

        return $this->line(
            date: $row->date,
            bankId: (int) $row->financial_institution_id,
            facilityType: $facilityType,
            facilityTypeLabel: $facilityTypeLabel,
            facilityKey: $this->instrumentFacilityKey($prefix, $row),
            facilityLabel: $row->facility_id ? ($row->facility_name ?: __('N/A')) : $sourceLabel,
            category: $category,
            item: $item,
            currency: $row->currency,
            amount: (float) $row->credit,
            comment: $this->comment($row),
        );
    }

    private function lcOverdraftInterestLines(string $startDate, string $endDate): Collection
    {
        $rows = DB::table('lc_overdraft_bank_statements as s')
            ->join('letter_of_credit_facilities as f', 'f.id', '=', 's.lc_facility_id')
            ->where('s.company_id', $this->companyId)
            ->whereBetween('s.date', [$startDate, $endDate])
            ->where(function (Builder $query) {
                $query->where(fn (Builder $q) => $q->where('s.type', 'interest')->where('s.interest_type', 'end_of_month'))
                    ->orWhere('s.type', 'highest_debit_balance');
            })
            ->where('s.credit', '!=', 0)
            ->tap(fn (Builder $query) => $this->whereBank($query, 'f.financial_institution_id'))
            ->get(['s.date', 's.type', 's.credit', 's.comment_en', 's.comment_ar', 'f.id as facility_id', 'f.name as facility_name', 'f.financial_institution_id', 'f.currency']);

        return $rows->map(function ($row) {
            $isInterest = $row->type === 'interest';

            return $this->line(
                date: $row->date,
                bankId: (int) $row->financial_institution_id,
                facilityType: AccountType::LETTER_OF_CREDIT_LCS,
                facilityTypeLabel: __('Letter Of Credit'),
                facilityKey: 'lc|facility:'.$row->facility_id,
                facilityLabel: $row->facility_name ?: __('N/A'),
                category: $isInterest ? self::INTEREST : self::COMMISSION,
                item: $isInterest ? __('LC Overdraft Interest') : __('Highest Debit Balance Commission'),
                currency: $row->currency,
                amount: (float) $row->credit,
                comment: $this->comment($row),
            );
        });
    }

    /**
     * Interest paid when an LC is settled. handleInterestSettlement() only
     * posts it to the LC overdraft, or to the paying account when that
     * account is a current account — anything else was never charged
     * anywhere, so it is not counted here either.
     */
    private function lcSettlementInterestLines(string $startDate, string $endDate): Collection
    {
        $currentAccountTypeId = AccountType::where('slug', AccountType::CURRENT_ACCOUNT)->value('id');

        $rows = DB::table('lc_settlement_internal_money_transfers as t')
            ->join('letter_of_credit_issuances as i', 'i.id', '=', 't.to_letter_of_credit_issuance_id')
            ->leftJoin('letter_of_credit_facilities as f', 'f.id', '=', 'i.lc_facility_id')
            ->where('t.company_id', $this->companyId)
            ->whereBetween('t.transfer_date', [$startDate, $endDate])
            ->where('t.interest_amount', '>', 0)
            ->where(function (Builder $query) use ($currentAccountTypeId) {
                $query->where('t.interest_destination', LcSettlementInternalMoneyTransfer::INTEREST_TO_LC_OVERDRAFT)
                    ->orWhere(fn (Builder $q) => $q
                        ->where('t.interest_destination', LcSettlementInternalMoneyTransfer::INTEREST_TO_CURRENT_ACCOUNT)
                        ->where('t.from_account_type_id', $currentAccountTypeId));
            })
            ->tap(fn (Builder $query) => $this->whereBank($query, 'i.financial_institution_id'))
            ->get(['t.transfer_date', 't.interest_amount', 't.currency', 'i.financial_institution_id', 'i.source', 'i.transaction_name', 'i.lc_facility_id as facility_id', 'f.name as facility_name']);

        $sources = LetterOfCreditIssuance::lcSources();

        return $rows->map(fn ($row) => $this->line(
            date: $row->transfer_date,
            bankId: (int) $row->financial_institution_id,
            facilityType: AccountType::LETTER_OF_CREDIT_LCS,
            facilityTypeLabel: __('Letter Of Credit'),
            facilityKey: $this->instrumentFacilityKey('lc', $row),
            facilityLabel: $row->facility_id ? ($row->facility_name ?: __('N/A')) : ($sources[$row->source ?: LetterOfCreditIssuance::LC_FACILITY] ?? __('N/A')),
            category: self::INTEREST,
            item: __('LC Settlement Interest'),
            currency: $row->currency,
            amount: (float) $row->interest_amount,
            comment: $row->transaction_name,
        ));
    }

    private function lcExpenseLines(string $startDate, string $endDate): Collection
    {
        $rows = DB::table('lc_issuance_expenses as e')
            ->join('letter_of_credit_issuances as i', 'i.id', '=', 'e.lc_issuance_id')
            ->leftJoin('letter_of_credit_facilities as f', 'f.id', '=', 'i.lc_facility_id')
            ->where('e.company_id', $this->companyId)
            ->whereBetween('e.date', [$startDate, $endDate])
            ->where('e.amount', '>', 0)
            ->tap(fn (Builder $query) => $this->whereBank($query, 'i.financial_institution_id'))
            ->get(['e.date', 'e.amount', 'e.currency', 'e.expense_name', 'i.financial_institution_id', 'i.source', 'i.transaction_name', 'i.lc_facility_id as facility_id', 'f.name as facility_name']);

        $sources = LetterOfCreditIssuance::lcSources();

        return $rows->map(fn ($row) => $this->line(
            date: $row->date,
            bankId: (int) $row->financial_institution_id,
            facilityType: AccountType::LETTER_OF_CREDIT_LCS,
            facilityTypeLabel: __('Letter Of Credit'),
            facilityKey: $this->instrumentFacilityKey('lc', $row),
            facilityLabel: $row->facility_id ? ($row->facility_name ?: __('N/A')) : ($sources[$row->source ?: LetterOfCreditIssuance::LC_FACILITY] ?? __('N/A')),
            category: self::CHARGES,
            item: $row->expense_name ?: __('LC Expenses'),
            currency: $row->currency,
            amount: (float) $row->amount,
            comment: $row->transaction_name,
        ));
    }

    /** Cash expenses paid by outgoing transfer and marked as bank charges. */
    private function bankChargeLines(string $startDate, string $endDate): Collection
    {
        $rows = DB::table('outgoing_transfers as o')
            ->join('cash_expenses as c', 'c.id', '=', 'o.cash_expense_id')
            ->leftJoin('cash_expense_category_names as n', 'n.id', '=', 'c.cash_expense_category_name_id')
            ->leftJoin('account_types as t', 't.id', '=', 'o.account_type')
            ->where('c.company_id', $this->companyId)
            ->where('o.is_bank_charges', 1)
            ->whereBetween('c.payment_date', [$startDate, $endDate])
            ->where('c.paid_amount', '>', 0)
            ->tap(fn (Builder $query) => $this->whereBank($query, 'o.delivery_bank_id'))
            ->get(['c.payment_date', 'c.paid_amount', 'c.currency', 'c.comment_en', 'c.comment_ar', 'n.name as category_name', 'o.delivery_bank_id', 'o.account_number', 't.slug as account_type_slug']);

        $shareholderAccounts = $this->canViewShareholderAccounts ? [] : FinancialInstitutionAccount::query()
            ->where('company_id', $this->companyId)
            ->onlyShareholderOwned()
            ->get(['financial_institution_id', 'account_number'])
            ->mapWithKeys(fn ($account) => [$account->financial_institution_id.'|'.$account->account_number => true])
            ->all();

        $types = self::facilityTypes();

        return $rows
            ->reject(fn ($row) => isset($shareholderAccounts[$row->delivery_bank_id.'|'.$row->account_number]))
            ->map(function ($row) use ($types) {
                $slug = isset($types[$row->account_type_slug]) ? $row->account_type_slug : AccountType::CURRENT_ACCOUNT;

                return $this->line(
                    date: $row->payment_date,
                    bankId: (int) $row->delivery_bank_id,
                    facilityType: $slug,
                    facilityTypeLabel: $types[$slug],
                    facilityKey: $slug.'|'.$row->delivery_bank_id.'|'.$row->account_number,
                    facilityLabel: (string) $row->account_number,
                    category: self::CHARGES,
                    item: $row->category_name ? __('Bank Charges').' — '.$row->category_name : __('Bank Charges'),
                    currency: $row->currency,
                    amount: (float) $row->paid_amount,
                    comment: $this->comment($row),
                );
            })
            ->values();
    }

    /* ───────────────────────── helpers ───────────────────────── */

    private function line(
        string $date,
        int $bankId,
        string $facilityType,
        string $facilityTypeLabel,
        string $facilityKey,
        string $facilityLabel,
        string $category,
        string $item,
        ?string $currency,
        float $amount,
        ?string $comment,
    ): array {
        return [
            'date' => substr($date, 0, 10),
            'bank_id' => $bankId,
            'bank_name' => $this->bankNames[$bankId] ?? __('N/A'),
            'facility_type' => $facilityType,
            'facility_type_label' => $facilityTypeLabel,
            // Bank first: two banks can carry the same account number.
            'facility_key' => $bankId.'#'.$facilityKey,
            'facility_label' => $facilityLabel !== '' ? $facilityLabel : __('N/A'),
            'category' => $category,
            'category_label' => self::categories()[$category],
            'item' => $item,
            'currency' => strtoupper(trim((string) $currency)),
            'amount' => round($amount, 2),
            'comment' => $comment,
        ];
    }

    /** An LG / LC with no facility behind it groups by its source at that bank. */
    private function instrumentFacilityKey(string $prefix, object $row): string
    {
        return $row->facility_id
            ? $prefix.'|facility:'.$row->facility_id
            : $prefix.'|source:'.($row->source ?: 'none');
    }

    private function loanKey(object $row): string
    {
        return AccountType::MEDIUM_TERM_LOAN.'|'.$row->financial_institution_id.'|'.$row->account_number;
    }

    private function loanLabel(object $row): string
    {
        return $row->name ? $row->name.' ('.$row->account_number.')' : (string) $row->account_number;
    }

    private function comment(object $row): ?string
    {
        return app()->getLocale() === 'ar'
            ? ($row->comment_ar ?: $row->comment_en)
            : ($row->comment_en ?: $row->comment_ar);
    }

    private function whereBank(Builder $query, string $column): void
    {
        if ($this->bankIds !== []) {
            $query->whereIn($column, $this->bankIds);
        }
    }

    /** Same rule as HasShareholderOwnership::scopeOnlyCompanyOwned(). */
    private function whereCompanyOwned(Builder $query, string $alias): void
    {
        if ($this->canViewShareholderAccounts) {
            return;
        }

        $query->where(function (Builder $builder) use ($alias) {
            $builder->where($alias.'.is_shareholder_account', 0)
                ->orWhereNull($alias.'.shareholder_partner_id');
        });
    }

    /**
     * Adds exchange_rate / amount_main / amount_display to every line.
     *
     * @return array{0: Collection, 1: array<int, string>} lines, and the currencies that had no rate
     */
    private function convert(Collection $lines, bool $isConverted, string $mainCurrency): array
    {
        $rates = $isConverted
            ? ForeignExchangeRate::where('company_id', $this->companyId)->where('to_currency', $mainCurrency)->get()
            : collect();
        $missing = [];

        $lines = $lines->map(function (array $line) use ($isConverted, $mainCurrency, $rates, &$missing) {
            $rate = 1.0;

            if ($isConverted && $line['currency'] !== $mainCurrency) {
                // getExchangeRateAt() falls back to 1 when there is no rate;
                // that silently books a dollar as a pound, so say so.
                $hasRate = $rates->where('from_currency', $line['currency'])->where('date', '<=', $line['date'])->isNotEmpty();
                if (! $hasRate) {
                    $missing[$line['currency']] = true;
                }
                $rate = (float) ForeignExchangeRate::getExchangeRateAt($line['currency'], $mainCurrency, $line['date'], $this->companyId, $rates);
            }

            $line['exchange_rate'] = $rate;
            $line['amount_main'] = round($line['amount'] * $rate, 2);
            $line['amount_display'] = $isConverted ? $line['amount_main'] : $line['amount'];

            return $line;
        });

        $missing = array_keys($missing);
        sort($missing);

        return [$lines, $missing];
    }

    /**
     * Bank → facility totals, in the display currency, largest first.
     *
     * @return array{0: array, 1: array}
     */
    private function summarize(Collection $lines): array
    {
        $totalsOf = function (Collection $group): array {
            $totals = [];
            foreach (array_keys(self::categories()) as $category) {
                $totals[$category] = round((float) $group->where('category', $category)->sum('amount_display'), 2);
            }
            $totals['total'] = round(array_sum($totals), 2);

            return $totals;
        };

        $banks = $lines
            ->groupBy('bank_id')
            ->map(function (Collection $bankLines) use ($totalsOf) {
                $facilities = $bankLines
                    ->groupBy('facility_key')
                    ->map(function (Collection $facilityLines) use ($totalsOf) {
                        $first = $facilityLines->first();

                        return [
                            'key' => $first['facility_key'],
                            'facility_type' => $first['facility_type'],
                            'facility_type_label' => $first['facility_type_label'],
                            'facility_label' => $first['facility_label'],
                            'currencies' => $facilityLines->pluck('currency')->unique()->sort()->values()->all(),
                            'line_count' => $facilityLines->count(),
                        ] + $totalsOf($facilityLines);
                    })
                    ->sortByDesc('total')
                    ->values()
                    ->all();

                return [
                    'bank_id' => $bankLines->first()['bank_id'],
                    'bank_name' => $bankLines->first()['bank_name'],
                    'facilities' => $facilities,
                ] + $totalsOf($bankLines);
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $grand = $totalsOf($lines);
        $share = fn (float $value) => $grand['total'] != 0 ? round($value / $grand['total'] * 100, 1) : 0.0;

        $kpis = [
            'interest' => $grand[self::INTEREST],
            'commission' => $grand[self::COMMISSION],
            'charges' => $grand[self::CHARGES],
            'total' => $grand['total'],
            'interestShare' => $share($grand[self::INTEREST]),
            'commissionShare' => $share($grand[self::COMMISSION]),
            'chargesShare' => $share($grand[self::CHARGES]),
            'lineCount' => $lines->count(),
            'bankCount' => count($banks),
            'facilityCount' => (int) collect($banks)->sum(fn (array $bank) => count($bank['facilities'])),
        ];

        return [['banks' => $banks, 'grand' => $grand], $kpis];
    }
}
