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
		declare _partner_id bigint unsigned default null ;
		declare _already_active_customer_total decimal(14,2) default 0 ;
		declare _remaining_customer_room decimal(14,2) default 0 ;
		declare _remaining_facility_room decimal(14,2) default 0 ;
		declare _terms_history_id bigint unsigned default null ;
		set new.created_at = CURRENT_TIMESTAMP;
		
		
		select `limit`,max_lending_limit_per_customer into _max_limit , _max_lending_limit_per_customer from overdraft_against_commercial_papers where id = new.overdraft_against_commercial_paper_id ;


		select  accumulated_limit  into _previous_accumulated_limit  from overdraft_against_commercial_paper_limits where company_id = new.company_id and overdraft_against_commercial_paper_id =  new.overdraft_against_commercial_paper_id   and  full_date < new.full_date and is_active = 1   order by full_date desc , id desc limit 1 ;
		
		select days_count,received_amount,  status , actual_collection_date , money_received.partner_id into _days_count , _cheque_amount , _cheque_status,_actual_collection_date , _partner_id
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
	
		-- Facility Renewal — Phase 3: resolve the CHAPTER active on this
		-- cheque's own deposit date (new.full_date) first, then look up
		-- the rate ONLY among that chapter's own tiers. This is what makes
		-- a cheque's rate permanently locked to whatever schedule was in
		-- force the day it was deposited, per the client's confirmed rule
		-- — a later renewal's tiers never retroactively apply to it.
		select id into _terms_history_id from overdraft_against_commercial_paper_terms_histories where overdraft_against_commercial_paper_id = new.overdraft_against_commercial_paper_id and effective_date <= date(new.full_date) order by effective_date desc , id desc limit 1;
		select lending_rate into _lending_rate from lending_information where overdraft_against_commercial_paper_id = new.overdraft_against_commercial_paper_id and terms_history_id = _terms_history_id and for_commercial_papers_due_within_days >= _days_count order by for_commercial_papers_due_within_days asc limit 1;

		-- ⚠️ REAL BUG FIXED HERE (client-confirmed, 2026-08-11): the
		-- per-customer cap used to be checked against THIS cheque's own
		-- contribution alone, so five separate cheques from the same
		-- customer could each individually pass the cap and together
		-- blow far past it. A bank's real risk exposure to a customer is
		-- CUMULATIVE across every currently-outstanding cheque from them
		-- — so the cap must be checked against the customer's total
		-- existing exposure (every other active, positive contribution
		-- from their cheques on this facility) plus this new one.
		-- ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): a collected
		-- cheque's contribution isn't removed or shrunk — a SEPARATE row
		-- with a NEGATIVE amount gets added to cancel it out, while the
		-- original positive row stays active forever (by design, so the
		-- history stays intact). The first version of this fix only
		-- summed POSITIVE rows, so a reversal's negative row was silently
		-- thrown away — the customer's exposure looked permanently stuck
		-- at whatever it peaked at, even long after they'd paid it down.
		-- Removing the `limit > 0` filter sums the NET of everything
		-- active for that customer — deposits add, reversals subtract —
		-- exactly the dynamic, checked-fresh-at-every-deposit behavior
		-- the client described.
		select ifnull(sum(ocpl.limit),0) into _already_active_customer_total
		from overdraft_against_commercial_paper_limits ocpl
		join cheques c2 on c2.id = ocpl.cheque_id
		join money_received mr2 on c2.money_received_id = mr2.id
		where ocpl.overdraft_against_commercial_paper_id = new.overdraft_against_commercial_paper_id
		and ocpl.is_active = 1
		and mr2.partner_id = _partner_id
		and ocpl.cheque_id != new.cheque_id ;
		set _already_active_customer_total = ifnull(_already_active_customer_total,0);
		set _remaining_customer_room = GREATEST(_max_lending_limit_per_customer - _already_active_customer_total , 0);

		-- ⚠️ REAL BUG FIXED HERE (client-confirmed, 2026-08-11): the
		-- facility's own overall limit was fetched (_max_limit) but never
		-- actually used anywhere — the accumulated total from every
		-- outstanding cheque was never capped by it at all. Same
		-- cumulative-net logic as the customer cap: room is whatever's
		-- left of the facility's limit after the running accumulated
		-- total so far (_previous_accumulated_limit, which already nets
		-- collections via their negative reversal rows), never negative.
		set _remaining_facility_room = GREATEST(_max_limit - _previous_accumulated_limit , 0);

		set new.limit =  LEAST(_lending_rate /100 * _cheque_amount , _remaining_customer_room , _remaining_facility_room)  ;
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
		declare _partner_id bigint unsigned default null ;
		declare _already_active_customer_total decimal(14,2) default 0 ;
		declare _remaining_customer_room decimal(14,2) default 0 ;
		declare _remaining_facility_room decimal(14,2) default 0 ;
		declare _terms_history_id bigint unsigned default null ;
		set new.created_at = CURRENT_TIMESTAMP;
		
		
		select `limit`,max_lending_limit_per_customer into _max_limit , _max_lending_limit_per_customer from overdraft_against_commercial_papers where id = new.overdraft_against_commercial_paper_id ;


		select  accumulated_limit  into _previous_accumulated_limit  from overdraft_against_commercial_paper_limits where company_id = new.company_id and overdraft_against_commercial_paper_id =  new.overdraft_against_commercial_paper_id   and  full_date < new.full_date and is_active = 1   order by full_date desc , id desc limit 1 ;
		
		select days_count,received_amount,  status , actual_collection_date , money_received.partner_id into _days_count , _cheque_amount , _cheque_status,_actual_collection_date , _partner_id
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
	
		-- Facility Renewal — Phase 3: resolve the CHAPTER active on this
		-- cheque's own deposit date (new.full_date) first, then look up
		-- the rate ONLY among that chapter's own tiers. This is what makes
		-- a cheque's rate permanently locked to whatever schedule was in
		-- force the day it was deposited, per the client's confirmed rule
		-- — a later renewal's tiers never retroactively apply to it.
		select id into _terms_history_id from overdraft_against_commercial_paper_terms_histories where overdraft_against_commercial_paper_id = new.overdraft_against_commercial_paper_id and effective_date <= date(new.full_date) order by effective_date desc , id desc limit 1;
		select lending_rate into _lending_rate from lending_information where overdraft_against_commercial_paper_id = new.overdraft_against_commercial_paper_id and terms_history_id = _terms_history_id and for_commercial_papers_due_within_days >= _days_count order by for_commercial_papers_due_within_days asc limit 1;

		-- ⚠️ REAL BUG FIXED HERE (client-confirmed, 2026-08-11): the
		-- per-customer cap used to be checked against THIS cheque's own
		-- contribution alone, so five separate cheques from the same
		-- customer could each individually pass the cap and together
		-- blow far past it. A bank's real risk exposure to a customer is
		-- CUMULATIVE across every currently-outstanding cheque from them
		-- — so the cap must be checked against the customer's total
		-- existing exposure (every other active, positive contribution
		-- from their cheques on this facility) plus this new one.
		-- ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): a collected
		-- cheque's contribution isn't removed or shrunk — a SEPARATE row
		-- with a NEGATIVE amount gets added to cancel it out, while the
		-- original positive row stays active forever (by design, so the
		-- history stays intact). The first version of this fix only
		-- summed POSITIVE rows, so a reversal's negative row was silently
		-- thrown away — the customer's exposure looked permanently stuck
		-- at whatever it peaked at, even long after they'd paid it down.
		-- Removing the `limit > 0` filter sums the NET of everything
		-- active for that customer — deposits add, reversals subtract —
		-- exactly the dynamic, checked-fresh-at-every-deposit behavior
		-- the client described.
		select ifnull(sum(ocpl.limit),0) into _already_active_customer_total
		from overdraft_against_commercial_paper_limits ocpl
		join cheques c2 on c2.id = ocpl.cheque_id
		join money_received mr2 on c2.money_received_id = mr2.id
		where ocpl.overdraft_against_commercial_paper_id = new.overdraft_against_commercial_paper_id
		and ocpl.is_active = 1
		and mr2.partner_id = _partner_id
		and ocpl.cheque_id != new.cheque_id ;
		set _already_active_customer_total = ifnull(_already_active_customer_total,0);
		set _remaining_customer_room = GREATEST(_max_lending_limit_per_customer - _already_active_customer_total , 0);

		-- ⚠️ REAL BUG FIXED HERE (client-confirmed, 2026-08-11): the
		-- facility's own overall limit was fetched (_max_limit) but never
		-- actually used anywhere — the accumulated total from every
		-- outstanding cheque was never capped by it at all. Same
		-- cumulative-net logic as the customer cap: room is whatever's
		-- left of the facility's limit after the running accumulated
		-- total so far (_previous_accumulated_limit, which already nets
		-- collections via their negative reversal rows), never negative.
		set _remaining_facility_room = GREATEST(_max_limit - _previous_accumulated_limit , 0);

		set new.limit =  LEAST(_lending_rate /100 * _cheque_amount , _remaining_customer_room , _remaining_facility_room)  ;
		
		if(_cheque_status = 'collected'
			and   _number_of_cheques_existence > 1 
			and new.full_date = _max_full_date 
		 )
		 then 
			 set new.limit = new.limit * -1 ;
		 end if;
		set new.accumulated_limit = _previous_accumulated_limit + new.limit ;
	
end //
