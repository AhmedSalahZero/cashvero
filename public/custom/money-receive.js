
$(document).on('click', '#js-drawee-bank', function (e) {
	e.preventDefault()
	$('#js-choose-bank-id').modal('show')
})
$(document).on('click', '.js-drawee-bank-class', function (e) {
	e.preventDefault()
	$('#js-choose-bank-id').modal('show')
})
$(document).on('click', '#js-append-bank-name-if-not-exist', function () {
	const receivingBank = document.getElementById('js-drawee-bank').parentElement
	const newBankId = $('#js-bank-names').val()
	const newBankName = $('#js-bank-names option:selected').attr('data-name')
	const isBankExist = $(receivingBank).find('option[value="' + newBankId + '"]').length

	if (!isBankExist) {
		const option = '<option selected value="' + newBankId + '">' + newBankName + '</option>'

		$('#js-drawee-bank').parent().find('select').append(option)
	}
	$('#js-choose-bank-id').modal('hide')
})


$(document).on('click', '.js-append-bank-name-if-not-exist-in-repeater', function () {
	const newBankId = $('#js-bank-names').val()
	const newBankName = $('#js-bank-names option:selected').attr('data-name')
	$('select.drawee-bank-class').each(function (index, selectElement) {
		const isBankExist = $(selectElement).find('option[value="' + newBankId + '"]').length
		if (!isBankExist) {
			const option = '<option  value="' + newBankId + '">' + newBankName + '</option>'
			$(selectElement).append(option).selectpicker("refresh")
		}
	})


	$('#js-choose-bank-id').modal('hide')
})

$(document).on('click', '#js-receiving-bank', function (e) {
	e.preventDefault()
	$('#js-choose-receiving-bank-id').modal('show')
})

$(document).on('click', '#js-append-receiving-bank-name-if-not-exist', function () {
	const receivingBank = document.getElementById('js-receiving-bank').parentElement
	const newBankId = $('#js-receiving-bank-names').val()
	const newBankName = $('#js-receiving-bank-names').find('option:selected').attr('data-name')
	const isBankExist = $(receivingBank).parent().find('select').find('option[value="' + newBankId + '"]').length
	if (!isBankExist) {
		const option = '<option selected value="' + newBankId + '">' + newBankName + '</option>'
		$('#js-receiving-bank').parent().find('select').append(option)
	}
	$('#js-choose-receiving-bank-id').modal('hide')
})






$(document).on('click', '#js-receiving-branch', function (e) {
	e.preventDefault()
	$('#js-choose-receiving-branch-id').modal('show')

})

$(document).on('click', '#js-append-receiving-branch-name-if-not-exist', function () {
	const receivingBranch = document.getElementById('js-receiving-branch').parentElement
	const newBranchName = $('#js-receiving-branch-names').val()
	const isBranchExist = $(receivingBranch).parent().find('select').find('option[value="' + newBranchName + '"]').length
	if (!isBranchExist) {
		const option = '<option selected value="' + newBranchName + '">' + newBranchName + '</option>'
		$('#js-receiving-branch').parent().find('select').append(option)
	}
	$('#js-choose-receiving-branch-id').modal('hide')
})




