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

        <form method="post" action="{{ isset($model) ?  route('loans.update',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'mediumTermLoan'=>$model->id]) :route('loans.store',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id]) }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
            <input type="hidden" name="id" value="{{ isset($model) ? $model->id : 0 }}">
            <input type="hidden" name="company_id" value="{{ $company->id }}">
            <input type="hidden" name="financial_institution_id" value="{{ $financialInstitution->id }}">
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
                                    <x-sectionTitle :title="__((isset($model) ? 'Edit' : 'Add') . ' Loans')"></x-sectionTitle>
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
                                            {{__('Loan Information')}}
                                        </h3>
                                    </div>
                                </div>

                                <div class="kt-portlet__body">
                                    <div class="form-group">
                                        <div class="row">
										
										   <div class="col-md-4 ">
                                                <label>{{__('Name')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input  type="text" value="{{ isset($model) ? $model->getName():'' }}" name="name" class="form-control  " >
                                                </div>
                                            </div> 
											
											

                                            <div class="col-md-2">
                                                <x-form.date :label="__('Start Date')" :required="true" :model="$model??null" :name="'start_date'" ></x-form.date>
                                            </div>
											
											 <div class="col-md-2">
                                                <x-form.date :label="__('End Date')" :required="true" :model="$model??null" :name="'end_date'" ></x-form.date>
                                            </div>
											
											
											    
											
											   <div class="col-md-2	">
                                                <label>{{__('Currency')}}
                                                    @include('star')
                                                </label>
                                                <div class="input-group">
                                                    <select name="currency" class="form-control" >
                                                        <option selected>{{__('Select')}}</option>
                                                        @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                        <option value="{{ $currencyName }}" @if(isset($model) && $model->getCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
											
											  <div class="col-md-2">
                                                <label>{{__('Limit')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input  type="text" value="{{ isset($model) ? $model->getLimit():  0 }}" name="limit" class="form-control only-greater-than-zero-allowed " >
                                                </div>
                                            </div> 
											
											  <div class="col-md-2 ">
                                                <label>{{__('Account Number')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input  type="text" value="{{ isset($model) ? $model->getAccountNumber():0 }}" name="account_number" class="form-control  " >
                                                </div>
                                            </div>   
											
											<div class="col-md-2 ">
                                                <label>{{__('Borrowing Rate %')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input  type="text" value="{{ isset($model) ? $model->getBorrowingRate():0 }}" name="borrowing_rate" id="borrowing-rate-id" class="form-control  recalculate-interest-rate " >
                                                </div>
                                            </div> 
											
											<div class="col-md-2 ">
                                                <label>{{__('Margin Rate %')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input  type="text" value="{{ isset($model) ? $model->getMarginRate():0 }}" name="margin_rate" id="margin-rate-id" class="form-control recalculate-interest-rate " >
                                                </div>
                                            </div> 
												
											
											
											<div class="col-md-2 ">
                                                <label>{{__('Interest Rate %')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input readonly name="interest_rate"  type="text" value="{{ isset($model) ? $model->getInterestRate():0 }}" name="margin_rate" class="form-control  " >
                                                </div>
                                            </div> 
                                        

                                       <div class="col-md-2">
                                                <label>{{__('Duration (In Months)')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input  type="text" value="{{ isset($model) ? $model->getDuration():0 }}" name="duration" class="form-control " >
                                                </div>
                                            </div>
											
											
											  <div class="col-md-2">
                                                <label>{{__('Installment Payment Interval')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <div class="input-group date">
                                                        <select required data-from-current-selected="{{ isset($model) ? $model->getPaymentInstallmentInterval(): 0 }}" name="installment_payment_interval" class="form-control ">
                                                            <option value="" selected>{{__('Select')}}</option>
															@foreach(\App\Helpers\HVero::getDurationIntervalTypesForSelect() as $intervalArr)
																<option value="{{ $intervalArr['value'] }}" @if(isset($model) && $intervalArr['value'] == $model->getPaymentInstallmentInterval() ) selected @endif > {{ $intervalArr['title'] }} </option>
															@endforeach 
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
            <x-submitting :backTo="route('loans.index',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id])" />
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
		$(document).on('change','.type',function(){
			
		})
		</script>

        <script>
            $('input[name="borrowing_rate"],input[name="margin_rate"]').on('change', function() {
                let borrowingRate = $('input[name="borrowing_rate"]').val();
                borrowingRate = borrowingRate ? parseFloat(borrowingRate) : 0;
                let bankMaringRate = $('input[name="margin_rate"]').val();
                bankMaringRate = bankMaringRate ? parseFloat(bankMaringRate) : 0;
                const interestRate = borrowingRate + bankMaringRate;
                $('input[name="interest_rate"]').attr('readonly', true).val(interestRate);
            })
            $('input[name="borrowing_rate"]').trigger('change')

        </script>
      
        @endsection
