<?php
namespace App\Services\Api;

use App\Models\Company;
use App\Models\Currency;
use App\Services\Api\Traits\AuthTrait;
use App\Services\Api\Traits\HasJournal;
use App\Services\Api\Traits\HasPayment;
use App\Services\Api\Traits\HasUnlinkAccountBankStatementLine;
use Exception;
use Illuminate\Support\Facades\Log;

class OdooPayment
{
    use AuthTrait,HasPayment,HasJournal,HasUnlinkAccountBankStatementLine ;
    
    public function createDownPayment($moneyModel)
    {
        try {
            $company = $moneyModel->company ;
            $paymentDate = $moneyModel->getReceivingOrPaymentMoneyDate();
            if (!$company->withinIntegrationDate($paymentDate)) {
                return ;
            }
            $journalId = $this->getJournalId($moneyModel) ;
        
            /**
             * * $bankOrSafeId
             */
            $paymentAmount = $moneyModel->isInvoiceSettlementWithDownPayment() ? $moneyModel->downPaymentSettlements->sum('down_payment_amount') : $moneyModel->getAmount()  ;
            
            if ($moneyModel->isChequeAndNotCustomerOrSupplier()) {
                $paymentAmount=$moneyModel->getAmount();
            }
            $currencyName = $moneyModel->getReceivingOrPaymentCurrency();
            $odooCurrencyId = Currency::getOdooId($currencyName);
            
            /**
             * @var Company $company;
             */
            
            $odooPartnerId = $moneyModel->partner ? $moneyModel->partner->getOdooId() : null;
            $inBoundOrOutBound =$moneyModel->getInboundOrOutbound();
            $customerOrSupplier = $moneyModel->getCustomerOrSupplier();


            // Step 2: Register payment using account.payment.register
            $context = [
                'active_model' => 'account.move',
                   'active_ids' => [],
            ];

            $paymentData = [
                'amount' => $paymentAmount,
                'journal_id' => $journalId,
                'date' => $paymentDate,
                'currency_id'=>$odooCurrencyId,
                'partner_id' => $odooPartnerId,
                'payment_type' => $inBoundOrOutBound,
                'partner_type' => $customerOrSupplier ,
                'payment_method_line_id'=>(int)$moneyModel->getPaymentMethodLineId(),
                'memo'=>$moneyModel->generateDownPaymentMessage(),
            ];

            /**
             * * الشيكات للشركاء اللي مش عميل/مورد (موظف - مساهم - شركة تابعة - جهة ضريبية ...)
             * * كانت بتتبعت من غير ما نقول لأودو الحساب المقابل، فأودو كان بياخد حساب
             * * الدائنين/المدينين الافتراضي بتاع الشريك والقيد ما بيوصلش لحساب العملية
             * * (السلفة - العهدة - التمويل - الاستثمار ... إلخ) لا عند الإصدار ولا عند الصرف
             * * destination_account_id بيخلي أودو يقيّد على حساب العملية مباشرةً:
             * *   صرف : من ح/ العملية    إلي ح/ أوراق الدفع
             * *   قبض : من ح/ أوراق القبض إلي ح/ العملية
             * * والطرف التاني (أوراق الدفع/القبض) بييجي من الـ payment method زي ما هو
             * * فالتسوية وقت صرف الشيك في markPayableChequeAsPaidInOdoo فضلت شغالة زي ما هي
             */
            if ($moneyModel->isChequeAndNotCustomerOrSupplier()) {
                $odooIdWithRef = $moneyModel->getOdooIdWithRefOfTransaction();
                if (!empty($odooIdWithRef['id'])) {
                    $paymentData['destination_account_id'] = (int) $odooIdWithRef['id'];
                    $paymentData['memo'] = $odooIdWithRef['ref'] ?: $paymentData['memo'];
                }
            }

            $paymentId = $this->models->execute_kw(
                $this->db,
                $this->uid,
                $this->password,
                'account.payment',
                'create',
                [$paymentData],
                ['context' => $context]
            );
            
            
            $this->models->execute_kw(
                $this->db,
                $this->uid,
                $this->password,
                'account.payment',
                'action_post',
                [[$paymentId]],
            );
    
            if (is_array($paymentId) && isset($paymentId['faultString'])) {
                session()->put('fail', $paymentId['faultString']);
                $moneyModel->update([
                    'synced_with_odoo'=>false ,
                    'odoo_error_message'=>$paymentId['faultString']
                ]);
                return ;
            }
            $odooAccountPayment = $this->fetchData('account.payment', [], [[['id','=',$paymentId]]]);
            $moneyModel->update([
                'odoo_id'=>$paymentId,
				'odoo_move_id'=>$odooAccountPayment[0]['move_id'][0]??null,
                'odoo_reference'=>$odooAccountPayment[0]['name']??null,
                'synced_with_odoo'=>true ,
                'odoo_error_message'=>null
            ]);
        } catch (\Exception $e) {
            session()->put('fail', __('Error While Connecting With Odoo : ' . $e->getMessage()));
            $moneyModel->update([
                'synced_with_odoo'=>false ,
                'odoo_error_message'=>$e->getMessage()
            ]);
        }
        

         
    }
    
