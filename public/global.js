$('.only-numeric-allowed').on('change',function(e){

    if(! isNumber($(this).val()))
    {
        let lang = $('body').data('lang');
        title = "Oops..." ;
        message = "Only Numeric Value Allowed" ;
        if(lang === 'ar'){
            title = 'Ø®Ø·Ø£'  ;
            message = "Ù…Ø³Ù…ÙˆØ­ ÙÙ‚Ø· Ø¨Ø§Ù„Ø§Ø±Ù‚Ø§Ù…"
        }


        Swal.fire({
            icon: "warning",
            title: title,
            text: message,
        })

        $(this).val(0);
    }

});

$(document).on('change','.only-percentage-allowed-between-minus-plus-hundred',function(e){
    if($(this).hasClass('only-percentage-allowed-between-minus-plus-hundred') && ! isPercentageNumberBetweenMinusPlusHundred($(this).val()))
    {
        let lang = $('body').data('lang');
        title = "Oops..." ;
        message = "Please Enter Valid Percentage" ;
        if(lang === 'ar'){
            title = 'Ø®Ø·Ø£'  ;
            message = "Ø¨Ø±Ø¬Ø§Ø¡ Ø§Ø¯Ø®Ø§Ù„ Ù†Ø³Ø¨Ù‡ ØµØ­ÙŠØ­Ù‡"
        }
		if($(this).val() != ''){
			
			Swal.fire({
				icon: "warning",
				title: title,
				text: message ,
			})
		}

        $(this).val(0);

    }

});

$(document).on('change','.only-percentage-allowed',function(e){
    if($(this).hasClass('only-percentage-allowed') && ! isPercentageNumber($(this).val()))
    {
	
        let lang = $('body').data('lang');
        title = "Oops..." ;
        message = "Please Enter Valid Percentage" ;
        if(lang === 'ar'){
            title = 'Ø®Ø·Ø£'  ;
            message = "Ø¨Ø±Ø¬Ø§Ø¡ Ø§Ø¯Ø®Ø§Ù„ Ù†Ø³Ø¨Ù‡ ØµØ­ÙŠØ­Ù‡"
        }
		if($(this).val() != ''){
			Swal.fire({
				icon: "warning",
				title: title,
				text: message ,
			})
			
		}

        $(this).val(0);

    }

});

$(document).on('change','.only-number-allowed',function(e){
    if($(this).hasClass('only-number-allowed') && ! isNumber($(this).val()))
    {
	
        let lang = $('body').data('lang');
        title = "Oops..." ;
        message = "Please Enter Valid Number" ;
        if(lang === 'ar'){
            title = 'Ø®Ø·Ø£'  ;
            message = "Ø¨Ø±Ø¬Ø§Ø¡ Ø§Ø¯Ø®Ø§Ù„ Ù†Ø³Ø¨Ù‡ ØµØ­ÙŠØ­Ù‡"
        }
		if($(this).val() != ''){
			Swal.fire({
				icon: "warning",
				title: title,
				text: message ,
			})
			
		}

        $(this).val(0);

    }

});

$(document).on('change','.only-greater-than-zero-allowed',function(){
    if(! isGreaterThanZero($(this).val()) && $(this).val())
    {
        let currentLang = $('body').data('lang');
        let trans = {
            "The Value Must Be Greater Than Zero":{
                "en":"The Value Must Be Greater Than Zero",
                "ar":"ÙŠØ¬Ø¨ Ø§Ù† ØªÙƒÙˆÙ† Ø§Ù„Ù‚ÙŠÙ…Ù‡ Ø§ÙƒØ¨Ø± Ù…Ù† Ø§Ù„ØµÙØ±"
            },
            "Oops...":{
                "en":'Oops...',
                "ar":"Ø®Ø·Ø£"
            }
        };
        Swal.fire({
            icon: "warning",
            title: trans['Oops...'][currentLang],
            text: trans['The Value Must Be Greater Than Zero'][currentLang],
        })
        $(this).val(1).trigger('change');

    }
});


$(document).on('change','.only-greater-than-or-equal-specific-number-allowed',function(){
    if(! isGreaterThanOrEqualSpecificNumber(number_unformat($(this).val()),number_unformat($(this).attr('data-specific-number'))) && $(this).val())
    {
        let currentLang = $('body').data('lang');
        let trans = {
            "The Value Must Be Greater Than Specific Number":{
                "en":"The Value Must Be Greater Than Specific Number",
                "ar":"ÙŠØ¬Ø¨ Ø§Ù† ØªÙƒÙˆÙ† Ø§Ù„Ù‚ÙŠÙ…Ù‡ Ø§ÙƒØ¨Ø± Ù…Ù† Ø§Ù„ØµÙØ±"
            },
            "Oops...":{
                "en":'Oops...',
                "ar":"Ø®Ø·Ø£"
            }
        };
        Swal.fire({
            icon: "warning",
            title: trans['Oops...'][currentLang],
            text: 'The Value Must Be Greater Than Or Equal To Study Duration In Months [ ' + $(this).attr('data-specific-number') + ' ]',
        })
        $(this).val($(this).attr('data-specific-number')).trigger('change');

    }
});


$(document).on('change','.only-greater-than-or-equal-zero-allowed',function(){
    let val = number_unformat($(this).val()) ;

    if(! isGreaterThanOrEqualZero(val) && val  != '')
    {
        let currentLang = $('body').data('lang');
         Swal.fire({
            icon: "warning",
            title: {
                "en":"Oops...",
                "ar":""
            }[currentLang],
            text: {
                "en":"The Value Must Be Greater Than Zero",
                "ar":""
            }[currentLang],
        })
        $(this).val(0);
    }
});


