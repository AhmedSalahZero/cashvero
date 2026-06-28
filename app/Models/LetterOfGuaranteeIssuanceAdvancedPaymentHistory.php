<?php

namespace App\Models;

use App\Services\Api\LetterOfGuaranteeService;
use App\Traits\HasCompany;
use App\Traits\Models\HasCurrentAccountCreditStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use App\Traits\Models\HasLetterOfGuaranteeCashCoverStatements;
use App\Traits\Models\HasLetterOfGuaranteeStatements;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $journal_entry_id
 * @property string|null $date
 * @property numeric $amount
 * @property int $letter_of_guarantee_issuance_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountBankStatements
 * @property-read int|null $current_account_bank_statements_count
 * @property-read bool|null $current_account_bank_statements_exists
 * @property-read \App\Models\CurrentAccountBankStatement|null $currentAccountCreditBankStatement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountCreditBankStatements
 * @property-read int|null $current_account_credit_bank_statements_count
 * @property-read bool|null $current_account_credit_bank_statements_exists
 * @property-read \App\Models\CurrentAccountBankStatement|null $currentAccountDebitBankStatement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountDebitBankStatements
 * @property-read int|null $current_account_debit_bank_statements_count
 * @property-read bool|null $current_account_debit_bank_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeCashCoverStatement> $letterOfGuaranteeCashCoverStatements
 * @property-read int|null $letter_of_guarantee_cash_cover_statements_count
 * @property-read bool|null $letter_of_guarantee_cash_cover_statements_exists
 * @property-read \App\Models\LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeStatement> $letterOfGuaranteeStatements
 * @property-read int|null $letter_of_guarantee_statements_count
 * @property-read bool|null $letter_of_guarantee_statements_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory whereJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory whereLetterOfGuaranteeIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LetterOfGuaranteeIssuanceAdvancedPaymentHistory extends Model
{
    use HasLetterOfGuaranteeStatements,HasLetterOfGuaranteeCashCoverStatements,HasCurrentAccountCreditStatement,HasDeleteButTriggerChangeOnLastElement,HasCompany;
    protected $table ='lg_issuance_advanced_payment_histories';
    protected $guarded =  [
        'id'
    ];
    public function letterOfGuaranteeIssuance()
    {
        return $this->belongsTo(LetterOfGuaranteeIssuance::class, 'letter_of_guarantee_issuance_id');
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
    public function currentAccountBankStatements():HasMany
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'lg_advanced_payment_history_id', 'id');
    }
    public function letterOfGuaranteeStatements()
    {
        return $this->hasMany(LetterOfGuaranteeStatement::class, 'lg_advanced_payment_history_id', 'id');
    }
    public function letterOfGuaranteeCashCoverStatements()
    {
        return $this->hasMany(LetterOfGuaranteeCashCoverStatement::class, 'lg_advanced_payment_history_id', 'id');
    }
    
    public function currentAccountCreditBankStatement()
    {
        return $this->hasOne(CurrentAccountBankStatement::class, 'lg_advanced_payment_history_id', 'id')->where('is_credit', 1);
    }
    public function currentAccountCreditBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'lg_advanced_payment_history_id', 'id')->where('is_credit', 1)->orderBy('full_date', 'desc');
    }
    
    public function currentAccountDebitBankStatement()
    {
        return $this->hasOne(CurrentAccountBankStatement::class, 'lg_advanced_payment_history_id', 'id')->where('is_debit', 1);
    }
    public function currentAccountDebitBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'lg_advanced_payment_history_id', 'id')->where('is_debit', 1)->orderBy('full_date', 'desc');
    }
	public function deleteOdooRelations()
	{
		foreach (['journal_entry_id'] as $journalColumnName) {
            $currentJournalEntryId = $this->{$journalColumnName};
            if ($currentJournalEntryId) {
                $odooLetterOfGuaranteeIssuance = new LetterOfGuaranteeService($this->company);
                $odooLetterOfGuaranteeIssuance->unlink($currentJournalEntryId);
            }
        }
		
	}
    public function deleteAllRelations():void
    {
        $this->deleteOdooRelations();
        
        LetterOfGuaranteeStatement::deleteButTriggerChangeOnLastElement($this->letterOfGuaranteeStatements);
        LetterOfGuaranteeCashCoverStatement::deleteButTriggerChangeOnLastElement($this->letterOfGuaranteeCashCoverStatements);
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountBankStatements);
    }

}
