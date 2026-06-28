

drop trigger if exists insert_loan_schedule_status_for_customers ;
delimiter //
CREATE TRIGGER `insert_loan_schedule_status_for_customers` BEFORE INSERT
	ON `loan_schedules` FOR EACH ROW
	begin
		
	IF (NEW.remaining = 0 ) THEN
			SET  NEW.status = 'paid';
		ELSEIF(ifnull(NEW.remaining,0)  > 0 and ifnull(NEW.remaining,0)  < new.schedule_payment and DATE(NEW.date) < DATE(NOW() )) THEN 
		SET  NEW.status = 'partially_paid_and_past_due'; 
	ELSEIF( DATE(NEW.date) > DATE(NOW() )) THEN 
		SET  NEW.status = 'not_due_yet'; 
	ELSEIF( DATE(NEW.date) = DATE(NOW() )) THEN 
		SET  NEW.status = 'due_to_day';
	ELSEIF(ifnull(NEW.remaining,0)  = new.schedule_payment and DATE(NEW.date) < DATE(NOW() )) THEN 
		SET  NEW.status = 'past_due';            
		END IF;
	
end//
delimiter ;
drop trigger if exists update_loan_schedule_status ;
delimiter // 
	-- 1-1
CREATE TRIGGER `update_loan_schedule_status` BEFORE
UPDATE
	ON `loan_schedules` FOR EACH ROW
	begin
	
	 IF (new.remaining = 0 ) THEN
        SET  new.status = 'paid';
     ELSEIF(ifnull(NEW.remaining,0)  > 0 and ifnull(NEW.remaining,0)  < new.schedule_payment and DATE(NEW.date) < DATE(NOW() )) THEN 
     SET  new.status = 'partially_paid_and_past_due'; 
 	ELSEIF( DATE(new.date) > DATE(NOW() )) THEN 
     SET  new.status = 'not_due_yet'; 
	ELSEIF( DATE(new.date) = DATE(NOW() )) THEN 
     SET  new.status = 'due_to_day';

	 ELSEIF(ifnull(new.remaining,0)  = new.schedule_payment and DATE(new.date) < DATE(NOW() )) THEN 
     SET  new.status = 'past_due';
	-- else 
	-- set new.status=new.net_balance;            
    END IF ;

END//
