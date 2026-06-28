$(document).on('change','.equity-funding-rate-input-hidden-class',function(){
	console.log('e')
	const value = number_unformat($(this).val());
	const columnIndex = parseInt($(this).attr('data-column-index'));
	$('input.new-loan-function-rates-js[data-column-index="'+columnIndex+'"]').val(100 - value).trigger('change');
})
