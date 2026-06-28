

$(document).on('click', '#js-delivery-bank', function (e) {
	e.preventDefault()
	$('#js-choose-bank-id').modal('show')
})
$(document).on('click', '.js-delivery-bank-class', function (e) {
	e.preventDefault()
	$('#js-choose-bank-id').modal('show')
})
$(document).on('click', '#js-append-bank-name-if-not-exist', function () {
	const deliveryBank = document.getElementById('js-delivery-bank').parentElement
	const newBankId = $('#js-bank-names').val()
	const newBankName = $('#js-bank-names option:selected').attr('data-name')
	const isBankExist = $(deliveryBank).find('option[value="' + newBankId + '"]').length

	if (!isBankExist) {
		const option = '<option selected value="' + newBankId + '">' + newBankName + '</option>'

		$('#js-delivery-bank').parent().find('select').append(option)
	}
	$('#js-choose-bank-id').modal('hide')
})


$(document).on('click', '.js-append-bank-name-if-not-exist-in-repeater', function () {
	const newBankId = $('#js-bank-names').val()
	const newBankName = $('#js-bank-names option:selected').attr('data-name')
	$('select.delivery-bank-class').each(function (index, selectElement) {
		const isBankExist = $(selectElement).find('option[value="' + newBankId + '"]').length
		if (!isBankExist) {
			const option = '<option  value="' + newBankId + '">' + newBankName + '</option>'
			$(selectElement).append(option).selectpicker("refresh")
		}
	})


	$('#js-choose-bank-id').modal('hide')
})

$(document).on('click', '#js-delivery-bank', function (e) {
	e.preventDefault()
	$('#js-choose-delivery-bank-id').modal('show')
})

$(document).on('click', '#js-append-delivery-bank-name-if-not-exist', function () {
	const deliveryBank = document.getElementById('js-delivery-bank').parentElement
	const newBankId = $('#js-delivery-bank-names').val()
	const newBankName = $('#js-delivery-bank-names').find('option:selected').attr('data-name')
	const isBankExist = $(deliveryBank).parent().find('select').find('option[value="' + newBankId + '"]').length
	if (!isBankExist) {
		const option = '<option selected value="' + newBankId + '">' + newBankName + '</option>'
		$('#js-delivery-bank').parent().find('select').append(option)
	}
	$('#js-choose-delivery-bank-id').modal('hide')
})






$(document).on('click', '#js-delivery-branch', function (e) {
	e.preventDefault()
	$('#js-choose-delivery-branch-id').modal('show')

})

$(document).on('click', '#js-append-delivery-branch-name-if-not-exist', function () {
	const deliveryBranch = document.getElementById('js-delivery-branch').parentElement
	const newBranchName = $('#js-delivery-branch-names').val()
	const isBranchExist = $(deliveryBranch).parent().find('select').find('option[value="' + newBranchName + '"]').length
	if (!isBranchExist) {
		const option = '<option selected value="' + newBranchName + '">' + newBranchName + '</option>'
		$('#js-delivery-branch').parent().find('select').append(option)
	}
	$('#js-choose-delivery-branch-id').modal('hide')
})




