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
        DB::unprepared("CREATE DEFINER=`root`@`localhost` PROCEDURE `resettlement_clean_overdraft_from`(in _type varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,  in _clean_overdraft_id integer , in _current_company_id integer  )
begin 
		declare _current_debit decimal(14,2) default 0 ;
		declare _total_settlements decimal(14,2) default 0 ;

		select sum(debit) into _current_debit from clean_overdraft_bank_statements where clean_overdraft_id = _clean_overdraft_id and is_debit > 0    ;
		select sum(settlement_amount) into _total_settlements from clean_overdraft_withdrawals where clean_overdraft_id =  _clean_overdraft_id ;
		set _current_debit = _current_debit - _total_settlements ;
				call start_settlement_process_clean_overdraft(_type,0 , _clean_overdraft_id , _current_debit  ,0 , _current_company_id , CURRENT_TIMESTAMP);
		
		
	end");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS resettlement_clean_overdraft_from");
    }
};
