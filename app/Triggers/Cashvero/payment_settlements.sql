delimiter ;
drop trigger if exists `insert_total_paid_amount` ;
DELIMITER //
create trigger  `insert_total_paid_amount` after insert on `payment_settlements` for each row 
BEGIN
	declare _settlement_amount decimal(14,2) ;
	declare _withhold_amount decimal(14,2) ;
	declare _total_down_payment_settlement decimal(14,2) ;
	declare _amount_in_invoice_currency decimal(14,2) ;
	select sum(settlement_amount) ,sum(withhold_amount) into _settlement_amount,_withhold_amount from payment_settlements where  invoice_id = new.invoice_id ;
	update `supplier_invoices` set withhold_amount = _withhold_amount  , paid_amount =_settlement_amount  where new.invoice_id  = id;
	
				select sum(settlement_amount) into _total_down_payment_settlement from payment_settlements where money_payment_id = new.money_payment_id  ;
		select amount_in_invoice_currency into _amount_in_invoice_currency from money_payments where id = new.money_payment_id; 
		update `down_payment_money_payment_settlements` set down_payment_balance = _amount_in_invoice_currency - _total_down_payment_settlement where money_payment_id = new.money_payment_id;
		
	
END //




delimiter ;
drop trigger if exists update_total_paid_amount ;
delimiter // 
create trigger  `update_total_paid_amount` after update on `payment_settlements` for each row 
BEGIN
	declare _settlement_amount decimal(14,2) ;
	declare _withhold_amount decimal(14,2) ;
	declare _total_down_payment_settlement decimal(14,2) ;
	declare _amount_in_invoice_currency decimal(14,2) ;
	
	select sum(settlement_amount),sum(withhold_amount)  into _settlement_amount,_withhold_amount from payment_settlements where  invoice_id = new.invoice_id ;
	
	
	update `supplier_invoices` set withhold_amount = _withhold_amount  , paid_amount = _settlement_amount where id = new.invoice_id   ;
	
			select sum(settlement_amount) into _total_down_payment_settlement from payment_settlements where money_payment_id = new.money_payment_id  ;
		select amount_in_invoice_currency into _amount_in_invoice_currency from money_payments where id = new.money_payment_id; 
		update `down_payment_money_payment_settlements` set down_payment_balance = _amount_in_invoice_currency - _total_down_payment_settlement where money_payment_id = new.money_payment_id;
		

END//



delimiter ;
drop trigger if exists delete_total_paid_amount ;
delimiter // 
create trigger  `delete_total_paid_amount` after delete on `payment_settlements` for each row 
BEGIN
	declare _settlement_amount decimal(14,2) ;
	declare _withhold_amount decimal(14,2) ;
	declare _amount_in_invoice_currency decimal(14,2) ;
	declare _total_down_payment_settlement decimal(14,2) ;
	select sum(settlement_amount),sum(withhold_amount) into _settlement_amount,_withhold_amount from payment_settlements where invoice_id = old.invoice_id ;
	update `supplier_invoices` set paid_amount = ifnull(_settlement_amount,0) , withhold_amount = ifnull(_withhold_amount,0) where  id = old.invoice_id   ;
		select sum(settlement_amount) into _total_down_payment_settlement from payment_settlements where money_payment_id = old.money_payment_id  ;
		select amount_in_invoice_currency into _amount_in_invoice_currency from money_payments where id = old.money_payment_id; 
		update `down_payment_money_payment_settlements` set down_payment_balance = _amount_in_invoice_currency - _total_down_payment_settlement where money_payment_id = old.money_payment_id;
END//
