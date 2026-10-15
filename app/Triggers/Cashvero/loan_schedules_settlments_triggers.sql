

drop trigger if exists insert_loan_schedule_settlement ;
delimiter //
CREATE TRIGGER `insert_loan_schedule_settlement` after INSERT
	ON `loan_schedule_settlements` FOR EACH ROW
	begin
		declare _total_paid_amount decimal(14,2) default 0 ;
		declare _medium_term_loan_id integer default 0 ;
		select medium_term_loan_id into _medium_term_loan_id from loan_schedule_settlements join loan_schedules on loan_schedules.id =  loan_schedule_settlements.loan_schedule_id where loan_schedule_settlements.id = new.id  ;
		select sum(amount) into _total_paid_amount from loan_schedule_settlements  join loan_schedules on loan_schedules.id =  loan_schedule_settlements.loan_schedule_id where medium_term_loan_id = _medium_term_loan_id  ;
		update medium_term_loans set paid_amount =  _total_paid_amount    where id = _medium_term_loan_id;
		update medium_term_loans set  outstanding_amount = `limit` - paid_amount    where id = _medium_term_loan_id;
	
end//
delimiter ;
drop trigger if exists update_loan_schedule_settlement ;
delimiter // 
	-- 1-1
CREATE TRIGGER `update_loan_schedule_settlement` BEFORE
UPDATE
	ON `loan_schedule_settlements` FOR EACH ROW
	begin
	
	

END//