$(document).on('change', '.ajax-get-sales-orders-for-contract', function () {
	let inEditMode = +$('#js-in-edit-mode').val()
	inEditMode = inEditMode ? inEditMode : 0
	let onlyOneSalesOrder = +$('#ajax-sales-order-item').attr('data-single-model')
	let specificSalesOrder = $('#ajax-sales-order-item').val()
	let downPaymentId = +$('#js-down-payment-id').val()
	downPaymentId = isNaN(downPaymentId) ? 0 : downPaymentId ;
	let contractId = $('select#contract-id').val()
	contractId = contractId ? contractId : $(this).closest('[data-repeater-item]').find('select.customer-name-js').val()
	let currency = $('select.current-currency').val()
	currency = currency ? currency : $(this).closest('[data-repeater-item]').find('select.current-currency').val()
	const companyId = $('body').attr('data-current-company-id')
	const lang = $('body').attr('data-lang')
	if(!contractId){
		$('.js-append-down-payment-to').empty().hide()
		return ;
	}else{
		$('.js-append-down-payment-to').show()
		
	}
	const url = '/' + lang + '/' + companyId + '/down-payments/get-sales-orders-for-contract/' + contractId + '/' + currency


		$.ajax({
			url,
			data: {
				inEditMode
				, down_payment_id: downPaymentId
			}
		}).then(function (res) {
	
			// second add settlements repeater 
			var lastNode = $('.js-down-payment-template .js-duplicate-node').clone(true)
			
			$('.js-append-down-payment-to').empty()
			if(res.sales_orders.length == 0){
				res.sales_orders[0] = {
					id:-1,
					so_number:"General",
					received_amount:0,
					amount:0,
				}
			}
			for (var i = 0; i < res.sales_orders.length; i++) {
				 var salesOrderId = res.sales_orders[i].id
				 var salesOrderNumber = res.sales_orders[i].so_number

				var amount = res.sales_orders[i].amount
				var receivedAmount = res.sales_orders[i].received_amount
				var domSalesOrder = $(lastNode).find('.js-sales-order-number')
				domSalesOrder.val(salesOrderId)
				domSalesOrder.attr('name', 'sales_orders_amounts[' + salesOrderId + '][sales_order_id]').val(salesOrderId)

				var domSalesOrder = $(lastNode).find('.js-sales-order-name')
				domSalesOrder.val(salesOrderNumber)
				$(lastNode).find('.contract-currency').html('[ ' + currency +' ]')
				domSalesOrder.attr('name', 'sales_orders_amounts[' + salesOrderId + '][sales_order_name]').val(salesOrderNumber)

				
				if (!onlyOneSalesOrder || (onlyOneSalesOrder && salesOrderId == specificSalesOrder)) {
					$(lastNode).find('.js-amount').val(number_format(amount, 2))
				
					var domReceivedAmount = $(lastNode).find('.js-received-amount')
					domReceivedAmount.val(receivedAmount)
					domReceivedAmount.attr('name', 'sales_orders_amounts[' + salesOrderId + '][received_amount]')
					$('.js-append-down-payment-to').append(lastNode)
					
					var lastNode = $('.js-down-payment-template .js-duplicate-node').clone(true)
					
				}

			}
			if(res.sales_orders.length == 0){
				$('.js-append-down-payment-to').append(lastNode)
			}

		})
	
})

