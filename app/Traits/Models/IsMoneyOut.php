<?php
namespace App\Traits\Models;

use App\Models\Branch;
use App\Models\CashExpense;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialInstitution;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\PayableCheque;
use App\Services\Api\CashExpenseOdooService;
use App\Services\Api\OdooPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * * ال تريت دا مشترك بين
 * * MoneyPayment || CashExpense
 */
trait IsMoneyOut
{

	/**
	 * Fallback for Odoo cheque refs when the parent money model itself is in
	 * $items (no settlements). Settlements override this via IsSettlement.
	 */
	public function getInvoiceNumber(): string
	{
		$chequeNumber = $this->getPayableChequeNumber();
		if ($chequeNumber) {
			return (string) $chequeNumber;
		}

		return (string) $this->id;
	}

/**
     * * This Code Is The Same For Money Payments And Cash Expenses
	 * * So If You Edit Here You Should Edit In CashExpense Model Also
     */
    public function markPayableChequeAsPaidInOdoo()
    {
			
			$actualPaymentDate =  $this->payableCheque->actual_payment_date ;
        
			$company = $this->company;
			$odooPaymentService = new OdooPayment($company);
			$odooSetting = $company->odooSetting;
			$financialInstitution =  $this->payableCheque->deliveryBank ;
			$currency = $this->getCurrency();
			$hasSettlements = $this instanceof MoneyPayment  && $this->settlements->count();
			$items = $hasSettlements ? $this->settlements : [$this];
			$debitAccountOdooId = $odooSetting->getChequesPayableId();
			$odooCurrencyId =Currency::getOdooId($currency);
			$accountTypeId=$this->payableCheque->getAccountTypeId();
			$accountNumber = $this->payableCheque->getAccountNumber();
			$journalId = $financialInstitution->getJournalIdForAccount($accountTypeId, $accountNumber);
			$creditOdooAccountId = $financialInstitution->getOdooIdForAccount($accountTypeId, $accountNumber);
			$odooPartnerId = $this->getPartnerOdooId();
			if ($this->isInvoiceSettlementWithDownPayment()) {
				$items->push($this);
			}
			foreach ($items as $settlementOrMoneyModel) {
				$odooId = $settlementOrMoneyModel->odoo_id ;
				$ref = 'Cheque Payment ' . $settlementOrMoneyModel->getInvoiceNumber();
				/**
				 * * $amountInInvoiceCurrency هو المبلغ بعملة الفاتورة (الأجنبية)
				 * * و $amount هو نفسه مضروب في الـ Rate يعني بعملة الصرف (الجنيه)
				 * * الاتنين لازم يروحوا لأودو عشان القيد يطلع صح من غير تعديل يدوي
				 */
				$amountInInvoiceCurrency = $settlementOrMoneyModel->getAmount();
				$amount= $settlementOrMoneyModel->getAmountInReceivingCurrency();
				$isMoneyPayment  = $settlementOrMoneyModel instanceof MoneyPayment ;
				if ($isMoneyPayment && $this->isInvoiceSettlementWithDownPayment()) {
						$amountInInvoiceCurrency = $this->downPaymentSettlements->sum('down_payment_amount');
						$amount = $amountInInvoiceCurrency * $this->getExchangeRate();

				}
				if ($settlementOrMoneyModel->account_bank_statement_line_id) {
					$odooPaymentService->unlinkBankCollection($settlementOrMoneyModel->account_bank_statement_line_id);
				}
				$res = $odooPaymentService->chequePayment($odooId, $amount, $actualPaymentDate, $odooCurrencyId, $journalId, $debitAccountOdooId, $creditOdooAccountId, $odooPartnerId, $ref, '', $amountInInvoiceCurrency);
				$settlementOrMoneyModel->update([
				'account_bank_statement_line_id'=>$res['statement_entry_id']??null,
					'odoo_reference'=>$res['bank_reference']??null
				]);
					
			}
    }

	/**
	 * Return a paid payable cheque to pending: restore actual_payment_date
	 * and the bank-statement date to the due date (the values used at
	 * create), and unlink the Odoo payment line that mark-as-paid posted.
	 * The original cheque odoo_id is left alone.
	 */
	public function revertPayableChequeToUnpaid(): void
	{
		$this->loadMissing('payableCheque');

		$cheque = $this->payableCheque;
		if (! $cheque || ! $cheque->isPaid()) {
			throw new \RuntimeException('Payable cheque is not paid.');
		}

		$dueDate = Carbon::make($cheque->getDueDate())->format('Y-m-d');
		$currentStatement = $this->getCurrentStatement();

		$cheque->update([
			'status' => PayableCheque::PENDING,
			'actual_payment_date' => $dueDate,
		]);

		$this->unlinkPayableChequePaidOdooLines();

		if ($currentStatement) {
			$currentStatement->handleFullDateAfterDateEdit(
				$dueDate,
				$currentStatement->debit,
				$currentStatement->credit
			);
		}
	}

	/**
	 * Unlink the Odoo bank-statement line written at mark-as-paid.
	 * Opening-balance cheques also store journal_entry_id from
	 * markOpeningPayableChequeAsPaidInOdoo — unlink that too.
	 * Skip the Odoo call entirely when the company has no credentials,
	 * including leftover ids from a disconnected integration.
	 */
	protected function unlinkPayableChequePaidOdooLines(): void
	{
		$company = $this->company;
		if (! $company || ! $company->hasOdooIntegrationCredentials()) {
			return;
		}

		$hasSettlements = $this instanceof MoneyPayment && $this->settlements->count();
		/** @var Collection $items */
		$items = $hasSettlements ? $this->settlements : collect([$this]);
		if ($this instanceof MoneyPayment && $this->isInvoiceSettlementWithDownPayment()) {
			$items->push($this);
		}

		$odooPaymentService = new OdooPayment($company);
		$isOpening = method_exists($this, 'isOpenBalance') && $this->isOpenBalance();

		foreach ($items as $settlementOrMoneyModel) {
			$odooFields = [];

			if ($settlementOrMoneyModel->account_bank_statement_line_id) {
				$odooPaymentService->unlinkBankCollection((int) $settlementOrMoneyModel->account_bank_statement_line_id);
				$odooFields['account_bank_statement_line_id'] = null;
				$odooFields['odoo_reference'] = null;
			}

			if ($isOpening && $settlementOrMoneyModel->journal_entry_id) {
				(new CashExpenseOdooService($company))->unlink((int) $settlementOrMoneyModel->journal_entry_id);
				$odooFields['journal_entry_id'] = null;
			}

			if ($odooFields !== []) {
				$settlementOrMoneyModel->update($odooFields);
			}
		}
	}

}
