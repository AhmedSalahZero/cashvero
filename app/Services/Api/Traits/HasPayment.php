<?php
namespace App\Services\Api\Traits;

use Exception;

trait HasPayment
{

    /**
     * * $inBoundOrOutBound [inbound , outbound]
     */
    // public function createPayment(string $inBoundOrOutBound, int $inOrOutJournalId, string $date, float $amount, int $odooCurrencyId)
    // {
        
    //     // Create payment in draft state
    //     $context = [
    //         'active_model' => 'account.move',
    //         'active_ids' => [],
    //     ];

    //     $paymentId = $this->execute(
    //         'account.payment',
    //         'create',
    //         [[
    //             'amount' => abs($amount), // Ensure positive amount
    //             'journal_id' => $inOrOutJournalId,
    //             'date' => $date,
    //             'currency_id' => $odooCurrencyId,
    //             'destination_account_id' => OdooSetting::getSuspenseAccountId() ,
    //             'payment_type' => $inBoundOrOutBound,
    //             'payment_method_id' => 1
    //         ]],
    //         ['context' => $context]
    //     );
    //     return $paymentId;

      
    // }
    // protected function postPayment($paymentId):void
    // {
    //     $this->models->execute_kw(
    //         $this->db,
    //         $this->uid,
    //         $this->password,
    //         'account.payment',
    //         'action_post',
    //         [[$paymentId]],
    //     );
       
    // }
        


    // protected function updatePayment($paymentId, $updateData)
    // {
    //     $this->execute(
    //         'account.payment',
    //         'write',
    //         [[$paymentId], $updateData]
    //     );
    //     return true;
     
    // }
    public function cancelPayments(int $paymentOdooId)
    {
        $filters = [
                ['id','=',$paymentOdooId]
        ];
        $payments = $this->models->execute_kw(
            $this->db,
            $this->uid,
            $this->password,
            'account.payment',
            'search_read',
            [$filters],
        );
        /**
         * * مش بكون عارف هي انهي مدفوعه بالظبط .. فا بلغيهم كلهم
         */
        foreach ($payments as $existingPayment) {
            $odooPaymentId = $existingPayment['id'] ;
            $this->setPaymentToDraft($odooPaymentId);
             $this->execute(
                'account.payment',
                'unlink',
                [[$odooPaymentId]]
            );
            
           
            
        }
    }
    
    public function cancelDownPayment(int $downPaymentOdooId)
    {
        return $this->cancelPayments($downPaymentOdooId);
    }
    
    

    public function setPaymentToDraft(int $paymentId)
    {
        // Check if the payment exists
        $entry = $this->execute(
            'account.payment',
            'read',
            [[$paymentId], ['id', 'state']]
        );

        if (empty($entry)) {
            throw new Exception("Payment not found: " . $paymentId);
        }
        if ($entry[0]['state'] === 'draft') {
            //        Log::info("Payment $paymentId is already in draft state");
            return true;
        }
        
        // Set the account.payment to draft
        $this->execute(
            'account.payment',
            'action_draft',
            [[$paymentId]]
        );
    }
    

    

}