    public function createDownPaymentFromSettlement($settlement)
    {
    
    
        try {
		
            $company = $settlement->company ;
            $moneyModel =  $settlement->getMoney() ;
            $paymentDate =$moneyModel->getReceivingOrPaymentMoneyDate();
            if (!$company->withinIntegrationDate($paymentDate)) {
                return ;
            }
            $journalId = $this->getJournalId($moneyModel) ;
            /**
             * * $bankOrSafeId
             */
            $paymentAmount = $settlement->getAmountInReceivingCurrency()   ;
            $currencyName = $moneyModel->getReceivingOrPaymentCurrency();
            $odooCurrencyId = Currency::getOdooId($currencyName);
            
            /**
             * @var Company $company;
             */
            
            $odooPartnerId = $moneyModel->partner->getOdooId();
            $inBoundOrOutBound =$moneyModel->getInboundOrOutbound();
            $customerOrSupplier = $moneyModel->getCustomerOrSupplier();
    
       
            // Step 2: Register payment using account.payment.register
            $context = [
                'active_model' => 'account.move',
                   'active_ids' => [],
            ];

            $paymentId = $this->models->execute_kw(
                $this->db,
                $this->uid,
                $this->password,
                'account.payment',
                'create',
                [[
                    'amount' => $paymentAmount,
                    'journal_id' => $journalId,
                    'date' => $paymentDate,
                    'currency_id'=>$odooCurrencyId,
                    'partner_id' => $odooPartnerId,
                    'payment_type' => $inBoundOrOutBound,
                    'partner_type' => $customerOrSupplier ,
                    'payment_method_line_id'=>(int)$moneyModel->getPaymentMethodLineId()
                ]],
                ['context' => $context]
            );
            
            
            $this->models->execute_kw(
                $this->db,
                $this->uid,
                $this->password,
                'account.payment',
                'action_post',
                [[$paymentId]],
            );
            if (is_array($paymentId) && isset($paymentId['faultString'])) {
                session()->put('fail', $paymentId['faultString']);
                $moneyModel->update([
                    'synced_with_odoo'=>false ,
                    'odoo_error_message'=>$paymentId['faultString']
                ]);
                return ;
            }
            $odooAccountPayment = $this->fetchData('account.payment', [], [[['id','=',$paymentId]]]);
            $moneyModel->update([
                'synced_with_odoo'=>true ,
                'odoo_error_message'=>null
            ]);
            $settlement->update([
                'odoo_id'=>$paymentId,
                'odoo_reference_name'=>$odooAccountPayment[0]['name']??null,
				'odoo_move_id'=>$odooAccountPayment[0]['move_id'][0]??null,
            ]);
                
        } catch (\Exception $e) {
            session()->put('fail', __('Error While Connecting With Odoo : ' . $e->getMessage()));
            $moneyModel->update([
                'synced_with_odoo'=>false ,
                'odoo_error_message'=>$e->getMessage()
            ]);
        }
        

         
    }
   
	
	public function createPayment($customerInvoiceSettlement)
    {
        
        
            
        $invoice = $customerInvoiceSettlement->invoice;
		if(!$invoice){
			Log::error('Invoice not found for customer invoice settlement: ' . $customerInvoiceSettlement->id);
			return ;
		}
        $moneyModel = $customerInvoiceSettlement->getMoney();
        $amountInInReceivingCurrency = $customerInvoiceSettlement->getAmountInReceivingCurrency();
        if ($invoice->opening_balance_id) {
            return $this->createDownPaymentFromSettlement($customerInvoiceSettlement);
        }
        $journalId = $this->getJournalId($moneyModel) ;
        /**
         * * $bankOrSafeId
         */
        $invoiceId = $invoice->getOdooId();
        $receivingCurrencyName = $moneyModel->getReceivingOrPaymentCurrency();
        $odooReceivingCurrencyId =  Currency::getOdooId($receivingCurrencyName) ;
        $paymentDate = $moneyModel->getReceivingOrPaymentMoneyDate();
        if (!$this->company->withinIntegrationDate($paymentDate)) {
            return ;
        }
        $odooPartnerId = $moneyModel->partner->getOdooId();
        $invoiceNumber = $invoice->getInvoiceNumber();
        $inBoundOrOutBound =$moneyModel->getInboundOrOutbound();
        $customerOrSupplier = $moneyModel->getCustomerOrSupplier();
    
       
        $context = [
            'active_model' => 'account.move',
            'active_ids' => [$invoiceId],
		];
            //  $paymentWizardData['manual_currency_exchange_rate'] = (float)$manualExchangeRate;
        $paymentWizardId = $this->models->execute_kw(
            $this->db,
            $this->uid,
            $this->password,
            'account.payment.register',
            'create',
            [[
                'amount' => $amountInInReceivingCurrency,
                'currency_id'=>$odooReceivingCurrencyId,
                'journal_id' => $journalId,
                'payment_date' => $paymentDate,
                'communication' => $invoiceNumber,
                'partner_id' => $odooPartnerId,
                'payment_type' => $inBoundOrOutBound,
                'partner_type' => $customerOrSupplier ,
                 'payment_method_line_id'=>$moneyModel->getPaymentMethodLineId(),
				 
            ]],
            ['context' => $context]
        );
            
        $paymentResult = $this->models->execute_kw(
            $this->db,
            $this->uid,
            $this->password,
            'account.payment.register',
            'action_create_payments',
            [[$paymentWizardId]],
            ['context' => $context]
        );
        if (is_array($paymentResult) && isset($paymentResult['faultString'])) {
            session()->put('fail', $paymentResult['faultString']);
            $moneyModel->update([
                'synced_with_odoo'=>false ,
                'odoo_error_message'=>$paymentResult['faultString']
            ]);
            return ;
        }
        $resId = $paymentResult['res_id'];
        if (is_numeric($resId)) {
            $odooAccountPayment = $this->fetchData('account.payment', ['id','name'], [[['id','=',$resId]]]);
            $moneyModel->update([
            'synced_with_odoo'=>true ,
            'odoo_error_message'=>null
            ]);
            $customerInvoiceSettlement->update([
                'odoo_reference_name'=>$odooAccountPayment[0]['name']??null,
                'odoo_id'=>$resId,
				'odoo_move_id'=>$odooAccountPayment[0]['move_id'][0]??null,
            ]);
			
			
			$this->models->execute_kw(
				$this->db,
				$this->uid,
				$this->password,
				'account.payment',
				'write',
				[
					[$resId],
					['ref' => $moneyModel->getUserComment()]
				]
			);
			logger('dddd');
            
            
    
            return [
                'odoo_id'=>$resId
            ];
            
        }
            
            
       
    }
    
    public function reCreatePayment($customerInvoiceSettlement)
    {
        if ($customerInvoiceSettlement->odoo_id) {
            $this->cancelPayments($customerInvoiceSettlement->odoo_id);
        }
        $this->createPayment($customerInvoiceSettlement);

    }
    
