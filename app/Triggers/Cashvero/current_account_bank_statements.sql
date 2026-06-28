drop trigger if exists before_insert_current_account_bank_statements ;
delimiter // 
create  trigger before_insert_current_account_bank_statements before insert on `current_account_bank_statements` for each row 
begin 
	declare _last_end_balance decimal(14,2) default 0 ;
	declare _previous_date date default null ;
		declare _count_all_rows integer default 0 ; 
	declare _min_interest_balance decimal(14,2) default 0 ;
	declare _current_interest_rate decimal(5,2) default 0 ;
	declare _interest_rate decimal(5,2) default 0 ;
	declare _min_interest_rate decimal(5,2) default 0 ; 
						
		set new.created_at = CURRENT_TIMESTAMP;
		select date , end_balance  into _previous_date,_last_end_balance  from current_account_bank_statements where is_active = 1 and company_id = new.company_id and financial_institution_account_id = new.financial_institution_account_id  and  date <= new.date   order by date desc , id desc  limit 1 ;
		select  count(*) into _count_all_rows from current_account_bank_statements where is_active = 1 and company_id = new.company_id  and financial_institution_account_id = new.financial_institution_account_id  and  date <= new.date   order by date desc , id desc limit 1 ;
		set new.beginning_balance = if(_count_all_rows,_last_end_balance,ifnull(new.beginning_balance,0)); 
		
		set new.end_balance = ifnull(new.beginning_balance + new.debit - new.credit,0) ; 
		insert into debugging (message) values(concat('beg',new.beginning_balance));
		insert into debugging (message) values(concat('debit',new.debit));
		insert into debugging (message) values(concat('credit',new.credit));
		set new.is_debit = if(new.debit > 0 , 1 , 0);
		set new.is_credit = if(new.debit > 0 , 0 , 1);
	
	
	
	
	
	
	
					set @dayCounts = 0 ;
					set @interestAmount = 0 ; 
					
						-- هنبدا نحسب الفوائد اللي عليه 
						
					select min_balance , interest_rate into _min_interest_balance, _interest_rate from account_interests where financial_institution_account_id = new.financial_institution_account_id and start_date <= new.date order by start_date desc , id desc limit 1 ;
					set _interest_rate = ifnull(_interest_rate,0);
					
					
						set _current_interest_rate = _interest_rate ;
					set _current_interest_rate = _current_interest_rate / 100 ;


			
					set @dailyInterestRate = _current_interest_rate/365 ;
					if _previous_date then 
					set @dayCounts = DATEDIFF(new.date,_previous_date) ;
					set @interestAmount = if(_min_interest_balance <=  new.beginning_balance , _last_end_balance * @dailyInterestRate * @dayCounts , 0)  ;
					end if ; 
					set new.interest_rate_annually = _current_interest_rate ;
					set new.interest_rate_daily = @dailyInterestRate ;
					set new.days_count = @dayCounts ;
					set new.interest_amount = @interestAmount;
					-- نهاية حسبة الفوائد
					
	
	

