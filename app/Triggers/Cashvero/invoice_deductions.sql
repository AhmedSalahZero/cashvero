delimiter ;
drop trigger if exists `insert_total_deductions_amount` ;
DELIMITER //
create trigger  `insert_total_deductions_amount` after insert on `invoice_deductions` for each row 
BEGIN
	declare _amount decimal(14,2) ;
	select sum(amount)  into _amount from invoice_deductions where company_id = new.company_id and invoice_type = new.invoice_type and invoice_id = new.invoice_id;
	if (new.invoice_type = "CustomerInvoice")
	then
	update `customer_invoices` set  total_deductions =_amount  where id = new.invoice_id  ;
	else 
	update `supplier_invoices` set  total_deductions =_amount  where id = new.invoice_id  ;
	end if ;
	
	
END //



delimiter ;
drop trigger if exists update_total_deductions_amount ;
delimiter // 
create trigger  `update_total_deductions_amount` after update on `invoice_deductions` for each row 
BEGIN
	declare _amount decimal(14,2) ;

	select sum(amount) into _amount from invoice_deductions where company_id = new.company_id and invoice_type = new.invoice_type and invoice_id = new.invoice_id ;
		if (new.invoice_type = "CustomerInvoice")
	then
	update `customer_invoices` set  total_deductions = _amount where id = new.invoice_id  ;
	else 
	update `supplier_invoices` set  total_deductions = _amount where id = new.invoice_id  ;
	end if ;


END//
delimiter ;
drop trigger if exists delete_total_deductions_amount ;
delimiter // 
create trigger  `delete_total_deductions_amount` after delete on `invoice_deductions` for each row 
BEGIN
	declare _amount decimal(14,2) ;
	select sum(amount) into _amount from invoice_deductions where company_id = old.company_id and  invoice_type = old.invoice_type and invoice_id = old.invoice_id ;
	if (old.invoice_type = "CustomerInvoice")
	then
	update `customer_invoices` set total_deductions = ifnull(_amount,0) where id = old.invoice_id    ;
	else 
	update `supplier_invoices` set total_deductions = ifnull(_amount,0) where id = old.invoice_id    ;
	end if ;

END//
