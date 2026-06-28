@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .money-flow-dark .kt-portlet .kt-portlet__head {
        border-bottom-color: #1490a833 !important;
    }

    .money-flow-dark label {
        white-space: nowrap !important
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

        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" method="post" action="{{ isset($model) ?  route('lc-settlement-internal-money-transfers.update',['company'=>$company->id,'lc_settlement_internal_transfer'=>$model->id]) :route('lc-settlement-internal-money-transfers.store',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
            <input type="hidden" id="model-id" name="id" value="{{ isset($model) ? $model->id : 0 }}">
            <input type="hidden" name="company_id" value="{{ $company->id }}">
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
                                    <x-sectionTitle :title="__((isset($model) ? 'Edit' : 'Add') . ' Bank To Letter Of Credit Internal Money Transfer')"></x-sectionTitle>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <!--begin::Form-->
                    <form class="kt-form kt-form--label-right">
                        <div class="kt-portlet">


                            <div class="kt-portlet ">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title head-title text-primary">
                                            {{__('Bank To Safe Transfer Information')}}
                                        </h3>
                                    </div>
                                </div>

                                <div class="kt-portlet__body">
                                    <div class="form-group">
                                        <div class="row">

                                            <div class="col-md-2">
                                                <x-form.date :label="__('Date')" :required="true" :model="$model??null" :name="'transfer_date'" :placeholder="__('Select Date')"></x-form.date>
                                            </div>
											
											
											    <div class="col-md-6">
                                                <label>{{__('From Bank')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select js-from-when-change-trigger-change-account-type data-from-financial-institution-id name="from_bank_id" class="form-control update-letter-of-credit-issuances">
                                                            @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                                            <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getFromBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                                            @endforeach
                                                        </select>

                                                    </div>
                                                </div>
                                            </div>
											
											   <div class="col-md-1	">
                                                <label>{{__('Currency')}}
                                                    @include('star')
                                                </label>
                                                <div class="input-group">
                                                    <select js-to-when-change-trigger-change-account-type name="currency" class="form-control current-from-currency update-letter-of-credit-issuances" js-from-when-change-trigger-change-account-type>
                                                        <option selected>{{__('Select')}}</option>
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($model) && $model->getCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        

                                            <div class="col-md-3 mb-4">
                                                <label>{{ __('To Letter Of Credit Issuance') }} <span class="multi_selection"></span> </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select id="letter-of-credit-issuance-id" data-live-search="true" data-actions-box="true" name="to_letter_of_credit_issuance_id" required class="form-control customers-js kt-bootstrap-select select2-select kt_bootstrap_select ">

                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
											
											
											 <div class="col-md-3 ">
                                                <label>{{__('Remaining Balance')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input id="remaining-balance-id" readonly step="1" type="numeric" value="0" class="form-control  " >
                                                </div>
                                            </div>
											




                                            <div class="col-md-3">
                                                <label>{{__('From Account Type')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required name="from_account_type_id" class="form-control js-from-update-account-number-based-on-account-type">
                                                            {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                            @foreach($accountTypes as $index => $accountType)
                                                            <option value="{{ $accountType->id }}" @if(isset($model) && $model->getFromAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-3">
                                                <label>{{__('From Account Number')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required data-current-selected="{{ isset($model) ? $model->getFromAccountNumber(): 0  }}" data-from-current-selected="{{ isset($model) ? $model->getFromAccountNumber(): 0 }}" name="from_account_number" class="form-control js-from-account-number">
                                                            <option value="" selected>{{__('Select')}}</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
											
											
                                            {{-- <div class="col-md-3 ">
                                                <label>{{__('Cheque Number')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input data-max-cheque-value="0" step="1" type="numeric" value="{{ isset($model) ? $model->getChequeNumber():0 }}" name="cheque_number" class="form-control  " placeholder="{{__('Insert Cheque Number')}}">
                                                </div>
                                            </div> --}}

                                            <div class="col-md-3 ">
                                                <label>{{__('Amount')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input data-max-cheque-value="0" type="text" value="{{ isset($model) ? $model->getAmount():0 }}" name="amount" class="form-control greater-than-or-equal-zero-allowed " placeholder="{{__('Insert Amount')}}">
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
			
            {{-- <x-submitting /> --}}
			<x-submitting-by-ajax :backTo="route('lc-settlement-internal-money-transfers.index',['company'=>$company->id])" />
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
            $(document).on('change', '.js-from-update-account-number-based-on-account-type', function() {
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
                    url
                    , data
                    , success: function(res) {
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
            $(document).on('change', '[js-from-when-change-trigger-change-account-type]', function() {

                $(this).closest('.kt-portlet__body').find('.js-from-update-account-number-based-on-account-type').trigger('change')
            })
            $(function() {
                $('.js-from-update-account-number-based-on-account-type').trigger('change')
            })


            $(document).on('change', '.js-to-update-account-number-based-on-account-type', function() {
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
                    url
                    , data
                    , success: function(res) {
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
            $(document).on('change', '[js-to-when-change-trigger-change-account-type]', function() {

                $(this).closest('.kt-portlet__body').find('.js-to-update-account-number-based-on-account-type').trigger('change')
            })
            $(function() {
                $('.js-to-update-account-number-based-on-account-type').trigger('change')
            })

        </script>

	<script>
	$(document).on('change','select.update-letter-of-credit-issuances',function(e){
		const financialInstitutionId = $('select[data-from-financial-institution-id]').val()
		const currency = $('select.current-from-currency').val()
		$.ajax({
			url:"{{ route('update.lc.issuance.based.on.financial.institution',['company'=>$company->id]) }}",
			data:{
				financialInstitutionId,
				currency
			},
			type:"get",
			success:function(res){
				let options = '';
				for(var id in  res.letterOfCreditIssuances){
					options+='<option value="'+ id +'" > '+ res.letterOfCreditIssuances[id] +' </option> '
				}
				     $('select#letter-of-credit-issuance-id').empty().append(options).selectpicker("refresh");
                $('select#letter-of-credit-issuance-id').trigger('change')
			}
		})
	})
	$(function(){
		$('select.update-letter-of-credit-issuances').trigger('change')
	})
	$(document).on('change','select#letter-of-credit-issuance-id',function(e){
		const letterOfCreditIssuanceId = $(this).val()
		const internalMoneyTransferId = $('#model-id').val();
		$.ajax({
			url:"{{ route('get.remaining.balance.lc.issuance',['company'=>$company->id]) }}",
			data:{
				letterOfCreditIssuanceId,
				internalMoneyTransferId
			},
			success:function(res){
				$('#remaining-balance-id').val(number_format(res.remaining_balance))				
			}
		})
	})
	</script>
        @endsection
