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
        DB::unprepared("CREATE DEFINER=`root`@`localhost` PROCEDURE `resettlement_lc_overdraft_from`(in _type varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,in _lc_issuance_id integer,in _lc_facility_id integer , in _current_company_id integer , in _source varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci )
begin 
					declare _current_debit decimal(14,2) default 0 ;
					declare _total_settlements decimal(14,2) default 0 ;
					select sum(debit) into _current_debit from lc_overdraft_bank_statements where lc_overdraft_bank_statements.lc_facility_id = _lc_facility_id and is_debit > 0 and source = _source ;
					select sum(settlement_amount) into _total_settlements from lc_overdraft_withdrawals where lc_overdraft_withdrawals.lc_facility_id =  _lc_facility_id ;
					set _current_debit = _current_debit - _total_settlements ;
					call start_settlement_process_lc_overdraft(_type,0 , _lc_issuance_id,_lc_facility_id , _current_debit  ,0 , _current_company_id , CURRENT_TIMESTAMP,_source);
end");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS resettlement_lc_overdraft_from");
    }
};
