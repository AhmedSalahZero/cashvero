delimiter ;
drop trigger if exists insert_total_collected_amount ;
DELIMITER //
create trigger  `insert_total_collected_amount` after insert on `settlements` for each row 
BEGIN
	declare _settlement_amount decimal(14,2) ;
	declare _total_down_payment_settlement decimal(14,2) ;
	declare _withhold_amount decimal(14,2) ;
	declare _amount_in_invoice_currency decimal(14,2) ;
	select sum(settlement_amount) into _settlement_amount from settlements where  invoice_id = new.invoice_id ;
	select sum(withhold_amount) into _withhold_amount from settlements where  invoice_id = new.invoice_id ;
	
	select sum(settlement_amount) into _total_down_payment_settlement from settlements where money_received_id = new.money_received_id  ;
	
	update `customer_invoices` set withhold_amount = _withhold_amount  where id = new.invoice_id   ;
	 update `customer_invoices` set collected_amount = _settlement_amount  where id  = new.invoice_id   ;
	select amount_in_invoice_currency into _amount_in_invoice_currency from money_received where id = new.money_received_id; 
	 	update `down_payment_settlements` set down_payment_balance = _amount_in_invoice_currency - _total_down_payment_settlement where money_received_id = new.money_received_id;

END //



delimiter ;
drop trigger if exists update_total_collected_amount ;
delimiter //
create trigger  `update_total_collected_amount` after update on `settlements` for each row 
BEGIN
	declare _settlement_amount decimal(14,2) ;
	declare _withhold_amount decimal(14,2) ;
		declare _total_down_payment_settlement decimal(14,2) ;
		declare _amount_in_invoice_currency decimal(14,2) ;
		
	select sum(settlement_amount) into _settlement_amount from settlements where  invoice_id = new.invoice_id  ;
	select sum(withhold_amount) into _withhold_amount from settlements where  invoice_id = new.invoice_id   ;
	select sum(settlement_amount) into _total_down_payment_settlement from settlements where money_received_id = new.money_received_id  ;
	update `customer_invoices` set collected_amount = _settlement_amount where id = new.invoice_id   ;
	update `customer_invoices` set withhold_amount = _withhold_amount where id = new.invoice_id   ;
	select amount_in_invoice_currency into _amount_in_invoice_currency from money_received where id = new.money_received_id; 
	update `down_payment_settlements` set down_payment_balance = _amount_in_invoice_currency - _total_down_payment_settlement where money_received_id = new.money_received_id;
	
END//
delimiter ;
drop trigger if exists delete_total_collected_amount ;
delimiter //
create trigger  `delete_total_collected_amount` after delete on `settlements` for each row 
BEGIN
	declare _settlement_amount decimal(14,2) ;
	declare _withhold_amount decimal(14,2) ;
	declare _total_down_payment_settlement decimal(14,2) ;
	declare _amount_in_invoice_currency decimal(14,2) ;
	
	select sum(settlement_amount) into _settlement_amount from settlements where  invoice_id = old.invoice_id ;
	select sum(withhold_amount) into _withhold_amount from settlements where  invoice_id = old.invoice_id ;
	select sum(settlement_amount) into _total_down_payment_settlement from settlements where money_received_id = old.money_received_id  ;
	
	update `customer_invoices` set collected_amount = ifnull(_settlement_amount,0)   where id = old.invoice_id    ;
	update `customer_invoices` set withhold_amount = ifnull(_withhold_amount,0)  where id = old.invoice_id    ;
		select amount_in_invoice_currency into _amount_in_invoice_currency from money_received where id = old.money_received_id; 
		update `down_payment_settlements` set down_payment_balance = _amount_in_invoice_currency - _total_down_payment_settlement where money_received_id = old.money_received_id;
END//