$(document).on('change', 'select.ajax-get-invoice-numbers', function () {
	let inEditMode = +$('#js-in-edit-mode').val()
	inEditMode = inEditMode ? inEditMode : 0
	let onlyOneInvoiceNumber = +$('#ajax-invoice-item').attr('data-single-model')
	let specificInvoiceId = $('#ajax-invoice-item').val()
	const moneyReceivedId = +$('#js-money-received-id').val()
	let customerInvoiceId = $('#customer_name').val()
	customerInvoiceId = customerInvoiceId ? customerInvoiceId : $(this).closest('[data-repeater-item]').find('select.customer-name-js').val()

	let currency = $('select.current-invoice-currency').val()
	currency =currency ? currency : $('select.current-currency').val()
	currency = currency ? currency : $(this).closest('[data-repeater-item]').find('select.current-currency').val()
	let downPaymentContractId = $('select.down-payment-contract-class').val()
	const companyId = $('body').attr('data-current-company-id')
	const lang = $('body').attr('data-lang')
	const url = '/' + lang + '/' + companyId + '/money-received/get-invoice-numbers/' + customerInvoiceId + '/' + currency
	if (customerInvoiceId) {
		$.ajax({
			url,
			data: {
				inEditMode
				, money_received_id: moneyReceivedId,
				downPaymentContractId
			}
		}).then(function (res) {
			// first append currencies 
			let currenciesOptions = ''
			var selectedCurrency = res.selectedCurrency
			for (var currencyName in res.currencies) {
				var currencyFormattedName = res.currencies[currencyName]
				currenciesOptions += `<option ${currencyName == currency ? 'selected' : ''} value="${currencyName}">${currencyFormattedName}</option>`
			}


		//	$('.current-currency').empty().append(currenciesOptions)
		
			// second add settlements repeater 
	
			var lastNode = $('.js-template .js-duplicate-node').clone(true)
	
	
			$('.js-append-to').empty()
		
			for (var i in res.invoices) {
				var invoiceId = res.invoices[i].id
				var invoiceNumber = res.invoices[i].invoice_number
				var currency = res.invoices[i].currency
				var netInvoiceAmount = res.invoices[i].net_invoice_amount
				var netBalance = res.invoices[i].net_balance
				var collectedAmount = res.invoices[i].collected_amount
				var invoiceDate = res.invoices[i].invoice_date
				
				var projectName = res.invoices[i].project_name
				projectName = projectName ? projectName : '--';
				var invoiceDueDate = res.invoices[i].invoice_due_date
				
				var settlementAmount = res.invoices[i].settlement_amount
				
				var withholdAmount = res.invoices[i].withhold_amount
				var domInvoiceNumber = $(lastNode).find('.js-invoice-number')
				var domInvoiceId = $(lastNode).find('.js-invoice-id')
				var domInvoiceDate = $(lastNode).find('.js-invoice-date')
				var domInvoiceDueDate = $(lastNode).find('.js-invoice-due-date')
				var domCurrency = $(lastNode).find('.js-currency')
				
				var domNetInvoiceAmount = $(lastNode).find('.js-net-invoice-amount')
				var domProjectName = $(lastNode).find('.js-project-name')
				var domCollectedAmount = $(lastNode).find('.js-collected-amount')
				var domNetBalance = $(lastNode).find('.js-net-balance')
				domInvoiceNumber.val(invoiceNumber)
				domInvoiceNumber.attr('name', 'settlements[' + invoiceId + '][invoice_number]')
				domInvoiceNumber.attr('data-invoice-id',invoiceId)
			
				domInvoiceId.val(invoiceId)
				domInvoiceId.attr('name', 'settlements[' + invoiceId + '][invoice_id]')
				
				domInvoiceDate.attr('name', 'settlements[' + invoiceId + '][invoice_date]')
				domInvoiceDueDate.attr('name', 'settlements[' + invoiceId + '][invoice_due_date]')
				domCurrency.attr('name', 'settlements[' + invoiceId + '][currency]')
				domProjectName.attr('name', 'settlements[' + invoiceId + '][project_name]')
				domNetInvoiceAmount.attr('name', 'settlements[' + invoiceId + '][net_invoice_amount]')
		

				domNetInvoiceAmount.closest('.common-parent-js').find('.currency-span').html(currency);
				domCollectedAmount.attr('name', 'settlements[' + invoiceId + '][collected_amount]')
				domNetBalance.attr('name', 'settlements[' + invoiceId + '][net_balance]')
				if (!onlyOneInvoiceNumber || (onlyOneInvoiceNumber && invoiceId == specificInvoiceId)) {
					$(lastNode).find('.js-invoice-date').val(invoiceDate)
					$(lastNode).find('.js-project-name').val(projectName)
					$(lastNode).find('.js-invoice-due-date').val(invoiceDueDate)
					$(lastNode).find('.js-net-invoice-amount').val(number_format(netInvoiceAmount, 2))
					$(lastNode).find('.js-currency').val(currency)
					$(lastNode).find('.js-net-balance').val(number_format(netBalance, 2))
					$(lastNode).find('.js-collected-amount').val(number_format(collectedAmount, 2))

					var domSettlementAmount = $(lastNode).find('.js-settlement-amount')
					var domWithholdAmount = $(lastNode).find('.js-withhold-amount')
					var domNetBalanceAmount = $(lastNode).find('.js-net-balance')
					domSettlementAmount.val(settlementAmount)
					domWithholdAmount.val(withholdAmount)
					domSettlementAmount.attr('name', 'settlements[' + invoiceId + '][settlement_amount]')
					domWithholdAmount.attr('name', 'settlements[' + invoiceId + '][withhold_amount]')
					domNetBalanceAmount.attr('name', 'settlements[' + invoiceId + '][net_balance]')
		
					$('.js-append-to').append(lastNode)
					var lastNode = $('.js-template .js-duplicate-node').clone(true)
				
				}
				
			}

			if(res.invoices.length == 0){
				$('.js-append-to').append(lastNode)
			}
			$('.js-append-to').find('.js-settlement-amount:first-of-type').trigger('change')

		})
	}
})