end //
delimiter ; 
drop trigger if exists before_update_current_account_bank_statements ;
delimiter // 
create  trigger before_update_current_account_bank_statements before update on `current_account_bank_statements` for each row 
begin 

	-- الكود دا نفس الكود اللي في ال
	-- before insert 
	-- فا لو عدلت ال
	-- before insert
	-- خده كوبي وبيست وحطة هنا
		
		declare _min_interest_balance decimal(14,2) default 0 ;
		declare _last_end_balance decimal(14,2) default 0 ;
		declare _previous_date date default null ;
		declare _count_all_rows integer default 0 ; 
		declare _current_interest_rate decimal(5,2) default 0 ;
	    declare _interest_rate decimal(5,2) default 0 ;
	    declare _min_interest_rate decimal(5,2) default 0 ; 
	    declare _total_month_interest_amount decimal(14,2) default 0 ;
		-- في حاله التعديل
		select date,end_balance into _previous_date, _last_end_balance  from current_account_bank_statements where is_active = 1 and company_id = new.company_id and financial_institution_account_id = new.financial_institution_account_id  and date = new.date and id < new.id order by date desc , id desc limit 1 ;
		if  (_previous_date)
			then
		select date,end_balance into _previous_date, _last_end_balance  from current_account_bank_statements where is_active = 1 and company_id = new.company_id and financial_institution_account_id = new.financial_institution_account_id  and date = new.date and id < new.id order by date desc , id desc limit 1 ;
			else 
		select date,end_balance into _previous_date, _last_end_balance  from current_account_bank_statements where is_active = 1 and company_id = new.company_id and financial_institution_account_id = new.financial_institution_account_id  and date < new.date order by date desc , id desc limit 1 ;
					
			end if ;
		set _count_all_rows =1 ;
	
	 set new.beginning_balance = _last_end_balance ;
	 
	
	
	
	
	
	
	
	
		-- هنبدا نحسب الفوائد اللي عليه 
						
							set @dayCounts = 0 ;
					set @interestAmount = 0 ; 

					select min_balance , interest_rate into _min_interest_balance, _interest_rate from account_interests where financial_institution_account_id = new.financial_institution_account_id and start_date <= new.date order by start_date desc , id desc limit 1 ;
	
					set _interest_rate = ifnull(_interest_rate,0);
					
				
						set _current_interest_rate = _interest_rate ;
					set _current_interest_rate = _current_interest_rate / 100 ;

				
					
					set @dailyInterestRate = _current_interest_rate/365 ;
					if _previous_date then 
					set @dayCounts = DATEDIFF(new.date,_previous_date) ;
					set @interestAmount = if(_min_interest_balance <=  new.beginning_balance , _last_end_balance * @dailyInterestRate * @dayCounts , 0)  ;
					-- set @interestAmount = if(_last_end_balance < 0 , _last_end_balance * @dailyInterestRate * @dayCounts , 0)  ;
					end if ; 
					set new.interest_rate_annually = _current_interest_rate ;
					set new.interest_rate_daily = @dailyInterestRate ;
					set new.days_count = @dayCounts ;
					set new.interest_amount = @interestAmount;
					
					-- نهاية حسبة الفوائد
					
					
						-- بدايه حسبه فايدة نهايه كل شهر
					select sum(interest_amount)  into _total_month_interest_amount from   current_account_bank_statements where id!= new.id and company_id = new.company_id and financial_institution_account_id = new.financial_institution_account_id and month(date) = month(new.date) and year(date) = year(new.date) ; 
					set _total_month_interest_amount = ifnull(_total_month_interest_amount,0); 
					if(new.interest_type = 'end_of_month' && new.end_balance >= _min_interest_balance 
					-- && new.debit <= 0
					) then  
						set new.debit = _total_month_interest_amount+new.interest_amount ;	
					end if ;
					
					set new.end_balance = ifnull(new.beginning_balance + new.debit - new.credit,0) ; 
					
			-- نهاية حسبه فايدة نهايه كل شهر
					
	
end //



				delimiter ;
				drop procedure if exists recalculate_end_of_month_current_account_interests ;
				-- delimiter // 
				-- create procedure recalculate_end_of_month_current_account_interests()
				-- begin 
				-- 	declare current_id integer default 0 ;
				-- 	declare _financial_institution_account_id integer default 0 ;
				-- 	declare _company_id integer default 0 ;
				-- 	declare _largest_end_balance decimal(14,2) default 0;
				-- 	declare interest_type_text varchar(100) default 'interest';
				
				-- 	declare _current_interest_amount decimal(14,2) default 0;
				-- 	declare i INTEGER DEFAULT 0 ;
				-- 	select count(distinct(financial_institution_account_id)) into @n from  current_account_bank_statements where `type` != interest_type_text  and EXTRACT(MONTH from date) = EXTRACT(MONTH from current_date()) and  EXTRACT(YEAR from date) = EXTRACT(YEAR from current_date()) group by financial_institution_account_id;
				-- 	set @n = ifnull(@n,0);
				-- 	if @n > 0 then 
					
				-- 	repeat 
				-- 				-- حساب الفايدة نهاية كل شهر
				-- 				select financial_institution_account_id , sum(interest_amount)  into _financial_institution_account_id,_current_interest_amount from  current_account_bank_statements where `type` != interest_type_text  and EXTRACT(MONTH from date) = EXTRACT(MONTH from current_date()) and  EXTRACT(YEAR from date) = EXTRACT(YEAR from current_date()) group by financial_institution_account_id limit i , 1;
				-- 				set _current_interest_amount = ifnull(_current_interest_amount , 0);
				-- 				select company_id into _company_id from financial_institution_accounts where id = _financial_institution_account_id  ;
				-- 				insert into current_account_bank_statements (type ,financial_institution_account_id,company_id,date,debit,interest_type,full_date) values(interest_type_text,_financial_institution_account_id,_company_id,current_date(),_current_interest_amount,'end_of_month',NOW());
				-- 				set i = i +1 ; 
				-- 			UNTIL i >= @n  end repeat ;
				-- 	end if ;
					
				-- end //
				
				
			 delimiter ; 
drop trigger if exists refresh_calculation_before_delete_current_acc_bank_stat ;
  delimiter //  
  
create  trigger refresh_calculation_before_delete_current_acc_bank_stat before delete on `current_account_bank_statements` for each row 
begin 
	delete from `temp_deleted_statements` where company_id = old.company_id and table_name = 'current_account_bank_statements';
	insert into `temp_deleted_statements` (company_id,table_name,deleted_id) values (old.company_id,'current_account_bank_statements',old.id);
end //
 delimiter ; 
 