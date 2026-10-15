<?php
namespace App\Services\Api\Traits;

trait HasUnlink
{
    public function unlink(string $modelName, int $id)
    {
        $this->models->execute_kw(
            $this->db,
            $this->uid,
            $this->password,
            $modelName,
            'unlink',
            [[$id]]
        );
    }
    // public function unlinkBankStatementLine(int $statement_line_id)
    // {
    //     $models = $this->models;

    //     // 1. Find the move (journal entry) linked to this statement line
    //     $move_ids = $models->execute_kw(
    //         $this->db,
    //         $this->uid,
    //         $this->password,
    //         'account.bank.statement.line',
    //         'read',
    //         [[$statement_line_id]],
    //         ['fields' => ['move_id']]
    //     );
    //     $move_id = $move_ids[0]['move_id'][0]; // e.g. 14496

    //     // 2. Unreconcile the move (remove reconciliation)
    //     $models->execute_kw(
    //         $this->db,
    //         $this->uid,
    //         $this->password,
    //         'account.move',
    //         'button_cancel_reconciliation',
    //         [[$move_id]]
    //     );

    //     // OR more precise: unreconcile only the specific lines
    //     $models->execute_kw(
    //         $this->db,
    //         $this->uid,
    //         $this->password,
    //         'account.move.line',
    //         'remove_move_reconcile',
    //         [[33352, 33353]]
    //     );

    //     // 3. Now delete the bank statement line
    //     $models->execute_kw(
    //         $this->db,
    //         $this->uid,
    //         $this->password,
    //         'account.bank.statement.line',
    //         'unlink',
    //         [[$statement_line_id]]
    //     );

    //     // Optional: delete the journal entry if no longer needed
    //     $models->execute_kw(
    //         $this->db,
    //         $this->uid,
    //         $this->password,
    //         'account.move',
    //         'unlink',
    //         [[$move_id]]
    //     );
    
    // }
    
    // public function unlinkBankCollection(int $accountBankStatementId)
    // {
    //     $models = $this->models;
    
    //     $move_line_ids = $models->execute_kw(
    //         $this->db,
    //         $this->uid,
    //         $this->password,
    //         'account.bank.statement.line',
    //         'read',
    //         [[$accountBankStatementId]],
    //         ['fields' => ['line_ids']]
    //     );
    //     $line_ids = $move_line_ids[0]['line_ids']; // [33352, 33353]

    //     $models->execute_kw(
    //         $this->db,
    //         $this->uid,
    //         $this->password,
    //         'account.move.line',
    //         'remove_move_reconcile',
    //         [$line_ids]
    //     );

    //     // Now unlink works
    //     $models->execute_kw(
    //         $this->db,
    //         $this->uid,
    //         $this->password,
    //         'account.bank.statement.line',
    //         'unlink',
    //         [[$accountBankStatementId]]
    //     );
    
    // }

    
    

}
