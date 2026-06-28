@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .kt-portlet .kt-portlet__head {
        border-bottom-color: #1490a833 !important;
    }
.money-flow-dark .font-normal{
	font-weight:normal !important;
}
    .money-flow-dark label {
        white-space: nowrap !important
    }

    .money-flow-dark [class*="col"] {
        margin-bottom: 1.5rem !important;
    }

    .money-flow-dark label {
        text-align: left !important;
    }

    .money-flow-dark .width-8 {
        max-width: initial !important;
        width: 8% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-10 {
        max-width: initial !important;
        width: 10% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-12 {
        max-width: initial !important;
        width: 13.5% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-45 {
        max-width: initial !important;
        width: 45% !important;
        flex: initial !important;
    }

    .money-flow-dark .kt-portlet {
        overflow: visible !important;
    }

</style>
@endsection
{{-- @section('sub-header')
{{ __('Internal Money Transfer Form') }}
@endsection --}}
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" method="post" action="{{ isset($model) ?  route('internal-money-transfers.update',['company'=>$company->id,'internal_money_transfer'=>$model->id,'type'=>$type]) :route('internal-money-transfers.store',['company'=>$company->id,'type'=>$type]) }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
            <input  id="model-id" type="hidden" name="id" value="{{ isset($model) ? $model->id : 0 }}">
			<input  type="hidden" name="modelId" value="{{ isset($model) ? $model->id : 0 }}">
            <input  type="hidden" name="modelType" value="InternalMoneyTransfer">
            <input  type="hidden" name="company_id" value="{{ $company->id }}">
			<input type="hidden" name="type" value="bank-to-safe" >
			@if(isset($model))
			<input type="hidden" name="updated_by" value="{{ auth()->user()->id }}">
			@else
			<input type="hidden" name="created_by" value="{{ auth()->user()->id }}">
			
			@endif 
            {{-- <input type="hidden" name="financial_institutions_id" value="{{ $financialInstitution->id }}"> --}}
            @csrf
            @if(isset($model))
            @method('put')
            @endif

            <div class="row">
                <div class="col-md-12">
                    <!--begin::Portlet-->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    <x-sectionTitle :title="__((isset($model) ? 'Edit' : 'Add') . ' Bank To Safe Internal Money Transfer')"></x-sectionTitle>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <!--begin::Form-->
                    <form class="kt-form kt-form--label-right">
                        <div class="kt-portlet">


                            <div class="kt-portlet ">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label flex-1">
                                        <h3 class="kt-portlet__head-title head-title text-primary">
                                          {{__('Bank To Safe Transfer Information')}}
                                        </h3>
										  <div class=" flex-1 d-flex justify-content-end pt-3">
                                            <div class="col-md-3 mb-3">
                                                <label>{{__('Balance')}} <span class="balance-date-js"></span> </label>
                                                <div class="kt-input-icon">
                                                    <input value="0" type="text" disabled class="form-control balance-js" placeholder="{{__('Account Balance')}}">
                                                </div>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label>{{__('Net Balance')}} <span class="net-balance-date-js"></span> </label>
                                                <div class="kt-input-icon">
                                                    <input value="0" type="text" disabled class="form-control net-balance-js" placeholder="{{__('Net Balance')}}">
                                                    {{-- <x-tool-tip title="{{__('Kash Vero')}}" /> --}}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="kt-portlet__body">
                                    <div class="form-group">
                                        <div class="row">

                                            <div class="col-md-3">
                                                <x-form.date :classes="'balance-date'"  :label="__('Date')" :required="true" :model="$model??null" :name="'transfer_date'" :placeholder="__('Select Date')"></x-form.date>
                                            </div>
                                            <div class="col-md-3 ">
                                                <label>{{__('Cheque Number/Cash Withdrawal')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input data-max-cheque-value="0" step="1" type="numeric" value="{{ isset($model) ? $model->getChequeNumber():0 }}" name="cheque_number" class="form-control  " placeholder="{{__('Insert Cheque Number')}}">
                                                </div>
												<div>
													<label for="" class="font-normal">{{ __('If Cash Withdrawal, Please write cash withdrawal') }}</label>
												</div>
                                            </div>

                                            <div class="col-md-3 ">
                                                <label>{{__('Cheque Amount')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input  type="text" value="{{ isset($model) ? number_format($model->getAmount()):0 }}" class="form-control greater-than-or-equal-zero-allowed " >
													<input type="hidden" name="amount" value="{{ isset($model) ? $model->getAmount():0 }}">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label>{{__('Currency')}}
                                                    @include('star')
                                                </label>
                                                <div class="input-group">
                                                    <select js-to-when-change-trigger-change-account-type name="currency" class="form-control current-from-currency" js-from-when-change-trigger-change-account-type>
                                                        <option selected>{{__('Select')}}</option>
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($model) && $model->getCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label>{{__('From Bank')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required js-from-when-change-trigger-change-account-type data-from-financial-institution-id name="from_bank_id" class="form-control from-financial-institution">
                                                            @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                                            <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getFromBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                                            @endforeach
                                                        </select>

                                                    </div>
                                                </div>
                                            </div>




                                            <div class="col-md-2">
                                                <label>{{__('From Account Type')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select name="from_account_type_id" class="form-control js-from-update-account-number-based-on-account-type">
                                                            <option value="" selected>{{__('Select')}}</option>
                                                            @foreach($accountTypes as $index => $accountType)
                                                            <option value="{{ $accountType->id }}" @if(isset($model) && $model->getFromAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-2">
                                                <label>{{__('From Account Number')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select data-current-selected="{{ isset($model) ? $model->getFromAccountNumber(): 0  }}" data-from-current-selected="{{ isset($model) ? $model->getFromAccountNumber(): 0 }}" name="from_account_number" class="form-control js-from-account-number">
                                                            <option value="" selected>{{__('Select')}}</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                              <div class="col-md-2 mb-4">
                            <label>{{ __('To Branch') }} <span class="multi_selection"></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select id="branch-id" data-current-selected="{{ isset($model) ? $model->getToBranchId() : 0 }}" required data-live-search="true" data-actions-box="true" name="to_branch_id" required class="form-control customers-js kt-bootstrap-select select2-select kt_bootstrap_select ">
                                        {{-- @foreach($selectedBranches as $id => $name) --}}
                                        {{-- <option value="{{ $id }}" >{{ $name }}</option> --}}
                                        {{-- @endforeach --}}
                                    </select>
                                </div>
                            </div>
                        </div>




                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>


                        <!--end::Form-->

                        <!--end::Portlet-->
                </div>
            </div>
			 @include('user_comment',['model'=>$model??null])
            <x-submitting :backTo="route('internal-money-transfers.index',['company'=>$company->id])" />
        </form>

        </div>
        @endsection
        @section('js')
        <!--begin::Page Scripts(used by this page) -->
        <script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
        <script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript">
        </script>
        <script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript">
        </script>
        <script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript">
        </script>
        <script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}" type="text/javascript">
        </script>
        <script src="{{ url('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
        <script src="{{ url('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript">
        </script>
        <script src="{{ url('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
        <script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js') }}" type="text/javascript"></script>
        <script>

        </script>

        <script>
            $(document).find('.datepicker-input').datepicker({
                dateFormat: 'mm-dd-yy'
                , autoclose: true
            })
            $('#m_repeater_0').repeater({
                initEmpty: false
                , isFirstItemUndeletable: true
                , defaultValues: {
                    'text-input': 'foo'
                },

                show: function() {
                    $(this).slideDown();
                    $('input.trigger-change-repeater').trigger('change')
                    $(document).find('.datepicker-input').datepicker({
                        dateFormat: 'mm-dd-yy'
                        , autoclose: true
                    })
                    $(this).find('.only-month-year-picker').each(function(index, dateInput) {
                        reinitalizeMonthYearInput(dateInput)
                    });
                    $('input:not([type="hidden"])').trigger('change');
                    $(this).find('.dropdown-toggle').remove();
                    $(this).find('select.repeater-select').selectpicker("refresh");

                },

                hide: function(deleteElement) {
                    if ($('#first-loading').length) {
                        $(this).slideUp(deleteElement, function() {

                            deleteElement();
                            //   $('select.main-service-item').trigger('change');
                        });
                    } else {
                        if (confirm('Are you sure you want to delete this element?')) {
                            $(this).slideUp(deleteElement, function() {

                                deleteElement();
                                $('input.trigger-change-repeater').trigger('change')

                            });
                        }
                    }
                }
            });

        </script>

        <script>
            let oldValForInputNumber = 0;
            $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('focus', function() {
                oldValForInputNumber = $(this).val();
                $(this).val('')
            })
            $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('blur', function() {

                if ($(this).val() == '') {
                    $(this).val(oldValForInputNumber)
                }
            })

            $(document).on('change', 'input:not([placeholder])[type="number"],input:not([placeholder])[type="password"],input:not([placeholder])[type="text"],input:not([placeholder])[type="email"],input:not(.exclude-text)', function() {
                if (!$(this).hasClass('exclude-text')) {
                    let val = $(this).val()
                    val = number_unformat(val)
                    $(this).parent().find('input[type="hidden"]:not([name="_token"])').val(val)

                }
            })

        </script>

       
    <script>
	$(document).on('change','.balance-date',function(){
				$('select.js-from-account-number').trigger('change');	
			})
	 $(document).on('change', 'select.js-from-account-number', function() {
                const parent = $(this).closest('.kt-portlet__body');
                const financialInstitutionId = parent.find('select.from-financial-institution').val()
                const accountNumber = $(this).val();
                const accountType = parent.find('select.js-from-update-account-number-based-on-account-type').val();
                 const modelId = $('#model-id').val();
				const modelType = 'InternalMoneyTransfer';
				const balanceDate = $('.balance-date').val();
                $.ajax({
                    url: "{{ route('update.balance.and.net.balance.based.on.account.number',['company'=>$company->id]) }}"
                    , data: {
                        accountNumber
                        , accountType
                        , financialInstitutionId,
				modelType,
				modelId,
				balanceDate
                    }
                    , type: "get"
                    , success: function(res) {
                        if (res.balance_date) {
                            $('.balance-date-js').html('[ ' + res.balance_date + ' ]')
                        }
                        if (res.net_balance_date) {
                            $('.net-balance-date-js').html('[ ' + res.net_balance_date + ' ]')
                        }
                        $('.net-balance-js').val(number_format(res.net_balance))
                        $('.balance-js').val(number_format(res.balance))

                    }
                })
            })
			
	$(document).on('change', 'select.js-from-update-account-number-based-on-account-type', function () {
	const val = $(this).val()
	const lang = $('body').attr('data-lang')
	const companyId = $('body').attr('data-current-company-id')
	const repeaterParentIfExists = $(this).closest('[data-repeater-item]')
	const parent = repeaterParentIfExists.length ? repeaterParentIfExists : $(this).closest('.kt-portlet__body')
	const data = []
	let currency = $(this).closest('form').find('select.current-from-currency').val()
	let financialInstitutionBankId = parent.find('[data-from-financial-institution-id]').val()
	financialInstitutionBankId = typeof financialInstitutionBankId !== 'undefined' ? financialInstitutionBankId : $('[data-financial-institution-id]').val()
	if (!val || !currency || !financialInstitutionBankId) {
		return
	}
	const url = '/' + lang + '/' + companyId + '/money-received/get-account-numbers-based-on-account-type/' + val + '/' + currency + '/' + financialInstitutionBankId
	$.ajax({
		url,
		data,
		success: function (res) {
			options = ''
			var selectToAppendInto = $(parent).find('.js-from-account-number')

			for (key in res.data) {
				var val = res.data[key]
				var selected = $(selectToAppendInto).attr('data-current-selected') == val ? 'selected' : ''
				options += '<option ' + selected + '  value="' + val + '">' + val + '</option>'
			}

			selectToAppendInto.empty().append(options).trigger('change')
		}
	})






})
$(document).on('change', '.js-from-update-account-number-based-on-account-type', function () {
	const val = $(this).val()
	const lang = $('body').attr('data-lang')
	const companyId = $('body').attr('data-current-company-id')
	const repeaterParentIfExists = $(this).closest('[data-repeater-item]')
	const parent = repeaterParentIfExists.length ? repeaterParentIfExists : $(this).closest('.kt-portlet__body')
	const data = []
	let currency = $(this).closest('form').find('select.current-from-currency').val()
	let financialInstitutionBankId = parent.find('[data-from-financial-institution-id]').val()
	financialInstitutionBankId = typeof financialInstitutionBankId !== 'undefined' ? financialInstitutionBankId : $('[data-financial-institution-id]').val()
	if (!val || !currency || !financialInstitutionBankId) {
		return
	}
	const url = '/' + lang + '/' + companyId + '/money-received/get-account-numbers-based-on-account-type/' + val + '/' + currency + '/' + financialInstitutionBankId
	$.ajax({
		url,
		data,
		success: function (res) {
			options = ''
			var selectToAppendInto = $(parent).find('.js-from-account-number')

			for (key in res.data) {
				var val = res.data[key]
				var selected = $(selectToAppendInto).attr('data-current-selected') == val ? 'selected' : ''
				options += '<option ' + selected + '  value="' + val + '">' + val + '</option>'
			}

			selectToAppendInto.empty().append(options).trigger('change')
		}
	})






})
$(document).on('change', '[js-from-when-change-trigger-change-account-type]', function () {

	$(this).closest('.kt-portlet__body').find('.js-from-update-account-number-based-on-account-type').trigger('change')
})
$(function () {
	$('.js-from-update-account-number-based-on-account-type').trigger('change')
})


$(document).on('change', '.js-to-update-account-number-based-on-account-type', function () {
	const val = $(this).val()
	const lang = $('body').attr('data-lang')
	const companyId = $('body').attr('data-current-company-id')
	const repeaterParentIfExists = $(this).closest('[data-repeater-item]')
	const parent = repeaterParentIfExists.length ? repeaterParentIfExists : $(this).closest('.kt-portlet__body')
	const data = []
	let currency = $(this).closest('form').find('select.current-from-currency').val()
	let financialInstitutionBankId = parent.find('[data-to-financial-institution-id]').val()
	financialInstitutionBankId = typeof financialInstitutionBankId !== 'undefined' ? financialInstitutionBankId : $('[data-financial-institution-id]').val()
	if (!val || !currency || !financialInstitutionBankId) {
		return
	}
	const url = '/' + lang + '/' + companyId + '/money-received/get-account-numbers-based-on-account-type/' + val + '/' + currency + '/' + financialInstitutionBankId
	$.ajax({
		url,
		data,
		success: function (res) {
			options = ''
			var selectToAppendInto = $(parent).find('.js-to-account-number')
			for (key in res.data) {
				var val = res.data[key]
				var selected = $(selectToAppendInto).attr('data-current-selected') == val ? 'selected' : ''
				options += '<option ' + selected + '  value="' + val + '">' + val + '</option>'
			}

			selectToAppendInto.empty().append(options).trigger('change')
		}
	})






})
$(document).on('change', '[js-to-when-change-trigger-change-account-type]', function () {

	$(this).closest('.kt-portlet__body').find('.js-to-update-account-number-based-on-account-type').trigger('change')
})
$(function () {
	$('.js-to-update-account-number-based-on-account-type').trigger('change')
})



	</script>



<script>
function getBranchFromCurrency()
	{					const branchQuery = $('select#branch-id') ;
						const currentFromBranchId = branchQuery.attr('data-current-selected');
        	            const currencyName = $('select.current-from-currency').val();
					
                        $.ajax({
                            url: "{{ route('get.branch.based.on.currency',['company'=>$company->id]) }}"
                            , data: {
								 currencyName
                            }
                            , success: function(res) {
								var branchOptions ='';
								for(var branchName in res.branches){
									var branchId = res.branches[branchName];
									var selected = branchId == currentFromBranchId ? 'selected':''; 
									branchOptions+=`<option value="${branchId}" ${selected} >${branchName}</option>`
								}
								branchQuery.empty().append(branchOptions);
								branchQuery.trigger('change');
                            }
                        })
	}
	getBranchFromCurrency();
	  $(document).on('change', 'select.current-from-currency', getBranchFromCurrency);
	  
</script>


        @endsection
