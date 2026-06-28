@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
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
@section('sub-header')
{{ __('Letter Of Credit Facility Form') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->
        {{-- <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
                        {{__('Money Received')}}
        </h3>
    </div>
</div>
</div> --}}
<form method="post" action="{{ isset($model) ?  route('update.letter.of.credit.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'letterOfCreditFacility'=>$model->id]) :route('store.letter.of.credit.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id]) }}" class="kt-form kt-form--label-right">
    <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
    <input id="js-money-received-id" type="hidden" name="id" value="{{ isset($model) ? $model->id : 0 }}">
    <input type="hidden" name="financial_institution_id" value="{{ $financialInstitution->id }}">
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
                            {{ __((isset($model) ? 'Edit' : 'Add') . ' Letter Of Credit')}}
                        </h3>
                    </div>
                </div>
            </div>
            <!--begin::Form-->
            <form class="kt-form kt-form--label-right">
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Contract Main Information')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">


                        <div class="form-group row">
                            <div class="col-md-4 ">
                                <label>{{__('Financial Institution Name')}} </label>
                                <div class="kt-input-icon">
                                    <input disabled value="{{ $financialInstitution->getName()  }}" type="text" class="form-control" placeholder="{{__('Financial Institution Name')}}">
                                </div>
                            </div>
                            <div class="col-md-4 ">
                                <x-form.input :model="$model??null" :label="__('Name')" :type="'text'" :placeholder="__('Name')" :name="'name'" :class="''" :required="true"></x-form.input>
                            </div>
                            <div class="col-md-2">

                                <x-form.date :label="__('Contract Start Date')" :required="true" :model="$model??null" :name="'contract_start_date'" :placeholder="__('Select Contract Start Date')"></x-form.date>
                            </div>
                            <div class="col-md-2">
                                <x-form.date :label="__('Contract End Date')" :required="true" :model="$model??null" :name="'contract_end_date'" :placeholder="__('Select Contract End Date')"></x-form.date>
                            </div>
							
							  <div class="col-md-2">
                                <label>{{__('Type')}} @include('star')</label>
                                <div class="input-group">
                                    <select  name="type" class="form-control " id="type">
                                        @foreach($letterOfCreditFacilitiesTypes as $type => $title )
                                        <option value="{{ $type }}" @if(isset($model) && $model->getType() == $type ) selected @endif > {{ $title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2 " id="limit-div-id"> 
                                <x-form.input  :model="$model??null" :label="__('Limit')" :type="'text'" :placeholder="__('Limit')" :name="'limit'" :class="'only-greater-than-zero-allowed'" :required="true"></x-form.input>
                            </div>

                            <div class="col-md-2">
                                <label>{{__('Select Currency')}} @include('star')</label>
                                <div class="input-group">
                                    <select name="currency" class="form-control repeater-select">
                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                        <option value="{{ $currencyName }}" @if(isset($model) && $model->getCurrency() == $currencyName ) selected @endif > {{ $currencyValue }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>


                          

                           




                        </div>
                    </div>
                </div>
				
				
				 <div class="kt-portlet" id="show-only-fully-secured-div-id" style="display:none">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title head-title text-primary">
                                        {{__('CD Or TD Information')}}
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">


                                <div class="form-group row">

                                    <div class="col-md-2">
                                        <label>{{__('Select Currency')}} 
										
										@include('star')
										</label>
                                        <div class="input-group">
                                            <select name="cd_or_td_currency" class="form-control repeater-select current-currency " js-when-change-trigger-change-account-type>
                                                {{-- <option selected>{{__('Select')}}</option> --}}
                                                @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                <option value="{{ $currencyName }}" @if(isset($model) && $model->getCurrency() == $currencyName ) selected @endif > {{ $currencyValue }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <input type="hidden" name="financial_institution_id" data-financial-institution-id value="{{ $financialInstitution->id }}">
                                    <div class="col-md-2">
                                        <label>{{ __('Account Type') }} <span class=""></span> </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select id="account_type_id" name="cd_or_td_account_type_id" class="form-control js-update-account-id-based-on-account-type">
                                                    @foreach($cdOrTdAccountTypes as $index => $accountType)
                                                    <option @if(isset($model) && ($accountType->id == $model->getCdOrTdAccountTypeId()) ) selected @endif value="{{ $accountType->id }}">{{ $accountType->getName() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-2">
                                        <label>{{ __('Account Number') }} <span class=""></span> </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select js-cd-or-td-account-number data-current-selected="{{ isset($model) ? $model->getCdOrTdId(): 0 }}" name="cd_or_td_id" class="form-control js-account-number">
                                                    <option value="" selected>{{__('Select')}}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-md-2 ">
                                        <x-form.input :id="'cd-or-td-amount-id'" :readonly="true" :default-value="0" :model="$model??null" :label="__('Amount')" :type="'text'" :placeholder="''" :name="'cd_or_td_amount'" :class="'recalculate-limit-js'" :required="true"></x-form.input>
                                    </div>

                                    <div class="col-md-2 ">
                                        <x-form.input :id="'cd-or-td-interest-rate-id'" :readonly="true" :default-value="0" :model="$model??null" :label="__('CD Or TD Interest Rate')" :type="'text'" :placeholder="''" :name="'cd_or_td_interest'" :class="''" :required="true"></x-form.input>
                                    </div>

                                    <div class="col-md-2 ">
                                        <x-form.input :id="'cd-or-td-lending-percentage-id'" :readonly="false" :default-value="0" :model="$model??null" :label="__('CD Or TD Lending Percentage')" :type="'text'" :placeholder="''" :name="'cd_or_td_lending_percentage'" :class="'only-percentage-allowed recalculate-limit-js'" :required="false"></x-form.input>
                                    </div>
									
									<div class="col-md-2 ">	
										<input id="limit-id" type="hidden" name="cd_or_td_limit" value="{{ isset($model) ? $model->limit : 0 }}">
                                        <x-form.input :id="'limit-formatted-id'" :readonly="true" :model="$model??null" :label="__('Limit')" :type="'text'" :placeholder="__('Limit')" :name="'limit_formatted'" :class="'only-greater-than-zero-allowed'" :required="true"></x-form.input>
                                    </div>
									



                                </div>
                            </div>
                        </div>

                <div class="kt-portlet ">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Terms & Conditions')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">

                        @php
                        $index = 0 ;
                        @endphp

                        @foreach(getLcTypes() as $name => $nameFormatted )
                        @php
                        $termAndCondition = isset($model) && isset($model->termAndConditions[$index]) ? $model->termAndConditions[$index] : null;
                        @endphp
                        <div class="form-group row" style="flex:1;">

                            <div class="col-md-4">
                                <label class="label">{!! __('LC <br> Type') !!}</label>
                                <input class="form-control" type="hidden" readonly value="{{ $name }}" name="termAndConditions[{{ $index }}][lc_type]">
                                <input class="form-control" type="text" readonly value="{{ $nameFormatted }}">
                            </div>



                            {{-- <div class="col-2">
                                <label class="form-label font-weight-bold ">
								{!! __('Outstanding  <br> Balance') !!}
                                </label>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input placeholder="{{ __('Outstanding Balance') }}" type="text" class="form-control only-greater-than-zero-allowed" name="termAndConditions[{{ $index }}][outstanding_balance]" value="{{ isset($termAndCondition) ? $termAndCondition->getOutstandingBalance() : old('outstanding_balance',0) }}">
									</div>
								</div>
							</div> --}}






                <div class="col-1">
                    <label class="form-label font-weight-bold text-center">
                        {!! __('Cash <br> Cover (%)') !!}
                        @include('star')
                    </label>
                    <div class="kt-input-icon">
                        <div class="input-group">
                            <input name="termAndConditions[{{ $index }}][cash_cover_rate]" type="text" class="form-control cash-cover-class only-percentage-allowed
								" value="{{ (isset($termAndCondition) ? $termAndCondition->cash_cover_rate : old('cash_cover_rate',0)) }}">
                        </div>
                    </div>
                </div>

                <div class="col-1">
                    <label class="form-label font-weight-bold text-center ">
                        {!! __('Commission <br> Rate (%)') !!}
                        @include('star')
                    </label>
                    <div class="kt-input-icon">
                        <div class="input-group">
                            <input name="termAndConditions[{{ $index }}][commission_rate]" type="text" class="form-control only-percentage-allowed
								" value="{{ (isset($termAndCondition) ? $termAndCondition->commission_rate : old('commission_rate',0)) }}">
                        </div>
                    </div>
                </div>





                <div class="col-2">
                    <label class="form-label font-weight-bold"> {!! __('Min Commissions <br> Fees Amount') !!}

                    </label>
                    <div class="kt-input-icon">
                        <div class="input-group">
                            <input name="termAndConditions[{{ $index }}][min_commission_fees]" type="text" class="form-control only-greater-than-or-equal-zero-allowed
								" value="{{ (isset($termAndCondition) ? $termAndCondition->min_commission_fees : old('min_commission_fees',0)) }}">
                        </div>
                    </div>
                </div>


                <div class="col-2">
                    <label class="form-label font-weight-bold">{!! __('Issuance <br> Fees Amount') !!}

                    </label>
                    <div class="kt-input-icon">
                        <div class="input-group">
                            <input name="termAndConditions[{{ $index }}][issuance_fees]" type="text" class="form-control only-greater-than-or-equal-zero-allowed
								" value="{{ (isset($termAndCondition) ? $termAndCondition->issuance_fees : old('issuance_fees',0)) }}">
                        </div>
                    </div>
                </div>





        </div>
        @php
        $index = $index + 1 ;
        @endphp

        @endforeach





    </div>
    </div>


    <div class="kt-portlet ">
        <div class="kt-portlet__head">
            <div class="kt-portlet__head-label">
                <h3 class="kt-portlet__head-title head-title text-primary">
                    {{__('Financing Terms & Conditions')}}
                </h3>
            </div>
        </div>
        <div class="kt-portlet__body">
            <div class="form-group row">


                <div class="col-md-2 ">
                    <x-form.input :id="'borrowing-rate-id'" :model="$model??null" :class="'only-percentage-allowed'" :label="__('Borrowing Rate (%)')" :type="'text'" :placeholder="__('Borrowing Rate (%)')" :name="'borrowing_rate'" :required="true"></x-form.input>
                </div>

                <div class="col-md-2 ">
                    <x-form.input :model="$model??null" :class="'only-percentage-allowed'" :label="__('Bank Margin Rate (%)')" :placeholder="__('Bank Margin Rate (%)')" :name="'bank_margin_rate'" :required="true" :type="'text'"></x-form.input>
                </div>

                <div class="col-md-2 ">
                    <x-form.input :model="$model??null" :class="'only-percentage-allowed'" :label="__('Interest Rate (%)')" :placeholder="__('Interest Rate (%)')" :name="'interest_rate'" :required="true" :type="'text'"></x-form.input>
                </div>

                <div class="col-md-2 ">
                    <x-form.input :model="$model??null" :class="'only-percentage-allowed'" :label="__('Min Intrest Rate (%)')" :placeholder="__('Min Intrest Rate (%)')" :name="'min_interest_rate'" :required="true" :type="'text'"></x-form.input>
                </div>
                <div class="col-md-2 ">
                    <x-form.input :model="$model??null" :class="'only-percentage-allowed'" :label="__('Highest Debt Balance Rate (%)')" :placeholder="__('Highest Debt Balance Rate (%)')" :name="'highest_debt_balance_rate'" :required="true" :type="'text'"></x-form.input>
                </div>
                {{-- <div class="col-md-4 ">
                                <x-form.input :model="$model??null" :class="'only-percentage-allowed'" :label="__('Admin Fees Rate (%)')" :placeholder="__('Admin Fees Rate (%)')" :name="'admin_fees_rate'" :required="true" :type="'text'"></x-form.input>
                            </div> --}}








            </div>






        </div>
    </div>



    <x-submitting :backTo="route('view.letter.of.credit.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id])" />


</form>

<!--end::Form-->

<!--end::Portlet-->
</div>
</div>

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

 <script src="/custom/money-receive.js">

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
    $('input[name="borrowing_rate"],input[name="bank_margin_rate"]').on('change', function() {
        let borrowingRate = $('input[name="borrowing_rate"]').val();
        borrowingRate = borrowingRate ? parseFloat(borrowingRate) : 0;
        let bankMaringRate = $('input[name="bank_margin_rate"]').val();
        bankMaringRate = bankMaringRate ? parseFloat(bankMaringRate) : 0;
        const interestRate = borrowingRate + bankMaringRate;
        $('input[name="interest_rate"]').attr('readonly', true).val(interestRate);
    })
    $('input[name="borrowing_rate"]').trigger('change');

</script>
<script>
$('select#type').on('change',function(){
	const val = $(this).val();
	if(val =='fully-secured'){
		$('#show-only-fully-secured-div-id').show();
		$('.cash-cover-class').val(0).prop('readonly',true)
		$('#limit-div-id').hide();
	}else{
		$('#limit-div-id').show();
		$('.cash-cover-class').prop('readonly',false)
		$('#show-only-fully-secured-div-id').hide();
	}
})
$('select#type').trigger('change')

$(document).on('change','.recalculate-limit-js',function(e){
			let amount = number_unformat($('#cd-or-td-amount-id').val());
			let lendingPercentage = number_unformat($('#cd-or-td-lending-percentage-id').val());
			let limit = amount * lendingPercentage / 100 ;
			$('#limit-id').val(limit)
			$('#limit-formatted-id').val(number_format(limit))
		})
		$('.recalculate-limit-js:eq(0)').trigger('change')

$(document).on('change', '[js-cd-or-td-account-number]', function() {
                const parent = $(this).closest('.kt-portlet__body');
                const accountType = parent.find('.js-update-account-id-based-on-account-type').val()
                const accountId = parent.find('[js-cd-or-td-account-number]').val();
               	const financialInstitutionId = "{{ $financialInstitution->id }}";
                    let url = "{{ route('get.account.amount.based.on.account.id',['company'=>$company->id , 'accountType'=>'replace_account_type' , 'accountId'=>'replace_account_id','financialInstitutionId'=>'replace_financial_institution_id' ]) }}";
					
                    url = url.replace('replace_account_type', accountType);
                    url = url.replace('replace_account_id', accountId);
					url = url.replace('replace_financial_institution_id', financialInstitutionId);
					
					if(accountType &&accountId &&financialInstitutionId){
						$.ajax({
                    url
                    , success: function(res) {
                        parent.find('#cd-or-td-amount-id').val(number_format(res.amount)).trigger('change')
						parent.find('#cd-or-td-interest-rate-id').val(number_format(res.interest_rate,2))
						
						$('#borrowing-rate-id').val(number_format(res.interest_rate,2)).trigger('change')
                    }
                });
					}
                
            })
            $('[js-cd-or-td-account-number]').trigger('change')
					
</script>
@endsection
