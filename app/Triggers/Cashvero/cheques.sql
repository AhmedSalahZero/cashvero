delimiter ;
drop trigger if exists before_update_cheques ;
delimiter // 
create  trigger before_update_cheques before update on `cheques` for each row 
begin 	
	if(new.due_date and new.deposit_date)
	then 
		set new.days_count = datediff(new.due_date,new.deposit_date);
	end if ;
end //
