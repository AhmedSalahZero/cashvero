$(function(){
	$(document).on('change','.installment_condition',function(){
		var val = $(this).val();
			var options = '<option value="quarterly"> Quarterly </option>'
			options += '<option value="semi annually"> Semi-annually </option>'
			options += '<option value="annually"> Annually </option>'
		if(val == 'semi annually' ){
				options ='';
				options += '<option value="semi annually"> Semi-annually </option>'
			options += '<option value="annually"> Annually </option>'
			
		} 
		$(this).closest('.kt-portlet__body').find('select.interval-calcs').empty().append(options);
	})
	$(document).on('change','.grace-period-class,select.installment_condition',function(){
		let gracePeriod = $(this).closest('.kt-portlet__body').find('.grace-period-class').val();
		gracePeriod = gracePeriod ? gracePeriod : 0 ;
		let installmentInterval = $(this).closest('.kt-portlet__body').find('select.installment_condition').val();
		if(installmentInterval == 'quarterly'){
			if(gracePeriod % 3 != 0){
				console.log('inside quarter not working')
				$(this).closest('.kt-portlet__body').find('.grace-period-class').val(3)
			}
		} 
		if(installmentInterval == 'semi annually'){
			if(gracePeriod % 6 != 0){
				$(this).closest('.kt-portlet__body').find('.grace-period-class').val(6)
			}
		} 
		
		
		
	})
	
})
