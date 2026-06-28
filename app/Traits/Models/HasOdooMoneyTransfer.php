<?php
namespace App\Traits\Models;

use App\Models\Branch;
use App\Models\BuyOrSellCurrency;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialInstitution;
use App\Models\InternalMoneyTransfer;
use App\Services\Api\InternalMoneyTransfer as OdooInternalMoneyTransfer;

trait HasOdooMoneyTransfer
{
    
    private function storeOdoo(string $userComment, float $exchangeRate , Company $company, string $date, int $outBankOdooId, int $outJournalId, int $inJournalId, int $inBankOdooId, float $amountInCurrency, string $currencyName, $isBreakDeposit= false,$secondCurrency = null , $amountToBuy = null)
    {
        
		$isInternalMoneyTransfer = is_null($secondCurrency) || is_null($amountToBuy) ;
	
        $odooCurrencyId = Currency::getOdooId($currencyName);
        $mainFunctionalCurrency =$company->getMainFunctionalCurrency();
		$secondCurrency = $isInternalMoneyTransfer ? $mainFunctionalCurrency : $secondCurrency;
		$isBuyOrSellWithTwoForeignCurrencies= !$isInternalMoneyTransfer && ($secondCurrency != $mainFunctionalCurrency && $currencyName != $mainFunctionalCurrency ); 
        $receiveOdooCurrencyId = $isInternalMoneyTransfer ? $odooCurrencyId  : Currency::getOdooId($secondCurrency);
        $amountInMainFunctionalCurrency = $currencyName != $secondCurrency  ? $amountInCurrency *  $exchangeRate : $amountInCurrency ;
		$amountInMainFunctionalCurrencyInSend = $isBuyOrSellWithTwoForeignCurrencies ? $amountInCurrency * $exchangeRate : $amountInMainFunctionalCurrency;
		$amountInMainFunctionalCurrencyInReceive= $amountInMainFunctionalCurrencyInSend;
		
		
		$receivedOdooAmount =  $isInternalMoneyTransfer ? $amountInCurrency : $amountToBuy;
		$sendMessage = $isInternalMoneyTransfer  ? __('Send - Internal Transfer' ) : __('Sell Currency');
		$receiveMessage = $isInternalMoneyTransfer  ? __('Receive - Internal Transfer' ) : __('Buy Currency');
        $internalMoneyTransferService = (new OdooInternalMoneyTransfer($company));
        $outboundStatementColumnName = 'outbound_account_bank_statement_odoo_id';
        $outboundJournalColumnName = 'outbound_journal_entry_id';
        $inboundStatementColumnName =  'inbound_account_bank_statement_odoo_id';
        $inboundJournalColumnName =  'inbound_journal_entry_id';
        $inboundReferenceColumnName ='inbound_odoo_reference';
        $outboundReferenceColumnName =  'outbound_odoo_reference';
        if ($this->{$outboundJournalColumnName}) {
            $receiveResult = $internalMoneyTransferService->unlink($this->{$outboundJournalColumnName});
        }
        $sendMoneyResult = $internalMoneyTransferService->sendMoneyTo($isBreakDeposit,$date, $amountInCurrency, $amountInMainFunctionalCurrencyInSend, $odooCurrencyId, $outJournalId, $outBankOdooId,$sendMessage,$userComment);
        $this->{$outboundStatementColumnName} = $sendMoneyResult['account_bank_statement_line_id'] ;
        $this->{$outboundJournalColumnName} = $sendMoneyResult['journal_entry_id'] ;
        $this->{$outboundReferenceColumnName} = $sendMoneyResult['reference'] ;
        /**
         * ! Need To Be Channged
         */
        $this->synced_with_odoo = $sendMoneyResult['synced_with_odoo'];
        $this->odoo_error_message = $sendMoneyResult['odoo_error_message'];
            
            
        if ($this->{$inboundJournalColumnName}) {
            $receiveResult = $internalMoneyTransferService->unlink($this->{$inboundJournalColumnName});
        }
        $receiveResult = $internalMoneyTransferService->storeReceiveMoneyTo($isBreakDeposit,$date, $receivedOdooAmount, $amountInMainFunctionalCurrencyInReceive, $receiveOdooCurrencyId, $inJournalId, $inBankOdooId,$receiveMessage,$userComment);
        $this->{$inboundStatementColumnName} = $receiveResult['account_bank_statement_line_id'] ;
        $this->{$inboundJournalColumnName} = $receiveResult['journal_entry_id'] ;
        $this->{$inboundReferenceColumnName} = $receiveResult['reference'] ;
        /**
         * ! Need To Be Channged
         */
        $this->synced_with_odoo = $receiveResult['synced_with_odoo'];
        $this->odoo_error_message = $receiveResult['odoo_error_message'];

        $this->save();
    }
    