$('select.ajax-get-invoice-numbers:eq(0)').trigger('change')
//$('select.ajax-get-sales-orders-for-contract').trigger('change')
$(document).on('change', '.js-settlement-amount,.settlement-amount-class,[data-max-cheque-value]', function () {
	let total = 0
	$('.js-settlement-amount').each(function (index, input) {
		var currentVal = $(input).val() ? number_unformat($(input).val()) : 0  ;
		total += parseFloat(currentVal)
	})
	const currentType = $('#type').val()
	const receivedAmount = number_unformat($('.amount-after-exchange-rate-class[data-type="'+currentType+'"]').val())

	let totalOrdersAmount = 0 ;
	$('.js-append-down-payment-to .settlement-amount-class').each(function(index,element){
		totalOrdersAmount += parseFloat(number_unformat($(element).val()));
	})
	var exchangeRate = $('.exchange-rate-class[data-type="'+currentType+'"]').val()

	let totalRemaining = receivedAmount - total ;
	totalRemaining = totalRemaining ? totalRemaining : 0
	if(totalRemaining > 0){
		$('#contract-row-id').show()
	}else{
		$('#contract-row-id').hide()
	}
	$('#remaining-settlement-js').val(number_format(totalRemaining,2))

	var totalRemainingInRecCurrency = totalRemaining * exchangeRate - (totalOrdersAmount) ;
	$('#remaining-settlement-taking-js').val(number_format(totalRemainingInRecCurrency,2))

})
$('.js-send-to-collection').on('change', function () {
	const noCheckedItems = $('.js-send-to-collection:checked').length
	const moneyType = $(this).attr('data-money-type')
	const sendToCollectionTrigger = $('#js-send-to-under-collection-trigger' + moneyType)
	if (noCheckedItems) {
		sendToCollectionTrigger.attr('title', '').removeClass('disabled')
	}
	else {
		sendToCollectionTrigger.attr('title', 'Please Select More Than One Cheque').addClass('disabled')
	}

})

$(document).on('change', '.js-update-account-number-based-on-account-type', function () {
	const val = $(this).val()

	let appendTo = $(this).attr('data-append-to-query');
	appendTo = appendTo ? appendTo : '.js-account-number';

	const lang = $('body').attr('data-lang')
	const companyId = $('body').attr('data-current-company-id')
	const repeaterParentIfExists = $(this).closest('[data-repeater-item]')
	let parent = repeaterParentIfExists.length ? repeaterParentIfExists : $(this).closest('.kt-portlet__body')
	if($(this).closest('.closest-parent-class').length){
		parent = $(this).closest('.closest-parent-class');
	}
	const moneyType = $(this).closest('form').attr('data-money-type')
	let currency = $(this).closest('form').find('select.current-currency').val()
	currency = currency ? currency : $('input[type="hidden"].current-currency').val();	 
	currency = currency ? currency : $('.js-send-to-collection[data-money-type="' + moneyType + '"]').closest('tr').find('[data-currency]').attr('data-currency')
	currency = currency ? currency : $(this).closest('.kt-portlet__body').find('.current-currency').val();
	currency = currency ? currency : $(this).closest('[data-repeater-item]').find('.select-for-currency').val();
	currency = currency ? currency: $(this).closest('.closest-parent-class').find('.select-for-currency').val();
	
	let financialInstitutionBankId = parent.find('[data-financial-institution-id]').val()
	financialInstitutionBankId = typeof financialInstitutionBankId !== 'undefined' ? financialInstitutionBankId : $('[data-financial-institution-id]').val()
	if (!val || !currency || !financialInstitutionBankId) {
		return
	}
	const url = '/' + lang + '/' + companyId + '/money-received/get-account-numbers-based-on-account-type/' + val + '/' + currency + '/' + financialInstitutionBankId
	$.ajax({
		url,
		data:{allAccounts:window.location.href.split('/').includes('bank-statement')},
		success: function (res) {
			
			options = ''
			var selectToAppendInto = $(parent).find(appendTo)

			for (key in res.data) {
				var val = res.data[key]
				var selected = $(selectToAppendInto).attr('data-current-selected') == val ? 'selected' : ''
				options += '<option ' + selected + '  value="' + val + '">' + val + '</option>'
				
			}
	
	
			selectToAppendInto.empty().append(options).trigger('change')
		}
	})

})



