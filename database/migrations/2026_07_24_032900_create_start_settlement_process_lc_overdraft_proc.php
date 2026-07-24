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
        DB::unprepared("CREATE DEFINER=`root`@`localhost` PROCEDURE `start_settlement_process_lc_overdraft`(in _type varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ,in _bank_statement_id integer ,in _lc_issuance_id integer, in _lc_facility_id integer , in _debit decimal , in _credit decimal , in _company_id integer , in _date_for_settlement date , in _source varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
begin 
					declare _lc_overdraft_to_be_settled_after integer default 0 ;
					declare _due_date date default null ;
					declare _row_credit decimal(14,2) default 0 ;
					declare _first_item_to_be_settled_amount decimal(14,2) default 0 ;
					declare _total_number_or_rows_to_be_settled integer default 0 ;
					declare _lc_overdraft_withdrawal_id integer default 0 ;
					declare _first_item_to_be_settled_net_balance decimal(14,2) default 0 ;
					declare current_available_debit decimal(14,2) default _debit ;
					declare _current_settlement_amount decimal(14,2) default 0 ;
					set current_available_debit = ifnull(current_available_debit , 0);
					select financing_duration into _lc_overdraft_to_be_settled_after from letter_of_credit_issuances where id = _lc_issuance_id ;
					set _lc_overdraft_to_be_settled_after = ifnull(_lc_overdraft_to_be_settled_after,0);
					set _due_date = if(_type = 'outstanding_balance' , _date_for_settlement ,ADDDATE(_date_for_settlement,_lc_overdraft_to_be_settled_after));
					set _lc_overdraft_to_be_settled_after = ifnull(_lc_overdraft_to_be_settled_after , 0) ; 
					-- 
					if  _lc_overdraft_to_be_settled_after > 0 and _credit > 0 and _type != 'interest' and _type != 'highest_debit_balance' and _type != 'fees'  then  -- في الحاله دي هنسجل سحبه جديدة
						insert into lc_overdraft_withdrawals (lc_overdraft_bank_statement_id,lc_facility_id , company_id  , max_settlement_days , due_date , settlement_amount , net_balance,created_at) values(_bank_statement_id,_lc_facility_id,_company_id,_lc_overdraft_to_be_settled_after,_due_date,0,_credit,CURRENT_TIMESTAMP);
					end if ; 
					if _lc_overdraft_to_be_settled_after > 0 then  -- في الحاله دي هنضيف القيم في جداول lc_overdraft_settlements + lc_overdraft_withdrawals
					
						select count(*) into _total_number_or_rows_to_be_settled from lc_overdraft_withdrawals where lc_overdraft_withdrawals.lc_facility_id = _lc_facility_id and net_balance > 0;
						set _total_number_or_rows_to_be_settled = ifnull(_total_number_or_rows_to_be_settled , 0);
						
					
						
						while current_available_debit > 0 and _total_number_or_rows_to_be_settled > 0 DO  -- معناه ان معاه فلوس يسدد بيها وكمان عليه فلوس لسه ما اتسددتش
						-- get first item need to be settled  هنجيب اول عنصر في المسحوبات محتاج يتعمله تسديد .. اللي هو النت بالانس بتاعه اكبر من الصفر
							-- هنجيب اللي المفروض تتسدد والاولويه هتكون للفؤايد اللي عليه
							select credit , settlement_amount , net_balance , lc_overdraft_withdrawals.id into _row_credit , _first_item_to_be_settled_amount , _first_item_to_be_settled_net_balance , _lc_overdraft_withdrawal_id from lc_overdraft_bank_statements
							join lc_overdraft_withdrawals on lc_overdraft_withdrawals.lc_overdraft_bank_statement_id = lc_overdraft_bank_statements.id
							where lc_overdraft_bank_statements.company_id =_company_id  
							and source = _source
							and lc_overdraft_bank_statements.credit > 0  -- علشان نجيب التسديدات فقط
							and lc_overdraft_bank_statements.lc_facility_id = _lc_facility_id  -- لحساب الاوفر درافت دا
							and lc_overdraft_withdrawals.net_balance > 0 -- اي متبقي عليها فلوس 
							order by  lc_overdraft_withdrawals.due_date asc , lc_overdraft_bank_statements.priority asc , lc_overdraft_bank_statements.id asc  limit 1  ; --  بنرتب علي حس الاولويه علشان الفؤايد ليها الالويه ولو تساو في الاولويه هناخد الاقدم يعني اللي الاي دي بتاعه اصغر 
						
						
							if(_first_item_to_be_settled_net_balance > current_available_debit) then   -- معناه ان الفلوس اللي عليه اكبر من الفلوس اللي معاه
							set _current_settlement_amount = current_available_debit ;
							else  -- الفلوس اللي معاه اكبر او تساوي وبالتالي هنسدد كل اللي معاه
							set _current_settlement_amount = _first_item_to_be_settled_net_balance ;
							end if ;
							set _first_item_to_be_settled_amount = ifnull(_first_item_to_be_settled_amount , 0);
							set _first_item_to_be_settled_net_balance = ifnull(_first_item_to_be_settled_net_balance , 0);
						
							
								
								
							
							update lc_overdraft_withdrawals set settlement_amount = _current_settlement_amount + ifnull(settlement_amount,0) , net_balance = _row_credit - settlement_amount where id = _lc_overdraft_withdrawal_id ;
							
							set current_available_debit = current_available_debit - _current_settlement_amount ;
							select count(*) into _total_number_or_rows_to_be_settled from lc_overdraft_withdrawals where lc_overdraft_withdrawals.lc_facility_id = _lc_facility_id and net_balance > 0;
							set _total_number_or_rows_to_be_settled = ifnull(_total_number_or_rows_to_be_settled , 0);
						end while ;
					
					end if ;
					
				end");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS start_settlement_process_lc_overdraft");
    }
};
