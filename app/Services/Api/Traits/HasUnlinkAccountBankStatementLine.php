<?php 
namespace App\Services\Api\Traits;


trait HasUnlinkAccountBankStatementLine 
{
	use HasAtomicOdooDeletion;


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

	/**
	 * * قبل كده كانت بتعمل button_draft بعدين unlink كل واحدة لوحدها ، فلو
	 * * الـ unlink فشل (اشهر سبب : القيد استهلك رقم تسلسلي و مش آخر واحد
	 * * في السلسلة) القيد كان بيفضل draft في اودو — نص عملية
	 *
	 * * دلوقتي العملية اما تكمل او ترجّع اودو زي ما كان و ترمي
	 * * OdooOperationNotAllowedException
	 *
	 * * السلوك القديم بتاع الـ draft اتساب زي ما هو : القيد اللي حالته
	 * * draft اصلا ما بيتحذفش
	 */
	public function unlink(int $journalEntryId)
    {
        $this->unlinkOdooRecordAtomically(
            'account.move',
            $journalEntryId,
            'button_draft',
            ['posted' => 'action_post', 'cancel' => 'button_cancel'],
            'Unlink Odoo journal entry #'.$journalEntryId,
            true
        );
    }
	
}
