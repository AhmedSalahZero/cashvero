drop trigger if exists before_insert_shareholder_statements ;
delimiter // 
create  trigger before_insert_shareholder_statements before insert on `shareholder_statements` for each row 
begin 
	declare _last_end_balance decimal(14,2) default 0 ;
	declare _previous_date date default null ;
		declare _count_all_rows integer default 0 ; 
		set new.created_at = CURRENT_TIMESTAMP;
		select date , end_balance  into _previous_date,_last_end_balance  from shareholder_statements where  company_id = new.company_id and partner_id = new.partner_id and currency_name = new.currency_name and  date <= new.date   order by date desc , id desc  limit 1 ;
		select  count(*) into _count_all_rows from shareholder_statements where  company_id = new.company_id  and partner_id = new.partner_id and currency_name = new.currency_name and  date <= new.date   order by date desc , id desc limit 1 ;
	 set new.beginning_balance = if(_count_all_rows,_last_end_balance,ifnull(new.beginning_balance,0)); 
	
	set new.end_balance = new.beginning_balance + new.debit - new.credit ; 
	set new.is_debit = if(new.debit > 0 , 1 , 0);
	set new.is_credit = if(new.debit > 0 , 0 , 1);

end //
delimiter ; 
drop trigger if exists before_update_shareholder_statements ;
delimiter // 
create  trigger before_update_shareholder_statements before update on `shareholder_statements` for each row 
begin 
	-- الكود دا نفس الكود اللي في ال
	-- before insert 
	-- فا لو عدلت ال
	-- before insert
	-- خده كوبي وبيست وحطة هنا
		
		declare _last_end_balance decimal(14,2) default 0 ;
		declare _beg_balance_from_form decimal(14,2) default 0 ;
		declare _previous_date date default null ;
		declare _count_all_rows integer default 0 ; 
		-- في حاله التعديل
		select date,end_balance into _previous_date, _last_end_balance  from shareholder_statements where  company_id = new.company_id and partner_id = new.partner_id and currency_name = new.currency_name  and date = new.date and id < new.id order by date desc , id desc limit 1;
		if  (_previous_date)
			then
		select date,end_balance into _previous_date, _last_end_balance  from shareholder_statements where  company_id = new.company_id and partner_id = new.partner_id and currency_name = new.currency_name  and date = new.date and id < new.id order by date desc , id desc limit 1;
			else 
		select date,end_balance into _previous_date, _last_end_balance  from shareholder_statements where  company_id = new.company_id and partner_id = new.partner_id and currency_name = new.currency_name  and date < new.date order by date desc , id desc limit 1;
					 
			end if ;
			
		set _count_all_rows =1 ;
	
	 set new.beginning_balance = _last_end_balance ;
	 
	set new.end_balance = new.beginning_balance + new.debit - new.credit ; 
	
end //
 delimiter ; 
drop trigger if exists refresh_calculation_before_delete_shareholder_statements ;
  delimiter //  
  
create  trigger refresh_calculation_before_delete_shareholder_statements before delete on `shareholder_statements` for each row 
begin 
	delete from `temp_deleted_statements` where company_id = old.company_id and table_name = 'shareholder_statements';
	insert into `temp_deleted_statements` (company_id,table_name,deleted_id) values (old.company_id,'shareholder_statements',old.id);
end //
 delimiter ; 
 