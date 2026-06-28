<?php

namespace App\Models;


use App\Traits\Models\HasCurrentAccountCreditStatement;
use App\Traits\Models\HasLetterOfGuaranteeCashCoverStatements;
use App\Traits\Models\HasLetterOfGuaranteeStatements;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $expense_name
 * @property int $company_id
 * @property int $lc_issuance_id
 * @property string $date
 * @property numeric $amount
 * @property string $currency
 * @property numeric $exchange_rate
 * @property numeric $amount_in_main_currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountBankStatements
 * @property-read int|null $current_account_bank_statements_count
 * @property-read bool|null $current_account_bank_statements_exists
 * @property-read \App\Models\CurrentAccountBankStatement|null $currentAccountCreditBankStatement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountCreditBankStatements
 * @property-read int|null $current_account_credit_bank_statements_count
 * @property-read bool|null $current_account_credit_bank_statements_exists
 * @property-read \App\Models\LetterOfCreditIssuance|null $letterOfCreditIssuance
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereExpenseName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereLcIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcIssuanceExpense whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LcIssuanceExpense extends Model
{
	use HasCurrentAccountCreditStatement;
	protected $table ='lc_issuance_expenses';
	protected $guarded =  [
		'id'
	];
	protected static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {
			$model->deleteAllRelations();
        });
    }	
	
	public function letterOfCreditIssuance()
	{
		return $this->belongsTo(LetterOfCreditIssuance::class,'lc_issuance_id');
	}
	public function getDate()
    {
        return $this->date ;
    }
	public function getDateFormatted()
    {
		$date = $this->getDate() ;
        return $date ? Carbon::make($date)->format('d-m-Y') : null   ;
    }
	public function getAmount()
	{
		return $this->amount ?:0 ;
	}
	public function getAmountFormatted()
	{
		return number_format($this->getAmount()) ;
	}	
	public function getName()
	{
		return $this->expense_name ;
	}
	public function currentAccountBankStatements():HasMany
	{
		return $this->hasMany(CurrentAccountBankStatement::class,'lc_issuance_expense_id','id');
	}	
	public function currentAccountCreditBankStatement()
	{
		return $this->hasOne(CurrentAccountBankStatement::class,'lc_issuance_expense_id','id')->where('is_credit',1);
	}
	public function currentAccountCreditBankStatements()
	{
		return $this->hasMany(CurrentAccountBankStatement::class,'lc_issuance_expense_id','id')->where('is_credit',1)->orderBy('full_date','desc');
	}
	public function deleteAllRelations()
	{
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountCreditBankStatements);
		
	}
	public function getCurrency()
	{
		return $this->currency;
	}
	public function getExchangeRate()
	{
		return $this->exchange_rate ?: 1 ;
	}
	
}
