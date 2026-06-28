<?php 
namespace App\Interfaces\Models;

use App\Models\CleanOverdraft;
use App\Models\FullySecuredOverdraft;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;

interface IHasDebitOverdraftStatement
{
	public function storeCleanOverdraftDebitBankStatement(string $moneyType , CleanOverdraft $cleanOverdraft , string $date , $debit );
	public function storeFullySecuredOverdraftDebitBankStatement(string $moneyType , FullySecuredOverdraft $fullySecuredOverdraft , string $date , $debit );
	public function storeOverdraftAgainstCommercialPaperDebitBankStatement(string $moneyType , OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper , string $date , $debit );
	public function storeOverdraftAgainstAssignmentOfContractDebitBankStatement(string $moneyType , OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract , string $date , $debit );
}
