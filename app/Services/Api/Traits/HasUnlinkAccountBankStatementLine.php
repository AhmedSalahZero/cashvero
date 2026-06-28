<?php 
namespace App\Services\Api\Traits;


trait HasUnlinkAccountBankStatementLine 
{

	public function unlinkWithOdooId(int $odooId): bool
{
    $entry = $this->execute(
        'account.move',
        'read',
        [[$odooId], ['id', 'state', 'name', 'move_type']]
    );

    if (empty($entry)) {
        throw new \Exception("Journal entry not found: ID {$odooId}");
    }

    $entry = $entry[0];
    $state = $entry['state'];

    if ($state === 'posted' || $state === 'cancel') {
        $this->execute('account.move', 'button_draft', [[$odooId]]);
    } elseif ($state !== 'draft') {
        throw new \Exception("Cannot delete journal entry #{$odooId} ({$entry['name']}) in state '{$state}'");
    }

    $this->execute('account.move', 'unlink', [[$odooId]]);

    // Optional: log success
    // Log::info("Deleted journal entry #{$odooId} ({$entry['name']})");

    return true;
}

	public function unlink(int $journalEntryId)
    { 
        // Check if the payment exists
        $entry = $this->execute(
            'account.move',
            'read',
            [[$journalEntryId], ['id', 'state']]
        );
		
		 if (empty($entry)) {
			return ;
            // throw new \Exception("Move ID not found: " . $journalEntryId);
        }
        if ($entry[0]['state'] === 'draft') {
    //        Log::info("Payment $accountBankStatementLineId is already in draft state");
            return true;
        }
        
        // Set the account.payment to draft
         $this->execute(
            'account.move',
            'button_draft',
            [[$journalEntryId]]
        );
		
		
		$this->execute(
                'account.move',
                'unlink',
                [[$journalEntryId]]
            );
			

       
    }
	
}