$(document).on('change', '.js-update-account-id-based-on-account-type', function () {
	const val = $(this).val()
	let appendTo = $(this).attr('data-append-to-query');
	appendTo = appendTo ? appendTo : '.js-account-number';
	const lang = $('body').attr('data-lang')
	const companyId = $('body').attr('data-current-company-id')
	const repeaterParentIfExists = $(this).closest('[data-repeater-item]')
	const parent = repeaterParentIfExists.length ? repeaterParentIfExists : $(this).closest('.kt-portlet__body')
	const moneyType = $(this).closest('form').attr('data-money-type')
	let currency = $(this).closest('form').find('select.current-currency').val()
	currency = currency ? currency : $('input[type="hidden"].current-currency').val();	 
	currency = currency ? currency : $('.js-send-to-collection[data-money-type="' + moneyType + '"]').closest('tr').find('[data-currency]').attr('data-currency')
	currency = currency ? currency : $(this).closest('.kt-portlet__body').find('.current-currency').val();
	currency = currency ? currency : $(this).closest('[data-repeater-item]').find('.select-for-currency').val();
	currency = currency ? currency : $('input.current-currency-input').val();	 
	let financialInstitutionBankId = parent.find('[data-financial-institution-id]').val()
	financialInstitutionBankId = typeof financialInstitutionBankId !== 'undefined' ? financialInstitutionBankId : $('[data-financial-institution-id]').val()
	financialInstitutionBankId = typeof financialInstitutionBankId !== 'undefined' ? financialInstitutionBankId : $(this).closest('.closest-parent').find('input[name="financial_institution_id"]').val()
	if (!val || !currency || !financialInstitutionBankId) {
		return
	}
	const url = '/' + lang + '/' + companyId + '/money-received/get-account-ids-based-on-account-type/' + val + '/' + currency + '/' + financialInstitutionBankId
	$.ajax({
		url,
		data:{allAccounts:window.location.href.split('/').includes('bank-statement')},
		success: function (res) {
			
			options = ''
			var selectToAppendInto = $(parent).find(appendTo)

			for (id in res.data) {
				var val = res.data[id]
				var selected = $(selectToAppendInto).attr('data-current-selected') == id ? 'selected' : ''
				options += '<option ' + selected + '  value="' + id + '">' + val + '</option>'
				
			}

			selectToAppendInto.empty().append(options).trigger('change')
		}
	})

})