$(document).on('change', '.ajax-get-purchases-orders-for-contract', function () {
	let inEditMode = +$('#js-in-edit-mode').val()
	inEditMode = inEditMode ? inEditMode : 0
	let onlyOneSalesOrder = +$('#ajax-purchases-order-item').attr('data-single-model')
	let specificSalesOrder = $('#ajax-purchases-order-item').val()
	let downPaymentId = +$('#js-down-payment-id').val()
	downPaymentId = isNaN(downPaymentId) ? 0 : downPaymentId ;
	let contractId = $('#contract-id').val()
	contractId = contractId ? contractId : $(this).closest('[data-repeater-item]').find('select.supplier-name-js').val()
	let currency = $('.current-currency').val()
	currency = currency ? currency : $(this).closest('[data-repeater-item]').find('select.current-currency').val()
	const companyId = $('body').attr('data-current-company-id')
	const lang = $('body').attr('data-lang')
	if(!contractId){
		$('.js-append-down-payment-to').empty().hide()
		return ;
	}else{
		$('.js-append-down-payment-to').show()
	}
	const url = '/' + lang + '/' + companyId + '/down-payments/get-purchases-orders-for-contract/' + contractId + '/' + currency


		$.ajax({
			url,
			data: {
				inEditMode
				, down_payment_id: downPaymentId
			}
		}).then(function (res) {
			var lastNode = $('.js-down-payment-template .js-duplicate-node').clone(true)
			$('.js-append-down-payment-to').empty()
			if(res.purchases_orders.length == 0){
				res.purchases_orders[0] = {
					id:-1,
					po_number:"General",
					paid_amount:0,
					amount:0,
				}
			}
			for (var i = 0; i < res.purchases_orders.length; i++) {
				 var purchaseOrderId = res.purchases_orders[i].id
				 var salesOrderNumber = res.purchases_orders[i].po_number
				var amount = res.purchases_orders[i].amount
				var paidAmount = res.purchases_orders[i].paid_amount
				var domSalesOrder = $(lastNode).find('.js-purchases-order-number')
				domSalesOrder.val(purchaseOrderId)
				$(lastNode).find('.contract-currency').html('[ ' + currency +' ]')
				domSalesOrder.attr('name', 'purchases_orders_amounts[' + purchaseOrderId + '][purchases_order_id]').val(purchaseOrderId)
				var domSalesOrder = $(lastNode).find('.js-purchases-order-name')
				//domSalesOrder.val(purchaseOrderId)'
				domSalesOrder.attr('name', 'purchases_orders_amounts[' + purchaseOrderId + '][purchases_order_name]').val(salesOrderNumber)
				
				if (!onlyOneSalesOrder || (onlyOneSalesOrder && purchaseOrderId == specificSalesOrder)) {
					$(lastNode).find('.js-amount').val(number_format(amount, 2))
					var domPaidAmount = $(lastNode).find('.js-paid-amount')
					domPaidAmount.val(paidAmount)
					domPaidAmount.attr('name', 'purchases_orders_amounts[' + purchaseOrderId + '][paid_amount]')
					
					$('.js-append-down-payment-to').append(lastNode)
					var lastNode = $('.js-down-payment-template .js-duplicate-node').clone(true)
				}

			}
	
			if(res.purchases_orders.length == 0){
				$('.js-append-down-payment-to').append(lastNode)
			}


		})
	
})

