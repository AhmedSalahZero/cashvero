<?php

namespace App\Traits\Models;

use App\Models\FactoringStatement;

trait HandlesFactoringStatement
{
    public function deleteFactoringStatements(): void
    {
        FactoringStatement::where('factoring_transaction_id', $this->id)->delete();
    }

    public function deleteFactoringSettlementStatements(): void
    {
        FactoringStatement::query()
            ->where('factoring_transaction_id', $this->id)
            ->where('entry_type', FactoringStatement::TYPE_FACTORING_SETTLEMENT)
            ->delete();
    }

    public function storeFactoringDisbursementStatement(
        int $companyId,
        int $factoringCompanyId,
        int $factoringContractId,
        string $date,
        float $creditAmount,
        string $currency,
        ?string $commentEn = null,
        ?string $commentAr = null
    ): FactoringStatement {
        return FactoringStatement::create([
            'company_id' => $companyId,
            'factoring_company_id' => $factoringCompanyId,
            'factoring_contract_id' => $factoringContractId,
            'factoring_transaction_id' => $this->id,
            'entry_type' => FactoringStatement::TYPE_FACTORING_DISBURSEMENT,
            'date' => $date,
            'debit' => 0,
            'credit' => $creditAmount,
            'currency' => $currency,
            'comment_en' => $commentEn,
            'comment_ar' => $commentAr,
            'created_by' => auth()->id(),
        ]);
    }

    public function storeFactoringSettlementStatement(
        int $companyId,
        int $factoringCompanyId,
        int $factoringContractId,
        string $date,
        float $debitAmount,
        string $currency,
        ?string $commentEn = null,
        ?string $commentAr = null
    ): FactoringStatement {
        return FactoringStatement::create([
            'company_id' => $companyId,
            'factoring_company_id' => $factoringCompanyId,
            'factoring_contract_id' => $factoringContractId,
            'factoring_transaction_id' => $this->id,
            'entry_type' => FactoringStatement::TYPE_FACTORING_SETTLEMENT,
            'date' => $date,
            'debit' => $debitAmount,
            'credit' => 0,
            'currency' => $currency,
            'comment_en' => $commentEn,
            'comment_ar' => $commentAr,
            'created_by' => auth()->id(),
        ]);
    }
}
