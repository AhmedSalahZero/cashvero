$(document).on('click','.repeat-to-right',function(){
	let columnIndex = parseInt($(this).attr('data-column-index'))
	let parent = $(this).closest('tr');
	let name = $(this).attr('data-name');
	let numberFormatDecimalsForCurrentRow = parent.attr('data-repeat-formatting-decimals')
	numberFormatDecimalsForCurrentRow = numberFormatDecimalsForCurrentRow ? numberFormatDecimalsForCurrentRow : 0 ;
	let input = parent.find('.repeat-to-right-input-formatted[data-column-index="'+ columnIndex +'"][data-name="'+name+'"]');
	let numberOfDecimalsForCurrentInput = $(input).attr('data-number-of-decimals');
	numberOfDecimalsForCurrentInput = numberOfDecimalsForCurrentInput == undefined ? numberFormatDecimalsForCurrentRow : numberOfDecimalsForCurrentInput;
	let inputValue = input.val();
	inputValue = number_unformat(inputValue);
	let totalPerYear = 0 ;
	$(this).closest('tr').find('.repeat-to-right-input-formatted[data-name="'+name+'"]').each(function(index,inputHiddenElement){
		let currentColumnIndex = $(inputHiddenElement).attr('data-column-index');
		if(currentColumnIndex >= columnIndex ){
			totalPerYear += parseFloat(inputValue) ;
			$(inputHiddenElement).val(number_format(inputValue,numberOfDecimalsForCurrentInput)).trigger('change');
		}
	})
})
$('.repeat-to-right-input-hidden').on('change',function(){
	const val = $(this).val();
	const columnIndex = $(this).attr('data-column-index');
	const numberOfDecimals = $(this).closest('.input-hidden-parent').find('.copy-value-to-his-input-hidden[data-column-index="'+columnIndex+'"]').attr('data-number-of-decimals');
	$(this).closest('.input-hidden-parent').find('.copy-value-to-his-input-hidden[data-column-index="'+columnIndex+'"]').val(number_format(val,numberOfDecimals))
})

$(document).on('click','.repeat-select-to-right',function(){
	let columnIndex = parseInt($(this).attr('data-column-index'))
	let parent = $(this).closest('tr');
	let value = parent.find('.repeat-to-right-select[data-column-index="'+ columnIndex +'"]').val();
	$(this).closest('tr').find('.repeat-to-right-select').each(function(index,select){
		if($(select).attr('data-column-index') >= columnIndex ){
			$(select).val(value).trigger('change');
		}
	})

})

$(document).on('change','.input-hidden-parent .copy-value-to-his-input-hidden',function(){
	let val = $(this).val();
	$(this).closest('.input-hidden-parent').find('input.input-hidden-with-name').val(number_unformat(val)).trigger('change');
})

function convertDateToDefaultDateFormat(dateStr)
{
	const [month,day, year] = dateStr.split("/"); // Split the string by "/";
	return  `${year}-${month}-${day}`; // Rearrange to YYYY-MM-DD
}
function getEndOfMonth(year, month) {
	// قم بإنشاء تاريخ لأول يوم من الشهر التالي
	let date = new Date(year, month + 1, 0);
	return date;
  }
  $(document).on('change','.recalculate-factoring',function(){
	const index = parseInt($(this).attr('data-column-index'));
	const rate = $('.factoring-rate[data-column-index="'+index+'"]').val();
	
	const rowIndex = $('.factoring-rate[data-column-index="'+index+'"]').closest('[data-repeater-item]').index();
	const value = $('.factoring-projection-amount[data-column-index="'+index+'"]').val();
	$('.factoring-value[data-column-index="'+index+'"]').val(rate/100*value).trigger('change');
  })

  $(function(){

	$('select.revenue-stream-type-js').trigger('change');
  })

  
  $(document).on('click','.add-btn-js',function(e){
	e.preventDefault();
	$(this).toggleClass('rotate-180')
	$(this).closest('[data-is-main-row]').nextUntil('[data-is-main-row]').toggleClass('hidden')
	})

	
	$(document).on('click','.collapse-before-me',function(){

		let columnIndex = $(this).attr('data-column-index') ;
		hide = true ;
		let counter = 0;
		while(hide){
			if(counter != 0){
		
				if($(this).closest('table').find('th[data-column-index="'+columnIndex+'"]').hasClass('exclude-from-collapse'))
				{
					hide = false;
					return ;
				}
			}
			
			$(this).closest('table').find('[data-column-index="'+columnIndex+'"]:not(.exclude-from-collapse)').toggleClass('hidden')
			
			columnIndex--;		
			counter ++;
			if(counter == 12){
				hide = false ;
			}
		}
	})
	$(document).on('change','.repeater-with-collapse-input',function(){
		let groupIndex = $(this).attr('data-group-index');
		let total = 0 ;
		$(this).closest('tr').find('input[data-group-index="'+groupIndex+'"]').each(function(index,element){
			total+= parseFloat($(element).val());
		})
		$(this).closest('tr').find('.year-repeater-index-'+groupIndex).val(number_format(total)).trigger('change');
	})

	