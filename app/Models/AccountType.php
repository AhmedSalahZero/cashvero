<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * * هي عباره عن انواع الحسابات البنكية وليكن مثلا ال
 * * debit  (فلوس ليا عند البنك)-> current , time deposit , certificate of deposit الحساب الجاري الحساب الودايع حساب الشهادات
 * * credit التسهيلات البنكية (فلوس عليا) ->
 *
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $slug
 * @property string|null $model_name
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyAgainstAssignmentOfContract()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyCashAccounts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyCashCoverAccounts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyCdAccounts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyCdOrTdAccounts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyCleanOverdraft()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyCurrentAccount()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyFullySecuredOverdraft()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyOverdraftAgainstAssignmentOfContract()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyOverdraftAgainstCommercialPaper()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyOverdraftsAccounts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlySlugs(array $slugs)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType onlyTdAccounts()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType whereModelName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType whereNameAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType whereNameEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AccountType extends Model
{
	CONST CURRENT_ACCOUNT = 'current-account';
	CONST FULLY_SECURED_OVERDRAFT = 'fully-secured-overdraft';
	CONST CLEAN_OVERDRAFT = 'clean-overdraft';
	CONST OVERDRAFT_AGAINST_COMMERCIAL_PAPER = 'overdraft-against-commercial-paper';
	CONST OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACTS= 'overdraft-against-assignment-of-contracts';
	const LETTER_OF_CREDIT_LCS = 'letter-of-credit-lcs';
	CONST CERTIFICATE_OF_DEPOSIT= 'certificate-of-deposit-cd';
	CONST TIME_OF_DEPOSIT= 'time-of-deposit-td';
	CONST  OVERDRAFT_ACCOUNT_SLUGS = [self::FULLY_SECURED_OVERDRAFT,self::CLEAN_OVERDRAFT,self::OVERDRAFT_AGAINST_COMMERCIAL_PAPER,self::OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACTS];
	
	protected $guarded =[
		'id'
	];
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyCashAccounts(Builder $builder)
	{
		return $builder->onlySlugs([self::CURRENT_ACCOUNT,self::FULLY_SECURED_OVERDRAFT,self::CLEAN_OVERDRAFT,self::OVERDRAFT_AGAINST_COMMERCIAL_PAPER,self::OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACTS]);
	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyCashCoverAccounts(Builder $builder)
	{
		return $builder->onlySlugs([self::CURRENT_ACCOUNT,self::CERTIFICATE_OF_DEPOSIT,self::TIME_OF_DEPOSIT]);
	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyOverdraftsAccounts(Builder $builder)
	{
		return $builder->onlySlugs(self::OVERDRAFT_ACCOUNT_SLUGS);
	}
	public function isOverdraftAccount()
	{
		return in_array($this->getSlug() , SELF::OVERDRAFT_ACCOUNT_SLUGS);
 	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyCurrentAccount(Builder $builder)
	{
		return $builder->onlySlugs([self::CURRENT_ACCOUNT]);
	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
    public function scopeOnlyCdOrTdAccounts(Builder $builder)
	{
		return $builder->onlySlugs([self::CERTIFICATE_OF_DEPOSIT,self::TIME_OF_DEPOSIT]);
	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyCleanOverdraft(Builder $builder)
	{
		return $builder->onlySlugs([self::CLEAN_OVERDRAFT]);
	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyFullySecuredOverdraft(Builder $builder)
	{
		return $builder->onlySlugs([self::FULLY_SECURED_OVERDRAFT]);
	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyOverdraftAgainstCommercialPaper(Builder $builder)
	{
		return $builder->onlySlugs([self::OVERDRAFT_AGAINST_COMMERCIAL_PAPER]);
	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyOverdraftAgainstAssignmentOfContract(Builder $builder)
	{
		return $builder->onlySlugs([self::OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACTS]);
	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyAgainstAssignmentOfContract(Builder $builder)
	{
		return $builder->onlySlugs([self::OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACTS]);
	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyCdAccounts(Builder $builder)
	{
		return $builder->onlySlugs([self::CERTIFICATE_OF_DEPOSIT]);
	}
	/**
 * @param Builder<self> $builder
 * @return Builder<self>
 */
	public function scopeOnlyTdAccounts(Builder $builder)
	{
		return $builder->onlySlugs([self::TIME_OF_DEPOSIT]);
	}
	public function isCdOrTdAccount()
	{
		return in_array($this->slug , [self::CERTIFICATE_OF_DEPOSIT , self::TIME_OF_DEPOSIT]);
	}
	public function isCertificateOfDeposit()
	{
		return in_array($this->slug , [self::CERTIFICATE_OF_DEPOSIT ]);
	}
	public function isTimeOfDeposit()
	{
		return in_array($this->slug , [self::TIME_OF_DEPOSIT ]);
	}
	public function isCleanOverdraftAccount():bool
	{
		return $this->slug === self::CLEAN_OVERDRAFT ;
	}
	public function isFullySecuredOverdraftAccount():bool
	{
		return $this->slug === self::FULLY_SECURED_OVERDRAFT ;
	}
	public function isOverdraftAgainstCommercialPaperAccount():bool
	{
		return $this->slug === self::OVERDRAFT_AGAINST_COMMERCIAL_PAPER ;
	}
	public function isOverdraftAgainstAssignmentOfContractAccount():bool
	{
		return $this->slug === self::OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACTS ;
	}
	public function isCurrentAccount():bool
	{
		return $this->slug === self::CURRENT_ACCOUNT ;
	}
	public function getModelName()
	{
		return $this->model_name;
	}
	public function getSlug()
	{
		return $this->slug ;
	}

	public function scopeOnlySlugs(Builder $builder , array $slugs)
	{
		return $builder->whereIn('slug',$slugs);
	}
	public function getId()
	{
		return $this->id ;
	}
	public function getName($lang = null)
	{
		$lang = is_null($lang) ? app()->getLocale() : $lang;
		return $this['name_'.$lang];
	}



}
