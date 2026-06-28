<?php

namespace App;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $cheques_receivable_code
 * @property int $cheques_receivable_id
 * @property string $liquidity_transfer_account_code
 * @property int $liquidity_transfer_account_id
 * @property string $cheques_payable_code
 * @property int $cheques_payable_id
 * @property string $bid_lg_cash_cover_code
 * @property int $bid_lg_cash_cover_id
 * @property string $final_lg_cash_cover_code
 * @property int $final_lg_cash_cover_id
 * @property string $advanced_lg_cash_cover_code
 * @property int $advanced_lg_cash_cover_id
 * @property string $performance_lg_cash_cover_code
 * @property int $performance_lg_cash_cover_id
 * @property string $sight_lc_cash_cover_code
 * @property int $sight_lc_cash_cover_id
 * @property string $deferred_lc_cash_cover_code
 * @property int $deferred_lc_cash_cover_id
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $interest_revenue_code
 * @property int $interest_revenue_id
 * @property string|null $custody_account_code
 * @property string|null $custody_account_id
 * @property string $employee_loans_account_code
 * @property int $employee_loans_account_id
 * @property string $dividend_payable_account_code
 * @property int $dividend_payable_account_id
 * @property string $shareholder_account_code
 * @property int $shareholder_account_id
 * @property string $insurance_from_account_code
 * @property string $insurance_from_account_id
 * @property string $insurance_to_account_code
 * @property string $insurance_to_account_id
 * @property int|null $letter_of_guarantee_commission_fees_code
 * @property int|null $letter_of_guarantee_commission_fees_id
 * @property int|null $letter_of_guarantee_issuance_fees_code
 * @property int|null $letter_of_guarantee_issuance_fees_id
 * @property int|null $letter_of_credit_commission_fees_code
 * @property int|null $letter_of_credit_commission_fees_id
 * @property int|null $letter_of_credit_other_fees_code
 * @property int|null $letter_of_credit_other_fees_id
 * @property int|null $fully_secured_overdraft_interest_expense_code
 * @property int|null $fully_secured_overdraft_interest_expense_id
 * @property int|null $clean_overdraft_interest_expense_code
 * @property int|null $clean_overdraft_interest_expense_id
 * @property int|null $overdraft_against_commercial_paper_interest_expense_code
 * @property int|null $overdraft_against_commercial_paper_interest_expense_id
 * @property int|null $overdraft_against_contract_assignment_interest_expense_code
 * @property int|null $overdraft_against_contract_assignment_interest_expense_id
 * @property int|null $medium_term_loan_interest_expense_code
 * @property int|null $medium_term_loan_interest_expense_id
 * @property int|null $vat_taxes_code
 * @property int|null $vat_taxes_id
 * @property int|null $credit_withhold_taxes_code
 * @property int|null $credit_withhold_taxes_id
 * @property int|null $salary_taxes_code
 * @property int|null $salary_taxes_id
 * @property int|null $social_insurance_code
 * @property int|null $social_insurance_id
 * @property int|null $income_taxes_code
 * @property int|null $income_taxes_id
 * @property int|null $real_estate_taxes_code
 * @property int|null $real_estate_taxes_id
 * @property int|null $stamp_duty_taxes_code
 * @property int|null $stamp_duty_taxes_id
 * @property int|null $other_taxes_code
 * @property int|null $other_taxes_id
 * @property int|null $takaful_code
 * @property int|null $takaful_id
 * @property int|null $tax_for_victims_code
 * @property int|null $tax_for_victims_id
 * @property string|null $advances_to_suppliers_code
 * @property string|null $advances_to_suppliers_id
 * @property string|null $advances_from_customers_code
 * @property string|null $advances_from_customers_id
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereAdvancedLgCashCoverCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereAdvancedLgCashCoverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereAdvancesFromCustomersCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereAdvancesFromCustomersId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereAdvancesToSuppliersCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereAdvancesToSuppliersId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereBidLgCashCoverCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereBidLgCashCoverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereChequesPayableCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereChequesPayableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereChequesReceivableCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereChequesReceivableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereCleanOverdraftInterestExpenseCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereCleanOverdraftInterestExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereCreditWithholdTaxesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereCreditWithholdTaxesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereCustodyAccountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereCustodyAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereDeferredLcCashCoverCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereDeferredLcCashCoverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereDividendPayableAccountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereDividendPayableAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereEmployeeLoansAccountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereEmployeeLoansAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereFinalLgCashCoverCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereFinalLgCashCoverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereFullySecuredOverdraftInterestExpenseCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereFullySecuredOverdraftInterestExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereIncomeTaxesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereIncomeTaxesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereInsuranceFromAccountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereInsuranceFromAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereInsuranceToAccountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereInsuranceToAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereInterestRevenueCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereInterestRevenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereLetterOfCreditCommissionFeesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereLetterOfCreditCommissionFeesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereLetterOfCreditOtherFeesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereLetterOfCreditOtherFeesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereLetterOfGuaranteeCommissionFeesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereLetterOfGuaranteeCommissionFeesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereLetterOfGuaranteeIssuanceFeesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereLetterOfGuaranteeIssuanceFeesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereLiquidityTransferAccountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereLiquidityTransferAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereMediumTermLoanInterestExpenseCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereMediumTermLoanInterestExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereOtherTaxesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereOtherTaxesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereOverdraftAgainstCommercialPaperInterestExpenseCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereOverdraftAgainstCommercialPaperInterestExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereOverdraftAgainstContractAssignmentInterestExpenseCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereOverdraftAgainstContractAssignmentInterestExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting wherePerformanceLgCashCoverCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting wherePerformanceLgCashCoverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereRealEstateTaxesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereRealEstateTaxesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereSalaryTaxesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereSalaryTaxesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereShareholderAccountCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereShareholderAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereSightLcCashCoverCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereSightLcCashCoverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereSocialInsuranceCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereSocialInsuranceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereStampDutyTaxesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereStampDutyTaxesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereTakafulCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereTakafulId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereTaxForVictimsCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereTaxForVictimsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereVatTaxesCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OdooSetting whereVatTaxesId($value)
 * @mixin \Eloquent
 */