    public function handleOdooTransfer()
    {
        /**
         * @var BuyOrSellCurrency
         */
        $company = $this->company;
        /**
         * @var Company $company
         */
        $transferDate = $this->getTransferDate();
        if ($company->hasOdooIntegrationCredentials() && $company->withinIntegrationDate($transferDate)) {
            $fromAccountTypeId = $this->from_account_type_id;
            $fromAccountNumber = $this->from_account_number;
            $toAccountTypeId = $this->to_account_type_id;
            $toAccountNumber = $this->to_account_number;
			$userComment = $this->getUserComment();
			$isBuyOrSell = $this instanceof BuyOrSellCurrency;
			$exchangeRate = $isBuyOrSell ? $this->getExchangeRate() : 1 ;
            $amountInCurrency = $isBuyOrSell ? $this->getAmountInMainCurrency() :  $this->getAmountInCurrency();
            $currencyName = $isBuyOrSell ? $this->getCurrencyToSell() :  $this->getCurrency();
			$secondCurrency=$isBuyOrSell ? $this->getCurrencyToBuy() : null;
			$amountToBuy=$isBuyOrSell ? $this->getAmountToBuy() : null;
            $fromBranchId = $this->from_branch_id;
            $toBranchId = $this->to_branch_id;
            $type = $this->getType();
            $fromFinancialInstitutionId =$this->from_bank_id;
            $toFinancialInstitutionId=$this->to_bank_id;
            $fromFinancialInstitution = FinancialInstitution::find($fromFinancialInstitutionId);
            $toFinancialInstitution = FinancialInstitution::find($toFinancialInstitutionId);
            if ($type == InternalMoneyTransfer::BANK_TO_BANK) {
                $fromJournalId = $fromFinancialInstitution->getJournalIdForAccount($fromAccountTypeId, $fromAccountNumber);
                $fromOdooId = $fromFinancialInstitution->getOdooIdForAccount($fromAccountTypeId, $fromAccountNumber);
                $toJournalId = $toFinancialInstitution->getJournalIdForAccount($toAccountTypeId, $toAccountNumber);
                $toOdooId = $toFinancialInstitution->getOdooIdForAccount($toAccountTypeId, $toAccountNumber);
                $this->storeOdoo($userComment,$exchangeRate,$company, $transferDate, $fromOdooId, $fromJournalId, $toJournalId, $toOdooId, $amountInCurrency, $currencyName,false,$secondCurrency,$amountToBuy);
            } elseif ($type == InternalMoneyTransfer::BANK_TO_SAFE) {
                $fromOdooId = $fromFinancialInstitution->getOdooIdForAccount($fromAccountTypeId, $fromAccountNumber);
                $fromJournalId = $fromFinancialInstitution->getJournalIdForAccount($fromAccountTypeId, $fromAccountNumber);
                $branch = Branch::find($toBranchId) ;
                $toJournalId = $branch->getJournalId();
                $toOdooId = $branch->getOdooId();
                $this->storeOdoo($userComment,$exchangeRate,$company, $transferDate, $fromOdooId, $fromJournalId,$toJournalId,$toOdooId, $amountInCurrency, $currencyName,false,$secondCurrency,$amountToBuy);
            } elseif ($type == InternalMoneyTransfer::SAFE_TO_BANK) {
                $branch = Branch::find($fromBranchId);
                $fromJournalId = $branch->getJournalId();
                $fromOdooId = $branch->getOdooId();
                $toJournalId = $toFinancialInstitution->getJournalIdForAccount($toAccountTypeId, $toAccountNumber);
                $toOdooId = $toFinancialInstitution->getOdooIdForAccount($toAccountTypeId, $toAccountNumber);
                $this->storeOdoo($userComment,$exchangeRate,$company, $transferDate,$fromOdooId, $fromJournalId, $toJournalId,$toOdooId, $amountInCurrency, $currencyName,false,$secondCurrency,$amountToBuy);
                
            } elseif ($type == InternalMoneyTransfer::SAFE_TO_SAFE) {
                $fromBranch = Branch::find($fromBranchId);
                $fromJournalId = $fromBranch->getJournalId();
                $fromOdooId = $fromBranch->getOdooId();
                $toBranch = Branch::find($toBranchId);
                $toJournalId = $toBranch->getJournalId();
                $toOdooId = $toBranch->getOdooId();
                
                $this->storeOdoo($userComment,$exchangeRate,$company,$transferDate,$fromOdooId,$fromJournalId,$toJournalId,$toOdooId,$amountInCurrency,$currencyName,false,$secondCurrency,$amountToBuy);
                
            }
            
        }
        
    }
    
    

}
