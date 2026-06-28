<?php

namespace App\Models;

use App\Models\Bank;
use App\Models\CertificatesOfDeposit;
use App\Models\CleanOverdraft;
use App\Models\FinancialInstitutionAccount;
use App\Models\OverdraftAgainstCommercialPaper;
use App\Services\Api\OdooService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $type
 * @property string|null $branch_name
 * @property int|null $bank_id
 * @property string|null $name
 * @property string|null $company_account_number
 * @property int|null $company_id
 * @property int|null $created_by
 * @property int $updated_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfCreditFacility> $LetterOfCreditFacilities
 * @property-read int|null $letter_of_credit_facilities_count
 * @property-read bool|null $letter_of_credit_facilities_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeFacility> $LetterOfGuaranteeFacilities
 * @property-read int|null $letter_of_guarantee_facilities_count
 * @property-read bool|null $letter_of_guarantee_facilities_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FinancialInstitutionAccount> $accounts
 * @property-read int|null $accounts_count
 * @property-read bool|null $accounts_exists
 * @property-read \App\Models\Bank|null $bank
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CertificatesOfDeposit> $brokenCertificatesOfDeposits
 * @property-read int|null $broken_certificates_of_deposits_count
 * @property-read bool|null $broken_certificates_of_deposits_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeOfDeposit> $brokenTimeOfDeposits
 * @property-read int|null $broken_time_of_deposits_count
 * @property-read bool|null $broken_time_of_deposits_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CertificatesOfDeposit> $certificatesOfDeposits
 * @property-read int|null $certificates_of_deposits_count
 * @property-read bool|null $certificates_of_deposits_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CleanOverdraft> $cleanOverdrafts
 * @property-read int|null $clean_overdrafts_count
 * @property-read bool|null $clean_overdrafts_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FullySecuredOverdraft> $fullySecuredOverdrafts
 * @property-read int|null $fully_secured_overdrafts_count
 * @property-read bool|null $fully_secured_overdrafts_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfCreditIssuance> $letterOfCreditIssuances
 * @property-read int|null $letter_of_credit_issuances_count
 * @property-read bool|null $letter_of_credit_issuances_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MediumTermLoan> $loans
 * @property-read int|null $loans_count
 * @property-read bool|null $loans_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CertificatesOfDeposit> $maturedCertificatesOfDeposits
 * @property-read int|null $matured_certificates_of_deposits_count
 * @property-read bool|null $matured_certificates_of_deposits_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeOfDeposit> $maturedTimeOfDeposits
 * @property-read int|null $matured_time_of_deposits_count
 * @property-read bool|null $matured_time_of_deposits_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstAssignmentOfContract> $overdraftAgainstAssignmentOfContracts
 * @property-read int|null $overdraft_against_assignment_of_contracts_count
 * @property-read bool|null $overdraft_against_assignment_of_contracts_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstCommercialPaper> $overdraftAgainstCommercialPapers
 * @property-read int|null $overdraft_against_commercial_papers_count
 * @property-read bool|null $overdraft_against_commercial_papers_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CertificatesOfDeposit> $runningCertificatesOfDeposits
 * @property-read int|null $running_certificates_of_deposits_count
 * @property-read bool|null $running_certificates_of_deposits_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeOfDeposit> $runningTimeOfDeposits
 * @property-read int|null $running_time_of_deposits_count
 * @property-read bool|null $running_time_of_deposits_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeOfDeposit> $timeOfDeposits
 * @property-read int|null $time_of_deposits_count
 * @property-read bool|null $time_of_deposits_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyBanks()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyForCompany(int $companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyForSource(string $source)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyHasCleanOverdrafts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyHasFullySecuredOverdrafts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyHasLgFacility()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyHasMediumTermLoans(string $currency)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyHasOverdraftAgainstAssignmentOfContracts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyHasOverdraftAgainstCommercialPapers()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution onlyHasOverdrafts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereBranchName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereCompanyAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitution whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class FinancialInstitution extends Model
{
    protected $guarded = ['id'];

	const BANK = 'bank';
	protected $with = [
		'bank'
	];

	public function scopeOnlyForCompany(Builder $builder , int $companyId){
		return $builder->where('company_id',$companyId);
	}

	public function scopeOnlyForSource(Builder $builder , string $source)
	{
		if($source === LetterOfGuaranteeIssuance::LG_FACILITY){
			return $builder->has('LetterOfGuaranteeFacilities');
		}
		if($source === LetterOfGuaranteeIssuance::AGAINST_CD){
			return $builder->has('certificatesOfDeposits');
		}
		if($source === LetterOfGuaranteeIssuance::AGAINST_TD){
			return $builder->has('timeOfDeposits');
		}
		if($source === LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER ){
			return $builder;
		}
		
		if($source === LetterOfCreditIssuance::LC_FACILITY){
			return $builder->has('LetterOfCreditFacilities');
		}
		
		throw new \Exception('custom exception .. invalid source for financial institution');
	}

	public function scopeOnlyBanks(Builder $builder)
	{
		$builder->where('type',self::BANK);
	}
	public function scopeOnlyHasCleanOverdrafts(Builder $builder){
		$builder
		->has('cleanOverdrafts');
	}
	public function scopeOnlyHasOverdraftAgainstCommercialPapers(Builder $builder){
		$builder
		->has('overdraftAgainstCommercialPapers');
	}
	public function scopeOnlyHasOverdraftAgainstAssignmentOfContracts(Builder $builder){
		$builder
		->has('overdraftAgainstAssignmentOfContracts');
	}
	public function scopeOnlyHasFullySecuredOverdrafts(Builder $builder){
		$builder
		->has('fullySecuredOverdrafts');
	}
	public function scopeOnlyHasOverdrafts(Builder $builder){
		$builder
		->has('cleanOverdrafts')
		->orHas('fullySecuredOverdrafts')
		->orHas('overdraftAgainstCommercialPapers');
	}
	public function scopeOnlyCompany(Builder $query,$companyId){
		return $query->where('company_id',$companyId);
	}
	public function scopeOnlyHasMediumTermLoans(Builder $builder,string $currency){
	
		$builder
		->whereHas('loans',function($builder) use ($currency){
			$builder->where('currency',$currency);
		});
	}
	
	/**
	 * * نوع المؤسسة المالية وليكن مثلا بنك
	 */
	public function getType():string
	{
		return $this->type ;
	}
    public function isBank():bool
    {
        return $this->getType() == self::BANK;
    }
   
	public function getName()
	{
		
		return $this->isBank() ? $this->getBankName() : $this->name ;
	}
	public function getBranchName()
	{
		return $this->branch_name ;
	}
	/**
	 * * هو رقم مميز للحساب الرئيسي زي ال الاي دي وبالتالي هو يختلف عن رقم الحساب نفسه
	 */
	public function getCompanyAccountNumber()
	{
		return $this->company_account_number ;
	}
	/**
	 * * تاريخ المبالغ الماليه اللي معايا في حساباتي في المؤسسة المالية دي
	 */
	// public function getBalanceDate()
	// {
	// 	return $this->balance_date ;
	// }
	// public function getBalanceDateFormatted()
	// {
	// 	$balanceDate = $this->getBalanceDate();
	// 	return $balanceDate ? Carbon::make($balanceDate)->format('d-m-Y') : null;
	// }
	// public function getBankId()
    // {
    //     return $this->bank_id ;
    // }
	public function bank():BelongsTo
	{
		return $this->belongsTo(Bank::class ,'bank_id','id');
	}
	public function getBankName()
	{
		 return $this->bank ? $this->bank->getViewName() : __('N/A');
	}
	// public function getBankNameIn(string $lang)
	// {
	// 	 return $this->bank ? $this->bank['name_'.$lang] : __('N/A');
	// }
	public function accounts():HasMany
	{
		return $this->hasMany(FinancialInstitutionAccount::class,'financial_institution_id','id');
	}

	public function certificatesOfDeposits():HasMany
	{
		return $this->hasMany(CertificatesOfDeposit::class , 'financial_institution_id','id');
	}
	public function timeOfDeposits():HasMany
	{
		return $this->hasMany(TimeOfDeposit::class , 'financial_institution_id','id');
	}
	public function cleanOverdrafts():HasMany
	{
		return $this->hasMany(CleanOverdraft::class , 'financial_institution_id','id');
	}
	public function fullySecuredOverdrafts():HasMany
	{
		return $this->hasMany(FullySecuredOverdraft::class , 'financial_institution_id','id');
	}
	public function overdraftAgainstCommercialPapers():HasMany
	{
		return $this->hasMany(OverdraftAgainstCommercialPaper::class , 'financial_institution_id','id');
	}
	public function overdraftAgainstAssignmentOfContracts():HasMany
	{
		return $this->hasMany(OverdraftAgainstAssignmentOfContract::class , 'financial_institution_id','id');
	}
	/**
	 * * use getCurrentAvailableLetterOfGuaranteeFacility instead
	 */
	public function LetterOfGuaranteeFacilities():HasMany
	{
		return $this->hasMany(LetterOfGuaranteeFacility::class , 'financial_institution_id','id');
	}
	public function scopeOnlyHasLgFacility($builder)
	{
		return $builder->whereHas('LetterOfGuaranteeFacilities',function($builder){
			$builder->where('contract_end_date','>=',now());
		});
	}

	public function LetterOfCreditFacilities():HasMany
	{
		return $this->hasMany(LetterOfCreditFacility::class , 'financial_institution_id','id');
	}
	
	public function storeNewAccounts(array $accounts,Company $company)
	{
		
		foreach($accounts as $index=>$accountArr){
			$balanceAmount = $accountArr['balance_amount'] ?? 0 ;
			$balanceDate = $accountArr['balance_date'];
			$currentBalanceDate = $balanceDate ? Carbon::make($balanceDate)->format('Y-m-d'):null;

			if($currentBalanceDate){
				/**
				 * @var FinancialInstitutionAccount $account
				 */
				$account = $this->accounts()->create([
					'account_number'=>$accountArr['account_number'],
					'odoo_code'=>$odooCode = $accountArr['odoo_code']??null,
					'balance_amount'=>$balanceAmount ,
					'exchange_rate'=>$accountArr['exchange_rate'],
					'currency'=> $accountArr['currency'],
					'iban'=>$accountArr['iban'],
					'balance_date'=>$currentBalanceDate,
					'company_id'=>getCurrentCompanyId(),
				]);
				
				$account->currentAccountBankStatements()->create([
					'company_id'=>getCurrentCompanyId() ,
					'beginning_balance'=>0,
					'is_beginning_balance'=>1 ,
					'debit'=>$balanceAmount,
					'is_debit'=>$isDebit =$balanceAmount >= 0 ,
					'is_credit' => !$isDebit,
					'date'=>$currentBalanceDate ,
					'comment_en'=>__('Beginning Balance',[],'en'),
					'comment_ar'=>__('Beginning Balance',[],'ar'),
				]);
				
				$account->accountInterests()->create([
					'interest_rate'=>$accountArr['interest_rate'],
					'min_balance'=>$accountArr['min_balance'],
					'start_date'=>$currentBalanceDate
				]);
				
				$account->updateBankStatementsFromDate($currentBalanceDate);
				if($company->hasOdooIntegrationCredentials()){
					$odoo = new OdooService($company);
					$odoo->syncFinancialInstitutions($account);
				}
				
				
			//	$endDate = Carbon::make($balanceDate)->addYear(FinancialInstitutionAccount::NUMBER_OF_YEARS_FOR_INTEREST_IN_CURRENT_STATEMENT)->format('Y-m-d');
			//	$account->handleEndOfMonthInterest($balanceDate,$endDate,$company->id);
			}
				
			/**
			 * * لو ال
			 * * balance amount > 0
			 * * هنضفله قيمة في ال
			 * * current account bank Statement
			 */
			// $startDate = isset($accountArr['start_date']) && $accountArr['start_date'] ? Carbon::make($accountArr['start_date'])->format('Y-m-d') : $startDate;
			
			
		}
		
			
		
	}
	
	public function runningCertificatesOfDeposits():HasMany
	{
		return $this->hasMany(CertificatesOfDeposit::class , 'financial_institution_id','id')
		->where('status',CertificatesOfDeposit::RUNNING);
	}
	public function maturedCertificatesOfDeposits():HasMany
	{
		return $this->hasMany(CertificatesOfDeposit::class , 'financial_institution_id','id')
		->where('status',CertificatesOfDeposit::MATURED);
	}
	public function brokenCertificatesOfDeposits():HasMany
	{
		return $this->hasMany(CertificatesOfDeposit::class , 'financial_institution_id','id')
		->where('status',CertificatesOfDeposit::BROKEN);
	}





	public function runningTimeOfDeposits():HasMany
	{
		return $this->hasMany(TimeOfDeposit::class , 'financial_institution_id','id')
		->where('status',TimeOfDeposit::RUNNING);
	}
	public function maturedTimeOfDeposits():HasMany
	{
		return $this->hasMany(TimeOfDeposit::class , 'financial_institution_id','id')
		->where('status',TimeOfDeposit::MATURED);
	}
	public function brokenTimeOfDeposits():HasMany
	{
		return $this->hasMany(TimeOfDeposit::class , 'financial_institution_id','id')
		->where('status',TimeOfDeposit::BROKEN);
	}
	public function letterOfCreditIssuances():HasMany
	{
		return $this->hasMany(LetterOfCreditIssuance::class ,'financial_institution_id','id');
	}	
	public function getAllAccountNumbers():array 
	{
		$currentAccountNumber = $this->accounts->pluck('account_number')->toArray();
		$cleanOverdraftAccount = $this->cleanOverdrafts->pluck('account_number')->toArray();
		$fullySecuredOverdraftAccount = $this->fullySecuredOverdrafts->pluck('account_number')->toArray();
		$overdraftAgainstCommercialPaperAccount = $this->overdraftAgainstCommercialPapers->pluck('account_number')->toArray();
		$overdraftAgainstAssignmentOfContractsAccount = $this->overdraftAgainstAssignmentOfContracts->pluck('account_number')->toArray();
		$certificatesOfDepositsAccount = $this->certificatesOfDeposits->pluck('account_number')->toArray();
		$timeOfDepositsAccount = $this->timeOfDeposits->pluck('account_number')->toArray();
		return array_merge(
			$currentAccountNumber ,
			$cleanOverdraftAccount ,
			$fullySecuredOverdraftAccount,
			$overdraftAgainstCommercialPaperAccount,
			$overdraftAgainstAssignmentOfContractsAccount,
			$certificatesOfDepositsAccount,
			$timeOfDepositsAccount
		) ;
	}
	public function loans():HasMany
	{
		return $this->hasMany(MediumTermLoan::class,'financial_institution_id','id');
	}
	public  function getOpeningBalanceForAccount( int $accountTypeId , string $accountNumber
	// ,string $currencyName
	 ){
		$accountModel = $this->getAccountFromTypeAndNumber($accountTypeId,$accountNumber);
		return $accountModel instanceof FinancialInstitutionAccount ? $accountModel->getOpeningBalanceDate() : $accountModel->getContractStartDate();
	}
	public function getAccountFromTypeAndNumber($accountTypeId,$accountNumber)
	{
		/**
		 * @var AccountType $accountType 
		 */
		$accountType = AccountType::find($accountTypeId);
		$accountTypeModelName = $accountType->getModelName();
		
		$fullModelName = 'App\Models\\'.$accountTypeModelName ;
	
		return  $fullModelName::where([
			['financial_institution_id','=',$this->id],
			['account_number','=',$accountNumber],
			['company_id','=',$this->company_id]
		])->first();
	}
	public  function getOdooPaymentIds( int $accountTypeId , string $accountNumber):array{
		$accountModel = $this->getAccountFromTypeAndNumber($accountTypeId,$accountNumber);
		return [
			'odoo_inbound_transfer_payment_method_id'=>$accountModel->getOdooInboundTransferPaymentMethodId(), // add HasOdooPaymentMethod Trait to CleanOverdraft and so on with the columns migration
			'odoo_outbound_transfer_payment_method_id'=>$accountModel->getOdooOutboundTransferPaymentMethodId(),
			'odoo_inbound_cheque_payment_method_id'=>$accountModel->getOdooInboundChequePaymentMethodId(),
			'odoo_outbound_cheque_payment_method_id'=>$accountModel->getOdooOutboundChequePaymentMethodId()
		];
		// return $accountModel instanceof FinancialInstitutionAccount ? $accountModel->getOpeningBalanceDate() : $accountModel->getContractStartDate();
	}
	public  function getOdooIdForAccount( int $accountTypeId , string $accountNumber){
		$accountModel = $this->getAccountFromTypeAndNumber($accountTypeId,$accountNumber);
		
		return $accountModel->getOdooId();
	}
	
	
	public  function getJournalIdForAccount( int $accountTypeId , string $accountNumber){
		$accountModel = $this->getAccountFromTypeAndNumber($accountTypeId,$accountNumber);
		return  $accountModel->getJournalId();
	}
}