class OdooSetting extends Model
{
    protected $guarded = ['id'];
	
	public function getId()
	{
		return $this->id;
	}
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}
	public  function getLiquidityAccountOdooId():int
	{
		return $this->liquidity_transfer_account_id; 
	}
	public function getChequesReceivableCode()
	{
		return $this->cheques_receivable_code;
	}
	public function getChequesReceivableId()
	{
		return $this->cheques_receivable_id;
	}
		public function getChequesPayableCode()
	{
		return $this->cheques_payable_code;
	}
	public function getChequesPayableId()
	{
		return $this->cheques_payable_id;
	}
	// public function getLgCashCoverCode()
	// {
	// 	return $this->lg_cash_cover_code;
	// }
	// public function getLgCashCoverId()
	// {
	// 	return $this->lg_cash_cover_id;
	// }
	// public function getLcCashCoverCode()
	// {
	// 	return $this->lc_cash_cover_code;
	// }
	// public function getLcCashCoverId()
	// {
	// 	return $this->lc_cash_cover_id;
	// }
	// public static function getSuspenseAccountId():int
	// {
	// 	return OdooSetting::where('company_id',getCurrentCompanyId())->first()->suspense_account_id ; 
	// }
	public function getInterestRevenueOdooId()
	{
		return $this->interest_revenue_id;
	}
	public function getCustodyAccountId():int 
	{
		if(!$this->custody_account_id){
			throw new \Exception('Custody Account Not Found .. Please Add It From Other Odoo Setting Form');
		}
		return  (int) $this->custody_account_id;
	}
	public function getEmployeeLoanAccountId():int 
	{
		if(!$this->employee_loans_account_id){
			throw new \Exception('Employee Loan Account Not Found .. Please Add It From Other Odoo Setting Form');
		}
		return  (int) $this->employee_loans_account_id;
	}
	public function getShareholderAccount():int 
	{
		if(!$this->shareholder_account_id){
			throw new \Exception('Shareholder Account Not Found .. Please Add It From Other Odoo Setting Form');
		}
		return  (int) $this->shareholder_account_id;
	}
	// public function getSisterCompanyAccount():int 
	// {
	// 	if(is_null($this->sister_company_account_id)){
	// 		throw new \Exception('Sister Company Account Not Found .. Please Add It From Other Odoo Setting Form');
	// 	}
	// 	return  $this->sister_company_account_id;
	// }
	public function getDividendPaymentAccount():int 
	{
		if(!$this->dividend_payable_account_id){
			throw new \Exception('Dividend Account Not Found .. Please Add It From Other Odoo Setting Form');
		}
		return  (int) $this->dividend_payable_account_id;
	}
	public function getInsuranceFromAccount():int 
	{
		if(!$this->insurance_from_account_id){
			throw new \Exception('Insurance From Account Not Found .. Please Add It From Other Odoo Setting Form');
		}
		return  (int) $this->insurance_from_account_id;
	}
	public function getInsuranceToAccount():int 
	{
		if(!$this->insurance_to_account_id){
			throw new \Exception('Insurance To Account Not Found .. Please Add It From Other Odoo Setting Form');
		}
		return  (int) $this->insurance_to_account_id;
	}
	public function getLetterOfGuaranteeIssuanceFeesId():int 
	{
		if(!$this->letter_of_guarantee_issuance_fees_id){
			throw new \Exception('Letter Of Guarantee Issuance Fees Id Not Found .. Please Add It From Other Odoo Setting Form');
		}
		return  (int) $this->letter_of_guarantee_issuance_fees_id;
	}
	public function getLetterOfGuaranteeCommissionFeesId():int 
	{
		if(!$this->letter_of_guarantee_commission_fees_id){
			throw new \Exception('Letter Of Guarantee Issuance Fees Id Not Found .. Please Add It From Other Odoo Setting Form');
		}
		return  (int) $this->letter_of_guarantee_commission_fees_id;
	}

	
}
