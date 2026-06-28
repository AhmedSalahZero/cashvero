

-- 1-1
delimiter ;
drop trigger if exists insert_net_invoice_amount_for_suppliers ;

delimiter //
CREATE TRIGGER `insert_net_invoice_amount_for_suppliers` BEFORE INSERT
	ON `supplier_invoices` FOR EACH ROW
	begin
	
	set new.withhold_amount_in_main_currency = (new.withhold_amount * new.exchange_rate);
	set new.total_withhold_amount = new.withhold_amount + new.odoo_withhold_amount;
	set new.total_withhold_amount_in_main_currency = new.withhold_amount_in_main_currency + new.odoo_withhold_amount_in_main_currency;
	
	
		set @totalInvoiceAmount := ifnull(new.invoice_amount,0)  + ifnull(new.vat_amount,0) - ifnull(new.discount_amount,0) ;
	set new.net_invoice_amount =  @totalInvoiceAmount  ;
	set new.invoice_amount_in_main_currency = (new.invoice_amount * new.exchange_rate);	
	set new.paid_amount_in_main_currency = new.paid_amount * new.exchange_rate;
	
	set new.total_paid_amount = new.paid_amount + new.odoo_paid_amount;
	 set new.total_paid_amount_in_main_currency = new.paid_amount_in_main_currency + new.odoo_paid_amount_in_main_currency;
	
	
	set new.discount_amount_in_main_currency = (new.discount_amount * new.exchange_rate);	
	set new.net_balance = round(@totalInvoiceAmount - ifnull(new.total_withhold_amount,0) - ifnull(new.total_paid_amount,0) - new.total_deductions,2);
	set new.net_balance_in_main_currency = round(new.net_balance * new.exchange_rate,2) ;
	IF(new.currency = 'EUR') then 
		set new.currency = 'EURO';
	end if; 
	
	set new.net_invoice_amount_in_main_currency = (new.net_invoice_amount * new.exchange_rate);
	set new.total_deductions_in_main_currency = new.total_deductions * new.exchange_rate;
	set new.vat_amount_in_main_currency = (new.vat_amount * new.exchange_rate);
	
	
	IF (NEW.net_balance = 0 ) THEN
			SET  NEW.invoice_status = 'paid';
		ELSEIF(ifnull(NEW.total_paid_amount,0) + ifnull(NEW.total_withhold_amount,0) > 0 and DATE(NEW.invoice_due_date) < DATE(NOW() )) THEN 
		SET  NEW.invoice_status = 'partially_paid_and_past_due'; 
	ELSEIF( DATE(NEW.invoice_due_date) > DATE(NOW() )) THEN 
		SET  NEW.invoice_status = 'not_due_yet'; 
	ELSEIF( DATE(NEW.invoice_due_date) = DATE(NOW() )) THEN 
		SET  NEW.invoice_status = 'due_to_day';

	ELSEIF(ifnull(NEW.total_paid_amount,0) + ifnull(NEW.total_withhold_amount,0) = 0 and DATE(NEW.invoice_due_date) < DATE(NOW() )) THEN 
		SET  NEW.invoice_status = 'past_due';            
		END IF;
		
		set new.invoice_month = LPAD(MONTH(new.invoice_date), 2, 0);
		set new.invoice_year = YEAR(new.invoice_date);
		
	
end//
delimiter ;
drop trigger if exists update_net_invoice_amount_for_suppliers ;
delimiter // 
	-- 1-1
CREATE TRIGGER `update_net_invoice_amount_for_suppliers` BEFORE
UPDATE
	ON `supplier_invoices` FOR EACH ROW
	begin
	
	set new.withhold_amount_in_main_currency = (new.withhold_amount * new.exchange_rate);
	set new.total_withhold_amount = new.withhold_amount + new.odoo_withhold_amount;
	set new.total_withhold_amount_in_main_currency = new.withhold_amount_in_main_currency + new.odoo_withhold_amount_in_main_currency;
	
	
	
	set @totalInvoiceAmount := ifnull(new.invoice_amount,0)  + ifnull(new.vat_amount,0) - ifnull(new.discount_amount,0) ;
	set @totalInvoiceAmountInMainCurrency := ifnull(new.invoice_amount_in_main_currency,0)  + ifnull(new.vat_amount_in_main_currency,0) - ifnull(new.discount_amount_in_main_currency,0) ;
	set new.net_invoice_amount = ( @totalInvoiceAmount );
	set new.net_invoice_amount_in_main_currency = (new.net_invoice_amount * new.exchange_rate);
	set new.invoice_amount_in_main_currency = (new.invoice_amount * new.exchange_rate);
	set new.paid_amount_in_main_currency = new.paid_amount * new.exchange_rate;
	
	
	 set new.total_paid_amount = new.paid_amount + new.odoo_paid_amount;
	 set new.total_paid_amount_in_main_currency = new.paid_amount_in_main_currency + new.odoo_paid_amount_in_main_currency;

	
	
	set new.total_deductions_in_main_currency = new.total_deductions * new.exchange_rate;
	set new.discount_amount_in_main_currency = (new.discount_amount * new.exchange_rate);
	set new.vat_amount_in_main_currency = (new.vat_amount * new.exchange_rate);
	
	set new.net_balance = round(@totalInvoiceAmount - ifnull(new.total_withhold_amount,0) - ifnull(new.total_paid_amount,2) - new.total_deductions,2);
	set new.net_balance_in_main_currency = round(new.net_balance * new.exchange_rate,2);
	IF(new.currency = 'EUR') then 
		set new.currency = 'EURO';
	end if; 
		
	
	 IF (new.net_balance = 0 ) THEN
        SET  new.invoice_status = 'paid';
     ELSEIF(ifnull(new.total_paid_amount,0) + ifnull(new.total_withhold_amount,0) > 0 and DATE(new.invoice_due_date) < DATE(NOW() )) THEN 
     SET  new.invoice_status = 'partially_paid_and_past_due'; 
 	ELSEIF( DATE(new.invoice_due_date) > DATE(NOW() )) THEN 
     SET  new.invoice_status = 'not_due_yet'; 
	ELSEIF( DATE(new.invoice_due_date) = DATE(NOW() )) THEN 
     SET  new.invoice_status = 'due_to_day';

	 ELSEIF(ifnull(new.total_paid_amount,0) + ifnull(new.total_withhold_amount,0) = 0 and DATE(new.invoice_due_date) < DATE(NOW() )) THEN 
     SET  new.invoice_status = 'past_due';
	-- else 
	-- set new.invoice_status=new.net_balance;            
    END IF ;
	set new.invoice_month = LPAD(MONTH(new.invoice_date), 2, 0);
	set new.invoice_year = YEAR(new.invoice_date);

		
END//
delimiter ;
drop trigger if exists remove_supplier_after_delete_its_invoice ;
delimiter //
create trigger remove_supplier_after_delete_its_invoice  after delete 	ON `supplier_invoices` FOR EACH ROW
begin 
	-- declare _length integer default 0 ;
	-- select count(*) into _length from `supplier_invoices` where supplier_id=old.supplier_id   ;
	-- if _length = 0  
	-- then 
	-- delete from `partners` where  id = old.supplier_id; 
	-- end if ;
end //  
