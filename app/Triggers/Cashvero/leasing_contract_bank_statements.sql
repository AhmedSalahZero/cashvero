-- ---------------------------------------------------------------------
-- Leasing Contract bank statement — balance/room cascade.
--
-- A straight copy of medium_term_loan_bank_statements.sql keyed to
-- leasing_contract_id: same sign convention, same room formula, NO
-- interest in the balance equation and NO withdrawal/settlement
-- machinery.
--
--   credit  = drawdown (the leasing company paid a supplier out of the
--             contract)  -> end_balance goes negative, room shrinks
--   debit   = principle portion of a repaid installment
--             -> end_balance moves back toward zero, room replenishes
--
--   end_balance = beginning_balance + debit - credit
--   room        = limit + end_balance      (end_balance is <= 0 when drawn)
--
-- Why interest_amount is recorded but never added: a leasing
-- installment already bundles its interest inside schedule_payment.
-- Moving the drawn balance by it here as well would double-count it.
-- Only the PRINCIPLE ever reaches end_balance.
--
-- Ordering key is full_date (then id), matching the MTL statement
-- rather than clean overdraft's date/priority/id — this table has no
-- priority semantics to preserve.
-- ---------------------------------------------------------------------

drop trigger if exists refresh_calculation_before_insert_lcbs ;
delimiter //
create trigger refresh_calculation_before_insert_lcbs before insert on `leasing_contract_bank_statements` for each row
begin
	declare _last_end_balance decimal(14,2) default 0 ;
	declare _count_all_rows integer default 0 ;
	declare _facility_limit decimal(14,2) default null ;

	set new.created_at = CURRENT_TIMESTAMP;

	select end_balance into _last_end_balance from leasing_contract_bank_statements
		where company_id = new.company_id
		and leasing_contract_id = new.leasing_contract_id
		and (full_date < new.full_date or (full_date = new.full_date and id < new.id))
		order by full_date desc , id desc limit 1 ;

	select count(*) into _count_all_rows from leasing_contract_bank_statements
		where company_id = new.company_id
		and leasing_contract_id = new.leasing_contract_id
		and (full_date < new.full_date or (full_date = new.full_date and id < new.id)) ;

	set new.beginning_balance = if(_count_all_rows, ifnull(_last_end_balance,0), ifnull(new.beginning_balance,0)) ;

	-- The limit always comes from the contract itself, never from whatever
	-- the application happened to pass in, so a backdated row can't be
	-- stamped with a stale limit.
	select `limit` into _facility_limit from leasing_contracts where id = new.leasing_contract_id ;
	set new.limit = ifnull(_facility_limit, ifnull(new.limit,0)) ;

	set new.debit  = ifnull(new.debit,0) ;
	set new.credit = ifnull(new.credit,0) ;
	set new.interest_amount = ifnull(new.interest_amount,0) ;
	set new.end_balance = new.beginning_balance + new.debit - new.credit ;
	set new.room = new.limit + new.end_balance ;
	set new.is_debit  = if(new.debit  > 0 , 1 , 0) ;
	set new.is_credit = if(new.credit > 0 , 1 , 0) ;
end //
delimiter ;

drop trigger if exists refresh_calculation_before_update_lcbs ;
delimiter //
create trigger refresh_calculation_before_update_lcbs before update on `leasing_contract_bank_statements` for each row
begin
	-- Same body as the before-insert trigger. If you change one, copy it
	-- into the other — that is the convention every other statement table
	-- in this codebase already follows.
	declare _last_end_balance decimal(14,2) default 0 ;
	declare _facility_limit decimal(14,2) default null ;

	select end_balance into _last_end_balance from leasing_contract_bank_statements
		where company_id = new.company_id
		and leasing_contract_id = new.leasing_contract_id
		and (full_date < new.full_date or (full_date = new.full_date and id < new.id))
		order by full_date desc , id desc limit 1 ;

	set new.beginning_balance = ifnull(_last_end_balance,0) ;

	select `limit` into _facility_limit from leasing_contracts where id = new.leasing_contract_id ;
	set new.limit = ifnull(_facility_limit, ifnull(new.limit,0)) ;

	set new.debit  = ifnull(new.debit,0) ;
	set new.credit = ifnull(new.credit,0) ;
	set new.interest_amount = ifnull(new.interest_amount,0) ;
	set new.end_balance = new.beginning_balance + new.debit - new.credit ;
	set new.room = new.limit + new.end_balance ;
	set new.is_debit  = if(new.debit  > 0 , 1 , 0) ;
	set new.is_credit = if(new.credit > 0 , 1 , 0) ;
end //
delimiter ;

drop trigger if exists refresh_calculation_before_delete_lcbs ;
delimiter //
create trigger refresh_calculation_before_delete_lcbs before delete on `leasing_contract_bank_statements` for each row
begin
	delete from `temp_deleted_statements` where company_id = old.company_id and table_name = 'leasing_contract_bank_statements';
	insert into `temp_deleted_statements` (company_id,table_name,deleted_id) values (old.company_id,'leasing_contract_bank_statements',old.id);
end //
delimiter ;
