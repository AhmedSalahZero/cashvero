    <script>
                $(document).on('change', '[change-financial-instutition-js]', function() {
                    const parent = $(this).closest('.kt-portlet__body');
                    const accountType = $('.js-update-account-id-based-on-account-type').val()
                    const accountId = $('[js-cd-or-td-account-number]').val();
					if(!accountId){
						return ;
					}
                    let financialInstitutionId = $('#financial-instutition-id').val();
                    financialInstitutionId = financialInstitutionId ? financialInstitutionId : $('[name="financial_institution_id"]').val();
                    let url = "{{ route('update.balance.and.net.balance.based.on.account.id.ajax',['company'=>$company->id , 'accountType'=>'replace_account_type' , 'accountId'=>'replace_account_id','financialInstitutionId'=>'replace_financial_institution_id' ]) }}";
                    url = url.replace('replace_account_type', accountType);
                    url = url.replace('replace_account_id', accountId);
                    url = url.replace('replace_financial_institution_id', financialInstitutionId);

                    $.ajax({
                        url
                        , success: function(res) {

                            if (res.balance_date) {
                                $(parent).find('.balance-date-js').html('[ ' + res.balance_date + ' ]')
                            }
                            if (res.net_balance_date) {
                                $(parent).find('.net-balance-date-js').html('[ ' + res.net_balance_date + ' ]')
                            }
						
                            $(parent).find('.net-balance-js').val(number_format(res.net_balance))
                            $(parent).find('.balance-js').val(number_format(res.balance))
                        }
                    });
                })
				
				
				$(document).on('change', '[js-when-change-trigger-change-account-type]', function () {
					$('.js-update-account-number-based-on-account-type').trigger('change')
					})

					$(document).on('change','.recalculate-cd-or-td-free-to-use',function(){
						const cdOrTdAmount = $('#cd-or-td-amount-id').attr('data-value')
						const currentLgOutstandBalance  = parseFloat(number_unformat($('#current-lc-outstanding-balance-id').val()))
						const againstCashCoverAmount  = parseFloat(number_unformat($('#against-cash-cover-amount-id').val()))
						const amount = cdOrTdAmount-currentLgOutstandBalance-againstCashCoverAmount ;
						$('#cd-or-td-free-to-use-amount-id').val(number_format(amount)).prop('readonly', true)
						$('input[name="lc_amount"]').attr('data-can-not-be-greater-than',amount);
					})

$(document).on('change','select.lc-currency',function(){
	var lcCashCoverSelect = $('select[name="lc_cash_cover_currency"]') ;
	var currentSelected = lcCashCoverSelect.attr('data-current-selected');
	const currentCurrency = $(this).val();
	const mainFunctionalCurrency = "{{ $company->getMainFunctionalCurrency() }}"
	let cashCoverCurrencies =  `<option ${currentSelected == mainFunctionalCurrency ? 'selected' : ''} value="${mainFunctionalCurrency}">${mainFunctionalCurrency}</option>`;
	
	if(currentCurrency != mainFunctionalCurrency ){
		cashCoverCurrencies+= `<option ${currentCurrency == currentSelected ? 'selected' : ''} value="${currentCurrency}">${currentCurrency}</option>`
	}
	lcCashCoverSelect.empty().append(cashCoverCurrencies).trigger('change');
})	
$('select.lc-currency').trigger('change')
            </script>
