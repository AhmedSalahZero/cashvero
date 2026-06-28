<?php

namespace App\Models;

use App\Services\Api\LetterOfGuaranteeService;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $renewal_fees_account_bank_statement_odoo_id
 * @property int|null $renewal_fees_journal_entry_id
 * @property int $letter_of_guarantee_issuance_id
 * @property string $renewal_date تاريخ التجديد
 * @property numeric $fees_amount هي عبارة عن المبلغ اللي هيدفعه للبنك علشان يجدد
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $commissionCurrentBankStatements
 * @property-read int|null $commission_current_bank_statements_count
 * @property-read bool|null $commission_current_bank_statements_exists
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory whereFeesAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory whereLetterOfGuaranteeIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory whereRenewalDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory whereRenewalFeesAccountBankStatementOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory whereRenewalFeesJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgRenewalDateHistory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LgRenewalDateHistory extends Model
{
    use HasDeleteButTriggerChangeOnLastElement;

    protected $guarded = [
        'id'
    ];
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }
    public function letterOfGuaranteeIssuance()
    {
        return $this->belongsTo(LetterOfGuaranteeIssuance::class, 'letter_of_guarantee_issuance_id', 'id');
    }
    public function getRenewalDate()
    {
        return $this->renewal_date ;
    }
    public function getRenewalDateFormatted()
    {
        $renewalDate = $this->getRenewalDate() ;
        return $renewalDate ? Carbon::make($renewalDate)->format('d-m-Y') : null   ;
    }
    public function setRenewalDateAttribute($value)
    {
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['renewal_date'] =  $value ;
            return ;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];
        
        $this->attributes['renewal_date'] = $year.'-'.$month.'-'.$day;
    }
    public function getRenewalDateFormattedForDatePicker()
    {
        $date = $this->getRenewalDate();
        return $date ? Carbon::make($date)->format('m/d/Y') : null;
    }
    public function getFeesAmount()
    {
        return $this->fees_amount ;
    }
    public function getFeesAmountFormatted()
    {
        $amount = $this->getFeesAmount();
        return number_format($amount) ;
    }
    public function commissionCurrentBankStatements():HasMany
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'lg_renewal_date_history_id', 'id');
    }
    public function unlinkRenewalFeesForOddo()
    {
        $company = $this->company;
        $odooLetterOfGuaranteeIssuance = new LetterOfGuaranteeService($company);
        if ($journalId = $this->renewal_fees_journal_entry_id) {
            $odooLetterOfGuaranteeIssuance->unlink($journalId);
        }
    }
    public function handleRenewalFeesForOdoo($renewalFeesAmount, $renewalDate)
    {
        $letterOfGuaranteeIssuance = $this->letterOfGuaranteeIssuance;
        $company=  $letterOfGuaranteeIssuance->company;
        if (!$company->hasOdooIntegrationCredentials()) {
            return ;
        }
        if (!$company->withinIntegrationDate($renewalDate)) {
            return ;
        }
        $odooSetting = $company->odooSetting;
        $financialInstitutionAccountForCommissionAndFees = FinancialInstitutionAccount::find($letterOfGuaranteeIssuance->getCommissionFeesAccountId());
        if (is_null($odooSetting)) {
            return ;
        }
        $odooLetterOfGuaranteeIssuance = new LetterOfGuaranteeService($company);
        $fromAccountNumber = $financialInstitutionAccountForCommissionAndFees->getAccountNumber();
        $journalId = $financialInstitutionAccountForCommissionAndFees->financialInstitution->getJournalIdForAccount(27, $fromAccountNumber);
        $accountOdooId = $financialInstitutionAccountForCommissionAndFees->financialInstitution->getOdooIdForAccount(27, $fromAccountNumber);
        $currency = $letterOfGuaranteeIssuance->getLgCurrency();
        $odooCurrencyId = Currency::getOdooId($currency);
        $debitOdooAccountId = $odooSetting->getLetterOfGuaranteeIssuanceFeesId();
        $lgType =$letterOfGuaranteeIssuance->getLgTypeFormatted();
        $ref = $lgType . ' Renewal Fees';
        $message = $ref;
        $analytic_distribution = $letterOfGuaranteeIssuance->formatAnalysisDistribution() ;
        $debitOdooAccountId = $odooSetting->getLetterOfGuaranteeCommissionFeesId();
        $result = $odooLetterOfGuaranteeIssuance->createLgIssuanceCashCover($renewalDate, $renewalFeesAmount, $journalId, $odooCurrencyId, $debitOdooAccountId, $accountOdooId, $letterOfGuaranteeIssuance->getBeneficiaryOdooId(), $ref, $message, $analytic_distribution);
        $this->renewal_fees_account_bank_statement_odoo_id=$result['account_bank_statement_line_id'];
        $this->renewal_fees_journal_entry_id=$result['journal_entry_id'];
        $this->save();
    }
}
