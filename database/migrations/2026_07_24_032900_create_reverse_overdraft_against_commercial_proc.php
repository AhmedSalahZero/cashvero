<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("CREATE DEFINER=`root`@`localhost` PROCEDURE `reverse_overdraft_against_commercial`(in _start_update_from_date_time date  , in _overdraft_against_commercial_paper_id integer )
begin 
				
					-- declare i INTEGER DEFAULT 0 ;
				--	declare _overdraft_against_commercial_paper_withdrawal_id integer default 0 ;
				-- هنجيب كل السحوبات اللي تاريخها اكبر من تاريخ الاغلاق لان اللي تاريخها اصغر من او يساوي تاريخ الاغلاق مش هنقدر نيجي يمها
					update overdraft_against_commercial_paper_withdrawals set net_balance = net_balance + settlement_amount , settlement_amount = 0 where due_date > _start_update_from_date_time  and overdraft_against_commercial_paper_id = _overdraft_against_commercial_paper_id ;
				end");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS reverse_overdraft_against_commercial");
    }
};