$(document).on('change','.only-less-than-or-equal-zero-allowed',function(){
    let val = number_unformat($(this).val()) ;

    if(! isLessThanOrEqualZero(val) && val  != '')
    {
        let currentLang = $('body').data('lang');
         Swal.fire({
            icon: "warning",
            title: {
                "en":"Oops...",
                "ar":""
            }[currentLang],
            text: {
                "en":"The Value Must Be Equal Or Less Than Zero ",
                "ar":""
            }[currentLang],
        })
        $(this).val(0);
    }
});

$(document).on('change','.only-smaller-than-or-equal-specific-number-allowed',function(){
    let val = number_unformat($(this).val());
	let greaterThan = parseFloat($(this).attr('data-can-not-be-greater-than'));
	let currentValue = parseFloat($(this).attr('data-current-value'))
	currentValue = currentValue ? currentValue : 0 ;
	greaterThan = greaterThan + currentValue;
	greaterThan = greaterThan ? greaterThan : 0 ;
    if(! isLessThanOrEqual(val,greaterThan) && val  != '')
    {
        let currentLang = $('body').data('lang');
		
				
         Swal.fire({
            icon: "warning",
            title: {
                "en":"Oops...",
                "ar":""
            }[currentLang],
            text: {
                "en":"LG Amount Must Be Less Than Or Equal To LG Room",
                "ar":"LG Amount Must Be Less Than Or Equal To LG Room"
            }[currentLang],
        })
        $(this).val(0).trigger('change');
    }
});

// $(document).on('change','.only-less-than-or-equal-specific-number-allowed',function(){
//     let val = number_unformat($(this).val());
// 	let type = $(this).attr('data-type')
// 	let currentSelectedType = $('#type').val();
// 	let currentNumber = $('select[name="account_number['+currentSelectedType+']"]').val()
// 	if(currentSelectedType != type || !currentNumber){
// 		return ;
// 	}
// 	let greaterThanValueOrQuery = $(this).attr('data-can-not-be-greater-than').replaceAll("'",'"');
// 	let greaterThan =  parseFloat(number_unformat($(greaterThanValueOrQuery).val()))
	

// 	let currentValue = parseFloat($(this).attr('data-current-value'))

// 	currentValue = currentValue ? currentValue : 0 ;
// 	greaterThan = greaterThan + currentValue;
// 	greaterThan = greaterThan ? greaterThan : 0 ;
//     if(! isLessThanOrEqual(val,greaterThan) && val  != '')
//     {
//         let currentLang = $('body').data('lang');
		
//          Swal.fire({
//             icon: "warning",
//             title: {
//                 "en":"Oops...",
//                 "ar":""
//             }[currentLang],
//             text: {
//                 "en":"The Value Must Be Less Than Or Equal " +greaterThan ,
//                 "ar":""
//             }[currentLang],
//         })
//         $(this).val(0).trigger('change');
//     }
// });

  function roundHalf(num) {
    return Math.round(num*2)/2;
}
function isNumber(number )
{
    return !isNaN(parseFloat(number)) && isFinite(number) ;
}

function isPercentageNumber(number )
{
    return !isNaN(parseFloat(number)) && isFinite(number) && number <= 100 && number>=0 ;
}

function isPercentageNumberBetweenMinusPlusHundred(number )
{
    return !isNaN(parseFloat(number)) && isFinite(number) && number <= 100 && number>=-100 ;
}


function isGreaterThanZero(number )
{

    return !isNaN(parseFloat(number)) && isFinite(number) && number > 0 ;
}
function isGreaterThanOrEqualSpecificNumber(number,specificNumber )
{
    return !isNaN(parseFloat(number)) && isFinite(number) && number >= parseFloat(specificNumber) ;
}

function isGreaterThanOrEqualZero(number )
{
    return  !isNaN(parseFloat(number)) && isFinite(number) && number >= 0 && number!='';
}function isLessThanOrEqualZero(number )
{
    return  !isNaN(parseFloat(number)) && isFinite(number) && number <= 0 && number!='';
}
function isLessThanOrEqual(number,specificNumber )
{
    return  !isNaN(parseFloat(number)) && isFinite(number) && number <= specificNumber && number!='';
}

function getCurrentLang()
{
    return $('body').data('lang') ;

}
function number_format(number, decimals, dec_point, thousands_sep) {
    // Strip all characters but numerical ones.
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
        s = '',
        toFixedFix = function (n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
        };
    // Fix for IE parseFloat(0.55).toFixed(0) = 0;
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    return s.join(dec);
}
$(document).on('change','.must-not-exceed-100',function(){
	let lang = $('body').data('lang');
	title = "Oops..." ;
	message = "Total Can Not Be Greater Than 100" ;
	if(lang === 'ar'){
		title = 'خطأ'  ;
		message = "مجموع القيم لا يمكن ان يتجاوز 100"
	}
	
	const parentQuery = $(this).attr('data-parent-query')
	let total = 0 ;
	$(this).closest(parentQuery).find('.must-not-exceed-100').each(function(index,element){
		total+=parseFloat($(element).val());
	})
	if(total > 100){
		$(this).val(0).trigger('change');
		Swal.fire({
            icon: "warning",
            title,
            text: message,
        })
	}
	
})
