drop trigger if exists before_insert_overdraft_against_commercial_paper_limits ;
delimiter // 
create  trigger before_insert_overdraft_against_commercial_paper_limits before insert on `overdraft_against_commercial_paper_limits` for each row 
begin 
	
		declare _cheque_status varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci default null ;
		declare _days_count integer default 0 ;
		declare _lending_rate decimal(10,4) default 0 ;
		declare _cheque_amount decimal(14,2) default 0 ;
		declare _previous_accumulated_limit decimal(14,2) default 0 ;
		declare _actual_collection_date decimal(14,2) default 0 ;
		declare _max_limit decimal(14,2) default 0 ;
		declare _max_lending_limit_per_customer decimal(14,2) default 0 ;
		declare _number_of_cheques_existence integer default 0 ; 
		declare _max_full_date datetime default null ;
		set new.created_at = CURRENT_TIMESTAMP;
		
		
		select `limit`,max_lending_limit_per_customer into _max_limit , _max_lending_limit_per_customer from overdraft_against_commercial_papers where id = new.overdraft_against_commercial_paper_id ;


		select  accumulated_limit  into _previous_accumulated_limit  from overdraft_against_commercial_paper_limits where company_id = new.company_id and overdraft_against_commercial_paper_id =  new.overdraft_against_commercial_paper_id   and  full_date < new.full_date and is_active = 1   order by full_date desc , id desc limit 1 ;
		
		select days_count,received_amount,  status , actual_collection_date into _days_count , _cheque_amount , _cheque_status,_actual_collection_date
		from cheques 
		join overdraft_against_commercial_paper_limits 
		on 
		cheques.id = overdraft_against_commercial_paper_limits.cheque_id 
		join money_received 
		on cheques.money_received_id = money_received.id 
		where cheque_id = new.cheque_id 
		and is_active = 1 
		limit 1 ;
		
		select count(*) , max(full_date) into _number_of_cheques_existence , _max_full_date from overdraft_against_commercial_paper_limits where cheque_id = new.cheque_id and is_active = 1  ; 
	
		select lending_rate into _lending_rate from lending_information where overdraft_against_commercial_paper_id = new.overdraft_against_commercial_paper_id and for_commercial_papers_due_within_days >= _days_count order by for_commercial_papers_due_within_days asc limit 1;
		
		set new.limit =  LEAST(_lending_rate /100 * _cheque_amount , _max_lending_limit_per_customer)  ;
		if(_cheque_status = 'collected'
			and   _number_of_cheques_existence > 1 
			and new.full_date = _max_full_date 
		 )
		 then 
			 set new.limit = new.limit * -1 ;
		 end if;
		set new.accumulated_limit = _previous_accumulated_limit + new.limit ;
		
		

end //
delimiter ;
drop trigger if exists after_insert_overdraft_against_commercial_paper_limits ;
delimiter // 
create  trigger after_insert_overdraft_against_commercial_paper_limits after insert on `overdraft_against_commercial_paper_limits` for each row 

begin 
		declare _facility_start_date date default null ;
		select contract_start_date into _facility_start_date from overdraft_against_commercial_papers where id = new.overdraft_against_commercial_paper_id ;
		update overdraft_against_commercial_paper_bank_statements set updated_at = CURRENT_TIMESTAMP where company_id = new.company_id and overdraft_against_commercial_paper_id = new.overdraft_against_commercial_paper_id and date >= _facility_start_date  order by full_date asc  ;
end //


delimiter ;
drop trigger if exists after_update_overdraft_against_commercial_paper_limits ;
delimiter // 
create  trigger after_update_overdraft_against_commercial_paper_limits after update on `overdraft_against_commercial_paper_limits` for each row 
begin 
	declare _facility_start_date date default null ;
		select contract_start_date into _facility_start_date from overdraft_against_commercial_papers where id = new.overdraft_against_commercial_paper_id ;
		
		update overdraft_against_commercial_paper_bank_statements set updated_at = CURRENT_TIMESTAMP where company_id = new.company_id and overdraft_against_commercial_paper_id = new.overdraft_against_commercial_paper_id and date >= _facility_start_date order by full_date asc  ;
end //
delimiter ; 
drop trigger if exists before_update_overdraft_against_commercial_paper_limits ;
delimiter // 
create  trigger before_update_overdraft_against_commercial_paper_limits before update on `overdraft_against_commercial_paper_limits` for each row 
begin 

		declare _cheque_status varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci default null ;
		declare _days_count integer default 0 ;
		declare _lending_rate decimal(10,4) default 0 ;
		declare _cheque_amount decimal(14,2) default 0 ;
		declare _previous_accumulated_limit decimal(14,2) default 0 ;
		declare _actual_collection_date decimal(14,2) default 0 ;
		declare _max_limit decimal(14,2) default 0 ;
		declare _max_lending_limit_per_customer decimal(14,2) default 0 ;
		declare _number_of_cheques_existence integer default 0 ; 
		declare _max_full_date datetime default null ;
		set new.created_at = CURRENT_TIMESTAMP;
		
		
		select `limit`,max_lending_limit_per_customer into _max_limit , _max_lending_limit_per_customer from overdraft_against_commercial_papers where id = new.overdraft_against_commercial_paper_id ;


		select  accumulated_limit  into _previous_accumulated_limit  from overdraft_against_commercial_paper_limits where company_id = new.company_id and overdraft_against_commercial_paper_id =  new.overdraft_against_commercial_paper_id   and  full_date < new.full_date and is_active = 1   order by full_date desc , id desc limit 1 ;
		
		select days_count,received_amount,  status , actual_collection_date into _days_count , _cheque_amount , _cheque_status,_actual_collection_date
		from cheques 
		join overdraft_against_commercial_paper_limits 
		on 
		cheques.id = overdraft_against_commercial_paper_limits.cheque_id 
		join money_received 
		on cheques.money_received_id = money_received.id 
		where cheque_id = new.cheque_id 
		and is_active = 1 
		limit 1 ;
		
		
		
		
		select count(*) , max(full_date) into _number_of_cheques_existence , _max_full_date from overdraft_against_commercial_paper_limits where cheque_id = new.cheque_id and is_active = 1  ; 
	
		select lending_rate into _lending_rate from lending_information where overdraft_against_commercial_paper_id = new.overdraft_against_commercial_paper_id and for_commercial_papers_due_within_days >= _days_count order by for_commercial_papers_due_within_days asc limit 1;
		
		set new.limit =  LEAST(_lending_rate /100 * _cheque_amount , _max_lending_limit_per_customer)  ;
		
		if(_cheque_status = 'collected'
			and   _number_of_cheques_existence > 1 
			and new.full_date = _max_full_date 
		 )
		 then 
			 set new.limit = new.limit * -1 ;
		 end if;
		set new.accumulated_limit = _previous_accumulated_limit + new.limit ;
	
end //
