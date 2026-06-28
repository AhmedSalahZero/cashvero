				drop trigger if exists refresh_calculation_before_insert_lc ;
				delimiter // 
				create  trigger refresh_calculation_before_insert_lc before insert on `lc_overdraft_bank_statements` for each row 
				begin 
					declare _last_end_balance decimal(14,2) default 0 ;
					declare _previous_date date default null ;
						declare _current_interest_rate decimal(5,2) default 0 ;
						declare _interest_rate decimal(5,2) default 0 ;
						declare _count_all_rows integer default 0 ; 
						declare _last_delete_id integer default 0 ; 
						declare interest_type_text varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci default 'interest';
						declare highest_debit_balance_text varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci default 'highest_debit_balance';
						-- في حالة الانشاء
						set new.created_at = CURRENT_TIMESTAMP;
						select date , end_balance  into _previous_date,_last_end_balance  from lc_overdraft_bank_statements where  source = new.source and lc_overdraft_bank_statements.lc_facility_id = new.lc_facility_id  and date <= new.date order by date desc , id desc limit 1 ; -- رتبت بالاي دي الاكبر علشان  لو كانوا متساوين في التاريخ بالظبط (ودا احتمال ضعيف ) ياخد اللي ال اي دي بتاعه اكبر
						select  count(*) into _count_all_rows from lc_overdraft_bank_statements where  source = new.source and lc_overdraft_bank_statements.lc_facility_id = new.lc_facility_id and date <= new.date ;
					set new.beginning_balance = if(_count_all_rows,_last_end_balance,ifnull(new.beginning_balance,0)); 
					set new.end_balance = new.beginning_balance + new.debit - new.credit ; 
					set new.room = new.limit +  new.end_balance ;
					set new.is_debit = if(new.debit > 0 , 1 , 0);
					set new.is_credit = if(new.debit > 0 , 0 , 1);
					set @dayCounts = 0 ;
					set @interestAmount = 0 ; 
					
						-- هنبدا نحسب الفوائد اللي عليه 
					select  interest_rate into  _interest_rate from letter_of_credit_facilities where id = new.lc_facility_id ;
			
					set _interest_rate = ifnull(_interest_rate,0);
						set _current_interest_rate = _interest_rate ;
				
					set _current_interest_rate = _current_interest_rate / 100 ;

					
					set @dailyInterestRate = _current_interest_rate/365 ;
					if _previous_date then 
					set @dayCounts = DATEDIFF(new.date,_previous_date) ;
					set @interestAmount = if(_last_end_balance < 0 , _last_end_balance * @dailyInterestRate * @dayCounts , 0)  ;
					end if ; 
					set new.interest_rate_annually = _current_interest_rate ;
					set new.interest_rate_daily = @dailyInterestRate ;
					set new.days_count = @dayCounts ;
					set new.interest_amount = @interestAmount;
					-- نهاية حسبة الفوائد
					

				end //
				delimiter ;
				drop trigger if exists  refresh_calculation_before_update_lc_overdraft ;
				drop procedure if exists resettlement_lc_overdraft_from ;
				delimiter // 
				create procedure resettlement_lc_overdraft_from(in _type varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,in _lc_issuance_id integer,in _lc_facility_id integer , in _current_company_id integer , in _source varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci )
				begin 
					declare _current_debit decimal(14,2) default 0 ;
					declare _total_settlements decimal(14,2) default 0 ;
					select sum(debit) into _current_debit from lc_overdraft_bank_statements where lc_overdraft_bank_statements.lc_facility_id = _lc_facility_id and is_debit > 0 and source = _source ;
					select sum(settlement_amount) into _total_settlements from lc_overdraft_withdrawals where lc_overdraft_withdrawals.lc_facility_id =  _lc_facility_id ;
					set _current_debit = _current_debit - _total_settlements ;
					call start_settlement_process_lc_overdraft(_type,0 , _lc_issuance_id,_lc_facility_id , _current_debit  ,0 , _current_company_id , CURRENT_TIMESTAMP,_source);
				end //
					
				delimiter ;
				drop procedure if exists reverse_lc_overdraft_settlements ;
				delimiter // 
				create procedure reverse_lc_overdraft_settlements(in _start_update_from_date date  , in _lc_facility_id integer )
				begin 
					-- declare i INTEGER DEFAULT 0 ;
				--	declare _lc_overdraft_withdrawal_id integer default 0 ;
				-- هنجيب كل السحوبات اللي تاريخها اكبر من تاريخ الاغلاق لان اللي تاريخها اصغر من او يساوي تاريخ الاغلاق مش هنقدر نيجي يمها
					update lc_overdraft_withdrawals set net_balance = net_balance + settlement_amount , settlement_amount = 0 where due_date > _start_update_from_date  and lc_overdraft_withdrawals.lc_facility_id = _lc_facility_id ;
				end //
				
				delimiter ; 
				

				create trigger refresh_calculation_before_update_lc_overdraft before update on `lc_overdraft_bank_statements` for each row 
				begin 

						declare _current_debit decimal(14,2) default 0 ;
						declare _total_settlements decimal(14,2) default 0 ;
						declare _last_end_balance decimal(14,2) default 0 ;
						declare _start_update_from_date date default '2000-01-01' ;
						declare _previous_date date default null ;
						declare _last_bank_statement_date_to_start_settlement_from datetime default null ;
						declare _current_interest_rate decimal(5,2) default 0 ;
						declare _interest_rate decimal(5,2) default 0 ;
						declare _count_all_rows integer default 0 ; 
						declare _current_bank_statement_id integer default 0 ; 
						declare _current_bank_statement_debit integer default 0 ; 
						declare _i integer default 0 ;
						declare _bank_statements_greater_than_current_one_length integer default 0 ;
						
						declare _last_bank_statement_date datetime default null ;
						declare _last_id integer default 0 ;
							declare _current_interest_amount decimal(14,2) default 0;
							declare _largest_end_balance decimal(14,2) default 0;
							declare _highest_debt_balance_rate decimal(5,2) default 0 ;
						-- declare _bank_statement_start_from_date datetime default null ;
							declare _lc_overdraft_to_be_settled_after integer default 0 ;
							declare interest_type_text varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci default 'interest';
							declare highest_debit_balance_text varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci default 'highest_debit_balance';
							 declare _total_month_interest_amount decimal(14,2) default 0 ;
						select date,end_balance,id into _previous_date, _last_end_balance,_last_id  from lc_overdraft_bank_statements where  lc_overdraft_bank_statements.lc_facility_id = new.lc_facility_id and source = new.source and date = new.date and id < new.id order by date desc , id desc limit 1 ; -- رتبت بالاي دي الاكبر علشان  لو كانوا متساوين في التاريخ بالظبط (ودا احتمال ضعيف ) ياخد اللي ال اي دي بتاعه اكبر
						if  (_previous_date)
			then
						select date,end_balance,id into _previous_date, _last_end_balance,_last_id  from lc_overdraft_bank_statements where  lc_overdraft_bank_statements.lc_facility_id = new.lc_facility_id and source = new.source and date = new.date and id < new.id order by date desc , id desc limit 1 ; -- رتبت بالاي دي الاكبر علشان  لو كانوا متساوين في التاريخ بالظبط (ودا احتمال ضعيف ) ياخد اللي ال اي دي بتاعه اكبر
			  
			else 
					
						select date,end_balance,id into _previous_date, _last_end_balance,_last_id  from lc_overdraft_bank_statements where  lc_overdraft_bank_statements.lc_facility_id = new.lc_facility_id and source = new.source and date < new.date order by date desc , id desc limit 1  ;  -- رتبت بالاي دي الاكبر علشان  لو كانوا متساوين في التاريخ بالظبط (ودا احتمال ضعيف ) ياخد اللي ال اي دي بتاعه اكبر
			end if ;
						
						set _count_all_rows =1 ;
					set new.beginning_balance = if(_count_all_rows,_last_end_balance,ifnull(new.beginning_balance,0)) ;
					
					
					set new.end_balance = new.beginning_balance + new.debit - new.credit ; 
					set new.room = new.limit +  new.end_balance ;
					set @dayCounts = 0 ;
					set @interestAmount = 0 ; 
						-- هنبدا نحسب الفوائد اللي عليه 
					select  interest_rate into  _interest_rate from letter_of_credit_facilities where id = new.lc_facility_id ;
					set _interest_rate = ifnull(_interest_rate,0);
							set _current_interest_rate = _interest_rate ;
					
					set _current_interest_rate = _current_interest_rate / 100 ;

					
					set @dailyInterestRate = _current_interest_rate/365 ;
					if _previous_date then 
					set @dayCounts = DATEDIFF(new.date,_previous_date) ;
					set @interestAmount = if(_last_end_balance < 0 , _last_end_balance * @dailyInterestRate * @dayCounts , 0)  ;
					end if ; 
					set new.interest_rate_annually = _current_interest_rate ;
					set new.interest_rate_daily = @dailyInterestRate ;
					set new.days_count = @dayCounts ;
					set new.interest_amount = @interestAmount;
					-- نهاية حسبة الفوائد
					-- هنيجي بعد كدا علي تحديث جدول ال 
					-- withdrawal 
					-- عن طريق اول هنعمل عمليه ال 
					-- reverse settlements 
					-- بمعني ان كل اللي التسديدات اللي 
					
					
										-- بدايه حسبه فايدة نهايه كل شهر
		select sum(interest_amount)  into _total_month_interest_amount from   lc_overdraft_bank_statements where id!= new.id and company_id = new.company_id and lc_overdraft_bank_statements.lc_facility_id = new.lc_facility_id and month(date) = month(new.date) and year(date) = year(new.date) ; 
		set _total_month_interest_amount = ifnull(_total_month_interest_amount,0); 
				
		if(new.interest_type = 'end_of_month') then  
			set new.credit = _total_month_interest_amount+new.interest_amount ;	
		end if ;
		
					
					-- هنجيب اخر اي دي للحساب دا لان من عندة هنبدا نسدد من اول وجديد 
					-- هنجيب اللي الدبت اكبر من الصفر علشان احنا هنسدد وبالتالي عايزين القيم اللي فيها دبنت
						-- لو العنصر دا اللي بنحدث حاليا هو اخر عنصر هنبدا ال السايكل بتاعت اعادة توزيع التسديدات لكل العناصر من اول عنصر اتغير 
							select full_date into _last_bank_statement_date_to_start_settlement_from from lc_overdraft_bank_statements where lc_overdraft_bank_statements.lc_facility_id = new.lc_facility_id order by date desc , priority asc , id asc limit 1 ; 
							select oldest_date into _start_update_from_date from letter_of_credit_facilities where id = new.lc_facility_id  ; 
							-- عايزين بدل السطر اللي فوق نجيب ال closing date 
						
					if(_last_bank_statement_date_to_start_settlement_from = new.full_date) then 		
						select sum(debit) into _current_debit from lc_overdraft_bank_statements where lc_overdraft_bank_statements.lc_facility_id = new.lc_facility_id and is_debit > 0  and source = new.source   ;
						select sum(settlement_amount) into _total_settlements from lc_overdraft_withdrawals where lc_overdraft_withdrawals.lc_facility_id =  new.lc_facility_id ;
						set _current_debit = _current_debit - _total_settlements ;
							call reverse_lc_overdraft_settlements(_start_update_from_date,new.lc_facility_id);	
							call resettlement_lc_overdraft_from(new.type,new.lc_issuance_id,new.lc_facility_id,new.company_id,new.source);
					end if;
					
				
					
					
					
					
					
					
					-- اعادة حساب فايدة نهاية كل شهر (في حالة التعديل مش الانشاء)

					if new.id and (new.type = interest_type_text or new.type = highest_debit_balance_text ) then 
								select  sum(interest_amount) , max(end_balance) into _current_interest_amount,_largest_end_balance from  lc_overdraft_bank_statements where `type` != interest_type_text and `type` != highest_debit_balance_text and lc_overdraft_bank_statements.lc_facility_id = new.lc_facility_id and source = new.source and EXTRACT(MONTH from date) = EXTRACT(MONTH from new.date ) and  EXTRACT(YEAR from date) = EXTRACT(YEAR from new.date) ;
								set _current_interest_amount = ifnull(_current_interest_amount,0);
								select highest_debt_balance_rate into _highest_debt_balance_rate from letter_of_credit_facilities where id = new.lc_facility_id  ;
								if new.type = interest_type_text then 
								-- للفايدة الخاصة باخر الشهر
									set new.credit = _current_interest_amount ;
								elseif new.type = highest_debit_balance_text then 
								-- حساب ال highest debit balance
								set _current_interest_amount = _highest_debt_balance_rate / 100 * _largest_end_balance ; 
									set new.credit = _current_interest_amount ;
								end if;
								
					end if ;
					
					
				end //

				delimiter ;
				drop procedure if exists start_settlement_process_lc_overdraft;
				delimiter //
				-- هنا هنبدا نضيف سحبة جديدة لو البنك استيت منت كان كريدت اما لو كان دبت (يعني) الدبت اكبر من الصفر وقتها هنبدا نسدد 
				create procedure start_settlement_process_lc_overdraft(in _type varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ,in _bank_statement_id integer ,in _lc_issuance_id integer, in _lc_facility_id integer , in _debit decimal , in _credit decimal , in _company_id integer , in _date_for_settlement date , in _source varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
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
					
				end //
				delimiter ; 
				drop trigger if exists insert_into_overdraft_withdrawal_after_insert_lc_overdraft ;
				delimiter // 
				create  trigger insert_into_overdraft_withdrawal_after_insert_lc_overdraft after insert on `lc_overdraft_bank_statements` for each row 
				begin 
					declare _date_for_settlement date default ifnull(new.outstanding_withdrawal_date,new.date) ;
					if new.is_credit > 0 then
						call start_settlement_process_lc_overdraft(new.type,new.id , new.lc_issuance_id,new.lc_facility_id , new.debit  , new.credit , new.company_id ,_date_for_settlement,new.source);
					end if;
				end //


				delimiter ;
				drop procedure if exists recalculate_end_of_month_lc_overdraft_interests ;
				delimiter // 
				-- create procedure recalculate_end_of_month_lc_overdraft_interests()
				-- begin 
				-- 	declare current_id integer default 0 ;
				-- 	declare _lc_overdraft_bank_statement_id integer default 0 ;
				-- 	declare _lc_facility_id integer default 0 ;
				-- 	declare _company_id integer default 0 ;
				-- 	declare _limit decimal(14,2) default 0;
				-- 	declare _largest_end_balance decimal(14,2) default 0;
				-- 	declare interest_type_text varchar(100) default 'interest';
				-- 	declare highest_debit_balance_text varchar(100) default 'highest_debit_balance';
				-- 	declare _current_interest_amount decimal(14,2) default 0;
				-- 	declare _highest_debt_balance_rate decimal(5,2) default 0 ;
				-- 	declare i INTEGER DEFAULT 0 ;
				-- 	set _highest_debt_balance_rate = ifnull(_highest_debt_balance_rate,0);
				-- 	select count(distinct(lc_facility_id)) into @n from  lc_overdraft_bank_statements where `type` != interest_type_text and `type` != highest_debit_balance_text  and EXTRACT(MONTH from date) = EXTRACT(MONTH from current_date()) and  EXTRACT(YEAR from date) = EXTRACT(YEAR from current_date()) group by lc_overdraft_bank_statements.lc_facility_id;
				-- 	set @n = ifnull(@n,0);
				-- 	if @n > 0 then 
				-- 	repeat 
				-- 				-- حساب الفايدة نهاية كل شهر
				-- 				select lc_facility_id , sum(interest_amount) , max(end_balance) into _lc_facility_id,_current_interest_amount,_largest_end_balance from  lc_overdraft_bank_statements where `type` != interest_type_text  and `type` != highest_debit_balance_text and EXTRACT(MONTH from date) = EXTRACT(MONTH from current_date()) and  EXTRACT(YEAR from date) = EXTRACT(YEAR from current_date()) group by lc_overdraft_bank_statements.lc_facility_id limit i , 1;
				-- 				set _current_interest_amount = ifnull(_current_interest_amount , 0);
				-- 				set _largest_end_balance = ifnull(_largest_end_balance,0);
				-- 				select company_id,`limit`,highest_debt_balance_rate into _company_id,_limit,_highest_debt_balance_rate from letter_of_credit_facilities where id = _lc_facility_id  ;
				-- 				insert into lc_overdraft_bank_statements (type ,priority,lc_facility_id,money_received_id,company_id,date,`limit`,credit,interest_type,full_date) values(interest_type_text,1,_lc_facility_id,0,_company_id,current_date(),_limit,_current_interest_amount,'end_of_month',NOW());
				-- 				-- حساب ال highest debit balance
				-- 				set _current_interest_amount = _highest_debt_balance_rate / 100 * _largest_end_balance ; 
				-- 				insert into lc_overdraft_bank_statements (type,priority ,lc_facility_id,money_received_id,company_id,date,`limit`,credit,interest_type,full_date) values(highest_debit_balance_text,1,_lc_facility_id,0,_company_id,current_date(),_limit,_current_interest_amount,'end_of_month',NOW());
				-- 			set i = i +1 ; 
				-- 			UNTIL i >= @n  end repeat ;
				-- 	end if ;
					
				-- end //
				delimiter ; 
				DROP EVENT IF EXISTS `recalculate_end_of_month_lc_overdraft_interests_event`;
				-- DELIMITER $$
				-- CREATE EVENT `recalculate_end_of_month_lc_overdraft_interests_event`
				-- ON SCHEDULE EVERY  1 day
				-- STARTS '2022-03-31 23:59:00'
				-- ON COMPLETION PRESERVE
				-- DO BEGIN
				-- -- do nothing
				-- -- call recalculate_end_of_month_lc_overdraft_interests();
				-- END$$
				
 delimiter ; 
drop trigger if exists refresh_calculation_before_delete_lc_over_statements ;
  delimiter //  
  
create  trigger refresh_calculation_before_delete_lc_over_statements before delete on `lc_overdraft_bank_statements` for each row 
begin 
	delete from `temp_deleted_statements` where company_id = old.company_id and table_name = 'lc_overdraft_bank_statements';
	insert into `temp_deleted_statements` (company_id,table_name,deleted_id) values (old.company_id,'lc_overdraft_bank_statements',old.id);
end //
 delimiter ; 
 