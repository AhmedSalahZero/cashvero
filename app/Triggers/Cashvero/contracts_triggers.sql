-- تطبيع عملة العقد : 'EUR' -> 'EURO'
--
-- العملة القياسية في النظام هي 'EURO' — دي القيمة اللي getCurrencies()
-- بترجّعها ، و اللي منتقي العملات في التقارير بيبعتها ، و اللي أسعار
-- الصرف متخزّنة بيها في foreign_exchange_rates .
--
-- نفس التطبيع ده موجود من زمان في customer_invoices_triggers.sql و
-- supplier_invoices_triggers.sql ، لكن جدول contracts كان مستثنى —
-- فعقد عملته 'EUR' كان بيقع بين كرسيين :
--   ١. تقرير الـ Consolidated بيفلتر whereIn(currency, [... 'EURO' ...])
--      فما كانش بيلقطه اصلا .
--   ٢. تقرير الشركة بيلقطه ، بس getExchangeRateAtOrOne('EUR','EGP')
--      بترجّع 1 لان مفيش سعر متخزّن بالاسم ده — فمبلغ اليورو كان بيتعرض
--      على انه جنيه ، اقل من قيمته الحقيقية بحوالي 56 مرة .
--
-- التريجر مقصود يكون على مستوى الداتابيز مش mutator في الموديل ، لان
-- جدول contracts بيتكتب من اكتر من موديل (App\Models\Contract و
-- App\Models\PropertyManagement\Contract) و كمان من الاستيراد و مزامنة
-- اودو — و الـ mutator بيغطي مسار واحد بس .

delimiter ;
drop trigger if exists `normalize_contract_currency_before_insert` ;
DELIMITER //
create trigger `normalize_contract_currency_before_insert` before insert on `contracts` for each row
BEGIN
	IF (new.currency = 'EUR') THEN
		SET new.currency = 'EURO';
	END IF;
END //

delimiter ;
drop trigger if exists `normalize_contract_currency_before_update` ;
DELIMITER //
create trigger `normalize_contract_currency_before_update` before update on `contracts` for each row
BEGIN
	IF (new.currency = 'EUR') THEN
		SET new.currency = 'EURO';
	END IF;
END //

delimiter ;