$(document).on('change', 'select.ajax-get-invoice-numbers', function () {

	let inEditMode = +$('#js-in-edit-mode').val()
	inEditMode = inEditMode ? inEditMode : 0
	let onlyOneInvoiceNumber = +$('#ajax-invoice-item').attr('data-single-model')
	let specificInvoiceId = $('#ajax-invoice-item').val()
	const moneyPaymentId = +$('#js-money-payment-id').val()
	let supplierInvoiceId = $('#supplier_name').val()
	supplierInvoiceId = supplierInvoiceId ? supplierInvoiceId : $(this).closest('[data-repeater-item]').find('select.supplier-name-js').val()
	let currency = $('select.current-invoice-currency').val()
	 currency =currency ? currency :  $('.current-currency').val()
	currency = currency ? currency : $(this).closest('[data-repeater-item]').find('select.current-currency').val()

	let downPaymentContractId = $('select.down-payment-contract-class').val()
	
	const companyId = $('body').attr('data-current-company-id')
	const lang = $('body').attr('data-lang')
	const url = '/' + lang + '/' + companyId + '/money-payment/get-invoice-numbers/' + supplierInvoiceId + '/' + currency

	
	if (supplierInvoiceId) {
		$.ajax({
			url,
			data: {
				inEditMode
				, money_payment_id: moneyPaymentId,
				downPaymentContractId
			}
		}).then(function (res) {
			// first append currencies 
			let currenciesOptions = ''
			var selectedCurrency = res.selectedCurrency
			for (var currencyName in res.currencies) {
				var currencyFormattedName = res.currencies[currencyName].toUpperCase()
				currenciesOptions += `<option ${currencyName == currency ? 'selected' : ''} value="${currencyName}">${currencyFormattedName}</option>`
			}


			
			// second add settlements repeater 
			var lastNode = $('.js-template .js-duplicate-node').clone(true)
			
			$('.js-append-to').empty()
	
			for (var i in res.invoices) {
			
				var invoiceId = res.invoices[i].id
				var invoiceNumber = res.invoices[i].invoice_number
				if($(lastNode).find('[data-target]').attr('data-target')){
					lastNode.find('[data-target]').attr('data-target',$(lastNode).find('[data-target]').attr('data-target').replace('--0',invoiceId));
					lastNode.find('.modal-class-js').attr('id',$(lastNode).find('.modal-class-js').attr('id').replace('--0',invoiceId));
				}
			
				var currentSettlementAllocation = res.invoices[i].settlement_allocations;

				var currency = res.invoices[i].currency
				var netInvoiceAmount = res.invoices[i].net_invoice_amount
				var netBalance = res.invoices[i].net_balance
				var paidAmount = res.invoices[i].paid_amount
				var invoiceDate = res.invoices[i].invoice_date
				var invoiceDueDate = res.invoices[i].invoice_due_date
				var settlementAmount = res.invoices[i].settlement_amount
				var withholdAmount = res.invoices[i].withhold_amount
				var domInvoiceNumber = $(lastNode).find('.js-invoice-number')
				var domInvoiceId = $(lastNode).find('.js-invoice-id')
				
				domInvoiceNumber.val(invoiceNumber)
				domInvoiceNumber.attr('name', 'settlements[' + invoiceId + '][invoice_number]')
				domInvoiceNumber.attr('data-invoice-id',invoiceId)
				domInvoiceId.val(invoiceId)
				domInvoiceId.attr('name', 'settlements[' + invoiceId + '][invoice_id]')
				
				if (!onlyOneInvoiceNumber || (onlyOneInvoiceNumber && invoiceId == specificInvoiceId)) {

					$(lastNode).find('.js-invoice-date').val(invoiceDate)
					$(lastNode).find('.js-invoice-due-date').val(invoiceDueDate)
					$(lastNode).find('.js-net-invoice-amount').val(number_format(netInvoiceAmount, 2))
					$(lastNode).find('.js-currency').val(currency)
					$(lastNode).find('.js-net-balance').val(number_format(netBalance, 2))
					$(lastNode).find('.js-paid-amount').val(number_format(paidAmount, 2))

					var domSettlementAmount = $(lastNode).find('.js-settlement-amount')
					var domWithholdAmount = $(lastNode).find('.js-withhold-amount')
					var domNetBalance = $(lastNode).find('.js-net-balance')
				//	var domAllocationAmount = $(lastNode).find('.allocation-amount-class')
					domSettlementAmount.val(settlementAmount)
					domWithholdAmount.val(withholdAmount)
					domSettlementAmount.attr('name', 'settlements[' + invoiceId + '][settlement_amount]')
					domWithholdAmount.attr('name', 'settlements[' + invoiceId + '][withhold_amount]')
					domNetBalance.attr('name', 'settlements[' + invoiceId + '][net_balance]')
					
					var editAllocationRow = generateAllocationRow(currentSettlementAllocation,res.clientsWithContracts,invoiceId,i)
					if(currentSettlementAllocation && Object.keys(currentSettlementAllocation).length ){
						$(lastNode).find('table.m_repeater--0 tbody[data-repeater-list]').empty().append(editAllocationRow)
					}
					$('.js-append-to').append(lastNode)
					$(lastNode).find('select.suppliers-or-customers-js').trigger('change')
					$(lastNode).find('.repeater-class').repeater({            
						initEmpty: false,
						  isFirstItemUndeletable: true,
						defaultValues: {
							'text-input': 'foo'
						},
						 
						show: function() {
							$(this).slideDown();   
							updateInputsNames(this)
							$('input.trigger-change-repeater').trigger('change')   
							 $(this).find('.only-month-year-picker').each(function(index,dateInput){
								reinitalizeMonthYearInput(dateInput)
							 });
							 $(document).find('.datepicker-input:not(.only-month-year-picker)').datepicker({
										dateFormat: 'mm-dd-yy'
										, autoclose: true
									})
							$('input:not([type="hidden"])').trigger('change');
							$(this).find('.dropdown-toggle').remove();
							$(this).find('.select3-select').selectpicker();
	      	           //   $(this).find('select.repeater-select').selectpicker("refresh");
								
						},
						
						hide: function(deleteElement) {
							$(this).closest('.table').find('[name]').each(function(i,element){
								var currentInvoiceId=$(this).closest('tbody').find('tr[data-invoice-id]').attr('data-invoice-id')
								var currentName = $(this).attr('data-name');
								$(element).attr('name','allocations['+currentInvoiceId+']['+i+']['+currentName+']')
							 })
							 
							if($('#first-loading').length){
									$(this).slideUp(deleteElement,function(){
							   
										   deleteElement();
										//   $('select.main-service-item').trigger('change');
								});
							}
							else{
								 if(confirm('Are you sure you want to delete this element?')) {
								$(this).slideUp(deleteElement,function(){
										   deleteElement();
								});
							}         
							}
								   }
					});
					
					$(lastNode).find('.select3-select.suppliers-or-customers-js').selectpicker();
					var currentName = null;
					if($(lastNode).find('select.suppliers-or-customers-js').attr('name')){
						$(lastNode).find('select.suppliers-or-customers-js').attr('name',$(lastNode).find('select.suppliers-or-customers-js').attr('name').replace('allocations[','allocations['+invoiceId+']['))
						$(lastNode).find('input.allocation-amount-class').attr('name',$(lastNode).find('input.allocation-amount-class').attr('name').replace('allocations[','allocations['+invoiceId+']['))
						 currentName = $(lastNode).find('select.contracts-js').attr('name').replace('allocations[','allocations['+invoiceId+'][') ;
						 $(lastNode).find('select.contracts-js').attr('name',currentName).attr('data-invoice-id',invoiceId)
						
					}
					$(lastNode).find('select.suppliers-or-customers-js').closest('tr').attr('data-invoice-id',invoiceId)
					
					
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
//$('select.ajax-get-purchases-orders-for-contract').trigger('change')
function generateAllocationRow(settlementAllocations , clientsWithContracts,invoiceId,rowIndex)
{
	var rows = '';
	
	for(var settlementIndex in settlementAllocations){
		var partnersSelect = '<select name="settlements['+ invoiceId +']['+rowIndex+'][partner_id]" data-name="partner_id" class="suppliers-or-customers-js select3-select"> ';
		var currentSettlementAllocation = settlementAllocations[settlementIndex];

		for(var clientId in clientsWithContracts ){
			var currentClientName = clientsWithContracts[clientId]
			var currentSelectClient = clientId == currentSettlementAllocation.partner_id ? 'selected':''  ; 
			partnersSelect+=` <option  value="${clientId}" ${currentSelectClient}> ${currentClientName} </option> `;
			
		}
		partnersSelect+= ' </select> ' 
		var currentRow = 	`<tr data-repeater-item >
		<td class="text-center">
		
			<div class="">
				<i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">
				</i>
			</div>
		</td>
		<td>
				${partnersSelect}
		</td>

		<td>
				<select data-name="contract_id" data-current-selected="${currentSettlementAllocation.contract_id}"  class="contracts-js select3-select" data-current-selected="" name="contract_id" >
					
				</select>
		</td>

		<td>
			<div class="kt-input-icon">
				<div class="input-group">
					<input disabled type="text" class="form-control contract-code" value="${currentSettlementAllocation.contract_code}">
				</div>
			</div>
		</td>
		<td>
			<div class="kt-input-icon ">
				<div class="input-group">
					<input disabled type="text" class="form-control contract-amount" value="${currentSettlementAllocation.contract_amount}">
				</div>
			</div>
		</td>
	  

			  <td>
			<div class="kt-input-icon ">
				<div class="input-group">
					<input  type="text" data-name="allocation_amount" name="settlements[${invoiceId}][${rowIndex}][allocation_amount]" class="form-control allocation-amount-class repeater-amount-class" value="${number_format(currentSettlementAllocation.allocation_amount,2)}">
				</div>
			</div>
		</td>

	</tr>`;
	rows += currentRow;
		
}

return rows ;	
	
	

}
$(document).on('change', '.js-settlement-amount,.settlement-amount-class,[data-max-cheque-value]', function () {
	let total = 0
	$('.js-settlement-amount').each(function (index, input) {
		var currentVal = $(input).val() ? number_unformat($(input).val()) : 0  ;
		total += parseFloat(currentVal)
	})
	const currentType = $('#type').val()
	const paidAmount = number_unformat($('.amount-after-exchange-rate-class[data-type="'+currentType+'"]').val())
	
	var exchangeRate = $('.exchange-rate-class[data-type="'+currentType+'"]').val()
	let totalOrdersAmount = 0 ;
	$('.js-append-down-payment-to .settlement-amount-class').each(function(index,element){
		totalOrdersAmount += parseFloat(number_unformat($(element).val()));
	})
	let totalRemaining = paidAmount - total
	totalRemaining = totalRemaining ? totalRemaining : 0
	
	if(totalRemaining > 0){
		$('#contract-row-id').show()
	}else{
		$('#contract-row-id').hide()
	}
	$('#remaining-settlement-js').val(number_format(totalRemaining,2))
	var totalRemainingInRecCurrency = totalRemaining * exchangeRate-  totalOrdersAmount;
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
	const lang = $('body').attr('data-lang')
	const companyId = $('body').attr('data-current-company-id')
	const repeaterParentIfExists = $(this).closest('[data-repeater-item]')
	const parent = repeaterParentIfExists.length ? repeaterParentIfExists : $(this).closest('.kt-portlet__body')
	const moneyType = $(this).closest('form').attr('data-money-type')
	const data = []
	let currency = $(this).closest('form').find('.current-currency').val()
	currency = currency ? currency : $('.js-send-to-collection[data-money-type="' + moneyType + '"]').closest('tr').find('[data-currency]').attr('data-currency')
	let financialInstitutionBankId = parent.find('[data-financial-institution-id]').val()

	financialInstitutionBankId = typeof financialInstitutionBankId !== 'undefined' ? financialInstitutionBankId : $('[data-financial-institution-id]').val()
	if (!val || !currency || !financialInstitutionBankId) {
		return
	}
	const url = '/' + lang + '/' + companyId + '/money-payment/get-account-numbers-based-on-account-type/' + val + '/' + currency + '/' + financialInstitutionBankId
	$.ajax({
		url,
		data,
		success: function (res) {
			options = ''
			var selectToAppendInto = $(parent).find('.js-account-number')

			for (key in res.data) {
				var val = res.data[key]
				var selected = $(selectToAppendInto).attr('data-current-selected') == val ? 'selected' : ''
				options += '<option ' + selected + '  value="' + val + '">' + val + '</option>'
			}

			selectToAppendInto.empty().append(options).trigger('change')
		}
	})






})
$(document).on('change', '[js-when-change-trigger-change-account-type]', function () {
	
	$(this).closest('.kt-portlet__body').find('.js-update-account-number-based-on-account-type').trigger('change')
})
$(function () {
	$('.js-update-account-number-based-on-account-type').trigger('change')
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
	const url = '/' + lang + '/' + companyId + '/get-suppliers-based-on-currency/'+currencyName
	const isDownPaymentForm = $('#is-down-payment-id').val();
	$('#remaining-settlement-js').closest('.closest-parent').find('.invoice-currency-span').html('[ ' +  currencyName +' ]');
	
		if(isDownPaymentForm){
			return ;
		}
		
	$.ajax({
		url,
		success:function(res){
			let options = '<option selected value="0">Select</option>';
			let currentSelected = $('select#supplier_name').val()
			for(supplierName in res.supplierInvoices ){
				var supplierId = res.supplierInvoices[supplierName]
				options +=` <option value="${supplierId}" ${currentSelected == supplierId ? 'selected' : ''} >${supplierName}</option>`
			}
			if($('#is-down-payment-id').val()){
				$('select#supplier_name').empty().append(options).trigger('change')
			}else{
				$('select#supplier_name').empty().append(options)
			}
		}
	})
});
//$('select.invoice-currency-class').trigger('change')
function updateInputsNames(element,invoiceId)
{
	$(element).find('[name]').attr('name',$(element).find('[name]').attr('name').replace('allocations[','allocations['+invoiceId+']['));
					
	$(element).closest('tbody').find('tr').each(function(trIndex,tr){
		$(tr).find('[name]').each(function(i,element){
			var currentInvoiceId=$(element).closest('.settlement-row-parent').find('.js-invoice-number').attr('data-invoice-id')
			var currentName = $(element).attr('data-name');
			$(element).attr('name','allocations['+currentInvoiceId+']['+trIndex+']['+currentName+']')
		 })
	 })
}



$(document).on('change','select#partner_type',function(){
	const partnerColumnName = $(this).val();
	if(partnerColumnName == 'is_supplier'){
		$('#settlement-card-id').fadeIn();
		$('#invoice-currency-div-id').fadeIn();
		$('.show-only-when-invoice-currency-not-equal-receiving-currency').removeClass('hidden')
	}else{
		$('#settlement-card-id').fadeOut();
		$('#invoice-currency-div-id').fadeOut();
		$('.show-only-when-invoice-currency-not-equal-receiving-currency').addClass('hidden')
	}
	
	showOrHideTransaction(partnerColumnName);
	
	
	if(partnerColumnName =='is_supplier'){
			$('#transaction-type-parent').hide();
		}else{
			$('#transaction-type-parent').show();
		}
		let options = '';
		let currentSelect = $('#transaction-type-parent').attr('data-current-selected')
		
		if(partnerColumnName == 'is_employee'){
			options = `
			<option ${currentSelect == 'custody' ? 'selected' :''}  value="custody">Custody</option>
			<option ${currentSelect == 'loan' ? 'selected' :''}  value="loan">Loan</option>
			`
		}else if(partnerColumnName =='is_shareholder' ){
			options = `
				<option ${currentSelect == 'funding-to' ? 'selected' :''}  value="funding-to">Funding To</option>
				<option ${currentSelect == 'dividend-payment' ? 'selected' :''}  value="funding-to">Dividend Payment</option>
			`
		}
		else if( partnerColumnName=="is_subsidiary_company"){
			options = `
				<option ${currentSelect == 'funding-to' ? 'selected' :''}  value="funding-to">Funding To</option>
			`
		}
		else if(partnerColumnName =='is_other_partner'){
			options = `
				<option ${currentSelect == 'insurance-to' ? 'selected' :''}  value="insurance-to">Insurance To</option>
			`
		}
			else if(partnerColumnName =='is_tax'){
			options = `
				<option ${currentSelect == 'pay-to' ? 'selected' :''}  value="pay-to">Pay To</option>
			`
		}
		
		$('#transaction-type-parent').find('select').empty().append(options);
		
	
	
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
			let elements = `<option value="" selected>Select</option>`;
			for(var name in partners){
				var id = partners[name];
				elements+=`<option value="${id}">${name}</option>`
			}
			$('select#supplier_name').empty().append(elements).trigger('change')
		}
	});
	
})	

function showOrHideTransaction(partnerColumnName)
{
	if(partnerColumnName =='is_supplier'){
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
			<option ${currentSelect == 'custody' ? 'selected' :''}  value="custody">Custody</option>
			<option ${currentSelect == 'loan' ? 'selected' :''}  value="loan">Loan</option>
			`
		}else if(partnerColumnName =='is_shareholder' || partnerColumnName=="is_subsidiary_company"){
			options = `
				<option ${currentSelect == 'funding-to' ? 'selected' :''}  value="funding-to">Funding To</option>
			`
		}else if(partnerColumnName =='is_other_partner'){
			options = `
				<option ${currentSelect == 'insurance-to' ? 'selected' :''}  value="insurance-to">Insurance To</option>
			`
		}
		else if(partnerColumnName =='is_tax'){
			options = `<option ${currentSelect == 'pay-to' ? 'selected' :''}  value="pay-to">Pay To</option>`
		
		}
		
		$('#transaction-type-parent').find('select').empty().append(options);
		
}
	
let currentPartnerType = $('select#partner_type')
showOrHideTransaction(currentPartnerType.val());