$(document).on('change', '[js-when-change-trigger-change-account-type]', function () {

	let parent = $(this).closest('.kt-portlet__body').find('.js-update-account-number-based-on-account-type') ;
	// if($(this).closest('.closest-parent-class').length){
	// 	parent= $(this).closest('.closest-parent-class').length;
	// 	$(parent).find('.js-update-account-number-based-on-account-type').trigger('change')
	// 	return ;
		
	// }
	
	$('.js-update-account-number-based-on-account-type').trigger('change')
	if(parseInt(parent)){
		parent.trigger('change')
	}
	
	 parent = $(this).closest('.kt-portlet__body').find('.js-update-account-id-based-on-account-type') ;
	
	 $('.js-update-account-id-based-on-account-type').trigger('change')
	if(parseInt(parent)){
		parent.trigger('change')
	}
	
})
$(function () {

	$('.js-update-account-number-based-on-account-type').trigger('change')

	$('.js-update-account-id-based-on-account-type').trigger('change')
	setTimeout(function () {
		$('.js-send-to-collection').trigger('change')
	}, 1000)
})

	$(document).on('change','select.invoice-currency-class',function(){
		const currencyName = $(this).val();
		$('select.receiving-currency-class').val(currencyName)
		//.trigger('change')
		;
		const companyId = $('body').data('current-company-id')
		const lang = $('body').data('lang')
		const url = '/' + lang + '/' + companyId + '/get-customers-based-on-currency/'+currencyName
		const isDownPaymentForm = $('#is-down-payment-id').val()
		$('#remaining-settlement-js').closest('.closest-parent').find('.invoice-currency-span').html('[ ' +  currencyName +' ]')
		if(isDownPaymentForm){
			return ;
		}
		$.ajax({
			url,
			success:function(res){
				let options = '<option selected value="0">Select</option>';
				let currentSelected = $('select#customer_name').val()
			
				for(customerName in res.customerInvoices ){
					var customerId = res.customerInvoices[customerName];
					options +=` <option value="${customerId}" ${currentSelected == customerId ? 'selected' : ''}>${customerName}</option>`
				}
				
				if($('#is-down-payment-id').val()){
					$('select#customer_name').empty().append(options).trigger('change')
				}else{
					$('select#customer_name').empty().append(options)
				}
		
			}
		})
	});

$(document).on('change','select#partner_type',function(){
	const removeSelect = $(this).attr('data-remove-select');
	// alert()
	
	const partnerColumnName = $(this).val();
	if(partnerColumnName == 'is_customer'){
		$('#settlement-card-id').fadeIn();
		$('#invoice-currency-div-id').fadeIn();
		$('.show-only-when-invoice-currency-not-equal-receiving-currency').removeClass('hidden')
	}else{
		$('#settlement-card-id').fadeOut();
		$('#invoice-currency-div-id').fadeOut();
		$('.show-only-when-invoice-currency-not-equal-receiving-currency').addClass('hidden')
	}
	
		showOrHideTransaction(partnerColumnName);
		
		
	
	
	const companyId = $('body').data('current-company-id')
	const lang = $('body').data('lang')
	const currencyName = $('select#invoice-currency-id').val();
	const url = '/' + lang + '/' + companyId + '/get-partners-based-on-type/'+currencyName;
	$.ajax({
		url,
		data:{partnerColumnName},
		type:"get",
		success:function(res){
			const partners = res.partners;
			let elements = removeSelect ? '' : `<option value="" selected>Select</option>`;
			for(var name in partners){
				var id = partners[name]
				elements+=`<option value="${id}">${name}</option>`
			}
			$('select#customer_name').empty().append(elements).trigger('change')
		}
	});
	
})		

function showOrHideTransaction(partnerColumnName)
{
	if(partnerColumnName =='is_customer'){
			$('#invoice-currency-div-id').show();
			$('#transaction-type-parent').hide();
		}else{
			$('#invoice-currency-div-id').hide();
			$('#transaction-type-parent').show();
		}
		
		let options = '';
		let currentSelect = $('#transaction-type-parent').attr('data-current-selected')

		if(partnerColumnName == 'is_employee'){
			
			options = `
			<option ${currentSelect == 'refund-custody' ? 'selected' :''}  value="refund-custody">Refund Custody</option>
			<option ${currentSelect == 'pay-loan' ? 'selected' :''}  value="pay-loan">Pay Loan</option>
			`
		}else if(partnerColumnName =='is_shareholder' ){
			options = `
				<option ${currentSelect == 'funding-from' ? 'selected' :''}  value="funding-from">Funding From</option>
			`
		}
		else if( partnerColumnName=="is_subsidiary_company"){
			options = `
				<option ${currentSelect == 'funding-from' ? 'selected' :''}  value="funding-from">Funding From</option>
			`
		}
		else if(partnerColumnName =='is_other_partner'){
			options = `
				<option ${currentSelect == 'insurance-from' ? 'selected' :''}  value="insurance-from">Insurance From</option>
			`
		}
		
		$('#transaction-type-parent').find('select').empty().append(options);
		
}
let currentPartnerType = $('select#partner_type')
showOrHideTransaction(currentPartnerType.val());