    public function reCreateDownPayment($moneyModel)
    {
        
        if ($moneyModel->odoo_id) {
            $this->cancelPayments($moneyModel->odoo_id);
        }
        
        $this->createDownPayment($moneyModel);

    }
    
   
    
    
    /**
     * * $amount هو المبلغ اللي دخل البنك فعلاً (بعملة التحصيل - الجنيه غالبًا)
     * * و $amountInEntryCurrency هو نفس المبلغ لكن بعملة الفاتورة الأجنبية
     * * قبل كده كنا بنبعت الاتنين كرقم واحد من غير amount_currency خالص
     * * فأودو كان بيحسب قيمة العملة الأجنبية بسعره هو مش بالـ Rate اللي المستخدم كتبه
     * * وكان لازم حد يفتح القيد في أودو ويظبط الرقم بإيده
     */
    public function chequeCollection(
        int $accountPayment_id,
        float $amount,
        string $date,
        int $currency_id,
        int $journal_id, // NBE Journal
        int $debitOdooAccountId, // Misr Account
        int $creditOdooAccountId, // Cheque Receivable Account
        int $PartnerId,
        $ref,
        $message = '',
        ?float $amountInEntryCurrency = null
    ) {
        /**
         * * amount_currency بيتبعت بس لو المستدعي حدّدها صراحةً
         * * لو مبعتهاش بنسيب أودو يحسبها زي الأول بالظبط — ده مقصود
         * * عشان جهة التحصيل (اللي قيدها متسق أصلاً) ما يتغيّرش سلوكها
         */
        $foreignAmountLine = $amountInEntryCurrency
            ? ['amount_currency' => abs($amountInEntryCurrency)]
            : [];
        $foreignAmountCounterLine = $amountInEntryCurrency
            ? ['amount_currency' => -abs($amountInEntryCurrency)]
            : [];

        
        try {
            // Step 1: Verify the payment exists and get its details
            $paymentData = $this->execute(
                'account.payment',
                'read',
                [[$accountPayment_id], ['state', 'move_id', 'reconciled_invoice_ids', 'is_matched']],
                []
            );
            if (!$paymentData || !is_array($paymentData) ) {
                throw new Exception("Payment ID $accountPayment_id not found or invalid response");
            }

            $paymentData = $paymentData[0]; // Access first element safely
            $paymentState = $paymentData['state'];
            $invoiceIds = $paymentData['reconciled_invoice_ids'] ?? [];
            $moveId = $paymentData['move_id'] ? $paymentData['move_id'][0] : null;
            $isMatched = $paymentData['is_matched'] ?? false;

            if (!in_array($paymentState, ['draft', 'posted', 'in_process'])) {
                throw new Exception("Payment ID $accountPayment_id is in state '$paymentState' and cannot be processed");
            }
            
            // Step 2: If payment is in draft, post it
            if ($paymentState === 'draft') {
                $this->execute(
                    'account.payment',
                    'action_post',
                    [[$accountPayment_id]],
                    []
                );
                $paymentState = 'posted';
            }


            // Step 3: Check if payment is already linked to a bank statement
            $existingStatementLines = $this->execute(
                'account.bank.statement.line',
                'search',
                [[['payment_ids', 'in', [$accountPayment_id]]]],
                []
            );



            $statementEntryId = null;
            $statementMoveId = null;
            $statementLineIds = [];

        
            if (empty($existingStatementLines)) {
                // Step 4: Create bank statement line to affect bank balance
                $statementEntryData = [
                    'journal_id' => $journal_id,
                    'amount' => $amount, // Positive for bank deposit
                    'date' => $date,
					'payment_ref'=>__('Cheque Received'),
                    'ref' => $ref,
                    'partner_id' => $PartnerId,
                    'payment_ids' => [[6, 0, [$accountPayment_id]]], // Link payment using payment_ids
                    'name' => $message ,
                    'is_reconciled' => true,
                    'line_ids' => [
                        [0, 0, array_merge([
                            'account_id' => $debitOdooAccountId,
                            'debit' => abs($amount),
                            'credit' => 0.0,
                            'currency_id' => $currency_id,
                            'name' => $message,
                            'partner_id' => $PartnerId,

                        ], $foreignAmountLine)],

                        [0, 0, array_merge([
                            'account_id' => $creditOdooAccountId,
                            'debit' => 0.0,
                            'credit' => abs($amount),
                            'currency_id' => $currency_id,
                            'name' => $message,
                          'partner_id' => $PartnerId,

                        ], $foreignAmountCounterLine)],
                    ],

                ];

                 

                $context = [
                    'check_move_validity' => true,
                ];

                $statementEntryId = $this->execute(
                    'account.bank.statement.line',
                    'create',
                    [$statementEntryData],
                    ['context' => $context]
                );
            
      
                if (!is_numeric($statementEntryId)) {
                    throw new Exception("Failed to create bank statement line: " . json_encode($statementEntryId));
                }



                // Step 5: Get the move_id and line_ids from the bank statement line
                $statementData = $this->execute(
                    'account.bank.statement.line',
                    'read',
                    [[$statementEntryId], ['move_id', 'line_ids']],
                    []
                );



                if (!is_array($statementData) || empty($statementData) || !isset($statementData[0]['move_id'])) {
                    throw new Exception("Failed to retrieve move_id for statement entry: $moveId, response: " . json_encode($statementData));
                }

                $statementMoveId = $statementData[0]['move_id'][0];
                $bankReference = $statementData[0]['move_id'][1];
        
                $statementLineIds = $statementData[0]['line_ids'][1] ?? [];



                // Step 6: Reconcile payment and bank statement lines
                $paymentLineIds = $this->execute(
                    'account.move.line',
                    'search',
                    [[['move_id', '=', $moveId], ['account_id', '=', $creditOdooAccountId]]],
                    []
                );

                if (!$paymentLineIds || !is_array($paymentLineIds)) {
                    throw new Exception("Failed to retrieve payment move lines for move_id: $moveId");
                }

                $linesToReconcile = array_merge($paymentLineIds, (array)$statementLineIds);


                try {
                    $result = $this->execute(
                        'account.move.line',
                        'reconcile',
                        [$linesToReconcile],
                        ['context' => ['skip_full_reconcile_check' => true]]
                    );
                    // Handle success
                } catch (Exception $e) {
                    session()->put('fail', $e->getMessage());
                
                    // Log or handle error
                    Log::error('Odoo reconciliation failed: ' . $e->getMessage());
                }
                
            }

       
            // Step 7: Update payment to set is_matched to true if not already
            if (!$isMatched) {
                $matching  = $this->execute(
                    'account.payment',
                    'write',
                    [[$accountPayment_id], ['is_matched' => true]],
                    []
                );
            }

            // Step 8: Verify invoice state is 'paid'
            if (!empty($invoiceIds)) {
                $invoiceState = $this->execute(
                    'account.move',
                    'read',
                    [$invoiceIds, ['state']],
                    []
                );

                foreach ($invoiceState as $invoice) {
                    if ($invoice['state'] !== 'paid') {
                        Log::warning("Invoice ID {$invoice['id']} state is {$invoice['state']} instead of 'paid'");
                    }
                }
            } else {
                Log::warning("No invoices linked to payment ID $accountPayment_id");
            }
    
            return [
                'statement_entry_id' => $statementEntryId,
                'bank_reference'=>$bankReference??null,
                'entry_id' => $statementMoveId,
                'payment_id' => $accountPayment_id,
                'invoice_state' => !empty($invoiceState) ? $invoiceState[0]['state'] : 'unknown',
                'message' => 'Cheque collection processed successfully, payment marked as matched, and invoice set to paid'
            ];

        } catch (\Exception $e) {
            session()->put('fail', 'Error in chequeCollection: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Failed to process cheque collection: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * * نفس فكرة chequeCollection: $amount بعملة الصرف الفعلية (الجنيه)
     * * و $amountInEntryCurrency بعملة الفاتورة الأجنبية عشان أودو
     * * يسجّل القيمة اللي المستخدم حددها مش اللي هو يحسبها بسعره
     */
    public function chequePayment(
        $accountPayment_id,
        float $amount,
        string $date,
        int $currency_id,
        int $journal_id, // Misr Bank Journal
        int $debitOdooAccountId, // Cheque Payable Account
        int $creditOdooAccountId, // Bank Misr Account
        ?int $PartnerId,
        string $ref,
        $message = '',
        ?float $amountInEntryCurrency = null
    ) {
        /**
         * * amount_currency بيتبعت بس لو المستدعي حدّدها صراحةً
         * * لو مبعتهاش بنسيب أودو يحسبها زي الأول بالظبط — ده مقصود
         * * عشان جهة التحصيل (اللي قيدها متسق أصلاً) ما يتغيّرش سلوكها
         */
        $foreignAmountLine = $amountInEntryCurrency
            ? ['amount_currency' => abs($amountInEntryCurrency)]
            : [];
        $foreignAmountCounterLine = $amountInEntryCurrency
            ? ['amount_currency' => -abs($amountInEntryCurrency)]
            : [];
        try {
            // Step 1: Verify the payment exists and get its details
            $paymentData = $this->execute(
                'account.payment',
                'read',
                [[$accountPayment_id], ['state', 'move_id', 'reconciled_invoice_ids', 'is_matched']],
                []
            );


            if (!$paymentData || !is_array($paymentData) ) {
                throw new Exception("Payment ID $accountPayment_id not found or invalid response");
            }

            $paymentData = $paymentData[0]; // Access first element safely
            $paymentState = $paymentData['state'];
            $invoiceIds = $paymentData['reconciled_invoice_ids'] ?? [];
            $moveId = $paymentData['move_id'] ? $paymentData['move_id'][0] : null;
            $isMatched = $paymentData['is_matched'] ?? false;

            if (!in_array($paymentState, ['draft', 'posted', 'in_process'])) {
                throw new Exception("Payment ID $accountPayment_id is in state '$paymentState' and cannot be processed");
            }
        
            // Step 2: If payment is in draft, post it
            if ($paymentState === 'draft') {
                $this->execute(
                    'account.payment',
                    'action_post',
                    [[$accountPayment_id]],
                    []
                );
                $paymentState = 'posted';
            }

            // Step 3: Check if payment is already linked to a bank statement
            $existingStatementLines = $this->execute(
                'account.bank.statement.line',
                'search',
                [[['payment_ids', 'in', [$accountPayment_id]]]],
                []
            );


            $statementEntryId = null;
            $statementMoveId = null;
            $statementLineIds = [];


            
    
            if (empty($existingStatementLines)) {
                // Step 4: Create bank statement line to affect bank balance
                $statementEntryData = [
                    'journal_id' => $journal_id,
                    'amount' => $amount * -1, // Negative for bank payments
                    'date' => $date,
                    'ref' => $ref,
                    'partner_id' => $PartnerId,
					'payment_ref'=>__('Cheque Paid'),
                    'payment_ids' => [[6, 0, [$accountPayment_id]]], // Link payment using payment_ids
                    'name' => $message ,
                    'is_reconciled' => true,
                    
                    'line_ids' => [
                        [0, 0, array_merge([
                            'account_id' => $debitOdooAccountId,
                            'debit' => abs($amount),
                            'credit' => 0.0,
                            'currency_id' => $currency_id,
                            'name' => $message,
                              'partner_id' => $PartnerId,

                        ], $foreignAmountLine)],

                        [0, 0, array_merge([
                            'account_id' => $creditOdooAccountId,
                            'debit' => 0.0,
                            'credit' => abs($amount),
                            'currency_id' => $currency_id,
                            'name' => $message,
                            'partner_id' => $PartnerId,

                        ], $foreignAmountCounterLine)],
                    ],

                ];

                 

                $context = [
                    'check_move_validity' => true,
                ];

                $statementEntryId = $this->execute(
                    'account.bank.statement.line',
                    'create',
                    [$statementEntryData],
                    ['context' => $context]
                );

           
                if (!is_numeric($statementEntryId)) {
                    throw new Exception("Failed to create bank statement line: " . json_encode($statementEntryId));
                }




                // Step 5: Get the move_id and line_ids from the bank statement line
                $statementData = $this->execute(
                    'account.bank.statement.line',
                    'read',
                    [[$statementEntryId], ['move_id', 'line_ids']],
                    []
                );



                if (!is_array($statementData) || empty($statementData) || !isset($statementData[0]['move_id'])) {
                    throw new Exception("Failed to retrieve move_id for statement entry: $moveId, response: " . json_encode($statementData));
                }

                $statementMoveId = $statementData[0]['move_id'][0];
                $statementLineIds = $statementData[0]['line_ids'][0] ?? [];
                $bankReference = $statementData[0]['move_id'][1];


                // Step 6: Reconcile payment and bank statement lines
                $paymentLineIds = $this->execute(
                    'account.move.line',
                    'search',
                    [[['move_id', '=', $moveId], ['account_id', '=', $debitOdooAccountId]]],
                    []
                );


                if (!$paymentLineIds || !is_array($paymentLineIds)) {
                    throw new Exception("Failed to retrieve payment move lines for move_id: $moveId");
                }

                $linesToReconcile = array_merge($paymentLineIds, (array)$statementLineIds);


                try {
                    $result = $this->execute(
                        'account.move.line',
                        'reconcile',
                        [$linesToReconcile],
                        ['context' => ['skip_full_reconcile_check' => true]]
                    );
                    // Handle success
                } catch (Exception $e) {
                    // Log or handle error
                    Log::error('Odoo reconciliation failed: ' . $e->getMessage());
                }
                
            }

       

            // Step 7: Update payment to set is_matched to true if not already
            if (!$isMatched) {
                $this->execute(
                    'account.payment',
                    'write',
                    [[$accountPayment_id], ['is_matched' => true]],
                    []
                );
            }

            // Step 8: Verify invoice state is 'paid'
            if (!empty($invoiceIds)) {
                $invoiceState = $this->execute(
                    'account.move',
                    'read',
                    [$invoiceIds, ['state']],
                    []
                );

                foreach ($invoiceState as $invoice) {
                    if ($invoice['state'] !== 'paid') {
                        Log::warning("Invoice ID {$invoice['id']} state is {$invoice['state']} instead of 'paid'");
                    }
                }
            } else {
                Log::warning("No invoices linked to payment ID $accountPayment_id");
            }

            // Step 9: Return result
            return [
                'bank_reference'=>$bankReference??null,
                'statement_entry_id' => $statementEntryId,
                'entry_id' => $statementMoveId,
                'payment_id' => $accountPayment_id,
                'invoice_state' => !empty($invoiceState) ? $invoiceState[0]['state'] : 'unknown',
                'message' => 'Cheque collection processed successfully, payment marked as matched, and invoice set to paid'
            ];

        } catch (\Exception $e) {
            Log::error('Error in chequeCollection: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Failed to process cheque collection: ' . $e->getMessage()
            ];
        }
    }
    
    public function unlinkBankCollection(int $accountBankStatementId)
    {
        $models = $this->models;
    
        $move_line_ids = $models->execute_kw(
            $this->db,
            $this->uid,
            $this->password,
            'account.bank.statement.line',
            'read',
            [[$accountBankStatementId]],
            ['fields' => ['line_ids']]
        );
        $line_ids = $move_line_ids[0]['line_ids']??[]; // [33352, 33353]

        $models->execute_kw(
            $this->db,
            $this->uid,
            $this->password,
            'account.move.line',
            'remove_move_reconcile',
            [$line_ids]
        );

        // Now unlink works
        $models->execute_kw(
            $this->db,
            $this->uid,
            $this->password,
            'account.bank.statement.line',
            'unlink',
            [[$accountBankStatementId]]
        );
    
    }
	
	
	
	///////////////////////////////////////
	
	
	// public function reconcileDownPaymentWithInvoice($downPaymentMoveId, $invoiceMoveId, $accountType = 'receivable')
    // {
    //     // try {
    //         // Get unreconciled lines from down payment
    //         $downPaymentLines = $this->getAccountMoveLines($downPaymentMoveId, $accountType);
            
    //         // Get unreconciled lines from invoice
    //         $invoiceLines = $this->getAccountMoveLines($invoiceMoveId, $accountType);
		
    //         if (empty($downPaymentLines)) {
    //             throw new \Exception("No unreconciled lines found in down payment");
    //         }

    //         if (empty($invoiceLines)) {
    //             throw new \Exception("No unreconciled lines found in invoice");
    //         }

    //         // Collect line IDs to reconcile
    //         $lineIdsToReconcile = [];
    //         foreach ($downPaymentLines as $line) {
    //             $lineIdsToReconcile[] = $line['id'];
    //         }
    //         foreach ($invoiceLines as $line) {
    //             $lineIdsToReconcile[] = $line['id'];
    //         }
	// 		// [1,2,3]
	// 		// ['amount'=>55 , 'id'=>4]

    //         // Perform reconciliation
    //         $result = $this->execute(
    //             'account.move.line',
    //             'reconcile',
    //             [$lineIdsToReconcile],
    //             []
    //         );

    //         // Log::info('Odoo reconciliation successful', [
    //         //     'down_payment_id' => $downPaymentMoveId,
    //         //     'invoice_id' => $invoiceMoveId,
    //         //     'reconciled_lines' => $lineIdsToReconcile
    //         // ]);

    //         return [
    //             'success' => true,
    //             'message' => 'Reconciliation completed successfully',
    //             'reconciled_line_ids' => $lineIdsToReconcile,
    //             'result' => $result
    //         ];

    //     // } catch (\Exception $e) {
    //     //     Log::error('Odoo reconciliation failed', [
    //     //         'error' => $e->getMessage(),
    //     //         'down_payment_id' => $downPaymentMoveId,
    //     //         'invoice_id' => $invoiceMoveId
    //     //     ]);

    //     //     return [
    //     //         'success' => false,
    //     //         'message' => $e->getMessage()
    //     //     ];
    //     // }
    // }

   


 
	
//  public function getAccountMoveLines($moveId, $accountType = 'receivable')
// {
//     $accountInternalType = $accountType === 'receivable' 
//         ? 'asset_receivable' 
//         : 'liability_payable';

//     $move = $this->execute(
//         'account.move',
//         'search_read',
//         [[['id', '=', $moveId]]],
//         ['fields' => ['line_ids', 'partner_id']]
//     );

//     if (empty($move)) {
//         throw new \Exception("Move ID {$moveId} not found");
//     }

//     $lineIds = $move[0]['line_ids'];

//     $lines = $this->execute(
//         'account.move.line',
//         'search_read',
//         [[
//             ['id', 'in', $lineIds],
//             ['account_id.account_type', '=', $accountInternalType],
//             ['reconciled', '=', false]
//         ]],
//         ['fields' => ['id', 'debit', 'credit', 'amount_residual', 'account_id', 'name']]
//     );
//     return $lines;
// }



	

	
	
	
	
	
	
	//// partial
	
	
	
	
  
		
// 	public function partialReconcile($advanceMoveId, $invoiceMoveId, $amount, $advanceAccountType = 'receivable', $invoiceAccountType = 'payable')
// {
//     try {
//         // Get advance line (asset_receivable for supplier prepayment)
//         $advanceLines = $this->getAccountMoveLines($advanceMoveId, $advanceAccountType);
        
//         if (empty($advanceLines)) {
//             throw new \Exception("No unreconciled advance lines found");
//         }

//         // Get invoice line (liability_payable for supplier invoice)
//         $invoiceLines = $this->getAccountMoveLines($invoiceMoveId, $invoiceAccountType);
        
//         if (empty($invoiceLines)) {
//             throw new \Exception("No unreconciled invoice lines found");
//         }

//         $advanceLine = $advanceLines[0];
//         $invoiceLine = $invoiceLines[0];

//         // Validate amounts
//         $advanceResidual = abs($advanceLine['amount_residual']);
//         $invoiceResidual = abs($invoiceLine['amount_residual']);

//         if ($amount > $advanceResidual) {
//             throw new \Exception("Amount exceeds advance residual ({$advanceResidual})");
//         }

//         if ($amount > $invoiceResidual) {
//             throw new \Exception("Amount exceeds invoice residual ({$invoiceResidual})");
//         }

//         // Perform reconciliation using Odoo 18's reconcile method
//         // In Odoo 18, reconcile() no longer accepts writeoff parameters
//         $lineIds = [$advanceLine['id'], $invoiceLine['id']];
        
//         $result = $this->execute(
//             'account.move.line',
//             'reconcile',
//             [$lineIds],
//             [] // Empty kwargs for Odoo 18
//         );

//         return [
//             'success' => true,
//             'message' => 'Partial reconciliation completed',
//             'partial_reconcile_id' => $result ?? true,
//             'reconciled_amount' => $amount
//         ];

//     } catch (\Exception $e) {
//         return [
//             'success' => false,
//             'message' => $e->getMessage()
//         ];
//     }
// }

	
  
// public function matchDownPaymentToMultipleInvoices($downPaymentMoveId, $invoiceMatches, $accountType = 'receivable')
// {
	
//     try {
//         $results = [];
//         $totalMatched = 0;

//         // Get initial down payment balance
//         // For supplier advance, this should be asset_receivable
//         $downPaymentLines = $this->getAccountMoveLines($downPaymentMoveId, $accountType);

//         if (empty($downPaymentLines)) {
//             throw new \Exception("No unreconciled lines found in down payment");
//         }

//         $availableBalance = abs($downPaymentLines[0]['amount_residual']);

//         // Calculate total amount to match
//         $totalToMatch = array_sum(array_column($invoiceMatches, 'amount'));

//         if ($totalToMatch > $availableBalance) {
//             throw new \Exception("Total amount to match ({$totalToMatch}) exceeds available down payment balance ({$availableBalance})");
//         }

//         // Determine invoice account type (opposite of advance type)
//         $invoiceAccountType = $accountType === 'receivable' ? 'payable' : 'receivable';

//         // Process each invoice match
//         foreach ($invoiceMatches as $match) {
//             $result = $this->partialReconcile(
//                 $downPaymentMoveId,
//                 $match['invoice_id'],
//                 $match['amount'],
//                 $accountType,  // Advance account type
//                 $invoiceAccountType  // Invoice account type
//             );
		
//             if ($result['success']) {
//                 $totalMatched += $match['amount'];
//                 $results[] = [
//                     'invoice_id' => $match['invoice_id'],
//                     'amount' => $match['amount'],
//                     'status' => 'matched',
//                     'partial_reconcile_id' => $result['partial_reconcile_id']
//                 ];
//             } else {
//                 $results[] = [
//                     'invoice_id' => $match['invoice_id'],
//                     'amount' => $match['amount'],
//                     'status' => 'failed',
//                     'error' => $result['message']
//                 ];
//             }
//         }

//         // Get final down payment balance
//         $finalDownPayment = $this->getAccountMoveLines($downPaymentMoveId, $accountType);
//         $remainingBalance = !empty($finalDownPayment) ? abs($finalDownPayment[0]['amount_residual']) : 0;

//         return [
//             'success' => true,
//             'message' => 'Down payment matched to multiple invoices',
//             'total_matched' => $totalMatched,
//             'remaining_balance' => $remainingBalance,
//             'matches' => $results
//         ];

//     } catch (\Exception $e) {
//         Log::error('Multiple invoice matching failed', [
//             'error' => $e->getMessage(),
//             'down_payment_id' => $downPaymentMoveId
//         ]);

//         return [
//             'success' => false,
//             'message' => $e->getMessage()
//         ];
//     }
// }

	

   
    // public function reconcileMultipleDownPayments($downPaymentMoveIds, $invoiceMoveId, $accountType = 'receivable')
    // {
    //     try {
    //         $lineIdsToReconcile = [];

    //         // Collect all down payment lines
    //         foreach ($downPaymentMoveIds as $downPaymentId) {
    //             $downPaymentLines = $this->getAccountMoveLines($downPaymentId, $accountType);
    //             foreach ($downPaymentLines as $line) {
    //                 $lineIdsToReconcile[] = $line['id'];
    //             }
    //         }

    //         // Get invoice lines
    //         $invoiceLines = $this->getAccountMoveLines($invoiceMoveId, $accountType);
    //         foreach ($invoiceLines as $line) {
    //             $lineIdsToReconcile[] = $line['id'];
    //         }

    //         if (count($lineIdsToReconcile) < 2) {
    //             throw new \Exception("Not enough lines to reconcile");
    //         }

    //         // Perform reconciliation
    //         $result = $this->execute(
    //             'account.move.line',
    //             'reconcile',
    //             [$lineIdsToReconcile],
    //             []
    //         );

    //         return [
    //             'success' => true,
    //             'message' => 'Multiple down payments reconciled successfully',
    //             'reconciled_line_ids' => $lineIdsToReconcile,
    //             'result' => $result
    //         ];

    //     } catch (\Exception $e) {
    //         Log::error('Multiple down payments reconciliation failed', [
    //             'error' => $e->getMessage(),
    //             'down_payment_ids' => $downPaymentMoveIds,
    //             'invoice_id' => $invoiceMoveId
    //         ]);

    //         return [
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ];
    //     }
    // }

   
//  public function getReconciliationStatus($moveId)
// {
//     $lines = $this->execute(
//         'account.move.line',
//         'search_read',
//         [[
//             ['move_id', '=', $moveId],
//             ['account_id.account_type', 'in', ['asset_receivable', 'liability_payable']]
//         ]],
//         ['fields' => ['id', 'reconciled', 'full_reconcile_id', 'amount_residual', 'amount_residual_currency', 'matched_debit_ids', 'matched_credit_ids', 'debit', 'credit']]
//     );

//     return $lines;
// }

   
  
	
	
	// Remove reconciliation - متوافق مع Odoo 18 Enterprise
// 	public function removeReconciliation($moveId)
// {
//     try {
//         $lines = $this->getReconciliationStatus($moveId);
        
//         // Get unique full_reconcile_ids from reconciled lines
//         $fullReconcileIds = [];
//         foreach ($lines as $line) {
//             if (!empty($line['full_reconcile_id'])) {
//                 $fullReconcileIds[] = $line['full_reconcile_id'][0];
//             }
//         }
        
//         // Remove duplicates
//         $fullReconcileIds = array_unique($fullReconcileIds);

//         if (empty($fullReconcileIds)) {
//             return [
//                 'success' => false,
//                 'message' => 'No reconciliations found for this move'
//             ];
//         }

//         // Unlink (delete) the reconciliation records
//         $result = $this->execute(
//             'account.full.reconcile',
//             'unlink',
//             [$fullReconcileIds]
//         );
        
//         return [
//             'success' => true,
//             'message' => 'Reconciliation removed successfully',
//             'removed_reconcile_ids' => $fullReconcileIds
//         ];

//     } catch (\Exception $e) {
//         return [
//             'success' => false,
//             'message' => $e->getMessage()
//         ];
//     }
// }


// public function removeReconciliationForMultipleMoves($moveIds)
// {
//     try {
//         $allFullReconcileIds = [];
        
//         foreach ($moveIds as $moveId) {
//             $lines = $this->getReconciliationStatus($moveId);
            
//             foreach ($lines as $line) {
//                 if (!empty($line['full_reconcile_id'])) {
//                     $allFullReconcileIds[] = $line['full_reconcile_id'][0];
//                 }
//             }
//         }
        
//         // Remove duplicates
//         $allFullReconcileIds = array_unique($allFullReconcileIds);

//         if (empty($allFullReconcileIds)) {
//             return [
//                 'success' => false,
//                 'message' => 'No reconciliations found'
//             ];
//         }

//         // Unlink all reconciliations at once
//         $result = $this->execute(
//             'account.full.reconcile',
//             'unlink',
//             [$allFullReconcileIds]
//         );
        
//         return [
//             'success' => true,
//             'message' => 'All reconciliations removed successfully',
//             'removed_reconcile_ids' => $allFullReconcileIds,
//             'count' => count($allFullReconcileIds)
//         ];

//     } catch (\Exception $e) {
//         return [
//             'success' => false,
//             'message' => $e->getMessage()
//         ];
//     }
// }



// Helper method to get account move lines with specific account type
private function getAccountMoveLinesByType($moveId, $accountType, $balanceType = null)
{
    $domain = [
        ['move_id', '=', $moveId],
        ['account_id.account_type', '=', $accountType]
    ];
    
    if ($balanceType === 'debit') {
        $domain[] = ['debit', '>', 0];
    } elseif ($balanceType === 'credit') {
        $domain[] = ['credit', '>', 0];
    }
    
    return $this->execute(
        'account.move.line',
        'search_read',
        [$domain],
        ['fields' => ['id', 'account_id', 'debit', 'credit', 'partner_id']]
    );
}

// Create journal entry to transfer advance - يعمل للعملاء والموردين - Odoo 18
public function transferAdvanceToReceivableOrPayable(int $odooCurrencyId , float $amountInMainFunctionalCurrency,float $amountInCurrency,$advanceMoveId, $partnerId, $isCustomer = false, $journalId = null)
{
    try {
        // Get company's default journal if not provided
        if (!$journalId) {
            $journals = $this->execute(
                'account.journal',
                'search_read',
                [[['type', '=', 'general']]],
                ['fields' => ['id'], 'limit' => 1]
            );
            
            if (empty($journals)) {
                throw new \Exception("No general journal found");
            }
            
            $journalId = $journals[0]['id'];
        }

        if ($isCustomer) {
            // ============================================
            // للعملاء - Customer Advance Settlement
            // ============================================
            // الدفعة المقدمة من العميل = التزام (liability_receivable)
            // الفواتير = أصل (asset_receivable)
            
            // Get the customer advance account (liability_receivable - credit balance)
            $advanceLines = $this->getAccountMoveLinesByType(
                $advanceMoveId, 
                'liability_payable',
                'credit'
            );
            if (empty($advanceLines)) {
                throw new \Exception("No customer advance lines found");
            }
            
            $advanceAccountId = $advanceLines[0]['account_id'][0];

            // Get accounts receivable account (asset_receivable)
            $receivableAccounts = $this->execute(
                'account.account',
                'search_read',
                [[['account_type', '=', 'asset_receivable']]],
                ['fields' => ['id'], 'limit' => 1]
            );
            
            if (empty($receivableAccounts)) {
                throw new \Exception("No receivable account found");
            }

            $receivableAccountId = $receivableAccounts[0]['id'];

            // Journal Entry للعملاء:
            // Dr. Customer Advances (يقلل الالتزام)
            // Cr. Accounts Receivable (يقلل المستحق من العميل)
            $moveData = [
                'journal_id' => $journalId,
                'date' => date('Y-m-d'),
                'ref' => 'Transfer Customer Advance to Receivable',
                'line_ids' => [
					//   'debit' => abs($amountInMainFunctionalCurrency),
                    //     'amount_currency' => abs($amountInCurrency),
						
                    [0, 0, [
                        'account_id' => $advanceAccountId,
                        'partner_id' => $partnerId,
                        'name' => 'Transfer from Customer Advance',
                        'debit' => abs($amountInMainFunctionalCurrency),
						'amount_currency' => abs($amountInCurrency),
                        'credit' => 0,
                    ]],
                    [0, 0, [
                        'account_id' => $receivableAccountId,
                        'partner_id' => $partnerId,
                        'name' => 'Transfer to Receivable',
                        'debit' => 0,
                        'credit' => $amountInMainFunctionalCurrency,
						'amount_currency' => -$amountInCurrency,
                    ]]
                ]
            ];
            
        } else {
            // ============================================
            // للموردين - Supplier Advance Settlement
            // ============================================
            // الدفعة المقدمة للمورد = أصل (asset_receivable)
            // الفواتير = التزام (liability_payable)
            
            // Get the supplier advance account (asset_receivable - debit balance)
            $advanceLines = $this->getAccountMoveLinesByType(
                $advanceMoveId,
                'asset_receivable',
                'debit'
            );
            
            if (empty($advanceLines)) {
                throw new \Exception("No supplier advance lines found");
            }

            $advanceAccountId = $advanceLines[0]['account_id'][0];

            // Get accounts payable account (liability_payable)
            $payableAccounts = $this->execute(
                'account.account',
                'search_read',
                [[['account_type', '=', 'liability_payable']]],
                ['fields' => ['id'], 'limit' => 1]
            );
            
            if (empty($payableAccounts)) {
                throw new \Exception("No payable account found");
            }

            $payableAccountId = $payableAccounts[0]['id'];

            // Journal Entry للموردين:
            // Dr. Accounts Payable (يقلل الالتزام)
            // Cr. Advances to Supplier (يقلل الأصل)
            $moveData = [
                'journal_id' => $journalId,
                'date' => date('Y-m-d'),
                'ref' => 'Transfer Supplier Advance to Payable',
				
				//  'line_ids' => [
                //     [0, 0, [
                //         'account_id' => $payableAccountId,
                //         'partner_id' => $partnerId,
                //         'name' => 'Transfer from Advance',
                //         'debit' => $amount,
                //         'credit' => 0,
                //     ]],
                //     [0, 0, [
                //         'account_id' => $advanceAccountId,
                //         'partner_id' => $partnerId,
                //         'name' => 'Transfer to Payable',
                //         'debit' => 0,
                //         'credit' => $amount,
                //     ]]
                // ]
				
                'line_ids' => [
                    [0, 0, [
                        'account_id' => $payableAccountId,
                        'partner_id' => $partnerId,
                        'name' => 'Transfer from Advance',
                        'debit' => abs($amountInMainFunctionalCurrency),
                        'amount_currency' => abs($amountInCurrency),
						'currency_id' => $odooCurrencyId,
                        'credit' => 0,
                    ]],
                    [0, 0, [
                        'account_id' => $advanceAccountId,
                        'partner_id' => $partnerId,
                        'name' => 'Transfer to Payable',
						'currency_id' => $odooCurrencyId,
                        'debit' => 0,
						'amount_currency'=>-$amountInCurrency,
                        'credit' =>abs($amountInMainFunctionalCurrency),
                    ]]
                ]
            ];
        }

        // Create and post the journal entry
        $newMoveId = $this->execute(
            'account.move',
            'create',
            [$moveData]
        );
	
        $this->execute(
            'account.move',
            'action_post',
            [[$newMoveId]]
        );

        return [
            'success' => true,
            'message' => 'Journal entry created successfully',
            'move_id' => $newMoveId,
            'transfer_amount' => $amountInCurrency,
            'is_customer' => $isCustomer
        ];

    } catch (\Exception $e) {
	
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Reconcile the transferred amount with original advance
// public function reconcileAdvanceTransfer($advanceMoveId, $transferMoveId, $isCustomer = false)
// {
//     try {
//         if ($isCustomer) {
//             // للعملاء: نربط الـ credit lines
//             // Original advance: credit في liability_receivable
//             // Transfer: debit في liability_receivable
            
//             $advanceLines = $this->getAccountMoveLinesByType(
//                 $advanceMoveId,
//                 'liability_payable',
//                 'credit'
//             );
            
//             if (empty($advanceLines)) {
//                 throw new \Exception("No customer advance lines found");
//             }

//             $transferMove = $this->execute(
//                 'account.move',
//                 'search_read',
//                 [[['id', '=', $transferMoveId]]],
//                 ['fields' => ['line_ids']]
//             );

//             if (empty($transferMove)) {
//                 throw new \Exception("Transfer move not found");
//             }

//             $transferLines = $this->execute(
//                 'account.move.line',
//                 'search_read',
//                 [[
//                     ['id', 'in', $transferMove[0]['line_ids']],
//                     ['account_id.account_type', '=', 'liability_payable'],
//                     ['debit', '>', 0]
//                 ]],
//                 ['fields' => ['id']]
//             );

//             if (empty($transferLines)) {
//                 throw new \Exception("No transfer debit line found for customer");
//             }

//         } else {
//             // للموردين: نربط الـ debit and credit lines
//             // Original advance: debit في asset_receivable
//             // Transfer: credit في asset_receivable
            
//             $advanceLines = $this->getAccountMoveLinesByType(
//                 $advanceMoveId,
//                 'asset_receivable',
//                 'debit'
//             );
            
//             if (empty($advanceLines)) {
//                 throw new \Exception("No supplier advance lines found");
//             }

//             $transferMove = $this->execute(
//                 'account.move',
//                 'search_read',
//                 [[['id', '=', $transferMoveId]]],
//                 ['fields' => ['line_ids']]
//             );

//             if (empty($transferMove)) {
//                 throw new \Exception("Transfer move not found");
//             }

//             $transferLines = $this->execute(
//                 'account.move.line',
//                 'search_read',
//                 [[
//                     ['id', 'in', $transferMove[0]['line_ids']],
//                     ['account_id.account_type', '=', 'asset_receivable'],
//                     ['credit', '>', 0]
//                 ]],
//                 ['fields' => ['id']]
//             );

//             if (empty($transferLines)) {
//                 throw new \Exception("No transfer credit line found for supplier");
//             }
//         }

//         // Reconcile advance payment with transfer entry
//         $lineIds = [$advanceLines[0]['id'], $transferLines[0]['id']];
        
//         $result = $this->execute(
//             'account.move.line',
//             'reconcile',
//             [$lineIds],
//             []
//         );

//         return [
//             'success' => true,
//             'message' => 'Advance reconciled with transfer',
//             'reconcile_id' => $result,
//             'is_customer' => $isCustomer
//         ];

//     } catch (\Exception $e) {
//         return [
//             'success' => false,
//             'message' => $e->getMessage()
//         ];
//     }
// }

// Reconcile receivable/payable with invoice
public function reconcileWithInvoice($transferMoveId, $invoiceMoveId, $isCustomer = false)
{
    try {
        $transferMove = $this->execute(
            'account.move',
            'search_read',
            [[['id', '=', $transferMoveId]]],
            ['fields' => ['line_ids']]
        );

        if (empty($transferMove)) {
            throw new \Exception("Transfer move not found");
        }

        $transferLineIds = $transferMove[0]['line_ids'];

        if ($isCustomer) {
            // للعملاء: نربط الـ Receivable lines
            // Transfer: credit في asset_receivable
            // Invoice: debit في asset_receivable
            
            $transferReceivableLines = $this->execute(
                'account.move.line',
                'search_read',
                [[
                    ['id', 'in', $transferLineIds],
                    ['account_id.account_type', '=', 'asset_receivable'],
                    ['credit', '>', 0]
                ]],
                ['fields' => ['id']]
            );

            if (empty($transferReceivableLines)) {
                throw new \Exception("No receivable line in transfer");
            }

            // Get invoice receivable line (debit side)
            $invoiceLines = $this->getAccountMoveLinesByType(
                $invoiceMoveId,
                'asset_receivable',
                'debit'
            );
            
        } else {
            // للموردين: نربط الـ Payable lines
            // Transfer: debit في liability_payable
            // Invoice: credit في liability_payable
            
            $transferReceivableLines = $this->execute(
                'account.move.line',
                'search_read',
                [[
                    ['id', 'in', $transferLineIds],
                    ['account_id.account_type', '=', 'liability_payable'],
                    ['debit', '>', 0]
                ]],
                ['fields' => ['id']]
            );

            if (empty($transferReceivableLines)) {
                throw new \Exception("No payable line in transfer");
            }

            // Get invoice payable line (credit side)
            $invoiceLines = $this->getAccountMoveLinesByType(
                $invoiceMoveId,
                'liability_payable',
                'credit'
            );
        }
        
        if (empty($invoiceLines)) {
            throw new \Exception("No invoice lines found");
        }

        // Reconcile
        $lineIds = [$transferReceivableLines[0]['id'], $invoiceLines[0]['id']];
        
        $result = $this->execute(
            'account.move.line',
            'reconcile',
            [$lineIds],
            []
        );

        return [
            'success' => true,
            'message' => 'Invoice reconciled successfully',
            'reconcile_id' => $result,
            'is_customer' => $isCustomer
        ];

    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// public function removeAllReconciliationsFromAdvance($advanceMoveId)
// {
//     try {
//         // جيب كل الأسطر المحاسبية للدفعة المقدمة
//         $lines = $this->getReconciliationStatus($advanceMoveId);
        
//         $lineIdsToUnreconcile = [];
//         $fullReconcileIds = [];
        
//         foreach ($lines as $line) {
//             // Type 1: Full Reconciliation
//             if (!empty($line['full_reconcile_id'])) {
//                 $fullReconcileIds[] = $line['full_reconcile_id'][0];
//             }
            
//             // Type 2 & 3: أي line مربوط (سواء partial أو full)
//             if ($line['reconciled'] === true) {
//                 $lineIdsToUnreconcile[] = $line['id'];
//             }
//         }
        
//         $removedCount = 0;
//         $errors = [];
        
//         // Step 1: حذف Full Reconciliations الأول
//         if (!empty($fullReconcileIds)) {
//             $fullReconcileIds = array_unique($fullReconcileIds);
            
//             try {
//                 $this->execute(
//                     'account.full.reconcile',
//                     'unlink',
//                     [$fullReconcileIds]
//                 );
//                 $removedCount += count($fullReconcileIds);
//             } catch (\Exception $e) {
//                 $errors[] = 'Full reconcile unlink failed: ' . $e->getMessage();
//             }
//         }
        
//         // Step 2: حذف أي reconciliation متبقية باستخدام remove_move_reconcile
//         // حتى لو كان في full_reconcile_id، الـ remove_move_reconcile هيشيل كل حاجة
//         if (!empty($lineIdsToUnreconcile)) {
//             try {
//                 $result = $this->execute(
//                     'account.move.line',
//                     'remove_move_reconcile',
//                     [$lineIdsToUnreconcile],
//                     []
//                 );
//                 $removedCount += count($lineIdsToUnreconcile);
//             } catch (\Exception $e) {
//                 $errors[] = 'Remove move reconcile failed: ' . $e->getMessage();
//             }
//         }
        
//         // Step 3: تحقق إن الحذف تم فعلاً
//         $verifyLines = $this->getReconciliationStatus($advanceMoveId);
//         $stillReconciled = array_filter($verifyLines, function($line) {
//             return $line['reconciled'] === true;
//         });
        
//         if (!empty($stillReconciled)) {
//             // لسه في lines مربوطة! جرب مرة تانية بطريقة مختلفة
//             $remainingLineIds = array_column($stillReconciled, 'id');
            
//             try {
//                 // جرب unreconcile مباشرة من الـ account.move
//                 $this->execute(
//                     'account.move',
//                     'button_draft',
//                     [[$advanceMoveId]]
//                 );
                
//                 // اعمل re-post
//                 $this->execute(
//                     'account.move',
//                     'action_post',
//                     [[$advanceMoveId]]
//                 );
                
//                 $errors[] = 'Warning: Had to reset move to draft to remove reconciliation';
//             } catch (\Exception $e) {
//                 $errors[] = 'Could not fully remove reconciliation: ' . $e->getMessage();
//             }
//         }

//         if ($removedCount === 0 && empty($errors)) {
//             return [
//                 'success' => true,
//                 'message' => 'No reconciliations found - advance is already unreconciled',
//                 'removed_count' => 0
//             ];
//         }
        
//         return [
//             'success' => empty($stillReconciled),
//             'message' => empty($stillReconciled) 
//                 ? 'All reconciliations removed successfully' 
//                 : 'Some reconciliations could not be removed',
//             'removed_full_reconcile_ids' => $fullReconcileIds,
//             'removed_line_ids' => $lineIdsToUnreconcile,
//             'removed_count' => $removedCount,
//             'still_reconciled_count' => count($stillReconciled ?? []),
//             'errors' => $errors
//         ];

//     } catch (\Exception $e) {
//         return [
//             'success' => false,
//             'message' => $e->getMessage()
//         ];
//     }
// }
// public function forceUnreconcileAdvance($advanceMoveId)
// {
//     try {
//         // طريقة 1: استخدم button_cancel_reconciliation لو موجودة
//         try {
//             $result = $this->execute(
//                 'account.move',
//                 'button_cancel_reconciliation',
//                 [[$advanceMoveId]]
//             );
            
//             return [
//                 'success' => true,
//                 'message' => 'Reconciliation cancelled via button',
//                 'method' => 'button_cancel_reconciliation'
//             ];
//         } catch (\Exception $e) {
//             // الـ method مش موجودة، جرب طريقة تانية
//         }
        
//         // طريقة 2: جيب كل الـ partial reconcile objects
//         $lines = $this->getReconciliationStatus($advanceMoveId);
//         $matchedIds = [];
        
//         foreach ($lines as $line) {
//             if (!empty($line['matched_debit_ids'])) {
//                 $matchedIds = array_merge($matchedIds, $line['matched_debit_ids']);
//             }
//             if (!empty($line['matched_credit_ids'])) {
//                 $matchedIds = array_merge($matchedIds, $line['matched_credit_ids']);
//             }
//         }
        
//         if (!empty($matchedIds)) {
//             $matchedIds = array_unique($matchedIds);
            
//             // احذف الـ account.partial.reconcile records
//             $this->execute(
//                 'account.partial.reconcile',
//                 'unlink',
//                 [$matchedIds]
//             );
            
//             return [
//                 'success' => true,
//                 'message' => 'Partial reconciliations removed',
//                 'method' => 'partial_reconcile_unlink',
//                 'removed_ids' => $matchedIds
//             ];
//         }
        
//         return [
//             'success' => false,
//             'message' => 'No reconciliation method worked'
//         ];
        
//     } catch (\Exception $e) {
//         return [
//             'success' => false,
//             'message' => $e->getMessage()
//         ];
//     }
// }
// Main method - يعمل للعملاء والموردين
public function settleAdvanceWithInvoices(int $odooCurrencyId, float $exchangeRate, $advanceMoveId, $invoiceMatches, $partnerId, $isCustomer = false, $journalId = null)
{
    try {
    //   $re=$this->removeAllReconciliationsFromAdvance($advanceMoveId);

        $results = [];
        $totalSettled = 0;
        $transferMoveIds = [];
        
        foreach ($invoiceMatches as $match) {
            $amountInCurrency = $match['amount'];
            $amountInMainFunctionalCurrency = $amountInCurrency * $exchangeRate;
            $invoiceId = $match['invoice_id'];
            $settlement = $match['settlement'];
            
            // Step 1: Create journal entry to transfer advance
            $transferResult = $this->transferAdvanceToReceivableOrPayable(
                $odooCurrencyId,
                $amountInMainFunctionalCurrency,
                $amountInCurrency,
                $advanceMoveId,
                $partnerId,
                $isCustomer,
                $journalId
            );
            
            if (!$transferResult['success']) {
                $results[] = [
                    'invoice_id' => $invoiceId,
                    'amount' => $amountInCurrency,
                    'status' => 'failed',
                    'step' => 'transfer_creation',
                    'error' => $transferResult['message']
                ];
                continue;
            }

            $transferMoveId = $transferResult['move_id'];
            $transferMoveIds[] = $transferMoveId;

            // Step 2: Reconcile transfer with invoice
            $invoiceReconcileResult = $this->reconcileWithInvoice(
                $transferMoveId,
                $invoiceId,
                $isCustomer
            );
            
            if ($invoiceReconcileResult['success']) {
				$settlement->update(['odoo_move_id'=>$transferMoveId]);
				
                $totalSettled += $amountInCurrency;
                $results[] = [
                    'invoice_id' => $invoiceId,
                    'amount' => $amountInCurrency,
                    'status' => 'success',
                    'transfer_move_id' => $transferMoveId
                ];
            } else {
                $results[] = [
                    'invoice_id' => $invoiceId,
                    'amount' => $amountInCurrency,
                    'status' => 'failed',
                    'step' => 'invoice_reconciliation',
                    'error' => $invoiceReconcileResult['message'],
                    'transfer_move_id' => $transferMoveId
                ];
            }
        }

        // Step 3: Reconcile advance with all transfers
        if (!empty($transferMoveIds) && $totalSettled > 0) {
            $finalReconcileResult = $this->reconcileAdvanceWithMultipleTransfers(
                $advanceMoveId,
                $transferMoveIds,
                $isCustomer
            );
        }
		
		return [
            'success' => true,
            'message' => 'Advance settlement completed',
            'total_settled' => $totalSettled,
            'settlements' => $results,
     //       'unreconcile_result' => $unreconcileResult,
            'is_customer' => $isCustomer
        ];

    } catch (\Exception $e) {
        Log::error('Advance settlement failed', [
            'error' => $e->getMessage(),
            'advance_move_id' => $advanceMoveId,
            'is_customer' => $isCustomer
        ]);

        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

private function reconcileAdvanceWithMultipleTransfers($advanceMoveId, $transferMoveIds, $isCustomer)
{
    try {
        // Get advance line
        if ($isCustomer) {
            $advanceLines = $this->getAccountMoveLinesByType(
                $advanceMoveId,
                'liability_payable',
                'credit'
            );
        } else {
            $advanceLines = $this->getAccountMoveLinesByType(
                $advanceMoveId,
                'asset_receivable',
                'debit'
            );
        }
        if (empty($advanceLines)) {
            throw new \Exception("No advance lines found");
        }

        // Get all transfer lines
        $transferLineIds = [];
        foreach ($transferMoveIds as $transferMoveId) {
            $transferMove = $this->execute(
                'account.move',
                'search_read',
                [[['id', '=', $transferMoveId]]],
                ['fields' => ['line_ids']]
            );

            if ($isCustomer) {
                $transferLines = $this->execute(
                    'account.move.line',
                    'search_read',
                    [[
                        ['id', 'in', $transferMove[0]['line_ids']],
                        ['account_id.account_type', '=', 'liability_payable'],
                        ['debit', '>', 0]
                    ]],
                    ['fields' => ['id']]
                );
            } else {
                $transferLines = $this->execute(
                    'account.move.line',
                    'search_read',
                    [[
                        ['id', 'in', $transferMove[0]['line_ids']],
                        ['account_id.account_type', '=', 'asset_receivable'],
                        ['credit', '>', 0]
                    ]],
                    ['fields' => ['id']]
                );
            }

            if (!empty($transferLines)) {
                $transferLineIds[] = $transferLines[0]['id'];
            }
        }

        // Reconcile advance with all transfers
        $lineIds = array_merge([$advanceLines[0]['id']], $transferLineIds);
        
        $result = $this->execute(
            'account.move.line',
            'reconcile',
            [$lineIds],
            []
        );

        return [
            'success' => true,
            'reconcile_id' => $result
        ];

    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}



}
