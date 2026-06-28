<?php 
namespace App\Traits\Models;


use App\Traits\HasCompany;

trait IsSettlement 
{
	use HasCompany;
	// public function getMoney()
	// {
	// 	if($this instanceof Settlement){
	// 		$id = $this->money_received_id ;
	// 		return MoneyReceived::find($id);			
	// 	}
	// 	if($this instanceof PaymentSettlement){
	// 		$id = $this->money_payment_id;
	// 		return MoneyPayment::find($id);
	// 	}
		
		
	// }
	public function getAmount()
	{
		return $this->settlement_amount ;
	}	
	public function getWithhold()
	{
		return $this->withhold_amount ;
	}		
	public function getWithholdAmount()
	{
		return $this->withhold_amount?:0 ; 
	}
	public function getWithholdAmountFormatted()
	{
		return number_format($this->getWithholdAmount(),0);
	}
	public function getSettlementAmount()
	{
		return $this->settlement_amount?:0 ; 
	}
	public function getSettlementAmountFormatted()
	{
		return number_format($this->getSettlementAmount(),0);
	}
	public function getInvoiceExchangeRate()
	{
		return $this->invoice->getExchangeRate();
	}
	public function getInvoiceNumber()
	{
		return $this->invoice->getInvoiceNumber();
	}
	public function getAmountInReceivingCurrency():float
	{
		return $this->getMoney()->getExchangeRate() * $this->getAmount();
	}
	
	
	
}
