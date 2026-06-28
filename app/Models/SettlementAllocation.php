<?php

namespace App\Models;

use App\Models\LetterOfCreditIssuance;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $contract_code
 * @property string $contract_amount
 * @property int $id
 * @property int|null $invoice_id
 * @property int|null $money_payment_id
 * @property int|null $letter_of_credit_issuance_id
 * @property int|null $contract_id
 * @property int|null $partner_id
 * @property numeric $allocation_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Contract|null $contract
 * @property-read \App\Models\LetterOfCreditIssuance|null $letterOfCreditIssuance
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @property-read \App\Models\SupplierInvoice|null $supplierInvoice
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation whereAllocationAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation whereLetterOfCreditIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SettlementAllocation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SettlementAllocation extends Model
{
	
	protected $guarded = ['id'];
	

	public function moneyPayment()
	{
		return $this->belongsTo(MoneyPayment::class,'money_payment_id','id');
	}
	public function letterOfCreditIssuance():BelongsTo
	{
		return $this->belongsTo(LetterOfCreditIssuance::class,'letter_of_credit_issuance_id','id');
	}
	public function contract():BelongsTo
	{
		return $this->belongsTo(Contract::class,'contract_id','id');
	}
	public function supplierInvoice():BelongsTo
	{
		return $this->belongsTo(SupplierInvoice::class,'invoice_id','id');
	}
	public function getInvoiceNumber()
	{
		return $this->supplierInvoice ? $this->supplierInvoice->getInvoiceNumber() : __('N/A') ;
	}
	public function getAmount()
	{
		return $this->allocation_amount ;
	}

	
	
	public static function getSettlementAllocationPerContractAndLetterOfCreditIssuance(array &$result ,$foreignExchangeRates , $mainFunctionalCurrency ,string $dateFieldName,int $contractId , int $customerId, string $startDate , string $endDate , string $currentWeekYear , int $companyId  ):void
	{
		
		$keyNameForCurrentType = __('Letter Of Credit');
		
		$settlementAllocations  =  self::where('settlement_allocations.contract_id',$contractId)->with(['letterOfCreditIssuance','letterOfCreditIssuance.supplier'])
			->join('letter_of_credit_issuances','settlement_allocations.letter_of_credit_issuance_id','=','letter_of_credit_issuances.id')
			->where('settlement_allocations.partner_id',$customerId)
			->whereBetween($dateFieldName,[$startDate,$endDate])
			->where('letter_of_credit_issuances.company_id',$companyId)
			->get(['settlement_allocations.contract_id','invoice_id','settlement_allocations.letter_of_credit_issuance_id','allocation_amount','payment_currency','payment_date']);
			/**
			 * @var SettlementAllocation $settlementAllocation
			 */
			foreach($settlementAllocations as $settlementAllocation){
				/**
				 * @var LetterOfCreditIssuance $letterOfCreditIssuance
				 */
				$letterOfCreditIssuance = $settlementAllocation->letterOfCreditIssuance;
				$supplier = $letterOfCreditIssuance->supplier ;
				$paymentCurrency = $settlementAllocation->getAttribute('payment_currency') ;
				$paymentDate = $settlementAllocation->getAttribute('payment_date') ;
				$exchangeRate =  $paymentCurrency!= $mainFunctionalCurrency ? ForeignExchangeRate::getExchangeRateAt($paymentCurrency,$mainFunctionalCurrency,$paymentDate,$companyId,$foreignExchangeRates) : 1 ;
				$invoiceId = $settlementAllocation->invoice_id ; 
				/**
				 * @var SupplierInvoice $currentSupplierInvoice
				 */
				$currentSupplierInvoice = SupplierInvoice::find($invoiceId);
				$invoiceNumber = $currentSupplierInvoice->getInvoiceNumber() ; 
				$keyNameForCurrentType = $keyNameForCurrentType.' - '. __('Invoice No') .' ' .$invoiceNumber ;
				$currentAmountAllocationAmount = $settlementAllocation->allocation_amount * $exchangeRate ;
				$supplierName = $supplier->getName();
				$result['suppliers'][$supplierName][$keyNameForCurrentType]['weeks'][$currentWeekYear] = isset($result['suppliers'][$supplierName][$keyNameForCurrentType]['weeks'][$currentWeekYear]) ? $result['suppliers'][$supplierName][$keyNameForCurrentType]['weeks'][$currentWeekYear] + $currentAmountAllocationAmount :  $currentAmountAllocationAmount;
				$result['suppliers'][$supplierName][$keyNameForCurrentType]['total'] = isset($result['suppliers'][$supplierName][$keyNameForCurrentType]['total']) ? $result['suppliers'][$supplierName][$keyNameForCurrentType]['total']  + $currentAmountAllocationAmount : $currentAmountAllocationAmount;
				$currentTotal = $currentAmountAllocationAmount;
				$result['suppliers'][$supplierName]['total'][$currentWeekYear] = isset($result['suppliers'][$supplierName]['total'][$currentWeekYear]) ? $result['suppliers'][$supplierName]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			}
	
	}
	
	
}	
